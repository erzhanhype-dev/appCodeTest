<?php

namespace App\Services\Kap\Transformer;

use Phalcon\Di\Injectable;

class VehicleDataTransformer extends Injectable
{
    private const string CODE_TYPE_TRUCK = '1200';
    private const array KAP_FUEL_TYPES = KAPFuelTypeTv;

    public function buildResponse(array $item, bool $isTemp, ?int $logId): array
    {
        $v = $item['Vehicle'];
        $vr = $item['VehicleRegistration'];
        $own = $item['VehicleOwner'];

        $engineCc = (int)($v['EngineCapacityCc'] ?? 0);
        $fuelIdx = (int)($v['FuelTypeTv'] ?? 0);

        $isElectric = $engineCc === 0 && (self::KAP_FUEL_TYPES[$fuelIdx] ?? null) === 4;

        // Логика extractCategoryCode перенесена сюда или в Helper
        $technicalCategoryTv = $v['TechnicalCategoryTv'] ?? '';
        $category = !empty($technicalCategoryTv)
            ? trim(explode('-', $technicalCategoryTv)[0])
            : ($v['CodeTypeTv'] ?? '');

        return [
            'category' => $category,
            'kapLogId' => $logId,
            'regDate' => $vr['OperationDateDeOrRegistration'] ?? '',
            'firstRegDate' => $this->dataHelper->ymd($v['InitialDateRegistrationTv'] ?? null),
            'permissibleMaximumWeight' => (int)($v['PermissibleMaximumWeight'] ?? 0),
            'engineCapacityCc' => $engineCc,
            // ymd вернет дату в 'Y-m-d', если нужен только год, нужно добавить отдельный метод в Helper
            'year' => date('Y', strtotime($v['ReleaseYearTv'] ?? '1970-01-01')),
            'isElectric' => $isElectric,
            'isTruck' => ($v['CodeTypeTv'] ?? '') === self::CODE_TYPE_TRUCK,
            'isTemporaryImportation' => $isTemp,
            'codeTypeTv' => $v['CodeTypeTv'] ?? '',
            'oldId' => $vr['OldId'] ?? '',
            'printDate' => $vr['PrintDateTvrc'] ?? '',
            'lastname' => $own['LastName'] ?? '',
        ];
    }
}