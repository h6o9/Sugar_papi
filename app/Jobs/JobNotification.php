<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class JobNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fcmToken;
    protected $title;
    protected $description;
    protected $data;

    public function __construct($fcmToken, $title, $description, $data = [])
    {
        $this->fcmToken   = $fcmToken;
        $this->title      = $title;
        $this->description = $description;
        $this->data       = $data;
    }

    public function handle()
    {
        $this->sendPushNotification();
    }

    private function sendPushNotification()
    {
        try {
            $serviceAccountPath = storage_path('firebase/sugar-pappi-firebase-adminsdk-fbsvc-693422ffa1.json');
            
            if (!file_exists($serviceAccountPath)) {
                \Log::error('Firebase service account file not found at: ' . $serviceAccountPath);
                return false;
            }
            
            $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
            
            // Get access token using JWT
            $accessToken = $this->getAccessToken($serviceAccount);
            
            if (!$accessToken) {
                \Log::error('Failed to get access token for JobNotification');
                return false;
            }
            
            // Convert data array to string values for FCM v1
            $dataPayload = [];
            foreach ($this->data as $key => $value) {
                $dataPayload[$key] = is_string($value) ? $value : json_encode($value);
            }
            
            $payload = [
                'message' => [
                    'token' => $this->fcmToken,
                    'notification' => [
                        'title' => $this->title,
                        'body'  => $this->description,
                    ],
                    'data' => $dataPayload,
                ]
            ];
            
            $headers = [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ];
            
            $projectId = $serviceAccount['project_id'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            \Log::info('JobNotification FCM Response', [
                'fcm_token' => substr($this->fcmToken, 0, 20) . '...',
                'title' => $this->title,
                'body' => $this->description,
                'response' => $response,
                'http_code' => $httpCode
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            \Log::error('JobNotification Error: ' . $e->getMessage());
            return false;
        }
    }

    private function getAccessToken($serviceAccount)
    {
        try {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        $iat = time();
        $exp = $iat + 3600; // 1 hour
        
        $payload = json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $exp,
            'iat' => $iat
        ]);
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $privateKey = $serviceAccount['private_key'];
        openssl_sign($base64UrlHeader . '.' . $base64UrlPayload, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        $jwt = $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $tokenData = json_decode($response, true);
            return $tokenData['access_token'] ?? null;
        }
        
        \Log::error('Token fetch failed', ['response' => $response, 'http_code' => $httpCode]);
        return null;
        
    } catch (\Exception $e) {
        \Log::error('getAccessToken Error: ' . $e->getMessage());
        return null;
    }
    }
}