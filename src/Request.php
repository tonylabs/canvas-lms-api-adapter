<?php

namespace TONYLABS\Canvas;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response as LaravelResponse;
use TONYLABS\Canvas\Exception\MissingTokenException;
use TONYLABS\Canvas\Exception\MissingDomainException;
use TONYLABS\Canvas\Exception\TokenRefreshException;

class Request
{
    protected Client $client;
    protected string $domain;
    protected string $token;
    protected array $headers = [];
    protected ?TokenManager $tokenManager = null;
    protected bool $autoRefresh = false;

    public function __construct(
        string $domain = null,
        string $token = null,
        ?TokenManager $tokenManager = null
    ) {
        if (empty($domain)) {
            throw new MissingDomainException('Canvas domain is required');
        }

        if (empty($token) && !$tokenManager) {
            throw new MissingTokenException('Canvas API token or TokenManager is required');
        }

        $this->domain = $domain;
        $this->tokenManager = $tokenManager;
        $this->autoRefresh = $tokenManager !== null;

        // Use TokenManager if available, otherwise use provided token
        if ($this->tokenManager) {
            $this->token = $this->tokenManager->getValidToken();
        } else {
            $this->token = $token;
        }

        $this->updateHeaders();
        $this->initializeClient();
    }

    /**
     * Create Request instance with auto-refresh capability
     */
    public static function withAutoRefresh(
        string $domain,
        string $clientId,
        string $clientSecret,
        string $refreshToken
    ): self {
        $tokenManager = new TokenManager($domain, $clientId, $clientSecret, $refreshToken);
        return new self($domain, null, $tokenManager);
    }

    public function makeRequest(string $method, string $endpoint, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true) ?? [];

            // Include response headers for pagination
            return [
                'data' => $data,
                'headers' => $response->getHeaders()
            ];
        } catch (ClientException $e) {
            // If we get a 401 and auto-refresh is enabled, try to refresh token
            if ($e->getCode() === 401 && $this->autoRefresh && $this->tokenManager) {
                try {
                    $this->tokenManager->refreshAccessToken();
                    $this->token = $this->tokenManager->getValidToken();
                    $this->updateHeaders();
                    $this->initializeClient();

                    // Retry the request with new token
                    $response = $this->client->request($method, $endpoint, $options);
                    $body = $response->getBody()->getContents();
                    $data = json_decode($body, true) ?? [];

                    // Include response headers for pagination
                    return [
                        'data' => $data,
                        'headers' => $response->getHeaders()
                    ];
                } catch (TokenRefreshException $refreshException) {
                    // If refresh fails, throw the original exception
                    throw new \Exception(
                        'Canvas API Error: Token expired and refresh failed - ' . $refreshException->getMessage(),
                        $e->getCode(),
                        $e
                    );
                }
            }

            $responseBody = $e->getResponse()->getBody()->getContents();
            $errorData = json_decode($responseBody, true) ?? ['message' => 'Unknown error'];

            throw new \Exception(
                'Canvas API Error: ' . ($errorData['message'] ?? $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Update authorization headers
     */
    protected function updateHeaders(): void
    {
        $this->headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Initialize or reinitialize the HTTP client
     */
    protected function initializeClient(): void
    {
        $this->client = new Client([
            'base_uri' => $this->domain,
            'headers' => $this->headers,
        ]);
    }

    /**
     * Get the current token
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Check if auto-refresh is enabled
     */
    public function hasAutoRefresh(): bool
    {
        return $this->autoRefresh;
    }

    /**
     * Get the token manager instance
     */
    public function getTokenManager(): ?TokenManager
    {
        return $this->tokenManager;
    }
}
