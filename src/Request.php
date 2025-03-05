<?php

namespace TONYLABS\Canvas;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response as LaravelResponse;
use TONYLABS\Canvas\Exception\MissingTokenException;
use TONYLABS\Canvas\Exception\MissingDomainException;

class Request
{
    protected Client $client;
    protected string $domain;
    protected string $token;
    protected array $headers = [];

    public function __construct(string $domain = null, string $token = null)
    {
        if (empty($domain)) {
            throw new MissingDomainException('Canvas domain is required');
        }

        if (empty($token)) {
            throw new MissingTokenException('Canvas API token is required');
        }

        $this->domain = $domain;
        $this->token = $token;
        $this->headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];

        $this->client = new Client([
            'base_uri' => $this->domain,
            'headers' => $this->headers,
        ]);
    }

    public function makeRequest(string $method, string $endpoint, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();
            return json_decode($body, true) ?? [];
        } catch (ClientException $e) {
            return [];
        }
    }
}
