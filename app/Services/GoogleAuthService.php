<?php

namespace App\Services;

use App\Models\YouTubeToken;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\YouTube;
use RuntimeException;

class GoogleAuthService
{
    public function baseClient(): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId(config('youtube.client_id'));
        $client->setClientSecret(config('youtube.client_secret'));
        $client->setRedirectUri(config('youtube.redirect_uri'));

        return $client;
    }

    public function authenticatedClient(): GoogleClient
    {
        $client = $this->baseClient();

        $tokenRecord = YouTubeToken::current();

        if (! $tokenRecord) {
            throw new RuntimeException('Google no está configurado. Autoriza la cuenta en /admin/youtube/authorize.');
        }

        $client->setAccessToken($tokenRecord->token);

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken();

            if (! $refreshToken) {
                throw new RuntimeException('Google refresh token no disponible. Re-autoriza la cuenta.');
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($newToken['error'])) {
                throw new RuntimeException("Google token refresh failed: {$newToken['error_description']}");
            }

            $tokenRecord->update(['token' => $client->getAccessToken()]);
        }

        return $client;
    }

    public function getAuthUrl(): string
    {
        $client = $this->baseClient();
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->addScope(YouTube::YOUTUBE);
        $client->addScope(Drive::DRIVE_FILE);

        return $client->createAuthUrl();
    }

    public function handleCallback(string $code): void
    {
        $client = $this->baseClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException("Google OAuth error: {$token['error_description']}");
        }

        YouTubeToken::query()->delete();
        YouTubeToken::create(['token' => $token]);
    }

    public function isConfigured(): bool
    {
        return YouTubeToken::current() !== null;
    }

    /** @return array{access_token: string, expires_at: int} */
    public function getAccessToken(): array
    {
        $client = $this->authenticatedClient();
        $token = $client->getAccessToken();

        return [
            'access_token' => $token['access_token'],
            'expires_at' => $token['created'] + $token['expires_in'],
        ];
    }
}
