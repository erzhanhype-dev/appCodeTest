<?php
namespace App\Services\Kap;

use App\Exceptions\AppException;
use App\Helpers\LogTrait;
use KapLogs;
use Kato;
use Phalcon\Di\Injectable;
use SimpleXMLElement;
use User;

class KapService extends Injectable
{
    use LogTrait;

    private const array KAP_SPECIAL_MARKS = KAPSpecialMarksForPrintingOnSmartCard;
    private const array KAP_FUEL_TYPES = KAPFuelTypeTv;
    private const array KAP_CARD_STATUSES = KAPCardStatus;
    private const array REASON_STATUS = KAPDeRegistrationReasonStatus;
    private const string FIRST_REG_THRESHOLD = STARTROP;
    private const string CARD_STATUS_ACTIVE = 'P';
    private const string CODE_TYPE_TRUCK = '1200';

    /**
     * @throws \Exception
     */
    private function getToken(): string
    {
        // Проверка кешированного токена в сессии
        if (!empty($_SESSION['INTEGRATION_SERVICE_TOKEN'])) {
            return $_SESSION['INTEGRATION_SERVICE_TOKEN'];
        }

        $url = $this->config->integration_service->base_url . '/token';
        $postData = [
            'name' => $this->config->integration_service->username,
            'password' => $this->config->integration_service->password,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData), // Исправлено: отправляем JSON
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json', // Заголовок соответствует телу
                'Accept: application/json',
            ],
            CURLOPT_FOLLOWLOCATION => true, // Разрешаем редиректы
            CURLOPT_MAXREDIRS => 3, // Лимит редиректов
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new AppException('Ошибка cURL');
        }

        // Проверяем успешные коды (200-299)
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new AppException("Ошибка получения токена. Код ответа: $httpCode");
        }

        $data = json_decode($response, true);

        if (empty($data['token'])) {
            throw new AppException("Токен не получен в ответе сервера");
        }

        $_SESSION['INTEGRATION_SERVICE_TOKEN'] = $data['token'];
        return $data['token'];
    }

    /**
     * @throws \Exception
     */
    public function get(array $payload)
    {
        $token = $this->getToken();
        $url = $this->config->integration_service->base_url . '/kap';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new AppException('Ошибка cURL');
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return json_decode($response, true);
    }

    /**
     * @throws \Exception
     */
    public function getItemsFromFileName($filename)
    {
        $token = $this->getToken();
        $url = $this->config->integration_service->base_url . '/kap/list/' . $filename;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception('Ошибка cURL: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $items = [];
        if ($response) {
            $decoded = json_decode($response);

            if(isset($decoded->data)) {
                if ($decoded->data->GreenDevVrResponse->ItemCount > 1) {
                    if(isset($decoded->data->GreenDevVrResponse->Items->Item)) {
                        $items = $decoded->data->GreenDevVrResponse->Items->Item;
                    }
                } else {
                    if(isset($decoded->data->GreenDevVrResponse->Items->Item)) {
                        $items[] = $decoded->data->GreenDevVrResponse->Items->Item;
                    }
                }
            }
        }

        return $items;
    }

    public function getRegInfo(string $value, ?string $from = null): array
    {
        $records = $this->fetchRecords($value, null);
        if (!$records) {
            return [];
        }

        [$item, $isTempImport] = $this->prepareRecord($records);

        $auth = User::getUserBySession();
        $kapLog = $this->store($value, 'vin', $auth->id, '', $item);
        $kapLogId = $from !== 'file'
            ? $kapLog->id ?? null
            : null;

        $response = $this->buildResponse($item, $isTempImport, $kapLogId);

        return $response;
    }

    private function fetchRecords(string $value, ?string $from): array
    {
        $records = $this->get([
            'value' => $value,
            'requestTypeCode' => 'VIN',
            'format' => '',
        ]);
        $items = $this->getItems($records);

        if ($items && count($items) > 0) {
            if (isset($items[0]['VehicleRegistration'])) {
                return $items;
            } else {
                return [$items];
            }
        }

        return [];
    }

    private function prepareRecord(array $records): array
    {
        /* сортировка по трём критериям DESC */
        usort($records, function ($a, $b) {
            return
                strtotime($b['VehicleRegistration']['OperationDateDeOrRegistration'] ?? '') <=> strtotime($a['VehicleRegistration']['OperationDateDeOrRegistration'] ?? '') ?:
                    strtotime($b['VehicleRegistration']['PrintDateTvrc'] ?? '') <=> strtotime($a['VehicleRegistration']['PrintDateTvrc'] ?? '') ?:
                        strnatcmp($b['VehicleRegistration']['OldId'] ?? '', $a['VehicleRegistration']['OldId'] ?? '');
        });

        $isTempImport = false;
        foreach ($records as $idx => $rec) {
            $temp = $rec['VehicleRegistration']['IsTemporaryImportation'] === 'true';
            $isTempImport = $isTempImport || $temp;

            if (
                !$temp &&
                $rec['VehicleRegistration']['CardStatus'] === self::CARD_STATUS_ACTIVE &&
                strtotime($rec['Vehicle']['InitialDateRegistrationTv']) > strtotime(self::FIRST_REG_THRESHOLD)
            ) {
                return [$rec, $isTempImport]; // нашли подходящую
            }
        }
        // если не нашли — берём первую после сортировки
        return [$records[0], $isTempImport];
    }

    private function buildResponse(array $item, bool $isTemp, ?int $logId): array
    {
        $v = $item['Vehicle'];
        $vr = $item['VehicleRegistration'];
        $own = $item['VehicleOwner'];

        $engineCc = (int)$v['EngineCapacityCc'];
        $fuelIdx = (int)$v['FuelTypeTv'];
        $isElectric = $engineCc === 0 && (KAPFuelTypeTv[$fuelIdx] ?? null) === 4;

        /* внутр. хелп: безопасное преобразование даты */
        $fmtDate = static function (?string $d, string $f) {
            return $d ? date($f, strtotime($d)) : '';
        };

        return [
            'category' => !empty($v['TechnicalCategoryTv'])
                ? trim(explode('-', $v['TechnicalCategoryTv'])[0])
                : ($v['CodeTypeTv'] ?? ''),
            'kapLogId' => $logId,
            'regDate' => $vr['OperationDateDeOrRegistration'] ?? '',
            'firstRegDate' => $fmtDate($v['InitialDateRegistrationTv'] ?? null, 'Y-m-d'),
            'permissibleMaximumWeight' => (int)$v['PermissibleMaximumWeight'],
            'engineCapacityCc' => $engineCc,
            'year' => $fmtDate($v['ReleaseYearTv'] ?? null, 'Y'),
            'isElectric' => $isElectric,
            'isTruck' => $v['CodeTypeTv'] === self::CODE_TYPE_TRUCK,
            'isTemporaryImportation' => $isTemp,
            'codeTypeTv' => $v['CodeTypeTv'] ?? '',
            'oldId' => $vr['OldId'] ?? '',
            'printDate' => $vr['PrintDateTvrc'] ?? '',
            'lastname' => $own['LastName'] ?? '',
        ];
    }

    public function getStatus($response)
    {
        $status = [];
        if ($response) {
            $status = $response['data']['list']['Status'];
        }

        return $status;
    }

    public function getItems($response)
    {
        $items = [];
        if ($response) {
            $items = $response['data']['list']['GreenDevVrResponse']['Items'];
            if (isset($items['Item'])) {
                $items = $items['Item'];
            }
        }

        return $items;
    }

    /**
     * @throws \Exception
     */
    public function getFile($filename)
    {
        $token = $this->getToken();
        $url = $this->config->integration_service->base_url . '/kap/file/' . $filename;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception('Ошибка cURL: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $response;
    }

    public function parseDataWithSorted($result): array
    {
        $data = json_decode($result, true);
        $sortedData = $this->sortVehicleData($data);
        return $this->formatVehicleData($sortedData);
    }

    /**
     * Сортировка данных транспортных средств
     */
    private function sortVehicleData(array $data): array
    {
        if (!isset($data[0]) && !isset($data['VehicleRegistration'])) {
            return $data;
        }

        $items = isset($data['VehicleRegistration']) ? [$data] : $data;
        usort($items, function ($a, $b) {
            $regA = $a['VehicleRegistration'] ?? [];
            $regB = $b['VehicleRegistration'] ?? [];

            // 1. Сортировка по OperationDateDeOrRegistration (по убыванию - новые сначала)
            $dateCompare = strtotime($regB['OperationDateDeOrRegistration'] ?? '')
                <=> strtotime($regA['OperationDateDeOrRegistration'] ?? '');
            if ($dateCompare !== 0) return $dateCompare;

            // 2. Сортировка по CardStatus (P имеет приоритет)
            $statusCompare = ($regA['CardStatus'] === 'P' ? 0 : 1)
                <=> ($regB['CardStatus'] === 'P' ? 0 : 1);
            if ($statusCompare !== 0) return $statusCompare;

            // 3. Сортировка по PrintDateTvrc (по убыванию - новые сначала)
            $printDateCompare = strtotime($regB['PrintDateTvrc'] ?? '')
                <=> strtotime($regA['PrintDateTvrc'] ?? '');
            if ($printDateCompare !== 0) return $printDateCompare;

            // 4. Сортировка по OldId (по убыванию - большие значения сначала)
            return ($regB['OldId'] ?? 0) <=> ($regA['OldId'] ?? 0);
        });

        // Инвертируем порядок
        return array_reverse($items);
    }

    /**
     * Форматирование данных для вывода
     */
    private function formatVehicleData(array $data): array
    {
        $fields = $this->getFieldList();
        $t = $this->translator;
        $headers = array_map(function ($field) use ($t) {
            return $t[$field] ?? $field;
        }, $fields);

        $result = [$headers];

        foreach ($data as $entry) {
            $vehicle = $entry['Vehicle'] ?? [];
            $registration = $entry['VehicleRegistration'] ?? [];
            $owner = $entry['VehicleOwner'] ?? [];
            $reason = $registration['DeRegistrationReasonCode'] ?? '-';
            if (is_array($reason)) {
                // Склеиваем элементы через запятую (можно поменять разделитель)
                $reason = implode(', ', $reason);
            }

            $katoText = '-';
            if($owner['OwnerTvLocalityKatoId']) {
                $katoIds = is_array($owner['OwnerTvLocalityKatoId'])
                    ? $owner['OwnerTvLocalityKatoId']
                    : [$owner['OwnerTvLocalityKatoId']];
                $katoText = $this->getKatoDescription($katoIds);
            }

            $isTruck = is_array($vehicle['CodeTypeTv']) ? $this->isTruck($vehicle['CodeTypeTv'][0] ?? '') : $this->isTruck($vehicle['CodeTypeTv'] ?? '');
            $isPickup = is_array($vehicle['CodeTypeTv']) ? $this->isPickup($vehicle['CodeTypeTv'][0] ?? '') : $this->isPickup($vehicle['CodeTypeTv'] ?? '');

            $result[] = [
                // Основная информация о ТС
                $vehicle['VinCodeTv'] ?? '-',
                $vehicle['BodyNumberTvVinCode'] ?? '-',
                $this->formatArrayValue($vehicle['ChassisNumberTv'] ?? null),
                $vehicle['EngineNumberTv'] ?? '-',

                // Даты
                $this->formatDate($vehicle['ReleaseYearTv'] ?? null, 'Y'),
                $this->formatDate($vehicle['InitialDateRegistrationTv'] ?? null),

                // Категории
                $this->getFirstValue($vehicle['CategoryTv'] ?? null, '-'),
                $vehicle['CategoryTvCode'] ?? '-',
                $this->getFirstValue($vehicle['TechnicalCategoryTv'] ?? null, '-'),
                $this->getFirstValue($vehicle['TechnicalCategoryTvCode'] ?? null, '-'),

                // Технические характеристики
                $vehicle['CodeTypeTv'] ?? '-',
                $isTruck,
                $isPickup,
                $vehicle['EngineCapacityCc'] ?? '-',
                $vehicle['PermissibleMaximumWeight'] ?? '-',
                $vehicle['WeightWithoutLoad'] ?? '-',
                $vehicle['SeatsNumber'] ?? '-',
                $vehicle['EnginePowerKw'] ?? '-',
                $vehicle['EnginePowerHp'] ?? '-',
                self::KAP_FUEL_TYPES[$vehicle['FuelTypeTv'] ?? ''] ?? '-',
                $vehicle['ModelModification'] ?? '-',
                $vehicle['ColorName'] ?? '-',

                // Регистрационные данные
                self::KAP_CARD_STATUSES[$registration['CardStatus'] ?? ''] ?? '-',
                $registration['OldStateLicencePlate'] ?? '-',
                $registration['StateLicencePlate'] ?? '-',
                $registration['SeriesAndNumberTvrc'] ?? '-',
                $this->formatBoolean($registration['IsRegistered'] ?? ''),

                // Дополнительная информация
                $reason ? (self::REASON_STATUS[$reason] ?: '-') : '-',
                $this->formatDate($registration['OperationDateDeOrRegistration'] ?? null),
                $registration['SpecialMarks'] ?? '-',
                $this->processSmartCard($registration['SpecialMarksForPrintingOnSmartCard'] ?? ''),
                $this->formatBoolean($registration['IsTemporaryImportation'] ?? ''),
                $this->formatTemporaryImportDate($registration['TemporaryImportationEndDate'] ?? null),
                $this->formatDateTime($registration['PrintDateTvrc'] ?? null),
                $registration['OldId'] ?? '-',

                // Данные владельца
                $owner['TypeOfPerson'] ?? '-',
                $owner['IinOrBin'] ?? '-',
                $owner['LastName'] ?? '-',
                $owner['FirstName'] ?? '-',
                $owner['MiddleName'] ?? '-',
                $katoText,
                $owner['OwnerStreetLocationTvrc'] ?? '-',
                $owner['OwnerHouseInformationNumberLocationTvrc'] ?? '-',
                $owner['OwnerApartmentInformationNumberLocationTvrc'] ?? '-',
                $owner['PhoneNumber'] ?? '-',
            ];
        }

        return $result;
    }

    /**
     * Вспомогательные методы форматирования
     */
    private function formatArrayValue($value): string
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }
        return $value ?? '-';
    }

    private function formatDate(?string $date, string $format = 'Y-m-d'): string
    {
        if (empty($date)) return '-';
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : '-';
    }

    private function formatDateTime(?string $date): string
    {
        return $this->formatDate($date, 'Y-m-d H:i');
    }

    private function formatBoolean(string $value): string
    {
        return strtoupper($value) === 'TRUE' ? 'Да' : 'Нет';
    }

    private function getFirstValue($value, $default = '-')
    {
        if (is_array($value)) {
            return $value[0] ?? $default;
        }
        return $value ?? $default;
    }

    private function isTruck(string $codeType): string
    {
        return $codeType == '1200' ? 'Да' : 'Нет';
    }

    private function isPickup(string $codeType): string
    {
        return $codeType == '4500' ? 'Да' : 'Нет';
    }

    private function formatTemporaryImportDate($date): string
    {
        if (empty($date)) return '-';
        if (is_array($date)) {
            return $this->formatDate($date[0] ?? null);
        }
        return $this->formatDate($date);
    }

    private function processSmartCard(string $specialMarks): string
    {
        if (empty($specialMarks)) return '-';

        $result = [];
        $entries = explode(';', $specialMarks);

        foreach ($entries as $entry) {
            if (empty(trim($entry))) continue;

            $parts = explode(' ', $entry, 2);
            $num = $parts[0] ?? null;
            $value = $parts[1] ?? null;

            if (!isset(self::KAP_SPECIAL_MARKS[$num])) continue;

            $label = self::KAP_SPECIAL_MARKS[$num];
            $displayValue = $num == 9 ? substr($entry, 1) : $value;
            $result[] = "$label: <b>$displayValue</b>";
        }

        return $result ? implode(';<br>', $result) . ';' : '-';
    }


    /**
     * Список полей в правильном порядке
     */
    private function getFieldList(): array
    {
        return [
            'VinCodeTv', 'BodyNumberTvVinCode',
            'ChassisNumberTv', 'EngineNumberTv', 'ReleaseYearTv', 'InitialDateRegistrationTv',
            'CategoryTv', 'CategoryTvCode', 'TechnicalCategoryTv', 'TechnicalCategoryTvCode',
            'CodeTypeTv', 'isTruck', 'isPickup', 'EngineCapacityCc', 'PermissibleMaximumWeight',
            'WeightWithoutLoad', 'SeatsNumber', 'EnginePowerKw', 'EnginePowerHp', 'FuelTypeTv',
            'ModelModification', 'ColorName', 'CardStatus', 'OldStateLicencePlate', 'StateLicencePlate',
            'SeriesAndNumberTvrc', 'IsRegistered', 'DeRegistrationReasonCode', 'OperationDateDeOrRegistration',
            'SpecialMarks', 'SpecialMarksForPrintingOnSmartCard', 'IsTemporaryImportation',
            'TemporaryImportationEndDate', 'PrintDateTvrc', 'OldId', 'TypeOfPerson', 'IinOrBin', 'LastName', 'FirstName',
            'MiddleName', 'OwnerTvLocalityKatoId', 'OwnerStreetLocationTvrc',
            'OwnerHouseInformationNumberLocationTvrc', 'OwnerApartmentInformationNumberLocationTvrc',
            'PhoneNumber',
        ];
    }

    /**
     * @throws \Exception
     */
    public function getSignFromXmlString(string $xmlString): string
    {
        $hash = '';

        try {
            // 1) Парсим внешний XML безопасно
            $xmlObject = $this->loadXmlSafe($xmlString);

            // 2) Ищем <Payload>
            $payloadNodes = $xmlObject->xpath('//Payload');
            if (empty($payloadNodes)) {
                throw new AppException("Элемент <Payload> не найден");
            }

            // 3) Извлекаем текст payload
            $payloadRaw = (string)$payloadNodes[0];

            // Иногда партнер шлёт:
            // - XML с экранированием (&lt;...&gt;), тогда надо decode;
            // - уже «сырой» XML с '<...>', тогда decode не нужен.
            // Определим простым эвристическим правилом.
            $payloadXml = $payloadRaw;
            if (strpos($payloadRaw, '<') === false && preg_match('/&lt;.*&gt;/s', $payloadRaw)) {
                // Похоже на экранированный XML: распакуем
                $payloadXml = html_entity_decode($payloadRaw, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }

            // 4) Парсим вложенный XML безопасно (фиксим только неверные '&')
            $innerXmlObject = $this->loadXmlSafe($payloadXml);
            $certificateNodes = $innerXmlObject->xpath('//*[local-name()="X509Certificate"]');

            // 5) Извлекаем X509Certificate без жёсткой привязки к префиксу
            //    (некоторые отправители используют другой префикс, но тот же namespace)
            // Вариант А: namespace-агностичный поиск

            // Если вдруг нужен именно namespace W3C Signature, можно уточнить:

            if (empty($certificateNodes)) {
                throw new AppException("Сертификаты не найдены в XML");
            }

            foreach ($certificateNodes as $certificate) {
                $hash .= trim((string)$certificate) . PHP_EOL;
            }

        } catch (AppException $e) {
        }

        return $hash;
    }

    /**
     * Безопасная загрузка XML из строки:
     *  - убирает BOM,
     *  - гарантирует UTF-8,
     *  - чинит только НЕвалидные амперсанды,
     *  - возвращает SimpleXMLElement или бросает AppException с подробностями.
     */
    private function loadXmlSafe(string $xmlString): SimpleXMLElement
    {
        // Удалим BOM
        $xmlString = preg_replace('/^\xEF\xBB\xBF/', '', $xmlString);

        // Нормализуем перевод строк
        $xmlString = str_replace("\r\n", "\n", $xmlString);

        // Кодировка -> UTF-8
        if (!mb_check_encoding($xmlString, 'UTF-8')) {
            $xmlString = mb_convert_encoding($xmlString, 'UTF-8', 'auto');
        }

        // Поправляем только "голые" &, не трогаем валидные сущности
        $xmlString = preg_replace(
            '/&(?!amp;|lt;|gt;|quot;|apos;|#\d+;)/u',
            '&amp;',
            $xmlString
        );

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(
            $xmlString,
            'SimpleXMLElement',
            LIBXML_COMPACT | LIBXML_PARSEHUGE
        );

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            $msgs = array_map(function ($e) {
                return trim(sprintf(
                    '[%s] line %d, col %d: %s',
                    $e->level === LIBXML_ERR_WARNING ? 'WARN' :
                        ($e->level === LIBXML_ERR_ERROR ? 'ERROR' : 'FATAL'),
                    $e->line,
                    $e->column,
                    $e->message
                ));
            }, $errors);

            throw new AppException("Не удалось распарсить XML:\n" . implode("\n", $msgs));
        }

        return $xml;
    }

    public function parseDataForDoc($result): array
    {
        $t = $this->translator;

        $list = $result ? json_decode($result, true) : [];

        // Сортируем данные по всем полям
        $list = $this->sortVehicleData($list);

        $list = end($list);

        $fields = [
            'OperationDateDeOrRegistration',
            'StateLicencePlate',
            'OldStateLicencePlate',
            'ModelModification',
            'ReleaseYearTv',
            'EngineNumberTv',
            'ChassisNumberTv',
            'BodyNumberTvVinCode',
            'CategoryTv',
            'CategoryTvCode',
            'TechnicalCategoryTv',
            'TechnicalCategoryTvCode',
            'ColorCode',
            'ColorName',
            'SeriesAndNumberTvrc',
            'CategoryTv',
            'EnginePowerKw',
            'EnginePowerHp',
            'EngineCapacityCc',
            'PermissibleMaximumWeight',
            'WeightWithoutLoad',
            'CardStatus',
            'TypeOfPerson',
            'CodeTypeTv',
            'VinCodeTv',
            'SpecialMarks',
            'DeRegistrationReasonCode',
            'OperationDateDeOrRegistration',
            'IinOrBin',
            'LastName',
            'FirstName',
            'MiddleName',
        ];

        $items = [array_map(function ($field) use ($t) {
            return $t[$field];
        }, $fields)];

        if(isset($list['VehicleRegistration'])){
        foreach ($list['VehicleRegistration'] ? [$list] : $list as $entry) {
            $vehicle = $entry['Vehicle'] ?? [];
            $registration = $entry['VehicleRegistration'] ?? [];
            $owner = $entry['VehicleOwner'] ?? [];

            $newItem = [
                $this->formatDate($registration['OperationDateDeOrRegistration'] ?? null),
                $registration['StateLicencePlate'] ?? '-',
                $registration['OldStateLicencePlate'] ?? '-',
                $vehicle['ModelModification'] ?? '-',
                $this->formatDate($vehicle['ReleaseYearTv'] ?? null, 'Y'),
                $vehicle['EngineNumberTv'] ?? '-',
                $this->formatArrayValue($vehicle['ChassisNumberTv'] ?? null),
                $vehicle['BodyNumberTvVinCode'] ?? '-',
                $this->getFirstValue($vehicle['CategoryTv'] ?? null, '-'),
                $vehicle['CategoryTvCode'] ?? '-',
                $this->getFirstValue($vehicle['TechnicalCategoryTv'] ?? null, '-'),
                $this->getFirstValue($vehicle['TechnicalCategoryTvCode'] ?? null, '-'),
                $vehicle['ColorCode'] ?? '-',
                $vehicle['ColorName'] ?? '-',
                $registration['SeriesAndNumberTvrc'] ?? '-',
                $this->getFirstValue($vehicle['CategoryTv'] ?? null, '-'),
                $vehicle['EnginePowerKw'] ?? '-',
                $vehicle['EnginePowerHp'] ?? '-',
                $vehicle['EngineCapacityCc'] ?? '-',
                $vehicle['PermissibleMaximumWeight'] ?? '-',
                $vehicle['WeightWithoutLoad'] ?? '-',
                KAPCardStatus[$registration['CardStatus'] ?? ''] ?? '-',
                $owner['TypeOfPerson'] ?? '-',
                $vehicle['CodeTypeTv'] ?? '-',
                $vehicle['VinCodeTv'] ?? '-',
                $registration['SpecialMarks'] ?? '-',
                $registration['DeRegistrationReasonCode']
                    ? (KAPDeRegistrationReasonStatus[$registration['DeRegistrationReasonCode']] ?? '-')
                    : '-',
                $this->formatDate($registration['OperationDateDeOrRegistration'] ?? null),
                $owner['IinOrBin'] ?? '-',
                $owner['LastName'] ?? '-',
                $owner['FirstName'] ?? '-',
                $owner['MiddleName'] ?? '-',
            ];

            $items[] = $newItem;
        }
        }

        return $items;
    }

    public function store($req_value, $req_type, $user_id, $comment, $response): KapLogs
    {
        $status = $this->getStatus($response);
        $payload_status = $status['Code'];
        $payload_message_ru = $status['MessageRu'];
        $payload_message_kz = $status['MessageKz'];
        $messageId = $response['data']['message_id'];
        $sessionId = $response['data']['session_id'];
        $code = $response['data']['code'];
        $message = isset($response['data']['message']) ? $response['data']['message'] : '';
        $responseDate = $response['data']['response_date'];
        $request_time = $response['data']['request_time'];
        $execution_time = $response['data']['execution_time'];
        $request_xml_file_name = $response['data']['request'];
        $response_xml_file_name = $response['data']['response'];

        $req_type_label = '';
        if ($req_type == 0) {
            $req_type_label = 'VIN';
        } else if ($req_type == 1) {
            $req_type_label = 'GRNZ';
        } else if ($req_type == 2) {
            $req_type_label = 'IINORBIN';
        }

        $kap_log = new KapLogs();
        $kap_log->req_value = $req_value;
        $kap_log->req_type = $req_type_label;
        $kap_log->req_time = $request_time;
        $kap_log->execution_time = $execution_time;
        $kap_log->request = $request_xml_file_name;
        $kap_log->response = $response_xml_file_name;
        $kap_log->message_id = $messageId;
        $kap_log->session_id = $sessionId;
        $kap_log->response_date = $responseDate;
        $kap_log->code = $code;
        $kap_log->message = $message;
        $kap_log->response_success = '';
        $kap_log->response_type = '';
        $kap_log->payload_status = $payload_status;
        $kap_log->payload_message_ru = $payload_message_ru;
        $kap_log->payload_message_kz = $payload_message_kz;
        $kap_log->user_id = $user_id;
        $kap_log->created = time();
        $kap_log->comment = $comment;
        $kap_log->save();

        $logString = json_encode($kap_log->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $this->writeLog($logString);

        return $kap_log;
    }

    private function getValueByPath(array $array, $path)
    {
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (isset($array[$key])) {
                $array = $array[$key];
            } else {
                return null;
            }
        }
        return $array;
    }

    private function getKatoDescription($katoCodes): string
    {
        $katoText = '-';

        if (count($katoCodes) > 0) {

            $names = [];

            foreach ($katoCodes as $katoCode) {
                if (!$katoCode) {
                    continue;
                }

                $kato = Kato::findFirstByKatoCode((int)$katoCode);

                if (!$kato) {
                    continue;
                }

                $parts = [];

                while ($kato) {
                    if (!empty($kato->name_ru)) {
                        $parts[] = $kato->name_ru;
                    }

                    if (!$kato->parent_id) {
                        break;
                    }

                    $kato = Kato::findFirstById($kato->parent_id);

                    if (!$kato) {
                        break;
                    }
                }

                if ($parts) {
                    // НЕ переворачиваем — оставляем от нижнего к верхнему
                    $names = array_merge($names, $parts);
                }
            }

            if (!empty($names)) {
                $uniqueNames = array_unique($names);
                $katoText = implode(', ', $uniqueNames);
            }
        }

        return $katoText;
    }
}