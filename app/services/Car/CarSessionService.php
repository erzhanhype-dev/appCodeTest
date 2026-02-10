<?php

namespace App\Services\Car;

use Phalcon\Di\Injectable;

final class CarSessionService extends Injectable
{
    private const string SESSION_KEY_CAR_DATA = 'CAR_DATA';
    private const string SESSION_KEY_CAR_TYPE = 'CAR_TYPE';

    public function getCarData(): array
    {
        return $this->session->get(self::SESSION_KEY_CAR_DATA) ?: [];
    }

    public function setCarData(array $data): void
    {
        $this->session->set(self::SESSION_KEY_CAR_DATA, $data);
    }

    public function clearCarData(): void
    {
        $this->session->remove(self::SESSION_KEY_CAR_DATA);
    }

    public function getCarType(): string
    {
        return (string)$this->session->get(self::SESSION_KEY_CAR_TYPE, 'CAR');
    }

    public function setCarType(string $type): void
    {
        $this->session->set(self::SESSION_KEY_CAR_TYPE, $type);
    }

    public function hasCarData(): bool
    {
        return $this->session->has(self::SESSION_KEY_CAR_DATA);
    }
}
