<?php

namespace TONYLABS\Canvas;

class Paginator
{
    protected RequestBuilder $builder;
    protected int $pageSize;
    protected ?Response $currentResponse = null;
    protected bool $hasMorePages = true;
    protected bool $isFirstRequest = true;
    protected int $currentPage = 0;

    public function __construct(RequestBuilder $builder, int $pageSize = 10)
    {
        $this->builder = $builder;
        $this->pageSize = $pageSize;
        $this->builder->pageSize($this->pageSize);
    }

    /**
     * Get the next page of results using Canvas Link headers
     */
    public function page(): ?Response
    {
        if (!$this->hasMorePages) {
            return null;
        }
        if ($this->isFirstRequest) {
            // Explicitly set page=1 for the first request to ensure proper pagination
            $this->builder->page(1);
            $this->currentResponse = $this->builder->setMethod('GET')->send(false);
            $this->isFirstRequest = false;
            $this->currentPage = 1;
        } else {
            if (!$this->currentResponse || !$this->currentResponse->hasNextPage()) {
                $this->hasMorePages = false;
                return null;
            }
            $nextPageUrl = $this->currentResponse->getNextPageUrl();
            $this->currentResponse = $this->builder->requestPaginationUrl($nextPageUrl);
            $this->currentPage++;
        }
        $this->hasMorePages = $this->currentResponse->hasNextPage();
        return $this->currentResponse;
    }

    /**
     * Get the next page of results
     */
    public function next(): ?Response
    {
        return $this->page();
    }

    /**
     * Get the previous page of results (if available)
     */
    public function previous(): ?Response
    {
        if (!$this->currentResponse || !$this->currentResponse->hasPreviousPage()) {
            return null;
        }
        $prevPageUrl = $this->currentResponse->getPreviousPageUrl();
        $this->currentResponse = $this->builder->requestPaginationUrl($prevPageUrl);
        if ($this->currentPage > 1) {
            $this->currentPage--;
        }
        return $this->currentResponse;
    }

    /**
     * Go to the first page
     */
    public function first(): ?Response
    {
        if (!$this->currentResponse || !$this->currentResponse->getFirstPageUrl()) {
            $this->reset();
            return $this->page();
        }
        $firstPageUrl = $this->currentResponse->getFirstPageUrl();
        $this->currentResponse = $this->builder->requestPaginationUrl($firstPageUrl);
        $this->hasMorePages = $this->currentResponse->hasNextPage();
        $this->currentPage = 1;
        return $this->currentResponse;
    }

    /**
     * Go to the last page
     */
    public function last(): ?Response
    {
        if (!$this->currentResponse || !$this->currentResponse->getLastPageUrl()) {
            return null;
        }
        $lastPageUrl = $this->currentResponse->getLastPageUrl();
        $this->currentResponse = $this->builder->requestPaginationUrl($lastPageUrl);
        $this->hasMorePages = false; // Last page has no next page
        // Note: We can't determine the exact last page number from Canvas API
        // The currentPage will remain at its previous value
        return $this->currentResponse;
    }

    /**
     * Check if there are more pages available
     */
    public function hasMorePages(): bool
    {
        return $this->hasMorePages;
    }

    /**
     * Get current response
     */
    public function getCurrentResponse(): ?Response
    {
        return $this->currentResponse;
    }

    /**
     * Get current page number
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get pagination links from current response
     */
    public function getPaginationLinks(): array
    {
        return $this->currentResponse ? $this->currentResponse->getPaginationLinks() : [];
    }

    /**
     * Reset paginator to initial state
     */
    public function reset(): void
    {
        $this->currentResponse = null;
        $this->hasMorePages = true;
        $this->isFirstRequest = true;
        $this->currentPage = 0;
    }

    /**
     * Iterate through all pages and collect all results
     */
    public function all(): array
    {
        $allResults = [];
        $this->reset();
        while ($response = $this->page()) {
            $allResults = array_merge($allResults, $response->toArray());
        }
        return $allResults;
    }

    /**
     * Get a specific page by URL
     */
    public function goToUrl(string $url): ?Response
    {
        $this->currentResponse = $this->builder->requestPaginationUrl($url);
        $this->hasMorePages = $this->currentResponse->hasNextPage();
        $this->isFirstRequest = false;
        // Note: We can't determine the page number from arbitrary URLs
        // Reset to unknown state
        $this->currentPage = 0;
        return $this->currentResponse;
    }
}
