<?php

namespace TONYLABS\Canvas;

use Illuminate\Support\Str;
use stdClass;

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

    public function __construct(string $domain, string $token)
    {
        $this->request = new Request($domain, $token);
    }

    public function getRequest(): Request
    {
        return $this->request;
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
     * Sends the request to Canvas
     */
    public function send(bool $reset = true): Response
    {
        $this->buildRequestJson()->buildRequestQuery();
        $responseData = $this->getRequest()->makeRequest($this->method, $this->endpoint, $this->options);
        $response = new Response($responseData, $this->pageKey);
        if ($reset) $this->reset();
        return $response;
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
}
