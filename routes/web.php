<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;

Route::get('/', function () {
    return redirect()->route('documents.index');
});

Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');

// Route specific harus di atas route dengan parameter dinamis
Route::get('/documents/merge', [DocumentController::class, 'mergePage']);
Route::post('/documents/merge', [DocumentController::class, 'mergePdf']);
Route::get('/documents/page-editor', [DocumentController::class, 'pageEditor']);
Route::post('/documents/save-edited-pdf', [DocumentController::class, 'saveEditedPdf']);
Route::post('/documents/merge-additional-pdf', [DocumentController::class, 'mergeAdditionalPdf']);
Route::post('/documents/merge-multiple-pdfs', [DocumentController::class, 'mergeMultiplePdfs']);

// Route dengan parameter dinamis di bawah
Route::post('/documents/{id}/send-otp', [DocumentController::class, 'sendOTP'])->name('documents.sendOTP');
Route::post('/documents/{id}/validate-otp', [DocumentController::class, 'validateOTP'])->name('documents.validateOTP');
Route::post('/documents/{id}/sign-with-session', [DocumentController::class, 'signWithSession']);
Route::get('/documents/{orderId}/download', [DocumentController::class, 'download'])->name('documents.download');
Route::post('/documents/{id}/send', [DocumentController::class, 'sendToPeruri'])->name('documents.send');
