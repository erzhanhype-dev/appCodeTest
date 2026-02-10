<?php

namespace App\Services\Auth;

use Phalcon\Di\Injectable;

/**
 * SRP: генерация и проверка одноразовых кодов (email verification).
 */
class VerificationService extends Injectable
{
    public const int DEFAULT_LENGTH = 6;

    public function generateCode(int $length = self::DEFAULT_LENGTH): string
    {
        $length = max(1, $length);

        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= (string)random_int(0, 9);
        }

        return $code;
    }

    public function validateCode(string $code, int $length = self::DEFAULT_LENGTH): bool
    {
        $code = trim($code);
        return strlen($code) === $length && ctype_digit($code);
    }

    /**
     * Сравнение кодов без утечки по времени.
     */
    public function verifyCode(string $inputCode, string $storedCode): bool
    {
        $inputCode = trim($inputCode);
        $storedCode = trim($storedCode);

        if ($inputCode === '' || $storedCode === '') {
            return false;
        }

        return hash_equals($storedCode, $inputCode);
    }
}
