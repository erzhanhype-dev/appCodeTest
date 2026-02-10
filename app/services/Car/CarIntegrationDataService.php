<?php

namespace App\Services\Car;

use App\Helpers\LogTrait;
use App\Services\Kap\KapRegInfoService;
use App\Services\Msx\MsxService;
use IntegrationData;
use Phalcon\Di\Injectable;
use RefCarCat;
use RefCountry;
use User;

class CarIntegrationDataService extends Injectable
{
    use LogTrait;

    /** @var KapRegInfoService */
    private KapRegInfoService $kapRegInfoService;

    /** Сколько раз делать повтор запроса к ЭПТС */
    private const int RETRY_MAX_ATTEMPTS = 3;
    /** Задержка перед первой повторной попыткой (сек.) */
    private const int RETRY_INITIAL_DELAY = 2;

    public function onConstruct(): void
    {
        $this->kapRegInfoService = $this->getDI()->getShared(KapRegInfoService::class);
    }

    public function getAgroData($user, string $bodyCode): array
    {
        $msxService = new MsxService();
        $result = $msxService->search([
            'requestTypeCode' => 'VIN',
            'value' => $bodyCode,
        ], 'addCar');


        if(!empty($result)) {

            $list = $result['data']['list'];

            $lastItem = null;
            $lastTs = null;
            foreach ($list as $item) {
                $date = $item['Transport']['registerDate'] ?? null;
                if (!is_string($date) || $date === '') {
                    continue;
                }

                // ожидаем формат YYYY-MM-DD
                $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if (!$dt) {
                    continue;
                }

                $ts = $dt->getTimestamp();
                if ($lastTs === null || $ts < $lastTs) {
                    $lastTs = $ts;
                    $lastItem = $item;
                }
            }

            $data = [];

            if ($lastItem) {
                if (mb_stripos($lastItem['Transport']['typeName'], 'Трактор', 0, 'UTF-8') !== false) {
                    $category = RefCarCat::findFirstByTechCategory('tractor');
                } else {
                    $category = RefCarCat::findFirstByTechCategory('combain');
                }

                $data = [
                    'msx_request_id' => $result['msx_request_id'],
                    'ref_car_cat_id' => $category ? $category->id : null,
                    'year' => $lastItem['Transport']['graduationYear'] ?? null,
                    'max_power_measure' => $lastItem['Transport']['powerTs'],
                    'factory_number' => $lastItem['Transport']['factoryNumber'],
                    'operation_date' => date('d.m.Y', strtotime($lastItem['Transport']['registerDate'])),
                    'unit_name' => $lastItem['Transport']['unitName'],
                ];

                if (!empty($data)) {
                    $requestId = $data['msx_request_id'] ?? null;
                    $requestType = 'msx';

                    if ($requestId) {
                        $this->storeIntegrationData($requestId, $requestType, $data['factory_number'], json_encode($data));
                    }
                }
            }

            return $data;
        }

        return [];
    }

    public function getCarData($user, string $vin, ?User $auth = null): array
    {
        $data = [];

        if ($user) {
            $data = $this->getEptsData($user, $vin, $auth);
        }

        if (empty($data)) {
            $data = $this->getKapData($vin);
        }

        if (!empty($data)) {
            $requestId = $data['kap_log_id'] ?? $data['epts_request_id'] ?? null;
            $requestType = isset($data['kap_log_id']) ? 'kap' : 'epts';

            if ($requestId) {
                $this->storeIntegrationData($requestId, $requestType, $vin, json_encode($data));
            }
        }

        return $data;
    }

    public function getEptsData(User $user, string $vin, ?User $auth = null): array
    {
        $data = [];

        if ($user->user_type_id != 2) {                 // только юр. лица
            $this->writeLog("Пользователь не юр. лицо, $vin, $user->user_type_id");
            return $data;
        }

        $epts = $this->retry(static function () use ($vin, $user) {
            return __getDataFromEpts($vin, 0, $user->id, 'addOrEdit car');
        });

        $statusCode = $epts['STATUS_CODE'] ?? null;
        if (empty($statusCode)) {
            $this->writeLog("Ошибка ЭПТС: STATUS_CODE пустой, $vin");
            return $data;
        }

        $allowedBins = [$user->idnum, $user->bin];

        if ($auth === null) {
            $auth = User::getUserBySession();
        }
        $maySeeData = in_array($epts['EPTS_BIN'] ?? null, $allowedBins, true) || ($auth && $auth->bin === ZHASYL_DAMU_BIN);

        if (!$maySeeData) {
            $this->writeLog("Поиск по ЭПТС: BIN/ИИН не совпадает, $vin");
            return $data;
        }

        $this->writeLog("Поиск по ЭПТС, {$statusCode} $vin, {$user->id}");

        if ($statusCode !== '200') {
            return [];
        }

        $country = isset($epts['EPTS_COUNTRY'])
            ? RefCountry::findFirstByAlpha2($epts['EPTS_COUNTRY'])
            : null;

        $category = isset($epts['EPTS_CATEGORY'])
            ? RefCarCat::findFirstByTechCategory($epts['EPTS_CATEGORY'])
            : null;

        return [
            'epts_request_id' => $epts['EPTS_REQUEST_ID'] ?? null,
            'kap_log_id' => null,
            'ref_country_id' => $country ? $country->id : null,
            'ref_car_cat_id' => $category ? $category->id : null,
            'year' => $epts['EPTS_YEAR'] ?? null,
            'operation_date' => null,
            'initial_registration_date' => null,
            'is_truck' => null,
            'is_electric' => null,
            'is_temporary_importation' => null,
            'permissible_max_weight' => isset($epts['EPTS_MassMeasure1'])
                ? (float)$epts['EPTS_MassMeasure1'] : null,
            'engine_capacity' => isset($epts['EPTS_CapacityMeasure'])
                ? (float)$epts['EPTS_CapacityMeasure'] : null,
            'max_power_measure' => isset($epts['EPTS_MaxPowerMeasure'])
                ? (float)$epts['EPTS_MaxPowerMeasure'] : null,
            'vin' => $vin,
        ];
    }

    public function getKapData(string $vin): array
    {
        $data = [];
        $kapRegInfo = $this->kapRegInfoService->getRegInfo($vin);
        if (!empty($kapRegInfo['regDate'])) {
            $category = RefCarCat::findFirstByTechCategory($kapRegInfo['category']);

            $data = [
                'epts_request_id' => null,
                'kap_log_id' => $kapRegInfo['kapLogId'],
                'ref_country_id' => null,
                'ref_car_cat_id' => $category ? $category->id : null,
                'category_code' => $kapRegInfo['codeTypeTv'] ?? '',
                'year' => $kapRegInfo['year'] ?? null,
                'operation_date' => date('d.m.Y', strtotime($kapRegInfo['regDate'])),
                'initial_registration_date' => date('d.m.Y', strtotime($kapRegInfo['firstRegDate'])),
                'permissible_max_weight' => floatval($kapRegInfo['permissibleMaximumWeight'] ?? 0),
                'engine_capacity' => floatval($kapRegInfo['engineCapacityCc'] ?? 0),
                'max_power_measure' => null,
                'vin' => $vin,
                'is_truck' => $kapRegInfo['isTruck'] ?? 0,
                'is_electric' => $kapRegInfo['isElectric'] ?? 0,
                'is_temporary_importation' => $kapRegInfo['isTemporaryImportation'] ?? false,
            ];
        }

        return $data;
    }

    private function retry(callable $fn): array
    {
        $attempt = 0;
        $delay = self::RETRY_INITIAL_DELAY;
        $result = [];

        do {
            try {
                $result = $fn();

                if (isset($result['STATUS_CODE']) && (int)$result['STATUS_CODE'] > 0) {
                    return $result;
                }
            } catch (\Throwable $e) {
                $this->writeLog("Ошибка при запросе ЭПТС (попытка $attempt): " . $e->getMessage());
            }

            ++$attempt;

            if ($attempt < self::RETRY_MAX_ATTEMPTS) {
                sleep($delay);
                $delay *= 2;
                $this->writeLog("Запрос ЭПТС: попытка $attempt");
            }

        } while ($attempt < self::RETRY_MAX_ATTEMPTS);

        return $result ?: [];
    }

    public function storeIntegrationData($requestId, $requestType, string $vin, $data): IntegrationData
    {
        $integration = new IntegrationData();
        $integration->request_id = $requestId;
        $integration->request_type = $requestType;
        $integration->vin = $vin;
        $integration->data = $data;
        $integration->created = time();
        $integration->save();

        return $integration;
    }

    public function getCarDataFromStorage(string $vin): array
    {
        $oneHourAgo = time() - 3600;

        $lastIntegration = IntegrationData::findFirst([
            'conditions' => 'vin = :vin: AND created >= :start:',
            'bind' => [
                'vin' => $vin,
                'start' => $oneHourAgo,
            ],
            'order' => 'created DESC',
        ]);

        $data = [];
        if ($lastIntegration && $lastIntegration->data) {
            $this->writeLog('Получение данных КАП из хранилище');

            $json = json_decode($lastIntegration->data, true);
            if (is_array($json)) {
                $data = $json;
            }
        }

        return $data;
    }
}
