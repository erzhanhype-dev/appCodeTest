<?php

namespace App\Services\Auth\DTO;

readonly class AuthResponseDTO
{
    public function __construct(
        public bool $success,
        public string $message = '',
        public mixed $data = null,
        public ?string $redirectUrl = null
    ) {}

    public static function success(string $message = '', mixed $data = null, ?string $redirectUrl = null): self
    {
        return new self(true, $message, $data, $redirectUrl);
    }

    public static function error(string $message, ?string $redirectUrl = null): self
    {
        return new self(false, $message, null, $redirectUrl);
    }
}