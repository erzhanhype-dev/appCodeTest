<?php

namespace App\Services\Kap\Processing;

class KapRecordSelector
{
    private const string CARD_STATUS_ACTIVE = 'P';
    // Предполагается, что STARTROP доступна (например, через DI или глобально)
    private const string FIRST_REG_THRESHOLD = STARTROP;

    /**
     * Сортирует и выбирает подходящую запись, а также определяет статус временного ввоза.
     * @param array $records Список записей транспортного средства.
     * @return array [$selectedRecord, $isTemporaryImport]
     */
    public function selectBestRecord(array $records): array
    {
        $sortedRecords = $this->sortRecords($records, 'desc');

        $isTempImportGlobal = false;
        foreach ($sortedRecords as $rec) {
            $isTemp = ($rec['VehicleRegistration']['IsTemporaryImportation'] ?? 'false') === 'true';
            $isTempImportGlobal = $isTempImportGlobal || $isTemp;

            $cardStatus = $rec['VehicleRegistration']['CardStatus'] ?? '';
            $initialDate = strtotime($rec['Vehicle']['InitialDateRegistrationTv'] ?? '1970-01-01');
            $threshold = strtotime(self::FIRST_REG_THRESHOLD);

            if (
                !$isTemp &&
                $cardStatus === self::CARD_STATUS_ACTIVE &&
                $initialDate > $threshold
            ) {
                return [$rec, $isTempImportGlobal]; // нашли подходящую
            }
        }

        // Если не нашли — берём первую после сортировки
        return [$sortedRecords[0], $isTempImportGlobal];
    }

    /**
     * Сортирует записи по трём датам/полям (логика перенесена из KapService)
     */
    private function sortRecords(array $records, string $direction = 'asc'): array
    {
        $multiplier = $direction === 'desc' ? -1 : 1;

        usort($records, function (array $a, array $b) use ($multiplier): int {
            $a1 = strtotime($a['VehicleRegistration']['OperationDateDeOrRegistration'] ?? '');
            $b1 = strtotime($b['VehicleRegistration']['OperationDateDeOrRegistration'] ?? '');

            if ($a1 !== $b1) {
                return $multiplier * ($a1 <=> $b1);
            }

            $a2 = strtotime($a['VehicleRegistration']['PrintDateTvrc'] ?? '');
            $b2 = strtotime($b['VehicleRegistration']['PrintDateTvrc'] ?? '');
            if ($a2 !== $b2) {
                return $multiplier * ($a2 <=> $b2);
            }

            return $multiplier * strnatcmp($a['VehicleRegistration']['OldId'] ?? '', $b['VehicleRegistration']['OldId'] ?? '');
        });

        return $records;
    }
}