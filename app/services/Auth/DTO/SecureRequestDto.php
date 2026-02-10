<?php

namespace App\Services\Auth\DTO;

use App\Exceptions\AppException;

readonly class SecureRequestDto
{
    public function __construct(
        public string $pemCertificate,
        public string $authHash
    ) {
        $this->validate();
    }

    /**
     * @throws AppException
     */
    private function validate(): void
    {
        if (empty(trim($this->pemCertificate))) {
            throw new AppException("PEM сертификат не может быть пустым");
        }

        if (empty(trim($this->authHash))) {
            throw new AppException("Хеш авторизации не может быть пустым");
        }

        if (strlen(trim($this->pemCertificate)) < 10) {
            throw new AppException("PEM сертификат слишком короткий");
        }
    }
}