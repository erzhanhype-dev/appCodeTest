<?php

namespace App\Services\Car;

use Car;
use KapLogs;
use Phalcon\Di\Injectable;
use RefCarValue;
use User;

class CarCalculationService extends Injectable
{
    public function calculationPaySum(
        $calculateMethod,
        $dateImport,
        $volume,
        $value,
        $refSt,
        $isElectric,
        $isTemporaryImportation,
        $kapLogId,
        $ref_country_import,
        $profile_created_at,
        $approve,
        ?User $auth = null
    ): array {
        if ($auth === null) {
            $auth = User::getUserBySession();
        }

        $is_new_profile = true;
        if($approve !== 'GLOBAL' && $profile_created_at < EAEU_NEW_COEFFICIENT_2026){
            $is_new_profile = false;
        }

        if ($auth && $auth->isSuperModerator()) {
            if ($calculateMethod == 1) {
                $sum = __calculateCar($volume, json_encode($value), $refSt, $isElectric, $ref_country_import, $is_new_profile);
            } else {
                $sum = __calculateCarByDate($dateImport, $volume, json_encode($value), $refSt, $isElectric, $ref_country_import, $is_new_profile);
            }
        } else {
            $kapLog = KapLogs::findFirstById($kapLogId);
            $isKapAvailable = $kapLog && $kapLog->payload_status == 200;
            $isAfterThreshold = $dateImport && strtotime($dateImport) > strtotime(STARTROP);

            if ($isKapAvailable && $isAfterThreshold && $refSt != 2 && !$isTemporaryImportation) {
                $calculateMethod = 2; // расчет по КАП
                $sum = __calculateCarByDate($dateImport, $volume, json_encode($value), $refSt, $isElectric, $ref_country_import, $is_new_profile);
            } else {
                $calculateMethod = 1;
                $sum = __calculateCar((float)$volume, json_encode($value), $refSt, $isElectric, $ref_country_import, $is_new_profile);
            }
        }

        return [
            'sum' => $sum,
            'calculate_method' => $calculateMethod,
        ];
    }

    public function getCarVolume(
        $category,
        $categoryCode,
        $engineCapacity,
        $permissibleMaxWeight,
        $maxPowerMeasure,
        $vehicleType = null
    ): float {
        $volume = 0;

        if (!$category && !$categoryCode) {
            return 0.0;
        }

        $techCategory = $category ? $category->tech_category : $categoryCode;

        if (in_array($techCategory, CAR_CATEGORY_M) || in_array($techCategory, CAR_CATEGORY_CODE_M)) {
            $volume = $engineCapacity;
        } elseif (in_array($techCategory, CAR_CATEGORY_N) || in_array($techCategory, CAR_CATEGORY_CODE_N)) {
            $volume = $permissibleMaxWeight;
        } elseif (in_array($techCategory, ['L6', 'L7'])) {
            $volume = ($vehicleType === Car::VEHICLE_TYPE_CARGO) ? $permissibleMaxWeight : $engineCapacity;
        } elseif (in_array($techCategory, CAR_CATEGORY_AGRO)) {
            $volume = $maxPowerMeasure;
        }

        return (float)($volume ?: 0);
    }

    public function getCarPriceValue($volume, $vehicleType, $refCarCat): ?array
    {
        if (!$refCarCat) {
            return null;
        }

        $rangeType = ($vehicleType === Car::VEHICLE_TYPE_CARGO) ? 'WEIGHT' : 'VOLUME';

        $conditions = "car_type = :car_type_id: AND volume_end >= :volume_end: AND volume_start <= :volume_start:";
        $bind = [
            "car_type_id" => $refCarCat->car_type,
            "volume_end" => (int)$volume,
            "volume_start" => (int)$volume,
        ];

        if (in_array($refCarCat->tech_category, ['L6', 'L7'])) {
            $conditions .= " AND type = :type:";
            $bind["type"] = $rangeType;
        }

        $value = RefCarValue::findFirst([
            "conditions" => $conditions,
            "bind" => $bind
        ]);

        return $value ? $value->toArray() : null;
    }
}
