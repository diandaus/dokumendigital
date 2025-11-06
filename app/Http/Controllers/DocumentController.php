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
use Illuminate\Support\Facades\Validator;
use setasign\Fpdf\Fpdf;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Exception;
use Log;
use Illuminate\Support\Facades\Storage;

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
        
        // Jika request AJAX, return partial view
        if ($request->ajax()) {
            return view('documents.table', compact('documents'))->render();
        }
        
        // Get unique emails for filter dropdown
        $emails = DB::table('tracking_dokumen_ttd')
            ->select('email_ttd')
            ->distinct()
            ->orderBy('email_ttd')
            ->get();
        
        $pegawai = DB::table('pegawai')->get();

        // Return full view untuk request normal
        return view('documents.index', compact('documents', 'pegawai', 'emails'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'documents.*' => 'required|mimes:pdf|max:2048',
                'pegawai_id' => 'required'
            ]);

            $pegawai = DB::table('pegawai')
                ->where('id', $request->pegawai_id)
                ->first();

            $successCount = 0;
            $errorCount = 0;

            foreach($request->file('documents') as $file) {
                try {
                    // Gunakan nama asli file tanpa timestamp
                    $originalName = $file->getClientOriginalName();
                    
                    // Convert PDF ke base64
                    $base64Document = base64_encode(file_get_contents($file));

                    // Kirim dokumen ke Peruri dengan nama asli
                    $response = $this->peruriService->sendDocument(
                        $pegawai->email,
                        $originalName,  // Gunakan nama asli untuk Peruri
                        $base64Document
                    );

                    if (isset($response['resultCode']) && $response['resultCode'] === '0') {
                        $orderId = $response['data']['orderId'] ?? null;

                        if ($orderId) {
                            // Simpan file dengan nama asli
                            $file->storeAs('documents', $originalName);
                            
                            // Simpan ke database dengan nama asli
                            DB::table('tracking_dokumen_ttd')->insert([
                                'nama_dokumen' => $originalName,  // Simpan nama asli
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
            
            // Tambah logging untuk response
            \Log::info('Download response', [
                'order_id' => $orderId,
                'response_structure' => array_keys($response),
                'has_base64' => isset($response['data']['base64Document'])
            ]);

            if (!isset($response['data']) || !isset($response['data']['base64Document'])) {
                \Log::error('Invalid response structure', [
                    'order_id' => $orderId,
                    'response' => $response
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Format response tidak valid'
                ], 500);
            }

            $pdfContent = base64_decode($response['data']['base64Document']);
            
            // Validasi hasil decode
            if ($pdfContent === false) {
                \Log::error('Failed to decode base64', [
                    'order_id' => $orderId
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal decode dokumen'
                ], 500);
            }

            // Perbaiki typo nama kolom
            \Log::info('Document name from DB', [
                'nama_dokumen' => $document->nama_dokumen,
                'order_id' => $orderId
            ]);

            // Gunakan nama file dari database
            $fileName = $document->nama_dokumen;
            
            // Tambahkan _signed jika sudah ditandatangani
            if ($document->status_ttd === 'Sudah') {
                $fileInfo = pathinfo($fileName);
                $fileName = $fileInfo['filename'] . '_signed.' . $fileInfo['extension'];
            }

            // Debug: Cek nama file final
            \Log::info('Final download filename', [
                'fileName' => $fileName,
                'status_ttd' => $document->status_ttd
            ]);

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        } catch (\Exception $e) {
            \Log::error('Download error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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
            \Log::info('Starting signWithSession process', ['id' => $id]);
            
            $document = DB::table('tracking_dokumen_ttd')
                ->where('id_tracking', $id)
                ->where('status_ttd', 'Belum')
                ->first();

            if (!$document) {
                \Log::error('Document not found or already signed', ['id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak ditemukan atau sudah ditandatangani'
                ], 404);
            }

            $email = $document->email_ttd;

            // Cek session aktif dalam 24 jam terakhir
            $activeSession = DB::table('tracking_tte_session')
                ->where('email', $email)
                ->where('status', 'Aktif')
                ->where('tgl_session', '>=', now()->subHours(24))
                ->orderBy('tgl_session', 'desc')
                ->first();

            if (!$activeSession) {
                // Jika tidak ada session aktif, minta OTP baru
                return response()->json([
                    'success' => false,
                    'needOTP' => true,
                    'message' => 'Session expired, silahkan validasi OTP'
                ]);
            }

            // Tanda tangan dokumen menggunakan session yang aktif
            $response = $this->peruriService->signingSession($document->order_id);

            if (isset($response['resultCode']) && $response['resultCode'] === '0') {
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
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal menandatangani dokumen: ' . ($response['resultDesc'] ?? 'Unknown error')
            ]);

        } catch (\Exception $e) {
            \Log::error('Sign document error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
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

    public function mergePage()
    {
        return view('documents.merge');
    }

    public function mergePdf(Request $request)
    {
        try {
            $request->validate([
                'pdfs.*' => 'required|mimes:pdf|max:20480' // 20MB
            ]);

            if (!$request->hasFile('pdfs')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada file yang dipilih'
                ], 400);
            }

            // Buat temporary directory
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            // Simpan semua file ke temp folder
            $tempFiles = [];
            foreach ($request->file('pdfs') as $file) {
                $tempName = uniqid() . '.pdf';
                $tempPath = storage_path('app/temp/' . $tempName);
                move_uploaded_file($file->getPathname(), $tempPath);
                $tempFiles[] = $tempPath;
            }

            // Gunakan ghostscript untuk menggabungkan PDF
            $outputPath = storage_path('app/temp/' . uniqid() . '_merged.pdf');
            $command = 'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPreserveAnnots=true ' .
                      '-dPreserveMarkedContent=true -dPrinted=false ' .
                      '-sOutputFile="' . $outputPath . '" ' .
                      implode(' ', array_map(function($file) {
                          return '"' . $file . '"';
                      }, $tempFiles));

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new Exception('Gagal menggabungkan PDF: ' . implode("\n", $output));
            }

            // Baca hasil gabungan
            $mergedContent = file_get_contents($outputPath);

            // Cleanup temporary files
            foreach ($tempFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }

            return response($mergedContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="dokumen-gabungan.pdf"');

        } catch (Exception $e) {
            // Cleanup jika terjadi error
            if (isset($tempFiles)) {
                foreach ($tempFiles as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
            if (isset($outputPath) && file_exists($outputPath)) {
                unlink($outputPath);
            }

            Log::error('Error in mergePdf: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menggabungkan PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    public function pageEditor()
    {
        return view('documents.page-editor');
    }

    public function saveEditedPdf(Request $request)
    {
        try {
            // Log untuk debugging
            \Log::info('saveEditedPdf called', [
                'has_file' => $request->hasFile('pdf'),
                'has_pages' => $request->has('pages'),
                'files' => $request->allFiles(),
                'all' => $request->all()
            ]);

            // Validasi manual untuk debugging
            if (!$request->hasFile('pdf')) {
                \Log::error('No PDF file in request');
                return response()->json([
                    'success' => false,
                    'message' => 'File PDF tidak ditemukan dalam request'
                ], 400);
            }

            if (!$request->has('pages')) {
                \Log::error('No pages data in request');
                return response()->json([
                    'success' => false,
                    'message' => 'Data halaman tidak ditemukan'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'pdf' => 'required|file|mimes:pdf|max:51200',
                'pages' => 'required|string'
            ]);

            if ($validator->fails()) {
                \Log::error('Validation failed', ['errors' => $validator->errors()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal: ' . $validator->errors()->first()
                ], 422);
            }

            $pagesData = json_decode($request->pages, true);

            if (empty($pagesData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data halaman tidak valid'
                ], 400);
            }

            // Buat temporary directory
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            // Simpan file upload
            $uploadedFile = $request->file('pdf');
            $inputPath = storage_path('app/temp/' . uniqid() . '_input.pdf');
            $uploadedFile->move(dirname($inputPath), basename($inputPath));

            // Ekstrak halaman individual menggunakan ghostscript
            $extractedPages = [];
            foreach ($pagesData as $index => $pageInfo) {
                $pageNum = $pageInfo['originalPage'];
                $rotation = $pageInfo['rotation'] ?? 0;

                // Ekstrak halaman
                $pagePath = storage_path('app/temp/' . uniqid() . '_page_' . $index . '.pdf');
                $command = sprintf(
                    'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dFirstPage=%d -dLastPage=%d -sOutputFile="%s" "%s"',
                    $pageNum,
                    $pageNum,
                    $pagePath,
                    $inputPath
                );

                exec($command, $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new Exception('Gagal ekstrak halaman ' . $pageNum);
                }

                // Jika ada rotasi, terapkan rotasi
                if ($rotation > 0) {
                    $rotatedPath = storage_path('app/temp/' . uniqid() . '_rotated_' . $index . '.pdf');

                    // Gunakan pdftk jika tersedia, atau ghostscript
                    $rotateCommand = sprintf(
                        'pdftk "%s" cat 1-%s%s output "%s" 2>&1 || gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dAutoRotatePages=/None -c "<</Orientation %d>> setpagedevice" -f "%s" -sOutputFile="%s"',
                        $pagePath,
                        'east',
                        str_repeat('', $rotation / 90 - 1),
                        $rotatedPath,
                        $rotation,
                        $pagePath,
                        $rotatedPath
                    );

                    // Cara sederhana: gunakan FPDI untuk rotasi
                    $pdf = new Fpdi();
                    $pdf->setSourceFile($pagePath);
                    $tplId = $pdf->importPage(1);
                    $size = $pdf->getTemplateSize($tplId);

                    // Tentukan ukuran dan orientasi
                    if ($rotation == 90 || $rotation == 270) {
                        $pdf->AddPage($size['height'] > $size['width'] ? 'P' : 'L', [$size['height'], $size['width']]);
                    } else {
                        $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                    }

                    // Gunakan template dengan rotasi
                    $pdf->useTemplate($tplId, null, null, null, null, true);

                    // Untuk rotasi, kita perlu memanipulasi PDF
                    if ($rotation == 90) {
                        $pdf->AddPage('L', [$size['height'], $size['width']]);
                        $pdf->useTemplate($tplId, 0, $size['width'], $size['height'], null, true);
                    } elseif ($rotation == 180) {
                        $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                        $pdf->useTemplate($tplId, $size['width'], $size['height'], -$size['width'], -$size['height'], true);
                    } elseif ($rotation == 270) {
                        $pdf->AddPage('L', [$size['height'], $size['width']]);
                        $pdf->useTemplate($tplId, $size['height'], 0, null, $size['width'], true);
                    }

                    $pdf->Output('F', $rotatedPath);

                    if (file_exists($pagePath)) {
                        unlink($pagePath);
                    }
                    $extractedPages[] = $rotatedPath;
                } else {
                    $extractedPages[] = $pagePath;
                }
            }

            // Gabungkan semua halaman
            $outputPath = storage_path('app/temp/' . uniqid() . '_output.pdf');
            $command = 'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="' . $outputPath . '" ' .
                      implode(' ', array_map(function($file) {
                          return '"' . $file . '"';
                      }, $extractedPages));

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new Exception('Gagal menggabungkan halaman');
            }

            // Baca hasil
            $outputContent = file_get_contents($outputPath);

            // Cleanup
            if (file_exists($inputPath)) {
                unlink($inputPath);
            }
            foreach ($extractedPages as $page) {
                if (file_exists($page)) {
                    unlink($page);
                }
            }
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }

            return response($outputContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="dokumen-edited.pdf"');

        } catch (Exception $e) {
            // Cleanup jika error
            if (isset($inputPath) && file_exists($inputPath)) {
                unlink($inputPath);
            }
            if (isset($extractedPages)) {
                foreach ($extractedPages as $page) {
                    if (file_exists($page)) {
                        unlink($page);
                    }
                }
            }
            if (isset($outputPath) && file_exists($outputPath)) {
                unlink($outputPath);
            }

            Log::error('Error in saveEditedPdf: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    public function mergeAdditionalPdf(Request $request)
    {
        try {
            \Log::info('mergeAdditionalPdf called');

            // Validasi
            $validator = Validator::make($request->all(), [
                'original_pdf' => 'required|file|mimes:pdf|max:51200',
                'new_pdf' => 'required|file|mimes:pdf|max:51200',
                'pages' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal: ' . $validator->errors()->first()
                ], 422);
            }

            $pagesData = json_decode($request->pages, true);

            // Buat temporary directory
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            // Simpan file original
            $originalFile = $request->file('original_pdf');
            $originalPath = storage_path('app/temp/' . uniqid() . '_original.pdf');
            $originalFile->move(dirname($originalPath), basename($originalPath));

            // Simpan file baru
            $newFile = $request->file('new_pdf');
            $newPath = storage_path('app/temp/' . uniqid() . '_new.pdf');
            $newFile->move(dirname($newPath), basename($newPath));

            // Array untuk menyimpan halaman yang akan digabung
            $extractedPages = [];

            // 1. Ekstrak halaman dari PDF original sesuai urutan dan rotasi
            foreach ($pagesData as $index => $pageInfo) {
                $pageNum = $pageInfo['originalPage'];
                $rotation = $pageInfo['rotation'] ?? 0;

                // Ekstrak halaman
                $pagePath = storage_path('app/temp/' . uniqid() . '_page_' . $index . '.pdf');
                $command = sprintf(
                    'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dFirstPage=%d -dLastPage=%d -sOutputFile="%s" "%s"',
                    $pageNum,
                    $pageNum,
                    $pagePath,
                    $originalPath
                );

                exec($command, $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new Exception('Gagal ekstrak halaman ' . $pageNum);
                }

                // Jika ada rotasi, terapkan rotasi
                if ($rotation > 0) {
                    $rotatedPath = storage_path('app/temp/' . uniqid() . '_rotated_' . $index . '.pdf');

                    $pdf = new Fpdi();
                    $pdf->setSourceFile($pagePath);
                    $tplId = $pdf->importPage(1);
                    $size = $pdf->getTemplateSize($tplId);

                    // Tentukan ukuran dan orientasi
                    if ($rotation == 90 || $rotation == 270) {
                        $pdf->AddPage($size['height'] > $size['width'] ? 'P' : 'L', [$size['height'], $size['width']]);
                    } else {
                        $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                    }

                    $pdf->useTemplate($tplId, null, null, null, null, true);

                    if ($rotation == 90) {
                        $pdf->AddPage('L', [$size['height'], $size['width']]);
                        $pdf->useTemplate($tplId, 0, $size['width'], $size['height'], null, true);
                    } elseif ($rotation == 180) {
                        $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                        $pdf->useTemplate($tplId, $size['width'], $size['height'], -$size['width'], -$size['height'], true);
                    } elseif ($rotation == 270) {
                        $pdf->AddPage('L', [$size['height'], $size['width']]);
                        $pdf->useTemplate($tplId, $size['height'], 0, null, $size['width'], true);
                    }

                    $pdf->Output('F', $rotatedPath);

                    if (file_exists($pagePath)) {
                        unlink($pagePath);
                    }
                    $extractedPages[] = $rotatedPath;
                } else {
                    $extractedPages[] = $pagePath;
                }
            }

            // 2. Tambahkan file PDF baru ke array
            $extractedPages[] = $newPath;

            // 3. Gabungkan semua halaman
            $outputPath = storage_path('app/temp/' . uniqid() . '_merged.pdf');
            $command = 'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="' . $outputPath . '" ' .
                      implode(' ', array_map(function($file) {
                          return '"' . $file . '"';
                      }, $extractedPages));

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new Exception('Gagal menggabungkan halaman');
            }

            // Baca hasil
            $outputContent = file_get_contents($outputPath);

            // Cleanup
            if (file_exists($originalPath)) {
                unlink($originalPath);
            }
            if (file_exists($newPath)) {
                unlink($newPath);
            }
            foreach ($extractedPages as $page) {
                if (file_exists($page) && $page !== $newPath) {
                    unlink($page);
                }
            }
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }

            return response($outputContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="merged-temp.pdf"');

        } catch (Exception $e) {
            // Cleanup jika error
            if (isset($originalPath) && file_exists($originalPath)) {
                unlink($originalPath);
            }
            if (isset($newPath) && file_exists($newPath)) {
                unlink($newPath);
            }
            if (isset($extractedPages)) {
                foreach ($extractedPages as $page) {
                    if (file_exists($page)) {
                        unlink($page);
                    }
                }
            }
            if (isset($outputPath) && file_exists($outputPath)) {
                unlink($outputPath);
            }

            Log::error('Error in mergeAdditionalPdf: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menggabungkan PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    public function mergeMultiplePdfs(Request $request)
    {
        try {
            \Log::info('mergeMultiplePdfs called', [
                'has_pdfs' => $request->hasFile('pdfs'),
                'all_files' => $request->allFiles(),
                'all_data' => array_keys($request->all())
            ]);

            // Check jika file ada
            if (!$request->hasFile('pdfs')) {
                \Log::error('No pdfs files in request');
                return response()->json([
                    'success' => false,
                    'message' => 'File PDF tidak ditemukan'
                ], 400);
            }

            $files = $request->file('pdfs');

            // Validasi manual
            if (!is_array($files) || count($files) === 0) {
                \Log::error('pdfs is not an array or empty', ['files' => $files]);
                return response()->json([
                    'success' => false,
                    'message' => 'Format file tidak valid'
                ], 400);
            }

            // Validasi setiap file
            foreach ($files as $index => $file) {
                if (!$file->isValid()) {
                    \Log::error('File not valid', ['index' => $index]);
                    return response()->json([
                        'success' => false,
                        'message' => 'File ke-' . ($index + 1) . ' tidak valid'
                    ], 400);
                }

                if ($file->getClientMimeType() !== 'application/pdf') {
                    return response()->json([
                        'success' => false,
                        'message' => 'File ke-' . ($index + 1) . ' bukan PDF'
                    ], 400);
                }

                if ($file->getSize() > 50 * 1024 * 1024) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File ke-' . ($index + 1) . ' melebihi 50MB'
                    ], 400);
                }
            }

            // Jika hanya 1 file, langsung return
            if (count($files) === 1) {
                $content = file_get_contents($files[0]->getRealPath());
                return response($content)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="document.pdf"');
            }

            // Buat temporary directory
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            // Simpan semua file PDF ke temp folder
            $tempFiles = [];
            foreach ($files as $index => $file) {
                $tempName = uniqid() . '_' . $index . '.pdf';
                $path = storage_path('app/temp/' . $tempName);
                $file->move(dirname($path), basename($path));
                $tempFiles[] = $path;
            }

            // Gabungkan semua PDF menggunakan ghostscript
            $outputPath = storage_path('app/temp/' . uniqid() . '_merged.pdf');
            $command = 'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPreserveAnnots=true ' .
                      '-dPreserveMarkedContent=true -dPrinted=false ' .
                      '-sOutputFile="' . $outputPath . '" ' .
                      implode(' ', array_map(function($file) {
                          return '"' . $file . '"';
                      }, $tempFiles));

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new Exception('Gagal menggabungkan PDF: ' . implode("\n", $output));
            }

            // Baca hasil gabungan
            $mergedContent = file_get_contents($outputPath);

            // Cleanup temporary files
            foreach ($tempFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }

            return response($mergedContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="merged-pdfs.pdf"');

        } catch (Exception $e) {
            // Cleanup jika terjadi error
            if (isset($tempFiles)) {
                foreach ($tempFiles as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
            if (isset($outputPath) && file_exists($outputPath)) {
                unlink($outputPath);
            }

            Log::error('Error in mergeMultiplePdfs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menggabungkan PDF: ' . $e->getMessage()
            ], 500);
        }
    }
} 