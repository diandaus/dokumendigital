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
        return view('documents.index', compact('documents', 'pegawai'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'document' => 'required|file|mimes:pdf|max:10240', // max 10MB
                'pegawai_id' => 'required|exists:pegawai,id'
            ]);

            $pegawai = Pegawai::findOrFail($request->pegawai_id);
            $file = $request->file('document');
            
            // Ambil nama file tanpa ekstensi
            $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            
            $path = $file->store('documents');

            $signaturePosition = [
                'lowerLeftX' => '86',
                'lowerLeftY' => '88',
                'upperRightX' => '145',
                'upperRightY' => '136',
                'page' => '1'
            ];

            // Upload ke Peruri
            $peruriResponse = $this->peruriService->uploadDocument(
                $file,
                $pegawai->email,
                $signaturePosition
            );

            // Simpan ke tracking_dokumen_ttd
            TrackingDokumenTtd::create([
                'nama_dokumen' => $fileName,
                'tgl_kirim' => now(),
                'order_id' => $peruriResponse['documentId'] ?? null,
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