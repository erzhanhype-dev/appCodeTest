<?php
namespace App\Helpers;

class UrlHelper
{
    /**
     * Build URL for pagination with current query parameters
     */
    public static function buildPaginatedUrl(string $baseUrl, array $currentParams, int $page): string
    {
        $params = $currentParams;
        unset($params['page']);

        $queryString = http_build_query($params);
        $separator = empty($queryString) ? '' : '&';

        return $baseUrl . '?' . $queryString . $separator . 'page=' . $page;
    }

    /**
     * Remove specific parameters from query string
     */
    public static function removeParams(array $params, array $keysToRemove): array
    {
        return array_diff_key($params, array_flip($keysToRemove));
    }

    /**
     * Build URL with query parameters
     */
    public static function buildUrl(string $baseUrl, array $params = []): string
    {
        if (empty($params)) {
            return $baseUrl;
        }

        return $baseUrl . '?' . http_build_query($params);
    }

    /**
     * Add parameter to existing URL
     */
    public static function addParam(string $url, string $key, $value): string
    {
        $urlParts = parse_url($url);
        $queryParams = [];

        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
        }

        $queryParams[$key] = $value;

        $newQuery = http_build_query($queryParams);
        $urlParts['query'] = $newQuery;

        return self::buildUrlFromParts($urlParts);
    }

    /**
     * Remove parameter from URL
     */
    public static function removeParam(string $url, string $key): string
    {
        $urlParts = parse_url($url);
        $queryParams = [];

        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
        }

        unset($queryParams[$key]);

        $urlParts['query'] = !empty($queryParams) ? http_build_query($queryParams) : null;

        return self::buildUrlFromParts($urlParts);
    }

    /**
     * Rebuild URL from parts
     */
    private static function buildUrlFromParts(array $urlParts): string
    {
        $url = '';

        if (isset($urlParts['scheme'])) {
            $url .= $urlParts['scheme'] . '://';
        }

        if (isset($urlParts['host'])) {
            $url .= $urlParts['host'];
        }

        if (isset($urlParts['port'])) {
            $url .= ':' . $urlParts['port'];
        }

        if (isset($urlParts['path'])) {
            $url .= $urlParts['path'];
        }

        if (isset($urlParts['query'])) {
            $url .= '?' . $urlParts['query'];
        }

        if (isset($urlParts['fragment'])) {
            $url .= '#' . $urlParts['fragment'];
        }

        return $url;
    }

    /**
     * Get current URL with modified parameters
     */
    public static function getCurrentUrlWithParams(array $newParams = [], array $removeParams = []): string
    {
        $currentParams = $_GET;

        // Remove specified parameters
        foreach ($removeParams as $param) {
            unset($currentParams[$param]);
        }

        // Merge with new parameters
        $params = array_merge($currentParams, $newParams);

        return self::buildUrl('', $params);
    }
}