<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('documents', DocumentController::class);
Route::post('documents/{document}/sign', [DocumentController::class, 'sign'])->name('documents.sign');
Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
Route::post('documents/{id}/sign', [DocumentController::class, 'sign'])->name('documents.sign');
Route::get('documents/{id}/download', [DocumentController::class, 'download'])->name('documents.download');
Route::post('documents/{id}/send-otp', [DocumentController::class, 'sendOTP'])->name('documents.sendOTP');
Route::post('documents/{id}/validate-otp', [DocumentController::class, 'validateOTP'])->name('documents.validateOTP');
