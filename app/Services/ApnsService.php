<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ApnsService
{
    private string $teamId;
    private string $keyId;
    private string $keyPath;
    private string $topic;
    private bool $sandbox;

    private ?string $jwt = null;
    private int $jwtExpiresAt = 0;

    public function __construct()
    {
        $this->teamId = (string) config('services.apns.team_id');
        $this->keyId = (string) config('services.apns.key_id');
        $this->keyPath = (string) config('services.apns.key_path');
        $this->topic = (string) config('services.apns.topic');
        $this->sandbox = (bool) config('services.apns.sandbox', false);
    }

    public function sendToUsers(array $users, array $payload, ?string $collapseId = null): array
    {
        $tokens = [];
        $tokenToUser = [];

        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }
            $prefs = is_array($user->preferences) ? $user->preferences : [];
            $token = $prefs['device_token'] ?? null;
            $platform = $prefs['device_platform'] ?? 'ios';
            if (!$token || $platform !== 'ios') {
                continue;
            }
            $tokens[] = $token;
            $tokenToUser[$token] = $user;
        }

        if (empty($tokens)) {
            return [];
        }

        $results = $this->sendToTokens($tokens, $payload, $collapseId);

        foreach ($results as $token => $result) {
            if (($result['status'] ?? 0) === 410) {
                $user = $tokenToUser[$token] ?? null;
                if ($user instanceof User) {
                    $prefs = is_array($user->preferences) ? $user->preferences : [];
                    $prefs['device_token'] = null;
                    $user->update(['preferences' => $prefs]);
                }
            }
        }

        return $results;
    }

    public function sendToTokens(array $tokens, array $payload, ?string $collapseId = null): array
    {
        $results = [];
        $jwt = $this->getJwt();

        foreach ($tokens as $token) {
            $results[$token] = $this->sendOne($token, $payload, $jwt, $collapseId);
        }

        return $results;
    }

    private function sendOne(string $token, array $payload, string $jwt, ?string $collapseId = null): array
    {
        $host = $this->sandbox ? 'api.sandbox.push.apple.com' : 'api.push.apple.com';
        $url = "https://{$host}/3/device/{$token}";

        $headers = [
            'authorization: bearer ' . $jwt,
            'apns-topic: ' . $this->topic,
            'apns-push-type: alert',
            'apns-priority: 10',
            'content-type: application/json',
        ];

        if ($collapseId) {
            $headers[] = 'apns-collapse-id: ' . $collapseId;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::warning('APNs curl error', ['error' => $curlError]);
        }

        return [
            'status' => $status,
            'response' => $response,
        ];
    }

    private function getJwt(): string
    {
        $now = time();
        if ($this->jwt && $now < $this->jwtExpiresAt) {
            return $this->jwt;
        }

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'ES256',
            'kid' => $this->keyId,
            'typ' => 'JWT',
        ]));

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $this->teamId,
            'iat' => $now,
        ]));

        $data = $header . '.' . $claims;

        $privateKey = @file_get_contents($this->keyPath);
        if (!$privateKey) {
            throw new \RuntimeException('APNs key file not readable: ' . $this->keyPath);
        }

        $pkey = openssl_pkey_get_private($privateKey);
        if (!$pkey) {
            throw new \RuntimeException('Invalid APNs key file');
        }

        $signature = '';
        openssl_sign($data, $signature, $pkey, 'sha256');
        openssl_free_key($pkey);

        $jwt = $data . '.' . $this->base64UrlEncode($signature);

        $this->jwt = $jwt;
        $this->jwtExpiresAt = $now + 50 * 60;

        return $jwt;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
