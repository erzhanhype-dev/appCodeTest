<?php

namespace App\Resources;

use Car;
use Phalcon\Di\Di;
use Phalcon\Di\Injectable;

class CarRowResource extends Injectable
{
    public static function collection(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::fromRow($row);
        }
        return $out;
    }

    public static function collectionObject(iterable $rows)
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = (object)self::fromRow($row);
        }
        return $out;
    }

    public static function fromRow(object|array $row): array
    {
        $di = Di::getDefault();
        $translator = $di->has('translator') ? $di->getShared('translator') : null;

        $car = Car::findFirstById($row->id);
        $volume = $car->volume;

        if ((defined('TRUCK') && (int)$car->vehicle_type === TRUCK) || ($car->vehicle_type === 'CARGO')) {
            $volume = "{$volume} кг";
        } elseif (((int)$car->vehicle_type > 0 && (int)$car->vehicle_type < 4) || ($car->vehicle_type === 'PASSENGER')) {
            $volume = "{$volume} см*3";
        } else {
            $volume = "{$volume} л.с.";
        }

        $label = $translator->_($car->ref_category->name);
        if (in_array($car->ref_category->name, ['cat-l6', 'cat-l7'], true)) {
            $vehicleTypeLabel = $label . ' (' . $translator->_(mb_strtolower($car->vehicle_type)) . ')';
        } else {
            $vehicleTypeLabel = $label;
        }

        $st_type =  $translator->_("ref-st-not");
        if ($car->ref_st_type == 1) {
            $st_type = $translator->_("ref-st-yes");
        } elseif ($car->ref_st_type == 2) {
            $st_type = $translator->_("ref-st-international-transport");
        }
        $country_import = $car->country_import ? $car->country_import->name : null;

        if($car->country_import->is_custom_union === 1){
            $country_import .= '(ЕАЭС)';
        }

        return [
            'id' => $car->id,
            'vin' => $car->vin,
            'volume' => $volume,
            'year' => $car->year,
            'cost' => self::fmtMoney((float)($car->cost ?? 0)),
            'date_import' => !empty($car->date_import) ? date('d.m.Y', $car->date_import) : null,
            'category' => $vehicleTypeLabel,
            'st_type' => $st_type,
            'calculate_method' => $car->calculate_method,
            'first_reg_date' => !empty($car->first_reg_date) ? date('d.m.Y', $car->first_reg_date) : null,
            'mask_id' => $car->mask_id,
            'country' => $car->country ? $car->country->name : null,
            'country_import' => $country_import,
            'epts_request_id' => $car->epts_request_id,
            'kap_request_id' => $car->kap_request_id,
            'kap_log_id' => $car->kap_log_id,
            'status' => $car->status,
        ];
    }

    private static function fmtMoney(float $num): string
    {
        return number_format($num, 2, ',', "\u{00A0}");
    }
}