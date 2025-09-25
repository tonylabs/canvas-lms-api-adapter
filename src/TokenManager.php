<?php

namespace TONYLABS\Canvas;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use TONYLABS\Canvas\Exception\MissingRefreshTokenException;
use TONYLABS\Canvas\Exception\TokenRefreshException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TokenManager
{
    protected Client $client;
    protected string $domain;
    protected string $clientId;
    protected string $clientSecret;
    protected string $refreshToken;
    protected ?string $accessToken = null;
    protected ?int $expiresAt = null;
    protected string $cacheKey;

    public function __construct(string $domain, string $clientId, string $clientSecret, string $refreshToken) {
        $this->domain = rtrim($domain, '/');
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->refreshToken = $refreshToken;
        $this->cacheKey = 'canvas_token_' . md5($domain . $clientId);
        $this->client = new Client([
            'base_uri' => $this->domain,
            'timeout' => 30,
        ]);
        $this->loadTokenFromCache();
    }

    /**
     * Get a valid access token, refreshing if necessary
     */
    public function getValidToken(): string
    {
        if ($this->isTokenExpired()) {
            $this->refreshAccessToken();
        }
        return $this->accessToken;
    }

    /**
     * Check if the current token is expired or will expire soon (within 5 minutes)
     */
    public function isTokenExpired(): bool
    {
        if (!$this->accessToken || !$this->expiresAt) {
            return true;
        }
        // Consider token expired if it expires within 5 minutes
        return time() >= ($this->expiresAt - 300);
    }

    /**
     * Refresh the access token using the refresh token
     */
    public function refreshAccessToken(): void
    {
        if (empty($this->refreshToken)) {
            throw new MissingRefreshTokenException('Refresh token is required for auto-refresh');
        }

        try {
            $response = $this->client->post('/login/oauth2/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['access_token'])) {
                throw new TokenRefreshException('Invalid response from token refresh endpoint');
            }

            $this->accessToken = $data['access_token'];
            $this->expiresAt = time() + ($data['expires_in'] ?? 3600);
            
            // Update refresh token if provided
            if (isset($data['refresh_token'])) {
                $this->refreshToken = $data['refresh_token'];
            }
            
            $this->saveTokenToCache();
            
            Log::info('Canvas API token refreshed successfully', [
                'domain' => $this->domain,
                'expires_at' => date('Y-m-d H:i:s', $this->expiresAt)
            ]);
            
        } catch (ClientException $e) {
            $errorBody = $e->getResponse()->getBody()->getContents();
            $errorData = json_decode($errorBody, true) ?? [];
            
            Log::error('Failed to refresh Canvas API token', [
                'domain' => $this->domain,
                'error' => $errorData['error_description'] ?? $e->getMessage(),
                'status_code' => $e->getCode()
            ]);
            
            throw new TokenRefreshException(
                'Failed to refresh token: ' . ($errorData['error_description'] ?? $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Manually set a new access token
     */
    public function setAccessToken(string $token, int $expiresIn = 3600): void
    {
        $this->accessToken = $token;
        $this->expiresAt = time() + $expiresIn;
        $this->saveTokenToCache();
    }

    /**
     * Get the current refresh token
     */
    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    /**
     * Update the refresh token
     */
    public function setRefreshToken(string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
        $this->saveTokenToCache();
    }

    /**
     * Load token data from cache
     */
    protected function loadTokenFromCache(): void
    {
        $cached = Cache::get($this->cacheKey);
        
        if ($cached && is_array($cached)) {
            $this->accessToken = $cached['access_token'] ?? null;
            $this->expiresAt = $cached['expires_at'] ?? null;
            $this->refreshToken = $cached['refresh_token'] ?? $this->refreshToken;
        }
    }

    /**
     * Save token data to cache
     */
    protected function saveTokenToCache(): void
    {
        $data = [
            'access_token' => $this->accessToken,
            'expires_at' => $this->expiresAt,
            'refresh_token' => $this->refreshToken,
        ];
        
        // Cache for the token lifetime plus 1 hour buffer
        $ttl = $this->expiresAt ? ($this->expiresAt - time() + 3600) : 3600;
        Cache::put($this->cacheKey, $data, $ttl);
    }

    /**
     * Clear cached token data
     */
    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
        $this->accessToken = null;
        $this->expiresAt = null;
    }
    
    // Add this method to the TokenManager class
    
    /**
     * Get the token expiration timestamp
     */
    public function getExpiresAt(): ?int
    {
        return $this->expiresAt;
    }
}