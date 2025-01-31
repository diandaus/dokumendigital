<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Pegawai;
use App\Models\TrackingDokumenTtd;
use App\Models\TTESession;
use App\Services\PeruriService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    protected $peruriService;

    public function __construct(PeruriService $peruriService)
    {
        $this->peruriService = $peruriService;
    }

    public function index(Request $request)
    {
        $query = DB::table('tracking_dokumen_ttd');

        // Filter berdasarkan tanggal
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('tgl_kirim', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        // Filter berdasarkan email
        if ($request->email) {
            $query->where('email_ttd', $request->email);
        }

        // Filter berdasarkan status
        if ($request->status) {
            $query->where('status_ttd', $request->status);
        }

        $documents = $query->orderBy('tgl_kirim', 'desc')->get();
        
        // Get unique emails for filter dropdown
        $emails = DB::table('tracking_dokumen_ttd')
            ->select('email_ttd')
            ->distinct()
            ->orderBy('email_ttd')
            ->get();
        
        $pegawai = DB::table('pegawai')->get();

        if ($request->ajax()) {
            return view('documents.table', compact('documents'))->render();
        }

        return view('documents.index', compact('documents', 'pegawai', 'emails'));
    }

    public function store(Request $request)
    {
        try {
            // Validasi input
            $request->validate([
                'documents.*' => 'required|mimes:pdf|max:2048',
                'pegawai_id' => 'required'
            ]);

            // Ambil data pegawai
            $pegawai = DB::table('pegawai')
                ->where('id', $request->pegawai_id)
                ->first();

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pegawai tidak ditemukan'
                ]);
            }

            $successCount = 0;
            $errorCount = 0;

            // Proses satu per satu file yang diupload
            foreach($request->file('documents') as $file) {
                try {
                    // Ambil nama asli file tanpa timestamp
                    $originalName = $file->getClientOriginalName();
                    // Simpan dengan timestamp untuk unique identifier
                    $fileName = time() . '_' . $originalName;
                    
                    $base64Document = base64_encode(file_get_contents($file));

                    $response = $this->peruriService->sendDocument(
                        $pegawai->email,
                        $originalName, // Kirim nama asli ke Peruri
                        $base64Document
                    );

                    if (isset($response['resultCode']) && $response['resultCode'] === '0') {
                        $orderId = $response['data']['orderId'] ?? null;

                        if ($orderId) {
                            $file->storeAs('documents', $fileName);
                            DB::table('tracking_dokumen_ttd')->insert([
                                'nama_dokumen' => $fileName,  // Simpan dengan format: timestamp_namaasli.pdf
                                'tgl_kirim' => now(),
                                'order_id' => $orderId,
                                'status_ttd' => 'Belum',
                                'user_pengirim' => 'SYSTEM',
                                'email_ttd' => $pegawai->email,
                                'keterangan' => 'Dokumen telah di kirim ke Peruri'
                            ]);
                            $successCount++;
                            continue;
                        }
                    }
                    $errorCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    \Log::error('Error processing file: ' . $e->getMessage());
                    continue;
                }
            }

            // Tampilkan hasil akhir
            $message = "Berhasil mengupload $successCount dokumen.";
            if ($errorCount > 0) {
                $message .= " Gagal mengupload $errorCount dokumen.";
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    public function sign($id)
    {
        try {
            $document = TrackingDokumenTtd::findOrFail($id);
            
            // Redirect ke method signDocument
            return $this->signDocument($id);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function download($orderId)
    {
        try {
            $document = DB::table('tracking_dokumen_ttd')
                ->where('order_id', $orderId)
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak ditemukan'
                ], 404);
            }

            $response = $this->peruriService->downloadDocument($orderId);
            
            if (!isset($response['data']['base64Document'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengunduh dokumen'
                ], 500);
            }

            $pdfContent = base64_decode($response['data']['base64Document']);
            
            // Ambil nama asli dari nama_dokumen yang tersimpan (hapus timestamp)
            $storedName = $document->nama_dokumen;
            if (preg_match('/^\d+_(.+)$/', $storedName, $matches)) {
                $originalName = $matches[1];
            } else {
                $originalName = $storedName;
            }

            // Tambahkan _signed jika dokumen sudah ditandatangani
            $fileName = ($document->status_ttd === 'Sudah') 
                ? pathinfo($originalName, PATHINFO_FILENAME) . '_signed.pdf'
                : $originalName;

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function signDocument($id)
    {
        try {
            // Cek dokumen yang dipilih
            $document = DB::table('tracking_dokumen_ttd')
                ->where('id_tracking', $id)
                ->where('status_ttd', 'Belum')
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak ditemukan atau sudah ditandatangani'
                ], 404);
            }

            // Ambil email dokter dari pegawai
            $email = DB::table('dokter')
                ->join('pegawai', 'dokter.kd_dokter', '=', 'pegawai.nik')
                ->where('dokter.kd_dokter', $document->kd_dokter)
                ->value('pegawai.email');

            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email dokter belum diset di data pegawai'
                ], 400);
            }

            // Cek session aktif dalam 24 jam terakhir
            $activeSession = DB::table('tracking_tte_session')
                ->where('email', $email)
                ->where('status', 'Aktif')
                ->where('tgl_session', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 24 HOUR)'))
                ->orderBy('tgl_session', 'desc')
                ->first();

            $needOTP = true;
            $tokenSession = null;

            if ($activeSession) {
                // Gunakan session yang masih aktif
                $tokenSession = $activeSession->token_session;
                $needOTP = false;

                // Lakukan signing session
                $signingResponse = $this->peruriService->signingSession($document->order_id);
                
                if (isset($signingResponse['resultCode']) && $signingResponse['resultCode'] === '0') {
                    // Update status dokumen
                    DB::table('tracking_dokumen_ttd')
                        ->where('order_id', $document->order_id)
                        ->update([
                            'status_ttd' => 'Sudah',
                            'keterangan' => 'Dokumen telah ditandatangani'
                        ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Dokumen berhasil ditandatangani'
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal signing session: ' . ($signingResponse['resultDesc'] ?? 'Unknown error')
                ], 500);
            }

            // Jika tidak ada session aktif, kirim OTP baru
            $response = $this->peruriService->initiateSession($email);

            if (isset($response['resultCode']) && $response['resultCode'] === '0') {
                $tokenSession = $response['data']['tokenSession'];

                // Nonaktifkan semua session lama
                DB::table('tracking_tte_session')
                    ->where('email', $email)
                    ->where('status', 'Aktif')
                    ->update(['status' => 'Expired']);

                // Simpan token session baru
                $saved = DB::table('tracking_tte_session')->insert([
                    'email' => $email,
                    'token_session' => $tokenSession,
                    'tgl_session' => now(),
                    'status' => 'Aktif'
                ]);

                if (!$saved) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal menyimpan token session'
                    ], 500);
                }

                return response()->json([
                    'success' => true,
                    'needOTP' => true,
                    'message' => 'OTP baru telah dikirim ke email: ' . $email,
                    'tokenSession' => $tokenSession,
                    'orderId' => $document->order_id
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim OTP: ' . ($response['resultDesc'] ?? 'Unknown error')
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
} 