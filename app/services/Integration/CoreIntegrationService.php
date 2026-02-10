<?php

namespace App\Services\Integration;

use App\Exceptions\AppException;
use Phalcon\Di\Injectable;

/**
 * Сервис для взаимодействия с внешним Core API
 */
class CoreIntegrationService extends Injectable
{
    /**
     * Выполняет вход во внешний сервис и возвращает токен
     * * @param string $login
     * @param string|null $email
     * @param int $roleId
     * @return string Токен авторизации (Sanctum)
     * @throws AppException При ошибках сети или API
     */
    public function loginToCore(string $login, ?string $email, int $roleId): string
    {
        // Получаем конфиг (предполагаем, что он доступен через DI)
        $config = $this->config->core_service;

        $data = [
            'name' => $login,
            'password' => $config->password,
        ];

        if ($email !== null) {
            $data['email'] = $email;
            $data['role_id'] = $roleId;
        }

        $url = rtrim($config->base_url, '/') . '/login';

        try {
            $response = $this->makeRequest($url, $data);
            return $this->extractToken($response);
        } catch (\RuntimeException $e) {
            // Логируем оригинальную ошибку, выбрасываем безопасную для UI
            error_log("CoreIntegrationService Error: " . $e->getMessage());
            // В зависимости от требований, можно не прерывать вход, если этот сервис не критичен,
            // но пока оставим поведение "ошибка = исключение"
            return '';
        }
    }

    /**
     * @throws \RuntimeException
     */
    private function makeRequest(string $url, array $data): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Ошибка cURL: ' . $error);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("Ошибка HTTP {$httpCode}: {$response}");
        }

        return $response;
    }

    /**
     * @throws AppException
     */
    private function extractToken(string $jsonResponse): string
    {
        $data = json_decode($jsonResponse, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AppException('Ошибка декодирования JSON от Core сервиса');
        }

        if (empty($data['token']) || !is_string($data['token'])) {
            throw new AppException('В ответе Core сервиса отсутствует токен');
        }

        return $data['token'];
    }
}