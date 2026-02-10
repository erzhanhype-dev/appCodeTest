<?php

namespace App\Services\Kap;

use App\Exceptions\AppException;
use App\Services\Integration\IntegrationService;

class KapOldService extends IntegrationService
{
    public function getFromVIN($vin): array
    {
        return $this->search([
            "type" => "VIN",
            "value" => $vin,
        ]);
    }

    public function getFromGRNZ($grnz): array
    {
        return $this->search([
            "type" => "GRNZ",
            "value" => $grnz,
        ]);
    }

    public function getFromIINBIN($iinbin): array
    {
        return $this->search([
            "type" => "IINBIN",
            "value" => $iinbin,
        ]);
    }

    /**
     * @throws AppException
     */
    public function search(array $payload): array
    {
        return $this->post('/kap-old', $payload);
    }
}
