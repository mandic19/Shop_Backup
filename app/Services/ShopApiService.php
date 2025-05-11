<?php

namespace App\Services;

use Exception;

class ShopApiService
{
    /**
     * @var RateLimitedApiClient
     */
    protected RateLimitedApiClient $apiClient;

    /**
     * Create a new shop API service instance.
     *
     * @param array $headers Default headers for API requests
     */
    public function __construct(array $headers = [])
    {
        $baseUrl = config('services.shop.api_url');
        $rateLimit = config('services.shop.rate_limit', 3);
        $timeWindow = config('services.shop.time_window', 60);
        $retryAfterHeader = config('services.shop.retry_after_header', 'Retry-After');

        if (empty($headers)) {
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];
        }

        $this->apiClient = new RateLimitedApiClient($baseUrl, $rateLimit, $timeWindow, $headers, $retryAfterHeader);
    }

    /**
     * Get products from the shop API.
     *
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Products data
     * @throws Exception If the API request fails
     */
    public function getProducts(int $page = 1, int $limit = 100): array
    {
        return $this->apiClient->get('products', [
            'page' => $page,
            'per_page' => $limit,
            'with' => ['image']
        ]);
    }

    /**
     * Get variants from the shop API.
     *
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Variants data
     * @throws Exception If the API request fails
     */
    public function getVariants(int $page = 1, int $limit = 100): array
    {
        return $this->apiClient->get("variants", [
            'page' => $page,
            'per_page' => $limit,
        ]);
    }

    /**
     * Get images from the shop API.
     *
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Variant images data
     * @throws Exception If the API request fails
     */
    public function getVariantImages(int $page = 1, int $limit = 100): array
    {
        return $this->apiClient->get("variant-images", [
            'page' => $page,
            'per_page' => $limit,
        ]);
    }

    /**
     * Get access to the underlying API client for custom requests.
     *
     * @return RateLimitedApiClient
     */
    public function getApiClient(): RateLimitedApiClient
    {
        return $this->apiClient;
    }
}
