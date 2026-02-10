<?php

namespace App\Services\Epts;

use App\Services\Integration\IntegrationService;

class EptsService extends IntegrationService
{
    public function search(array $payload): array
    {
        return $this->post('/epts', $payload);
    }
}