<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class RateLimitedApiClient
{
    /**
     * @var string Base URL for the API
     */
    protected string $baseUrl;

    /**
     * @var int Maximum number of requests allowed per time window
     */
    protected int $rateLimit;

    /**
     * @var int Time window in seconds for rate limiting
     */
    protected int $timeWindow;

    /**
     * @var array Queue of timestamps when requests were made
     */
    protected array $requestTimestamps = [];

    /**
     * @var array Default headers to include with every request
     */
    protected array $defaultHeaders = [];

    /**
     * @var string The header to check for server-side rate limit information
     */
    protected string $retryAfterHeader = 'Retry-After';

    /**
     * Create a new API client instance.
     *
     * @param string $baseUrl Base URL for the API
     * @param int $rateLimit Maximum number of requests per time window (default: 3)
     * @param int $timeWindow Time window in seconds (default: 60)
     * @param array $headers Default headers to include with every request
     * @param string|null $retryAfterHeader Custom header for rate limit information
     */
    public function __construct(
        string  $baseUrl,
        int     $rateLimit = 3,
        int     $timeWindow = 60,
        array   $headers = [],
        ?string $retryAfterHeader = null
    )
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->rateLimit = $rateLimit;
        $this->timeWindow = $timeWindow;
        $this->defaultHeaders = $headers;

        if ($retryAfterHeader) {
            $this->retryAfterHeader = $retryAfterHeader;
        }
    }

    /**
     * Make a rate-limited GET request to the API.
     *
     * @param string $endpoint The API endpoint
     * @param array $queryParams Query parameters
     * @param array $headers Additional headers
     * @return array|null Response data or null on failure
     * @throws Exception If the request fails
     */
    public function get(string $endpoint, array $queryParams = [], array $headers = []): ?array
    {
        return $this->request('GET', $endpoint, [], $queryParams, $headers);
    }

    /**
     * Make a rate-limited POST request to the API.
     *
     * @param string $endpoint The API endpoint
     * @param array $data The request payload
     * @param array $headers Additional headers
     * @return array|null Response data or null on failure
     * @throws Exception If the request fails
     */
    public function post(string $endpoint, array $data = [], array $headers = []): ?array
    {
        return $this->request('POST', $endpoint, $data, [], $headers);
    }

    /**
     * Make a rate-limited PUT request to the API.
     *
     * @param string $endpoint The API endpoint
     * @param array $data The request payload
     * @param array $headers Additional headers
     * @return array|null Response data or null on failure
     * @throws Exception If the request fails
     */
    public function put(string $endpoint, array $data = [], array $headers = []): ?array
    {
        return $this->request('PUT', $endpoint, $data, [], $headers);
    }

    /**
     * Make a rate-limited DELETE request to the API.
     *
     * @param string $endpoint The API endpoint
     * @param array $headers Additional headers
     * @return array|null Response data or null on failure
     * @throws Exception If the request fails
     */
    public function delete(string $endpoint, array $headers = []): ?array
    {
        return $this->request('DELETE', $endpoint, [], [], $headers);
    }

    /**
     * Make a rate-limited request to the API.
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request payload
     * @param array $queryParams Query parameters
     * @param array $headers Additional headers
     * @return array|null Response data or null on failure
     * @throws Exception If the request fails
     */
    protected function request(
        string $method,
        string $endpoint,
        array  $data = [],
        array  $queryParams = [],
        array  $headers = []
    ): ?array
    {
        $this->waitForRateLimit();

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $allHeaders = array_merge($this->defaultHeaders, $headers);

        try {
            $response = Http::withHeaders($allHeaders);

            if (!empty($queryParams)) {
                $response = $response->withQueryParameters($queryParams);
            }

            Log::debug('Initiating client request', [
                'method' => $method,
                'url' => $url,
                'data' => $data,
                'queryParams' => $queryParams
            ]);

            $response = match ($method) {
                'GET' => $response->get($url),
                'POST' => $response->post($url, $data),
                'PUT' => $response->put($url, $data),
                'DELETE' => $response->delete($url),
                default => throw new Exception("Unsupported HTTP method: {$method}")
            };

            $this->recordRequest();

            if ($response->successful()) {
                return $response->json();
            }

            $statusCode = $response->status();
            $errorMessage = $response->body();

            Log::error("API request failed: {$statusCode} - {$errorMessage}", [
                'url' => $url,
                'method' => $method,
                'data' => $data,
            ]);

            if ($statusCode === 429) {
                // Handle rate limiting from the API side
                $retryAfter = (int)$response->header($this->retryAfterHeader) ?? $this->timeWindow;

                if ($retryAfter > 0) {
                    Log::warning("Rate limited by API, waiting {$retryAfter} seconds");

                    sleep($retryAfter);
                }

                return $this->request($method, $endpoint, $data, $queryParams, $headers);
            }

            throw new Exception("API request failed: {$statusCode} - {$errorMessage}");
        } catch (Exception $e) {
            Log::error("Exception during API request: " . $e->getMessage(), [
                'url' => $url,
                'method' => $method,
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * Record the current request timestamp.
     */
    protected function recordRequest(): void
    {
        $now = microtime(true);
        $this->requestTimestamps[] = $now;

        // Remove timestamps older than 60 seconds
        $this->requestTimestamps = array_values(
            array_filter(
                $this->requestTimestamps,
                fn($timestamp) => $timestamp > ($now - 60)
            )
        );
    }

    /**
     * Wait if necessary to comply with the rate limit.
     */
    protected function waitForRateLimit(): void
    {
        $now = microtime(true);

        // Clean up old timestamps
        $this->requestTimestamps = array_values(
            array_filter(
                $this->requestTimestamps,
                fn($timestamp) => $timestamp > ($now - 60)
            )
        );

        // If we haven't reached the rate limit, continue immediately
        if (count($this->requestTimestamps) < $this->rateLimit) {
            return;
        }

        // Calculate how long to wait
        $oldestAllowedTimestamp = $this->requestTimestamps[0] ?? 0;
        $timeToWait = 60 - ($now - $oldestAllowedTimestamp);

        if ($timeToWait > 0) {
            Log::info("Rate limit reached, waiting {$timeToWait} seconds");
            usleep((int)($timeToWait * 1000000));
        }
    }
}
