<?php

namespace App\Services\Car\Dto;

use Phalcon\Http\Request;

class CarCreateDto
{
    public string $vin;
    public ?string $id_code = null;
    public ?string $body_code = null;
    public ?float $volume = null;
    public ?int $calculate_method = null;
    public ?string $vehicle_type = null;
    public ?int $ref_country_id = null;
    public ?int $ref_country_import = null; // <-- добавили
    public ?int $ref_car_cat_id = null;
    public ?int $semi_truck = null;
    public ?int $is_electric = null;
    public ?string $date_import = null;
    public ?string $year = null;

    public static function fromRequest(Request $request): self
    {
        $d = new self();
        $post = (array)$request->getPost();

        $d->vin          = mb_strtoupper((string)($post['vin'] ?? ''));
        $d->id_code      = isset($post['id_code']) ? mb_strtoupper((string)$post['id_code']) : null;
        $d->body_code    = isset($post['body_code']) ? mb_strtoupper((string)$post['body_code']) : null;

        $volumeRaw = $post['volume'] ?? null;
        if ($volumeRaw === null || $volumeRaw === '') {
            $d->volume = null;
        } else {
            $volumeStr = str_replace(',', '.', (string)$volumeRaw);
            $d->volume = is_numeric($volumeStr) ? (float)$volumeStr : null;
        }

        $d->calculate_method   = isset($post['calculate_method']) ? (int)$post['calculate_method'] : null;
        $d->vehicle_type       = isset($post['vehicle_type']) ? (string)$post['vehicle_type'] : null;
        $d->ref_country_id     = isset($post['ref_country_id']) ? (int)$post['ref_country_id'] : null;
        $d->ref_country_import = isset($post['ref_country_import_id']) ? (int)$post['ref_country_import_id'] : null;
        $d->ref_car_cat_id     = isset($post['ref_car_cat_id']) ? (int)$post['ref_car_cat_id'] : null;
        $d->semi_truck         = isset($post['semi_truck']) ? (int)$post['semi_truck'] : null;
        $d->is_electric        = isset($post['is_electric']) ? (int)$post['is_electric'] : null;
        $d->date_import        = isset($post['import_date']) ? (string)$post['import_date'] : null;
        $d->year               = isset($post['year']) ? (string)$post['year'] : null;

        return $d;
    }
}
