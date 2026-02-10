<?php

namespace App\Services\Auth\Support;

use App\Exceptions\AppException;
use Phalcon\Session\ManagerInterface;

/**
 * HTTP client for core_service (/login).
 * Responsibility: request token and store it in session.
 */
final class CoreServiceClient
{
    private object $config;
    private ManagerInterface $session;

    public function __construct(object $config, ManagerInterface $session)
    {
        $this->config = $config;
        $this->session = $session;
    }

    /**
     * Returns token or null; does not break primary auth flow.
     */
    public function tryLogin(string $login, ?string $email = null, ?int $roleId = null): ?string
    {
        try {
            return $this->login($login, $email, $roleId);
        } catch (\Throwable $e) {
            error_log('[CoreServiceClient] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @throws AppException
     */
    private function login(string $login, ?string $email, ?int $roleId): string
    {
        if (!isset($this->config->core_service->base_url, $this->config->core_service->password)) {
            throw new AppException('core_service config is missing');
        }

        $data = [
            'name' => $login,
            'password' => $this->config->core_service->password,
        ];

        if ($email !== null) {
            $data['email'] = $email;
            if ($roleId !== null) {
                $data['role_id'] = $roleId;
            }
        }

        $ch = curl_init(rtrim((string)$this->config->core_service->base_url, '/') . '/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new AppException('Ошибка cURL: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new AppException('Ошибка HTTP: ' . $httpCode . ' Ответ: ' . $response);
        }

        $json = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AppException('Ошибка декодирования JSON: ' . json_last_error_msg());
        }

        $token = $json['token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new AppException('В ответе нет корректного поля token. Ответ: ' . $response);
        }

        $this->session->set('sanctum_token', $token);

        return $token;
    }
}
