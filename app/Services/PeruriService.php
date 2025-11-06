<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

    public function sendDocument($email, $fileName, $base64Document)
    {
        try {
            // Get JWT Token first
            $jwtToken = $this->getJwtToken();

            $url = $this->baseUrl . '/digitalSignatureSession/1.0/sendDocument/v1';

            // Setup signer configuration
            $signer = [
                "isVisualSign" => "YES",
                "lowerLeftX" => "523",
                "lowerLeftY" => "771",
                "upperRightX" => "557",
                "upperRightY" => "804",
                "page" => "1",
                "certificateLevel" => "NOT_CERTIFIED",
                "varLocation" => "Sigli",
                "varReason" => "Signed",
                "teraImage" => "QR-DETECSI"
            ];

            // Setup request body
            $requestBody = [
                "param" => [
                    "email" => $email,
                    "payload" => [
                        "fileName" => $fileName,
                        "base64Document" => $base64Document,
                        "signer" => [$signer]
                    ],
                    "systemId" => $this->systemId,
                    "orderType" => "INDIVIDUAL"
                ]
            ];

            Log::info('Sending document to Peruri', [
                'email' => $email,
                'fileName' => $fileName
            ]);

            $response = Http::withHeaders([
                'x-Gateway-APIKey' => $this->apiKey,
                'Authorization' => 'Bearer ' . $jwtToken,
                'Content-Type' => 'application/json'
            ])->post($url, $requestBody);

            Log::info('Peruri Send Document Response:', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to send document: ' . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Error in sendDocument:', [
                'message' => $e->getMessage(),
                'email' => $email,
                'fileName' => $fileName
            ]);
            throw $e;
        }
    }

    protected function getJwtToken()
    {
        try {
            Log::info('Requesting new JWT token');
            
            $url = $this->baseUrl . '/jwt/1.0/getJsonWebToken/v1';
            
            $response = Http::withHeaders([
                'x-Gateway-APIKey' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($url, [
                'param' => [
                    'systemId' => $this->systemId
                ]
            ]);

            Log::info('JWT Full Response:', [
                'status' => $response->status(),
                'body' => $response->json(),
                'url' => $url,
                'systemId' => $this->systemId
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to get JWT: ' . $response->body());
            }

            $data = $response->json();
            
            if (isset($data['resultCode']) && $data['resultCode'] === '0' && isset($data['data']['jwt'])) {
                Log::info('New JWT token generated');
                return $data['data']['jwt'];
            }

            throw new \Exception('Invalid JWT response format: ' . json_encode($data));

        } catch (\Exception $e) {
            Log::error('JWT Generation Error', [
                'message' => $e->getMessage(),
                'system_id' => $this->systemId
            ]);
            throw $e;
        }
    }

    public function initiateSession($email)
    {
        try {
            // Get JWT Token first
            $jwtToken = $this->getJwtToken();

            $url = $this->baseUrl . '/digitalSignatureSession/1.0/sessionInitiate/v1';

            // Setup request body
            $requestBody = [
                "param" => [
                    "email" => $email,
                    "systemId" => $this->systemId,
                    "sendEmail" => "1",
                    "sendSms" => "1",
                    "sendWhatsapp" => "0"
                ]
            ];

            Log::info('Initiating session with Peruri', [
                'email' => $email,
                'url' => $url
            ]);

            $response = Http::withHeaders([
                'x-Gateway-APIKey' => $this->apiKey,
                'Authorization' => 'Bearer ' . $jwtToken,
                'Content-Type' => 'application/json'
            ])->post($url, $requestBody);

            Log::info('Peruri Session Initiate Response:', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if (!$response->successful()) {
                throw new \Exception('Gagal melakukan inisiasi session: ' . $response->body());
            }

            $data = $response->json();

            if (isset($data['resultCode']) && $data['resultCode'] === '0') {
                return $data;
            }

            throw new \Exception('Invalid session initiate response: ' . json_encode($data));

        } catch (\Exception $e) {
            Log::error('Error in initiateSession:', [
                'message' => $e->getMessage(),
                'email' => $email
            ]);
            throw new \Exception('Error memanggil Session Initiate API: ' . $e->getMessage());
        }
    }

    public function validateSession($email, $tokenSession, $otpCode)
    {
        try {
            // Get JWT Token first
            $jwtToken = $this->getJwtToken();

            $url = $this->baseUrl . '/digitalSignatureSession/1.0/sessionValidation/v1';

            // Setup request body
            $requestBody = [
                "param" => [
                    "email" => $email,
                    "systemId" => $this->systemId,
                    "tokenSession" => $tokenSession,
                    "otpCode" => $otpCode,
                    "duration" => "1440" // 24 jam dalam menit
                ]
            ];

            Log::info('Validating session with Peruri', [
                'email' => $email,
                'tokenSession' => $tokenSession,
                'url' => $url
            ]);

            $response = Http::withHeaders([
                'x-Gateway-APIKey' => $this->apiKey,
                'Authorization' => 'Bearer ' . $jwtToken,
                'Content-Type' => 'application/json'
            ])->post($url, $requestBody);

            Log::info('Peruri Session Validation Response:', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if (!$response->successful()) {
                throw new \Exception('Gagal memvalidasi session: ' . $response->body());
            }

            $data = $response->json();

            if (isset($data['resultCode']) && $data['resultCode'] === '0') {
                return $data;
            }

            throw new \Exception('Invalid session validation response: ' . json_encode($data));

        } catch (\Exception $e) {
            Log::error('Error in validateSession:', [
                'message' => $e->getMessage(),
                'email' => $email,
                'tokenSession' => $tokenSession
            ]);
            throw new \Exception('Error memanggil Session Validation API: ' . $e->getMessage());
        }
    }

    public function signingSession($orderId)
    {
        try {
            // Get JWT Token first
            $jwtToken = $this->getJwtToken();

            $url = $this->baseUrl . '/digitalSignatureSession/1.0/signingSession/v1';

            // Setup request body
            $requestBody = [
                "param" => [
                    "orderId" => $orderId
                ]
            ];

            Log::info('Signing session with Peruri', [
                'orderId' => $orderId,
                'url' => $url
            ]);

            $response = Http::withHeaders([
                'x-Gateway-APIKey' => $this->apiKey,
                'Authorization' => 'Bearer ' . $jwtToken,
                'Content-Type' => 'application/json'
            ])->post($url, $requestBody);

            Log::info('Peruri Signing Session Response:', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if (!$response->successful()) {
                throw new \Exception('Gagal melakukan signing session: ' . $response->body());
            }

            $data = $response->json();

            if (isset($data['resultCode']) && $data['resultCode'] === '0') {
                return $data;
            }

            throw new \Exception('Invalid signing session response: ' . json_encode($data));

        } catch (\Exception $e) {
            Log::error('Error in signingSession:', [
                'message' => $e->getMessage(),
                'orderId' => $orderId
            ]);
            throw new \Exception('Error memanggil Signing Session API: ' . $e->getMessage());
        }
    }

    public function downloadDocument($orderId)
    {
        try {
            // Get JWT Token first
            $jwtToken = $this->getJwtToken();

            $url = $this->baseUrl . '/digitalSignatureSession/1.0/downloadDocument/v1';

            // Setup request body
            $requestBody = [
                "param" => [
                    "orderId" => $orderId
                ]
            ];

            Log::info('Downloading document from Peruri', [
                'orderId' => $orderId,
                'url' => $url
            ]);

            $response = Http::withHeaders([
                'x-Gateway-APIKey' => $this->apiKey,
                'Authorization' => 'Bearer ' . $jwtToken,
                'Content-Type' => 'application/json'
            ])->post($url, $requestBody);

            Log::info('Peruri Download Document Response:', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if (!$response->successful()) {
                throw new \Exception('Gagal mengunduh dokumen: ' . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Error in downloadDocument:', [
                'message' => $e->getMessage(),
                'orderId' => $orderId
            ]);
            throw $e;
        }
    }
}

   