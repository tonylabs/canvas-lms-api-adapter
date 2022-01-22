<?php

namespace TONYLABS\Canvas;

class Paginator
{
    protected int $page = 1;

    protected RequestBuilder $objRequestBuilder;

    public function __construct(RequestBuilder $builder, int $pageSize = 100)
    {
        $this->objRequestBuilder = $builder->pageSize($pageSize);
    }

    public function page(): ?Response
    {
        $arrayResults = $this->objRequestBuilder->page($this->page)->send(false);

        //@A single record wrapped in an array
        if (!$arrayResults->isEmpty() && !$arrayResults[0]) {
            $arrayResults->setData([$arrayResults->data]);
        }
        if ($arrayResults->isEmpty()) {
            $this->page = 1;
            return null;
        }
        $this->page += 1;
        return $arrayResults;
    }
}
