<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PeruriService
{
    protected $baseUrl;
    protected $apiKey;
    protected $systemId;

    public function __construct()
    {
        $this->baseUrl = config('services.peruri.base_url');
        $this->apiKey = config('services.peruri.api_key');
        $this->systemId = config('services.peruri.system_id');
    }

    protected function getJwtToken()
    {
        // Cek apakah token sudah ada di cache
        if (Cache::has('peruri_jwt_token')) {
            return Cache::get('peruri_jwt_token');
        }

        $response = Http::withHeaders([
            'x-Gateway-APIKey' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/jwt/1.0/getJsonWebToken/v1', [
            'param' => [
                'systemId' => $this->systemId
            ]
        ]);

        $token = $response->json()['token'] ?? null;

        // Simpan token di cache (misalnya untuk 55 menit)
        if ($token) {
            Cache::put('peruri_jwt_token', $token, now()->addMinutes(55));
        }

        return $token;
    }

    public function uploadDocument($file, $email, $signaturePosition)
    {
        $token = $this->getJwtToken();

        // Baca file dan convert ke base64
        $base64Document = base64_encode(file_get_contents($file->path()));
        
        $payload = [
            'param' => [
                'email' => $email,
                'payload' => [
                    'fileName' => $file->getClientOriginalName(),
                    'base64Document' => $base64Document,
                    'signer' => [
                        [
                            'isVisualSign' => 'YES',
                            'lowerLeftX' => $signaturePosition['lowerLeftX'] ?? '86',
                            'lowerLeftY' => $signaturePosition['lowerLeftY'] ?? '88',
                            'upperRightX' => $signaturePosition['upperRightX'] ?? '145',
                            'upperRightY' => $signaturePosition['upperRightY'] ?? '136',
                            'page' => $signaturePosition['page'] ?? '1',
                            'certificateLevel' => 'NOT_CERTIFIED',
                            'varLocation' => 'Jakarta',
                            'varReason' => 'Signed'
                        ]
                    ]
                ],
                'systemId' => $this->systemId,
                'orderType' => 'INDIVIDUAL'
            ]
        ];

        $response = Http::withHeaders([
            'x-Gateway-APIKey' => $this->apiKey,
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/digitalSignatureSession/1.0/sendDocument/v1', $payload);

        return $response->json();
    }

    public function signDocument($documentId)
    {
        $token = $this->getJwtToken();

        $response = Http::withHeaders([
            'x-Gateway-APIKey' => $this->apiKey,
            'Authorization' => 'Bearer ' . $token,
            'System-ID' => $this->systemId
        ])->post($this->baseUrl . '/documents/' . $documentId . '/sign');

        return $response->json();
    }

    public function downloadDocument($orderId)
    {
        $token = $this->getJwtToken();

        $response = Http::withHeaders([
            'x-Gateway-APIKey' => $this->apiKey,
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/digitalSignatureSession/1.0/downloadDocument/v1', [
            'param' => [
                'orderId' => $orderId
            ]
        ]);

        return $response->json();
    }

    public function createSigningSession($orderId)
    {
        $token = $this->getJwtToken();

        $response = Http::withHeaders([
            'x-Gateway-APIKey' => $this->apiKey,
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/digitalSignatureSession/1.0/signingSession/v1', [
            'param' => [
                'orderId' => $orderId
            ]
        ]);

        return $response->json();
    }

    public function sendOTP($email)
    {
        $token = $this->getJwtToken();

        $response = Http::withHeaders([
            'x-Gateway-APIKey' => $this->apiKey,
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/digitalSignatureSession/1.0/sessionInitiate/v1', [
            'param' => [
                'email' => $email,
                'systemId' => $this->systemId,
                'sendEmail' => '1',
                'sendSms' => '1',
                'sendWhatsapp' => '1'
            ]
        ]);

        return $response->json();
    }

    public function validateOTP($email, $tokenSession, $otpCode)
    {
        $token = $this->getJwtToken();

        $response = Http::withHeaders([
            'x-Gateway-APIKey' => $this->apiKey,
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/digitalSignatureSession/1.0/sessionValidation/v1', [
            'param' => [
                'email' => $email,
                'systemId' => $this->systemId,
                'tokenSession' => $tokenSession,
                'otpCode' => $otpCode,
                'duration' => 'in minutes'
            ]
        ]);

        return $response->json();
    }
} 