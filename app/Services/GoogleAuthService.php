<?php

namespace App\Services;

use App\Models\YouTubeToken;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\YouTube;
use RuntimeException;

class GoogleAuthService
{
    /**
     * Build a base Google Client without tokens.
     *
     * Uses the same OAuth credentials as YouTube since both services
     * share the same Google Cloud project.
     */
    public function baseClient(): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId(config('youtube.client_id'));
        $client->setClientSecret(config('youtube.client_secret'));
        $client->setRedirectUri(config('youtube.redirect_uri'));

        return $client;
    }

    /**
     * Build an authenticated Google Client with auto-refresh.
     *
     * Loads the stored OAuth token and refreshes it automatically
     * when expired. Updates the stored token after refresh.
     */
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

    /**
     * Get the Google OAuth authorization URL.
     *
     * Requests both YouTube and Drive scopes so a single authorization
     * grants access to both services.
     */
    public function getAuthUrl(): string
    {
        $client = $this->baseClient();
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->addScope(YouTube::YOUTUBE);
        $client->addScope(Drive::DRIVE_FILE);

        return $client->createAuthUrl();
    }

    /** Exchange an authorization code for tokens and store them. */
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

    /** Check if Google is configured with a valid token. */
    public function isConfigured(): bool
    {
        return YouTubeToken::current() !== null;
    }

    /**
     * Get the current valid access token for frontend use.
     *
     * @return array{access_token: string, expires_at: int}
     */
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
