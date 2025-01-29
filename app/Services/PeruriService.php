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
        if (Cache::has('peruri_jwt_token')) {
            return Cache::get('peruri_jwt_token');
        }

        try {
            $baseUrl = rtrim($this->baseUrl, '/');
            $endpoint = $baseUrl . '/jwtSandbox/1.0/getJsonWebToken/v1';
            
            \Log::info('JWT Request:', [
                'endpoint' => $endpoint,
                'systemId' => $this->systemId
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-Gateway-APIKey' => $this->apiKey
            ])->post($endpoint, [
                'param' => [
                    'systemId' => $this->systemId
                ]
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to get JWT: ' . $response->body());
            }

            $data = $response->json();
            
            if (!isset($data['data']['jwt'])) {
                throw new \Exception('JWT token not found in response');
            }

            \Log::info('JWT Token Generated', [
                'token_preview' => substr($data['data']['jwt'], 0, 30) . '...'
            ]);

            $token = $data['data']['jwt'];
            Cache::put('peruri_jwt_token', $token, now()->addMinutes(55));
            
            return $token;
            
        } catch (\Exception $e) {
            \Log::error('JWT Generation Error', [
                'message' => $e->getMessage(),
                'system_id' => $this->systemId,
                'endpoint' => $endpoint ?? null
            ]);
            throw new \Exception('JWT Error: ' . $e->getMessage());
        }
    }

    public function uploadDocument($file, $email, $signaturePosition)
    {
        try {
            $token = $this->getJwtToken();
            if (!$token) {
                throw new \Exception('Gagal mendapatkan JWT token');
            }

            $url = rtrim($this->baseUrl, '/') . '/digitalSignatureSession/1.0/sendDocument/v1';
            
            $base64Document = base64_encode(file_get_contents($file->path()));
            
            $payload = [
                'param' => [
                    'email' => $email,
                    'payload' => [
                        'fileName' => $file->getClientOriginalName(),
                        'base64Document' => $base64Document,
                        'signer' => [
                            [
                                'email' => $email,
                                'isVisualSign' => 'YES',
                                'lowerLeftX' => '556',
                                'lowerLeftY' => '6',
                                'upperRightX' => '589',
                                'upperRightY' => '40',
                                'page' => '1',
                                'certificateLevel' => 'NOT_CERTIFIED',
                                'varLocation' => 'Sigli',
                                'varReason' => 'Signed',
                                'teraImage' => 'QR-DETECSI'
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
            ])->post($url, $payload);

            $responseData = $response->json();
            \Log::info('Upload Document Response:', $responseData);

            if ($responseData['resultCode'] === 'SB001') {
                throw new \Exception('Email ' . $email . ' belum terdaftar di Peruri. Silahkan daftarkan email terlebih dahulu.');
            }

            if (!isset($responseData['documentId'])) {
                throw new \Exception('Gagal mendapatkan documentId dari Peruri: ' . ($responseData['resultDesc'] ?? 'Unknown error'));
            }

            return $responseData;
        } catch (\Exception $e) {
            \Log::error('Error dalam PeruriService::uploadDocument: ' . $e->getMessage());
            throw $e;
        }
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