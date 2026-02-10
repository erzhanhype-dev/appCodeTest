<?php

namespace App\Services\Integration;

use App\Exceptions\AppException;
use Phalcon\Di\Injectable;

abstract class IntegrationService extends Injectable
{
    protected string $baseUrl;
    protected string $tokenUrl;
    protected string $username;
    protected string $password;

    public function __construct()
    {
        $this->baseUrl  = rtrim($this->config->integration_service->base_url, '/');
        $this->tokenUrl = $this->baseUrl . '/token';

        $this->username = $this->config->integration_service->username;
        $this->password = $this->config->integration_service->password;
    }

    /**
     * Универсальный POST-запрос
     */
    protected function post(string $endpoint, array $payload): array
    {
        $token = $this->getToken();
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            throw new AppException('Ошибка cURL');
        }

        curl_close($ch);

        $data = json_decode($response, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Получение токена (с кэшированием в сессии)
     */
    protected function getToken(): string
    {
        if (!empty($_SESSION['INTEGRATION_SERVICE_TOKEN'])) {
            return $_SESSION['INTEGRATION_SERVICE_TOKEN'];
        }

        $postData = [
            'name'     => $this->username,
            'password' => $this->password,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->tokenUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($postData),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new AppException('Ошибка cURL');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new AppException("Ошибка получения токена. Код ответа: $httpCode");
        }

        $data = json_decode($response, true);

        if (empty($data['token'])) {
            throw new AppException("Токен не получен в ответе сервера");
        }

        $_SESSION['INTEGRATION_SERVICE_TOKEN'] = $data['token'];
        return $data['token'];
    }
}
