<?php

namespace App\Services\Auth\Support;

use Phalcon\Http\RequestInterface;

/**
 * IP geolocation lookup (ipinfo.io) with file cache.
 * Responsibility: IP -> human-readable location string.
 */
final class IpInfoService
{
    private RequestInterface $request;
    private ?string $token;
    private string $cacheDir;
    private int $cacheTtlSec;

    public function __construct(
        RequestInterface $request,
        ?string $token = null,
        string $cacheDir = '/var/www/recycle/storage/ipinfo',
        int $cacheTtlSec = 86400
    ) {
        $this->request = $request;
        $this->token = $token;
        $this->cacheDir = $cacheDir;
        $this->cacheTtlSec = $cacheTtlSec;
    }

    public function resolveCurrentIpLocation(): string
    {
        $ip = (string)$this->request->getClientAddress(true);

        // Never send local/private IPs to third-party services.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'Local/Private IP';
        }

        $cacheFile = $this->cacheFilePath($ip);
        $cached = $this->readCache($cacheFile);
        if ($cached !== null) {
            return $cached;
        }

        $details = $this->fetchIpInfo($ip);
        if ($details === null) {
            return 'Геолокация недоступна';
        }

        $location = $this->formatIpInfo($details);
        $this->writeCache($cacheFile, json_encode($details, JSON_UNESCAPED_UNICODE));

        return $location;
    }

    private function cacheFilePath(string $ip): string
    {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }

        $safe = preg_replace('~[^0-9a-fA-F:\.]~', '_', $ip);
        return rtrim($this->cacheDir, '/') . '/' . $safe . '.json';
    }

    private function readCache(string $cacheFile): ?string
    {
        if (!is_file($cacheFile)) {
            return null;
        }

        if (filemtime($cacheFile) <= time() - $this->cacheTtlSec) {
            return null;
        }

        $json = @file_get_contents($cacheFile);
        $details = json_decode($json ?: '', true);
        if (!is_array($details) || empty($details)) {
            return null;
        }

        return $this->formatIpInfo($details);
    }

    private function writeCache(string $cacheFile, ?string $json): void
    {
        if (!$json) {
            return;
        }
        @file_put_contents($cacheFile, $json);
    }

    private function fetchIpInfo(string $ip): ?array
    {
        $url = "https://ipinfo.io/{$ip}/json";
        if ($this->token) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'token=' . rawurlencode($this->token);
        }

        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 2,
                'ignore_errors' => true,
                'header'        => "User-Agent: recycle-app\r\nAccept: application/json\r\n",
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $json = @file_get_contents($url, false, $context);
        if ($json === false) {
            return null;
        }

        $status = 0;
        if (isset($http_response_header[0]) && preg_match('~HTTP/\S+\s+(\d+)~', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }

        if ($status === 429) {
            return null;
        }

        if ($status >= 400) {
            return null;
        }

        $details = json_decode($json, true);
        if (!is_array($details) || empty($details)) {
            return null;
        }

        return $details;
    }

    private function formatIpInfo(array $details): string
    {
        $data = [];
        foreach (['city', 'region', 'country', 'org'] as $key) {
            if (!empty($details[$key]) && is_string($details[$key])) {
                $data[] = $details[$key];
            }
        }
        return $data ? implode(', ', $data) : 'Геолокация недоступна';
    }
}
