<?php

namespace App\Services\Kap\Client;

use App\Exceptions\AppException;
use Phalcon\Di\Injectable;

class KapApiClient extends Injectable
{
    private string $baseUrl;
    private string $username;
    private string $password;

    public function onConstruct(): void
    {
        $this->baseUrl = $this->config->integration_service->base_url;
        $this->username = $this->config->integration_service->username;
        $this->password = $this->config->integration_service->password;
    }

    /**
     * @throws AppException
     */
    private function request(
        string $method,
        string $endpoint,
        ?array $payload = null,
        ?string $bearerToken = null
    ): string {
        $url = $this->baseUrl . $endpoint;

        $extraHeaders = [];
        $headers = array_merge(
            ['Accept: application/json'],
            $payload !== null ? ['Content-Type: application/json'] : [],
            $bearerToken ? ['Authorization: Bearer ' . $bearerToken] : [],
            $extraHeaders
        );

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 30,

            CURLOPT_SSL_VERIFYPEER => false, // В проде включить!
            CURLOPT_SSL_VERIFYHOST => 0,

            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_POSTFIELDS => $payload !== null ? json_encode($payload) : null,

            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new AppException('Ошибка cURL');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
             new AppException("HTTP {$httpCode} при обращении к {$url}" . $error);
        }

        return (string)$response;
    }

    /**
     * @throws AppException
     */
    public function getToken(): string
    {
        // Кеш токена в сессии (сохраняем, но изолируем доступ)
        if (!empty($_SESSION['INTEGRATION_SERVICE_TOKEN'])) {
            return $_SESSION['INTEGRATION_SERVICE_TOKEN'];
        }

        $postData = [
            'name' => $this->username,
            'password' => $this->password,
        ];

        $response = $this->request('POST', '/token', $postData);
        $data = json_decode($response, true);

        if (empty($data['token'])) {
            throw new AppException('Токен не получен в ответе сервера');
        }

        $_SESSION['INTEGRATION_SERVICE_TOKEN'] = $data['token'];

        return $data['token'];
    }

    /**
     * Выполняет запрос к KAP API.
     * @throws AppException
     */
    public function get(array $payload): array
    {
        $token = $this->getToken();
        $response = $this->request('POST', '/kap', $payload, $token);
        if (empty($response)) {
            return [];
        }

        $data = json_decode($response, true);

        return is_array($data) ? $data : [];
    }
}