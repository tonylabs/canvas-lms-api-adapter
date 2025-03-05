<?php

namespace TONYLABS\Canvas;

class Paginator
{
    protected RequestBuilder $builder;
    protected int $pageSize;
    protected int $currentPage = 1;
    protected bool $hasMorePages = true;

    public function __construct(RequestBuilder $builder, int $pageSize = 50)
    {
        $this->builder = $builder;
        $this->pageSize = $pageSize;
    }

    public function page(): ?Response
    {
        if (!$this->hasMorePages) {
            return null;
        }

        $this->builder->pageSize($this->pageSize);
        $this->builder->page($this->currentPage);
        
        $response = $this->builder->send(false);
        
        // If we got fewer results than the page size, we're done
        if (count($response) < $this->pageSize) {
            $this->hasMorePages = false;
        }
        
        $this->currentPage++;
        
        return $response;
    }

    public function reset(): void
    {
        $this->currentPage = 1;
        $this->hasMorePages = true;
    }
}
