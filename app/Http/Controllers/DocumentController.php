<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Pegawai;
use App\Models\TrackingDokumenTtd;
use App\Services\PeruriService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    protected $peruriService;

    public function __construct(PeruriService $peruriService)
    {
        $this->peruriService = $peruriService;
    }

    public function index()
    {
        $documents = TrackingDokumenTtd::orderBy('tgl_kirim', 'desc')->get();
        $pegawai = Pegawai::all();
        $selectedPegawaiId = session('selected_pegawai_id');
        return view('documents.index', compact('documents', 'pegawai', 'selectedPegawaiId'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'document' => 'required|file|mimes:pdf|max:10240',
                'pegawai_id' => 'required|exists:pegawai,id'
            ]);

            // Simpan pegawai_id ke session
            session(['selected_pegawai_id' => $request->pegawai_id]);

            // Mengambil data pegawai berdasarkan ID
            $pegawai = Pegawai::findOrFail($request->pegawai_id);
            
            // Email diambil dari data pegawai
            $email = $pegawai->email;

            // Tambahkan logging untuk debugging
            \Log::info('Mulai proses upload dokumen');
            \Log::info('Request data:', $request->all());

            $file = $request->file('document');
            
            $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $path = $file->store('documents');

            \Log::info('File berhasil disimpan di: ' . $path);

            $signaturePosition = [
                'lowerLeftX' => '86',
                'lowerLeftY' => '88',
                'upperRightX' => '145',
                'upperRightY' => '136',
                'page' => '1'
            ];

            // Upload ke Peruri dengan try-catch terpisah
            try {
                $peruriResponse = $this->peruriService->uploadDocument(
                    $file,
                    $pegawai->email,
                    $signaturePosition
                );

                \Log::info('Respons dari Peruri:', $peruriResponse);

                if (!isset($peruriResponse['documentId'])) {
                    throw new \Exception('Gagal mendapatkan documentId dari Peruri');
                }
            } catch (\Exception $e) {
                \Log::error('Error saat upload ke Peruri: ' . $e->getMessage());
                throw $e;
            }

            // Simpan ke database
            TrackingDokumenTtd::create([
                'nama_dokumen' => $fileName,
                'tgl_kirim' => now(),
                'order_id' => $peruriResponse['documentId'],
                'status_ttd' => 'Proses',
                'keterangan' => 'Dokumen telah dikirim ke Peruri',
                'user_pengirim' => Auth::user()->username ?? 'system',
                'email_ttd' => $pegawai->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dokumen berhasil dikirim ke Peruri'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error dalam proses store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
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

    public function download($id)
    {
        $document = TrackingDokumenTtd::findOrFail($id);
        
        try {
            $response = $this->peruriService->downloadDocument($document->order_id);
            
            // Jika response mengandung base64 document
            if (isset($response['base64Document'])) {
                $pdf_content = base64_decode($response['base64Document']);
                $filename = $document->nama_dokumen . '_signed.pdf';
                
                return response($pdf_content)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            }

            return redirect()->route('documents.index')
                ->with('error', 'Gagal mengunduh dokumen');
        } catch (\Exception $e) {
            return redirect()->route('documents.index')
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function sendOTP($id)
    {
        $document = TrackingDokumenTtd::findOrFail($id);
        
        try {
            $response = $this->peruriService->sendOTP($document->email_ttd);
            
            if (isset($response['tokenSession'])) {
                // Simpan token session
                $document->update([
                    'token_session' => $response['tokenSession'],
                    'token_expired_at' => now()->addHours(24)
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'OTP telah dikirim'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim OTP'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    public function validateOTP(Request $request, $id)
    {
        $document = TrackingDokumenTtd::findOrFail($id);
        
        try {
            // Validasi OTP
            $response = $this->peruriService->validateOTP(
                $document->email_ttd,
                $document->token_session,
                $request->otp_code
            );

            if (isset($response['success']) && $response['success']) {
                // Lanjut ke proses tanda tangan
                return $this->sign($id);
            }

            return response()->json([
                'success' => false,
                'message' => 'OTP tidak valid'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }
} 