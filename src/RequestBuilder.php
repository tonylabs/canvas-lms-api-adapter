<?php

namespace TONYLABS\Canvas;

use Illuminate\Support\Str;
use stdClass;
use TONYLABS\Canvas\Exception\TokenRefreshException;

class RequestBuilder {

    const GET = 'GET';
    const POST = 'POST';
    const PUT = 'PUT';
    const PATCH = 'PATCH';
    const DELETE = 'DELETE';

    protected Request $request;
    protected ?string $endpoint;
    protected ?string $method;
    protected array $options = [];
    protected ?array $data = null;
    protected array $queryString = [];
    protected string $pageKey = 'per_page';
    protected Paginator $paginator;
    protected ?TokenManager $tokenManager = null;
    protected bool $autoRefresh = false;
    protected string $domain;
    protected string $currentToken;

    public function __construct(string $domain, string $token, ?TokenManager $tokenManager = null)
    {
        $this->domain = $domain;
        $this->currentToken = $token;
        $this->tokenManager = $tokenManager;
        $this->autoRefresh = $tokenManager !== null;
        if ($this->tokenManager) {
            $this->currentToken = $this->tokenManager->getValidToken();
        }
        $this->request = new Request($domain, $this->currentToken);
    }

    /**
     * Create RequestBuilder instance with auto-refresh capability
     */
    public static function withAutoRefresh(string $domain, string $client_id, string $client_secret, string $refreshToken, ?string $initialToken = null): self {
        $objTokenManager = new TokenManager($domain, $client_id, $client_secret, $refreshToken);
        if ($initialToken) {
            $objTokenManager->setAccessToken($initialToken);
        }
        return new self($domain, $objTokenManager->getValidToken(), $objTokenManager);
    }

    /**
     * Create RequestBuilder from CanvasConfig
     */
    public static function fromConfig(CanvasConfig $config): self
    {
        if ($config->isAutoRefreshEnabled() && $config->getRefreshToken()) {
            return self::withAutoRefresh(
                $config->getDomain(),
                $config->getClientId(),
                $config->getClientSecret(),
                $config->getRefreshToken(),
                $config->getAccessToken()
            );
        }

        return new self($config->getDomain(), $config->getAccessToken());
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Refresh the token and recreate the Request instance
     */
    protected function refreshTokenAndRequest(): void
    {
        if (!$this->tokenManager) {
            throw new TokenRefreshException('Token manager is not available for token refresh');
        }
        $this->tokenManager->refreshAccessToken();
        $this->currentToken = $this->tokenManager->getValidToken();
        $this->request = new Request($this->domain, $this->currentToken);
    }

    /**
     * Reset all the variables for the next request
     */
    public function reset(): static
    {
        $this->endpoint = null;
        $this->method = null;
        $this->options = [];
        $this->data = null;
        $this->pageKey = 'per_page';
        unset($this->paginator);
        return $this;
    }

    /**
     * Sets the endpoint for the request
     */
    public function setEndpoint(string $endpoint): static
    {
        $this->endpoint = $endpoint;
        $this->pageKey = Str::afterLast($endpoint, '/');
        return $this;
    }

    /**
     * @see setEndpoint
     */
    public function toEndpoint(string $endpoint): static
    {
        return $this->setEndpoint($endpoint);
    }

    /**
     * @see setEndpoint
     */
    public function to(string $endpoint): static
    {
        return $this->setEndpoint($endpoint);
    }

    /**
     * @see setEndpoint
     */
    public function endpoint(string $endpoint): static
    {
        return $this->setEndpoint($endpoint);
    }

    /**
     * Sets the data for the post/put/patch requests
     * Also performs basic sanitation for PS, such
     * as bool translation
     */
    public function setData(array $data): static
    {
        $this->data = $this->flatDataToString($data);
        return $this;
    }

    /**
     * Alias for setData()
     */
    public function withData(array $data): static
    {
        return $this->setData($data);
    }

    /**
     * Alias for setData()
     */
    public function with(array $data): static
    {
        return $this->setData($data);
    }

    /**
     * Sets an item to be included in the post request
     */
    public function setDataItem(string $key, $value): static
    {
        $this->data[$key] = $this->flatDataToString($value);
        return $this;
    }

    /**
     * Sets the query string for get requests
     */
    public function withQueryString(string|array $queryString): static
    {
        if (is_array($queryString)) {
            $this->queryString = $queryString;
        } else {
            parse_str($queryString, $this->queryString);
        }
        return $this;
    }

    /**
     * Alias of withQueryString()
     */
    public function query(string|array $queryString): static
    {
        return $this->withQueryString($queryString);
    }

    /**
     * Adds a variable to the query string
     */
    public function addQueryVar(string $key, $value): static
    {
        $this->queryString[$key] = $value;
        return $this;
    }

    /**
     * Syntactic sugar for the q query string var
     */
    public function q(string $query): static
    {
        return $this->addQueryVar('q', $query);
    }

    /**
     * Sugar for q()
     */
    public function queryExpression(string $expression): static
    {
        return $this->q($expression);
    }

    /**
     * Syntactic sugar for the `pagesize` query string var
     */
    public function pageSize(int $pageSize): static
    {
        return $this->addQueryVar('per_page', $pageSize);
    }

    /**
     * Sets the page query variable
     */
    public function page(int $page): static
    {
        return $this->addQueryVar('page', $page);
    }

    /**
     * Flat all the data in an array recursively as a string
     */
    protected function flatDataToString(array $data): array
    {
        foreach ($data as $key => $value)
        {
            if (is_array($value)) {
                $data[$key] = $this->flatDataToString($value);
                continue;
            }
            // If it's null set the value to an empty string
            if (is_null($value)) $value = '';

            // If the type is a bool, set it to the
            // integer type that Canvas uses, 1 or 0
            if (is_bool($value)) $value = $value ? '1' : '0';

            $data[$key] = (string) $value;
        }
        return $data;
    }

    /**
     * Builds the dumb request structure for Canvas
     */
    public function buildRequestJson(): static
    {
        if ($this->method === static::GET || $this->method === 'delete') {
            return $this;
        }
        // Reset the json object from previous requests
        $this->options['json'] = [];
        if ($this->data) {
            $this->options['json'] = $this->data;
        }
        // Remove the json option if there is nothing there
        if (empty($this->options['json'])) {
            unset($this->options['json']);
        }
        return $this;
    }

    /**
     * Builds the query string for the request
     */
    public function buildRequestQuery(): static
    {
        if ($this->method !== static::GET && $this->method !== static::POST) {
            return $this;
        }

        $this->options['query'] = '';$this->options['query'] = '';

        $qs = [];
        foreach ($this->queryString as $var => $val) {
            $qs[] = $var . '=' . $val;
        }
        if (!empty($qs)) {
            $this->options['query'] = implode('&', $qs);
        }
        return $this;
    }

    /**
     * Sets the request method
     */
    public function setMethod(string $method): static
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Alias for setMethod()
     */
    public function method(string $method): static
    {
        return $this->setMethod($method);
    }

    /**
     * Sets method to get, sugar around setMethod(), then sends the request
     */
    public function get(string $endpoint = null): Response
    {
        if ($endpoint) $this->setEndpoint($endpoint);
        return $this->setMethod(static::GET)->send();
    }

    /**
     * Sets method to post, sugar around setMethod(), then sends the request
     */
    public function post(): Response
    {
        return $this->setMethod(static::POST)->send();
    }

    /**
     * Sets method to put, sugar around setMethod(), then sends the request
     */
    public function put(): Response
    {
        return $this->setMethod(static::PUT)->send();
    }

    /**
     * Sets method to patch, sugar around setMethod(), then sends the request
     */
    public function patch(): Response
    {
        return $this->setMethod(static::PATCH)->send();
    }

    /**
     * Sets method to delete, sugar around setMethod(), then sends the request
     */
    public function delete(): Response
    {
        return $this->setMethod(static::DELETE)->send();
    }

    /**
     * Sends the request to Canvas with auto-refresh capability
     */
    public function send(bool $reset = true): Response
    {
        $this->buildRequestJson()->buildRequestQuery();
        try {
            $responseData = $this->getRequest()->makeRequest($this->method, $this->endpoint, $this->options);
            $response = new Response($responseData, '', $this);
            if ($reset) $this->reset();
            return $response;
        } catch (\Exception $e) {
            // Check if it's a 401 error and auto-refresh is enabled
            if ($e->getCode() === 401 && $this->autoRefresh && $this->tokenManager) {
                try {
                    $this->refreshTokenAndRequest();
                    $responseData = $this->getRequest()->makeRequest($this->method, $this->endpoint, $this->options);
                    $response = new Response($responseData, '', $this);
                    if ($reset) $this->reset();
                    return $response;
                } catch (TokenRefreshException $refreshException) {
                    // If refresh fails, throw the original exception with additional context
                    throw new \Exception(
                        'Canvas API Error: Token expired and refresh failed - ' . $refreshException->getMessage(),
                        $e->getCode(),
                        $e
                    );
                }
            }
            throw $e;
        }
    }

    /**
     * This will return a chunk of data from Canvas
     */
    public function paginate(int $pageSize = 50): ?Response
    {
        if (!isset($this->paginator)) {
            $this->paginator = new Paginator($this, $pageSize);
        }
        $results = $this->paginator->page();
        if ($results === null) {
            $this->reset();
        }
        return $results;
    }

    /**
     * Get a Paginator instance for iterating through pages
     */
    public function getPaginator(int $pageSize = 10): Paginator
    {
        return new Paginator($this, $pageSize);
    }

    /**
     * Check if auto-refresh is enabled
     */
    public function hasAutoRefresh(): bool
    {
        return $this->autoRefresh;
    }

    /**
     * Get the current token
     */
    public function getCurrentToken(): string
    {
        return $this->currentToken;
    }

    /**
     * Get the token manager instance
     */
    public function getTokenManager(): ?TokenManager
    {
        return $this->tokenManager;
    }

    /**
     * Get the token expiration time in Y-m-d H:i:s format
     */
    public function getTokenExpirationTime(): ?string
    {
        if (!$this->tokenManager) {
            return null;
        }
        $expiresAt = $this->tokenManager->getExpiresAt();

        if (!$expiresAt) {
            return null;
        }
        return date('Y-m-d H:i:s', $expiresAt);
    }

    /**
     * Make a request to a Canvas pagination URL (from Link headers)
     */
    public function requestPaginationUrl(string $url): Response
    {
        // Parse the URL to extract the endpoint and query parameters
        $parsedUrl = parse_url($url);
        $endpoint = $parsedUrl['path'] ?? '';

        // Store original query parameters that should be preserved
        $originalQueryString = $this->queryString;
        
        // Parse new query parameters from pagination URL
        $newQueryString = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $newQueryString);
        }
        
        // Merge original parameters with new ones, giving priority to new ones
        // but preserving important original parameters like per_page if not present in new URL
        $this->queryString = array_merge($originalQueryString, $newQueryString);
        
        // Ensure per_page is preserved if it was originally set but not in pagination URL
        if (isset($originalQueryString['per_page']) && !isset($newQueryString['per_page'])) {
            $this->queryString['per_page'] = $originalQueryString['per_page'];
        }

        return $this->setEndpoint($endpoint)->setMethod(static::GET)->send();
    }
}
