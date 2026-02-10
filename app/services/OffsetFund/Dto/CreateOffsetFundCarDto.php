<?php

namespace App\Services\OffsetFund\Dto;

use Phalcon\Http\RequestInterface;

class CreateOffsetFundCarDto
{
    public int $offset_fund_id;
    public ?int $id = null;
    public ?string $vin = null;
    public ?string $id_code = null;     // Для спецтехники
    public ?string $body_code = null;   // Для спецтехники
    public float $volume = 0.0;
    public int $ref_car_cat_id;
    public int $ref_country_id;
    public int $ref_st_type_id = 0;
    public int $is_electric = 0;
    public string $manufacture_year;
    public int $import_at;
    public string $vehicle_type;

    public static function fromRequest(RequestInterface $request, int $fundId, ?int $carId = null): self
    {
        $dto = new self();
        $dto->offset_fund_id = $fundId;
        $dto->id = $carId;

        $dto->vin = $request->getPost('vin', 'string');
        $dto->id_code = $request->getPost('id_code', 'string');
        $dto->body_code = $request->getPost('body_code', 'string');

        // Нормализация объема
        $rawVolume = $request->getPost('volume');
        $dto->volume = (float)str_replace(',', '.', (string)$rawVolume);

        $dto->ref_car_cat_id = (int)$request->getPost('ref_car_cat_id', 'int');

        // Логика дефолтной страны
        $countryId = (int)$request->getPost('ref_country_id', 'int');
        $dto->ref_country_id = ($countryId === 0) ? 201 : $countryId;

        $dto->ref_st_type_id = (int)$request->getPost('is_truck', 'int');
        $dto->is_electric = (int)$request->getPost('is_electric', 'int');
        $dto->manufacture_year = (string)$request->getPost('production_year');

        // Обработка даты
        $dateImport = $request->getPost('date_import');
        $dto->import_at = $dateImport ? strtotime($dateImport) : time();

        if ($dto->vin) {
            $dto->vin = $dto->vin;
        }

        $dto->vehicle_type = $request->getPost('vehicle_type');

        return $dto;
    }
}