<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected ?string $projectId;
    protected ?string $clientEmail;
    protected ?string $privateKey;

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id');
        $this->clientEmail = config('services.firebase.client_email');
        $this->privateKey = config('services.firebase.private_key');
        
        // Handle double-escaped newlines in environment variables
        if ($this->privateKey) {
            $this->privateKey = str_replace('\n', "\n", $this->privateKey);
        }
    }

    /**
     * Generate Google OAuth2 Access Token using Service Account JWT.
     */
    protected function getAccessToken(): ?string
    {
        if (!$this->clientEmail || !$this->privateKey) {
            Log::warning('Firebase service account credentials missing client_email or private_key.');
            return null;
        }

        $now = time();
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'iss' => $this->clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $signature = '';
        $success = openssl_sign(
            $base64UrlHeader . "." . $base64UrlPayload,
            $signature,
            $this->privateKey,
            'SHA256'
        );

        if (!$success) {
            Log::error('Firebase private key signing failed. Check if private key format is correct.');
            return null;
        }

        $base64UrlSignature = $this->base64UrlEncode($signature);
        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

        // Exchange JWT for access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            Log::error('Curl error exchanging JWT for Firebase token: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);

        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            Log::error('Firebase OAuth error: ' . ($data['error_description'] ?? $data['error']));
            return null;
        }

        return $data['access_token'] ?? null;
    }

    protected function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Send push notification via FCM.
     */
    public function sendPushNotification(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        if (!$this->projectId) {
            Log::warning('Firebase Project ID is missing.');
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::warning('Could not generate Firebase access token.');
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $payload = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ]
        ];

        if (!empty($data)) {
            // Convert all data values to strings as FCM data payload requires string values
            $stringData = [];
            foreach ($data as $key => $value) {
                $stringData[(string)$key] = (string)$value;
            }
            $payload['message']['data'] = $stringData;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            Log::error('Curl error sending FCM push: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            Log::error("FCM API returned HTTP {$httpCode}: {$response}");
            return false;
        }

        return true;
    }
}
