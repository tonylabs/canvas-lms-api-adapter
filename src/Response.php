<?php

namespace TONYLABS\Canvas;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use ArrayAccess;
use IteratorAggregate;
use ArrayIterator;

class Response implements ArrayAccess, IteratorAggregate
{
    protected array $data = [];
    protected array $headers = [];
    protected array $paginationLinks = [];
    protected ?RequestBuilder $requestBuilder = null;

    public function __construct(array $responseData, string $tableName = '', ?RequestBuilder $requestBuilder = null)
    {
        // Handle new response format with data and headers
        if (isset($responseData['data']) && isset($responseData['headers'])) {
            $this->data = $responseData['data'];
            $this->headers = $responseData['headers'];
            $this->parsePaginationLinks();
        } else {
            // Backward compatibility for old format
            $this->data = $responseData;
        }

        $this->requestBuilder = $requestBuilder;
    }

    public function __serialize(): array
    {
        return [
            'data' => $this->data
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->data = $data['data'];
    }

    // Implement ArrayAccess methods
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    // Implement IteratorAggregate
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function toJson(): string
    {
        return json_encode($this->data);
    }

    public function collect(): Collection
    {
        return collect($this->data);
    }

    /**
     * Parse Canvas Link headers for pagination
     */
    protected function parsePaginationLinks(): void
    {
        $this->paginationLinks = [];
        if (!isset($this->headers['Link'])) {
            return;
        }
        $linkHeader = is_array($this->headers['Link']) ? $this->headers['Link'][0] : $this->headers['Link'];
        $links = explode(',', $linkHeader);
        foreach ($links as $link) {
            $link = trim($link);
            if (preg_match('/<([^>]+)>;\s*rel="([^"]+)"/', $link, $matches)) {
                $url = $matches[1];
                $rel = $matches[2];
                $this->paginationLinks[$rel] = $url;
            }
        }
    }

    /**
     * Get pagination links
     */
    public function getPaginationLinks(): array
    {
        return $this->paginationLinks;
    }

    /**
     * Get specific pagination link
     */
    public function getPaginationLink(string $rel): ?string
    {
        return $this->paginationLinks[$rel] ?? null;
    }

    /**
     * Check if there's a next page
     */
    public function hasNextPage(): bool
    {
        return isset($this->paginationLinks['next']);
    }

    /**
     * Check if there's a previous page
     */
    public function hasPreviousPage(): bool
    {
        return isset($this->paginationLinks['prev']);
    }

    /**
     * Get next page URL
     */
    public function getNextPageUrl(): ?string
    {
        $nextPage = $this->getPaginationLink('next');
        return $nextPage;
    }

    /**
     * Get previous page URL
     */
    public function getPreviousPageUrl(): ?string
    {
        return $this->getPaginationLink('prev');
    }

    /**
     * Get first page URL
     */
    public function getFirstPageUrl(): ?string
    {
        return $this->getPaginationLink('first');
    }

    /**
     * Get last page URL
     */
    public function getLastPageUrl(): ?string
    {
        return $this->getPaginationLink('last');
    }

    /**
     * Get current page URL
     */
    public function getCurrentPageUrl(): ?string
    {
        return $this->getPaginationLink('current');
    }

    /**
     * Get response headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the next page of results
     * This method provides direct pagination access from Response objects
     */
    public function page(): ?Response
    {
        if (!$this->requestBuilder) {
            throw new \Exception('RequestBuilder not available for pagination. Response was not created with pagination support.');
        }

        if (!$this->hasNextPage()) {
            return null;
        }

        $nextPageUrl = $this->getNextPageUrl();
        return $this->requestBuilder->requestPaginationUrl($nextPageUrl);
    }

    /**
     * Get the next page of results (alias for page())
     */
    public function next(): ?Response
    {
        return $this->page();
    }

    /**
     * Get the previous page of results
     */
    public function previous(): ?Response
    {
        if (!$this->requestBuilder) {
            throw new \Exception('RequestBuilder not available for pagination. Response was not created with pagination support.');
        }

        if (!$this->hasPreviousPage()) {
            return null;
        }

        $prevPageUrl = $this->getPreviousPageUrl();
        return $this->requestBuilder->requestPaginationUrl($prevPageUrl);
    }

    /**
     * Get the first page of results
     */
    public function first(): ?Response
    {
        if (!$this->requestBuilder) {
            throw new \Exception('RequestBuilder not available for pagination. Response was not created with pagination support.');
        }

        $firstPageUrl = $this->getFirstPageUrl();
        if (!$firstPageUrl) {
            return null;
        }

        return $this->requestBuilder->requestPaginationUrl($firstPageUrl);
    }

    /**
     * Get the last page of results
     */
    public function last(): ?Response
    {
        if (!$this->requestBuilder) {
            throw new \Exception('RequestBuilder not available for pagination. Response was not created with pagination support.');
        }

        $lastPageUrl = $this->getLastPageUrl();
        if (!$lastPageUrl) {
            return null;
        }

        return $this->requestBuilder->requestPaginationUrl($lastPageUrl);
    }

    /**
     * Set the RequestBuilder for pagination support
     */
    public function setRequestBuilder(RequestBuilder $requestBuilder): self
    {
        $this->requestBuilder = $requestBuilder;
        return $this;
    }
}
