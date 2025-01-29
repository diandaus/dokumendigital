<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;

Route::get('/', function () {
    return redirect()->route('documents.index');
});

Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
Route::post('/documents/{id}/send-otp', [DocumentController::class, 'sendOTP'])->name('documents.sendOTP');
Route::post('/documents/{id}/validate-otp', [DocumentController::class, 'validateOTP'])->name('documents.validateOTP');
Route::post('/documents/{id}/sign-with-session', [DocumentController::class, 'signWithSession']);
Route::get('/documents/{orderId}/download', [DocumentController::class, 'downloadSignedDocument']);
Route::post('/documents/{id}/send', [DocumentController::class, 'sendToPeruri'])->name('documents.send');
