<?php

namespace TONYLABS\Canvas\Exception;

class CanvasApiException extends \Exception
{
    protected array $responseData;

    public function __construct(string $message, int $code, \Throwable $previous = null, array $responseData = [])
    {
        parent::__construct($message, $code, $previous);
        $this->responseData = $responseData;
    }

    public function getResponseData(): array
    {
        return $this->responseData;
    }
}