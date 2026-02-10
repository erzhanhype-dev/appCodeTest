<?php

namespace App\Services\Kap;

use Phalcon\Di\Injectable;
use User;

class KapRegInfoService extends Injectable
{
    public function getRegInfo($value, $from = null): array
    {
        // 1. Запрос (через инжектированный клиент)
        $response = $this->kapApiClient->get([
            'value' => $value,
            'requestTypeCode' => 'VIN',
            'format' => '',
        ]);

        // 2. Аутентификация
        $auth = User::getUserBySession();

        // 3. Логирование (через инжектированный логгер)
        // Тип запроса '0' для VIN
        $kapLog = $this->kapLogger->store($value, 0, $auth->id, '', $response);

        // 4. Извлечение записей (через инжектированный парсер)
        $records = $this->kapResponseParser->getItems($response);

        if (!$records) {
            return [];
        }

        // 5. Выбор лучшей записи (через инжектированный селектор)
        [$item, $isTempImport] = $this->kapRecordSelector->selectBestRecord($records);

        // 6. Формирование ответа
        $kapLogId = $from !== 'file'
            ? $kapLog->id ?? null
            : null;

        // 7. Трансформация (через инжектированный трансформер)
        $response = $this->vehicleDataTransformer->buildResponse($item, $isTempImport, $kapLogId);

        return $response;
    }

    public function __invoke($value, $from = null): array
    {
        return $this->getRegInfo($value, $from);
    }
}