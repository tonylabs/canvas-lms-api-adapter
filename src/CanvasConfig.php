<?php

namespace TONYLABS\Canvas;

class CanvasConfig
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Create config from Laravel config
     */
    public static function fromLaravelConfig(string $configKey = 'canvas'): self
    {
        return new self(config($configKey, []));
    }

    /**
     * Get Canvas domain
     */
    public function getDomain(): string
    {
        return $this->config['domain'] ?? '';
    }

    /**
     * Get OAuth client ID
     */
    public function getClientId(): string
    {
        return $this->config['client_id'] ?? '';
    }

    /**
     * Get OAuth client secret
     */
    public function getClientSecret(): string
    {
        return $this->config['client_secret'] ?? '';
    }

    /**
     * Get refresh token
     */
    public function getRefreshToken(): string
    {
        return $this->config['refresh_token'] ?? '';
    }

    /**
     * Get access token (if manually set)
     */
    public function getAccessToken(): ?string
    {
        return $this->config['access_token'] ?? null;
    }

    /**
     * Check if auto-refresh is enabled
     */
    public function isAutoRefreshEnabled(): bool
    {
        return $this->config['auto_refresh'] ?? true;
    }

    /**
     * Get token refresh endpoint
     */
    public function getTokenEndpoint(): string
    {
        return $this->config['token_endpoint'] ?? '/login/oauth2/token';
    }

    /**
     * Create Request instance from config
     */
    public function createRequest(): Request
    {
        if ($this->isAutoRefreshEnabled() && $this->getRefreshToken()) {
            return Request::withAutoRefresh(
                $this->getDomain(),
                $this->getClientId(),
                $this->getClientSecret(),
                $this->getRefreshToken()
            );
        }

        return new Request($this->getDomain(), $this->getAccessToken());
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [
            'domain' => env('CANVAS_DOMAIN'),
            'client_id' => env('CANVAS_CLIENT_ID'),
            'client_secret' => env('CANVAS_CLIENT_SECRET'),
            'refresh_token' => env('CANVAS_REFRESH_TOKEN'),
            'access_token' => env('CANVAS_ACCESS_TOKEN'),
            'auto_refresh' => env('CANVAS_AUTO_REFRESH', true),
            'token_endpoint' => '/login/oauth2/token',
        ];
    }

    /**
     * Get all config values
     */
    public function toArray(): array
    {
        return $this->config;
    }
}