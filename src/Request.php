<?php

namespace TONYLABS\Canvas;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response as LaravelResponse;
use TONYLABS\Canvas\Exception\MissingTokenException;

class Request
{
    protected Client $client;
    protected string $token;
    protected string $token_type;
    protected string $expires;
    protected int $attempts = 0;

    /**
     * Creates a new Request object to interact with Canvas LMS API
     *
     * @param string $domain The url of the Canvas LMS
     * @param string $token The token generated by user from user settings, it can be authorized without approval
     */
    public function __construct(string $domain, string $token)
    {
        $this->client = new Client(['base_uri' => $domain]);
        $this->token = $token;
    }

    /**
     * Makes an api call to Canvas API
     */
    public function makeRequest(string $method, string $endpoint, array $options, bool $returnResponse = false): JsonResponse|array
    {
        $this->attempts++;

        if (!$this->token) {
            throw new MissingTokenException('Canvas token is missing.');
        }

        if (!isset($options['headers'])) $options['headers'] = [];
        $options['headers']['Accept'] = 'application/json';
        $options['headers']['Content-Type'] = 'application/json';
        $options['headers']['Authorization'] = 'Bearer ' . $this->token;
        $options['http_errors'] = true;

        try {
            $response = $this->getClient()->request($method, $endpoint, $options);
        } catch (ClientException $exception) {
            $response = $exception->getResponse();
            if ($response->getStatusCode() === 401 && $this->attempts < 3) {
                return $this->authenticate(true)->makeRequest($method, $endpoint, $options);
            }
            Debug::log(fn () => ray()->json($response->getBody()->getContents())->red()->label($response->getStatusCode()));
            throw $exception;
        }
        $this->attempts = 0;

        $jsonContent = $response->getBody()->getContents();
        Debug::log($jsonContent);
        $objBody = json_decode($jsonContent, true);

        if ($returnResponse) {
            return LaravelResponse::json($objBody, $response->getStatusCode());
        }
        return $objBody ?? [];
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getTokenType()
    {
        return $this->token_type;
    }

    public function getExpires()
    {
        return $this->expires;
    }
}