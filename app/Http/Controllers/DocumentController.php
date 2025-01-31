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
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    
                    // Convert PDF ke base64
                    $base64Document = base64_encode(file_get_contents($file));

                    // Kirim dokumen ke Peruri satu per satu
                    $response = $this->peruriService->sendDocument(
                        $pegawai->email,
                        $fileName,
                        $base64Document
                    );

                    // Jika berhasil terkirim ke Peruri, lanjut proses simpan
                    if (isset($response['resultCode']) && $response['resultCode'] === '0') {
                        $orderId = $response['data']['orderId'] ?? null;

                        if ($orderId) {
                            // Simpan file dan data ke database
                            $file->storeAs('documents', $fileName);
                            DB::table('tracking_dokumen_ttd')->insert([
                                'nama_dokumen' => $fileName,
                                'tgl_kirim' => now(),
                                'order_id' => $orderId,
                                'status_ttd' => 'Belum',
                                'user_pengirim' => 'SYSTEM',
                                'email_ttd' => $pegawai->email,
                                'keterangan' => 'Dokumen telah di kirim ke Peruri'
                            ]);
                            $successCount++;
                            
                            // Lanjut ke file berikutnya
                            continue;
                        }
                    }
                    
                    // Jika gagal, tambah error counter
                    $errorCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    \Log::error('Error processing file: ' . $e->getMessage());
                    // Lanjut ke file berikutnya meskipun current file error
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
        $document = TrackingDokumenTtd::findOrFail($id);
        
        try {
            $response = $this->peruriService->createSigningSession($document->order_id);
            
            // Update status dokumen
            $document->update([
                'status_ttd' => 'Proses',
                'keterangan' => 'Sesi tanda tangan telah dibuat'
            ]);

            // Redirect ke URL signing session jika ada
            if (isset($response['signingUrl'])) {
                return redirect()->away($response['signingUrl']);
            }

            return redirect()->route('documents.index')
                ->with('error', 'Gagal membuat sesi tanda tangan');
        } catch (\Exception $e) {
            return redirect()->route('documents.index')
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function download($orderId)
    {
        try {
            // Cek dokumen di database
            $document = DB::table('tracking_dokumen_ttd')
                ->where('order_id', $orderId)
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak ditemukan'
                ], 404);
            }

            // Download dari Peruri
            $response = $this->peruriService->downloadDocument($orderId);
            
            if (!isset($response['data']['base64Document'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengunduh dokumen'
                ], 500);
            }

            // Decode dan return PDF
            $pdfContent = base64_decode($response['data']['base64Document']);
            
            // Gunakan nama dokumen asli dari database
            $originalName = $document->nama_dokumen;
            
            // Hapus timestamp dari nama file jika ada (misal: 1234567890_dokumen.pdf -> dokumen.pdf)
            if (preg_match('/^\d+_(.+)$/', $originalName, $matches)) {
                $originalName = $matches[1];
            }
            
            // Tambahkan _signed sebelum ekstensi
            $fileName = pathinfo($originalName, PATHINFO_FILENAME) . '_signed.pdf';

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

    public function sendOTP($id)
    {
        try {
            // Ambil email dari tracking_dokumen_ttd
            $document = DB::table('tracking_dokumen_ttd')
                ->where('id_tracking', $id)
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak ditemukan'
                ]);
            }

            // Kirim OTP ke email yang tersimpan
            $response = $this->peruriService->initiateSession($document->email_ttd);

            // Simpan token session ke database
            if (isset($response['data']['tokenSession'])) {
                DB::table('tracking_tte_session')->insert([
                    'email' => $document->email_ttd,
                    'token_session' => $response['data']['tokenSession'],
                    'status' => 'Aktif',
                    'tgl_session' => date('Y-m-d H:i:s')
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'OTP berhasil dikirim'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal mendapatkan token session'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    public function validateOTP($id, Request $request)
    {
        try {
            $document = DB::table('tracking_dokumen_ttd')
                ->where('id_tracking', $id)
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak ditemukan'
                ]);
            }

            // Ambil token session dari database
            $session = DB::table('tracking_tte_session')
                ->where('email', $document->email_ttd)
                ->where('status', 'Aktif')
                ->orderBy('tgl_session', 'desc')
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session tidak ditemukan'
                ]);
            }

            // Validasi OTP via Peruri
            $response = $this->peruriService->validateSession(
                $document->email_ttd,
                $session->token_session,
                $request->otp
            );

            return response()->json([
                'success' => true,
                'message' => 'OTP berhasil divalidasi'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    public function signWithSession($id)
    {
        try {
            // Ambil data dokumen termasuk email penandatangan
            $document = DB::table('tracking_dokumen_ttd')
                ->where('id_tracking', $id)
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak ditemukan'
                ]);
            }

            // Cek session aktif untuk email tersebut
            $session = DB::table('tracking_tte_session')
                ->where('email', $document->email_ttd)  // Menggunakan email dari tracking_dokumen_ttd
                ->where('status', 'Aktif')
                ->first();

            if (!$session) {
                // Jika tidak ada session aktif, minta OTP baru
                return response()->json([
                    'success' => false,
                    'needOTP' => true,
                    'message' => 'Session expired, silahkan validasi OTP'
                ]);
            }

            // Tanda tangan dokumen menggunakan session yang aktif
            $response = $this->peruriService->signingSession($document->order_id);

            // Update status dokumen
            DB::table('tracking_dokumen_ttd')
                ->where('id_tracking', $id)
                ->update([
                    'status_ttd' => 'Sudah',
                    'keterangan' => 'Dokumen telah ditandatangani'
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Dokumen berhasil ditandatangani'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    public function sendToPeruri($id)
    {
        try {
            $document = DB::table('tracking_dokumen_ttd')
                ->where('id_tracking', $id)
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak ditemukan'
                ]);
            }

            // Generate order ID
            $orderId = 'DOC-' . date('YmdHis') . '-' . rand(1000, 9999);

            // Ambil file PDF dan convert ke base64
            $pdfPath = storage_path('app/documents/' . $document->nama_dokumen);
            $base64Document = base64_encode(file_get_contents($pdfPath));

            // Kirim dokumen ke Peruri
            $response = $this->peruriService->sendDocument(
                $document->email_ttd,
                $document->nama_dokumen,
                $base64Document
            );

            // Update status dan order_id di database
            DB::table('tracking_dokumen_ttd')
                ->where('id_tracking', $id)
                ->update([
                    'status_ttd' => 'Terkirim',
                    'order_id' => $orderId,
                    'tgl_kirim' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Dokumen berhasil dikirim ke Peruri'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }
} 