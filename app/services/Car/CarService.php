<?php

namespace App\Services\Car;

use App\Exceptions\AppException;
use App\Helpers\LogTrait;
use App\Repositories\CarRepository;
use App\Services\Car\Dto\CarCreateDto;
use Car;
use File;
use IntegrationData;
use Phalcon\Di\Injectable;
use Profile;
use RefCarCat;
use Transaction;
use User;

class CarService extends Injectable
{
    use LogTrait;

    protected CarIntegrationDataService $carDataEnrichmentService;
    protected CarCalculationService $carCalculationService;
    protected CarValidator $carValidator;
    protected VinService $vinService;
    protected CarRepository $carRepository;
    protected CarSessionService $carSessionService;

    public function __construct()
    {
        $this->onConstruct();
    }

    public function onConstruct(): void
    {
        $this->carDataEnrichmentService = $this->getDI()->getShared(CarIntegrationDataService::class);
        $this->carCalculationService = $this->getDI()->getShared(CarCalculationService::class);
        $this->carValidator = $this->getDI()->getShared(CarValidator::class);
        $this->vinService = $this->getDI()->getShared(VinService::class);
        $this->carRepository = $this->getDI()->getShared(CarRepository::class);
        $this->carSessionService = $this->getDI()->getShared(CarSessionService::class);
    }

    // -------------------------------------------------------------------------
    // PUBLIC API: Core Actions
    // -------------------------------------------------------------------------

    /**
     * @throws AppException
     */
    public function create(CarCreateDto $dto, User $user, ?Profile $profile, $authUser): Car
    {
        $profile = $this->carValidator->assertProfileAccessible($profile, $authUser);
        $this->carValidator->assertCarLimit($profile);
        $this->carValidator->assertVinUnique($dto->vin, $dto->id_code, $dto->body_code);

        $data = $this->buildCarData($dto, $user, $profile, 'create');
        $car = $this->storeCar(new Car(), $data, $authUser);

        $this->writeLog("Создано новое ТС: ID {$car->id}, VIN {$car->vin}, Profile ID {$profile->id}");

        return $car;
    }

    /**
     * @throws AppException
     */
    public function update(CarCreateDto $dto, User $user, Car $car, $authUser): Car
    {
        $profile = $this->loadCarProfile($car, $authUser);
        $this->carValidator->assertVinUnique($dto->vin, $dto->id_code, $dto->body_code, (int)$car->id);

        if ($car->ref_car_type_id != 6) {
            $dto->vehicle_type = $car->vehicle_type;
        }

        $data = $this->buildCarData($dto, $user, $profile, 'update');
        $this->carValidator->check($data);
        $car = $this->storeCar($car, $data, $authUser);

        $this->writeLog("Обновлено ТС: ID {$car->id}, VIN {$car->vin}");

        return $car;
    }

    /**
     * @throws AppException
     */
    public function upload($data, ?User $authUser = null): Car
    {
        $car = $this->storeCar(new Car(), $data, $authUser);
        $this->writeLog("Загружено ТС через импорт: ID {$car->id}, VIN {$car->vin}");
        return $car;
    }

    /**
     * Подготовка данных для нового ТС (без сохранения).
     * @throws AppException
     */
    public function new(CarCreateDto $dto, User $user, ?Profile $profile, $authUser): array
    {
        $profile = $this->carValidator->assertProfileAccessible($profile, $authUser);
        $this->carValidator->assertCarLimit($profile);
        $this->carValidator->assertVinUnique($dto->vin, $dto->id_code, $dto->body_code);

        $carType = $this->carSessionService->getCarType();
        $validateData = [
            'vin' => $dto->vin,
            'id_code' => $dto->id_code,
            'body_code' => $dto->body_code,
            'vehicle_type' => $carType === 'CAR' ? Car::VEHICLE_TYPE_PASSENGER : 'AGRO',
        ];

        $this->carValidator->check($validateData);
        $data = $this->buildCarData($dto, $user, $profile);

        if ($data['vehicle_type'] !== 'AGRO') {
            $data = $this->attachIntegrationData($data);
        }

        return $data;
    }

    /**
     * Подготовка данных для редактирования ТС (без сохранения).
     * @throws AppException
     */
    public function edit(CarCreateDto $dto, User $user, Car $car, $authUser): array
    {
        $profile = $this->loadCarProfile($car, $authUser);

        $dto->vin = $car->vin;
        $dto->volume = $car->volume;
        $dto->ref_country_id = $car->ref_country;
        $dto->ref_country_import = $car->ref_country_import;
        $dto->ref_car_cat_id = $car->ref_car_cat;
        $dto->semi_truck = $car->ref_st_type;
        $dto->is_electric = $car->electric_car;
        $dto->date_import = $car->date_import;
        $dto->year = $car->year;
        $dto->vehicle_type = $car->vehicle_type;

        $data = $this->buildCarData($dto, $user, $profile, 'edit');

        if ($data['vehicle_type'] !== 'AGRO') {
            $data = $this->attachIntegrationData($data);
        }

        return $data;
    }

    /**
     * @throws AppException
     */
    public function delete(Car $car, $authUser): bool
    {
        $profile = Profile::findFirstById($car->profile_id);
        if (!$profile) {
            throw new AppException("Заявка не найдена");
        }

        $this->checkProfileLockedForChanges((int)$profile->id);

        if (!$this->canEdit($authUser, $profile)) {
            throw new AppException("Вы не имеете права удалять этот объект.");
        }

        $carId = $car->id;
        $carVin = $car->vin;

        if ($car->delete()) {
            $this->cleanupCarFiles($car, $profile);
            __carRecalc($profile->id);
            $this->writeLog("Удалено ТС: ID {$carId}, VIN {$carVin}, Profile ID {$profile->id}");
            return true;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // PUBLIC API: Queries & Data Fetching (Unified Wrappers)
    // -------------------------------------------------------------------------

    public function itemsByProfile($profileId)
    {
        return $this->carRepository->findByProfileId((int)$profileId);
    }

    public function itemsBySuperModeratorProfile($profileId)
    {
        $auth = User::getUserBySession();
        if (!$auth || (!$auth->isSuperModerator() && !$auth->isAdminSoft())) {
            return false;
        }
        return $this->carRepository->findBySuperModerator((int)$profileId, (int)$auth->id);
    }

    public function itemsCancelledByProfile($profileId): array
    {
        $data = [];
        $cars = $this->carRepository->findHistoryByProfileId((int)$profileId);

        foreach ($cars as $c) {
            if ((int)$c->c_type === 2 || $c->c_vehicle_type === 'CARGO') {
                $volumeText = "{$c->c_volume} кг";
            } elseif ((int)$c->c_type < 4 || $c->c_vehicle_type === 'PASSENGER') {
                $volumeText = "{$c->c_volume} см*3";
            } else {
                $volumeText = "{$c->c_volume} л.с.";
            }

            $data[] = [
                'id' => $c->c_id,
                'volume' => $volumeText,
                'vin' => $c->c_vin,
                'year' => $c->c_year,
                'action' => $c->c_action,
                'car_cat' => $c->c_car_cat,
                'date_import' => date('d.m.Y', convertTimeZone($c->c_date_import)),
                'country' => $c->c_country,
                'country_import' => $c->c_country_import,
            ];
        }

        return $data;
    }

    public function getCarData($user, string $vin, ?User $auth = null): array
    {
        $auth = $auth ?: User::getUserBySession();
        return $this->carDataEnrichmentService->getCarData($user, $vin, $auth);
    }

    public function getCarDataFromStorage(string $vin): array
    {
        return $this->carDataEnrichmentService->getCarDataFromStorage($vin);
    }

    public function storeIntegrationData($requestId, $requestType, string $vin, $data): IntegrationData
    {
        return $this->carDataEnrichmentService->storeIntegrationData($requestId, $requestType, $vin, $data);
    }

    public function calculationPaySum(
        $method, $date, $vol, $val, $st, $electric, $temp, $kapId,
        $countryImport = null, $profileCreated = null, $trApprove = null, $auth = null
    ): array {
        return $this->carCalculationService->calculationPaySum(
            $method, $date, $vol, $val, $st, $electric, $temp, $kapId,
            $countryImport, $profileCreated, $trApprove, $auth
        );
    }

    public function getCarVolume($cat, $code, $capacity, $weight, $power, $type = null): float
    {
        return $this->carCalculationService->getCarVolume($cat, $code, $capacity, $weight, $power, $type);
    }

    public function getCarPriceValue($volume, $vehicleType, $refCarCat): ?array
    {
        return $this->carCalculationService->getCarPriceValue($volume, $vehicleType, $refCarCat);
    }

    public function calculationCarCost(array $carIds): float
    {
        $sum = 0.0;
        foreach ($carIds as $id) {
            $car = $this->carRepository->findById((int)$id);
            if ($car) {
                $sum += (float)$car->cost;
            }
        }
        return $sum;
    }

    public function isOldExportCar(array $carIds): array
    {
        $existInOldExport = [];
        foreach ($carIds as $id) {
            $car = $this->carRepository->findById((int)$id);
            if ($car && __checkInner($car->vin)) {
                $existInOldExport[] = $car->vin;
            }
        }
        return $existInOldExport;
    }

    public function setTransactionSum($pid): void
    {
        $tr = Transaction::findFirstByProfileId($pid);
        if (!$tr) {
            return;
        }
        $amount = Car::sum([
            'column' => 'cost',
            'conditions' => 'profile_id = :pid:',
            'bind' => ['pid' => $tr->profile_id]
        ]);
        $tr->amount = (float)($amount ?: 0);
        $tr->save();
    }

    // -------------------------------------------------------------------------
    // PUBLIC API: Permissions & Logic Checks
    // -------------------------------------------------------------------------

    public function canEdit($auth, $p): bool
    {
        if (!$auth) return false;
        if ($auth->isSuperModerator()) return true;
        if ($auth->isModerator() && $auth->id == $p->moderator_id) return true;
        if ($auth->isClient() && $auth->id == $p->user_id) return true;
        return false;
    }

    public function isUserBlacklisted($auth): bool
    {
        return in_array($auth->idnum, CAR_BLACK_LIST, true) || in_array("BLOCK_ALL", CAR_BLACK_LIST, true);
    }

    public function isProfileBlockedForUser($auth, $profileUserId, $profileBlocked): bool
    {
        return $auth->id != $profileUserId && $profileBlocked;
    }

    /**
     * @throws AppException
     */
    public function checkProfileLockedForChanges(int $profileId): void
    {
        $filesCount = File::count([
            "type = 'application' AND profile_id = :pid: AND visible = 1",
            "bind" => ["pid" => $profileId]
        ]);

        if ($filesCount > 0) {
            throw new AppException('Уважаемый пользователь, вы не можете вносить изменения в ТС, так как уже подписали электронное Заявление!');
        }
    }

    // -------------------------------------------------------------------------
    // PRIVATE METHODS: Internal Logic (Using Unified Access)
    // -------------------------------------------------------------------------

    /**
     * Общая логика подготовки данных для create/update/new/edit.
     */
    private function buildCarData(CarCreateDto $dto, User $user, Profile $profile, ?string $type = null): array
    {
        $auth = User::getUserBySession();
        $isModeratorAction = ($type === 'create' || $type === 'update') && $auth && $auth->isSuperModerator();
        $this->carSessionService->clearCarData();
        $sessionData = $this->enrichSessionData($dto, $user, $type, (bool)$isModeratorAction, $auth);
        $this->carSessionService->setCarData($sessionData);

        $details = $this->extractCarDetails($dto, $sessionData);

        $refCarCatId = $sessionData['ref_car_cat_id'] ?? $dto->ref_car_cat_id;
        $refCarCat = $refCarCatId ? RefCarCat::findFirstById($refCarCatId) : null;

        // Использование внутренних методов для единого обращения
        $countryId = $this->resolveCountryId($dto, $sessionData, $auth);
        $vehicleType = $this->resolveVehicleType($dto, $refCarCat);
        $vin = $this->resolveVin($dto);

        $volume = (float)$dto->volume;
        $sum = 0.0;
        $refCarTypeId = null;
        $calculateMethod = $dto->calculate_method;

        if ($refCarCat) {
            $volume = $this->getCarVolume(
                $refCarCat,
                $details['category_code'],
                $details['engine_capacity'],
                $details['permissible_max_weight'],
                $details['max_power_measure'],
                $vehicleType
            );

            $carValue = $this->getCarPriceValue($volume, $vehicleType, $refCarCat);

            $pay = $this->calculationPaySum(
                $calculateMethod,
                $sessionData['operation_date'] ?? $dto->date_import,
                $volume,
                $carValue,
                $details['is_truck'],
                $details['is_electric'],
                $details['temp_import'],
                $details['kap_log_id'],
                $dto->ref_country_import,
                time(),
                $profile->tr->approve,
                $auth
            );

            $sum = (float)($pay['sum'] ?? 0);

            if ($auth && $auth->isClient() && isset($pay['calculate_method'])) {
                $calculateMethod = $pay['calculate_method'];
            }

            $refCarTypeId = (int)($refCarCat->car_type ?? 0);
            $vehicleType = $this->mapVehicleType($refCarTypeId, $refCarCat->id, $vehicleType);

            if (in_array($refCarTypeId, [1, 3, 4, 5, 6], true)) {
                $details['is_truck'] = 0;
            }
        } elseif ($details['engine_capacity'] && $details['permissible_max_weight']) {
            $volume = (float)$details['engine_capacity'];
        }

        if ($refCarTypeId === 6) {
            $vehicleType = $dto->vehicle_type;
        }

        $vin = $this->finalizeVin($vin, $vehicleType, (string)$dto->vin);

        if ($vehicleType === 'AGRO') {
            $codeParts = explode('&', $vin);
            $vinIdCode = $codeParts[0] ?? '';
            $vinBodyCode = $codeParts[1] ?? '';
            $dto->id_code = ($dto->id_code !== '') ? $dto->id_code : $vinIdCode;
            $dto->body_code = ($dto->body_code !== '') ? $dto->body_code : $vinBodyCode;
            $vin = preg_replace('/[^\p{L}\p{N}]+/u', '', $vinIdCode) . '&' . $vinBodyCode;
        }

        return [
            'vin' => $vin,
            'id_code' => $dto->id_code,
            'body_code' => $dto->body_code,
            'calculate_method' => $calculateMethod,
            'cost' => $sum,
            'created' => time(),
            'date_import' => $this->toTimestamp($sessionData['operation_date'] ?? $dto->date_import),
            'electric_car' => $details['is_electric'],
            'epts_request_id' => $sessionData['epts_request_id'] ?? 0,
            'first_reg_date' => $this->toTimestamp($sessionData['initial_registration_date'] ?? null),
            'kap_log_id' => $details['kap_log_id'] ?? 0,
            'profile_id' => (int)$profile->id,
            'ref_car_cat' => $refCarCatId,
            'ref_car_type_id' => $refCarTypeId,
            'ref_country' => $countryId,
            'ref_country_import' => $dto->ref_country_import,
            'ref_st_type' => $details['is_truck'],
            'vehicle_type' => $vehicleType,
            'volume' => ($volume > 0) ? $volume : $dto->volume,
            'year' => $sessionData['year'] ?? $dto->year,
        ];
    }

    private function enrichSessionData(CarCreateDto $dto, User $user, ?string $type, bool $isMod, ?User $auth): array
    {
        if ($isMod) return [];

        $mode = (string)$this->session->get('CAR_TYPE');
        $vin = (string)$dto->vin;

        if ($mode === 'CAR' && $type !== 'edit') {
            return $this->getCarDataFromStorage($vin) ?: $this->getCarData($user, $vin, $auth);
        }

        if ($type === 'edit') {
            return $this->getCarDataFromStorage($vin);
        }

        return [];
    }

    private function resolveVin(CarCreateDto $dto): string
    {
        $mode = (string)$this->session->get('CAR_TYPE');
        $vType = ($mode === 'CAR') ? 'CAR' : (($mode === 'TRAC') ? 'AGRO' : (string)($dto->vehicle_type ?? 'CAR'));

        if ($vType === 'AGRO' && $dto->id_code && $dto->body_code) {
            return $this->vinService->sanitize((string)$dto->id_code) . '&' . $dto->body_code;
        }

        return mb_strtoupper((string)$dto->vin);
    }

    private function resolveVehicleType(CarCreateDto $dto, $refCarCat): string
    {
        $mode = (string)$this->session->get('CAR_TYPE');
        $vehicleType = ($mode === 'TRAC') ? 'AGRO' : 'CAR';

        if ($refCarCat) {
            if (in_array($refCarCat->car_type, [2], true)) {
                $vehicleType = 'CARGO';
            } elseif (in_array($refCarCat->car_type, [1, 3], true)) {
                $vehicleType = 'PASSENGER';
            } elseif (in_array($refCarCat->car_type, [4, 5], true)) {
                $vehicleType = 'AGRO';
            }

            if ($refCarCat->car_type == 6) {
                $vehicleType = $dto->vehicle_type;
            }
        }

        return $vehicleType;
    }

    private function resolveCountryId(CarCreateDto $dto, array $sessionData, ?User $auth): ?int
    {
        $countryId = $sessionData['ref_country_id'] ?? $dto->ref_country_id;
        if ($auth && ($auth->isSuperModerator() || $auth->isAdminSoft())) {
            if (empty($countryId)) return 201;
        }
        return $countryId ? (int)$countryId : null;
    }

    private function extractCarDetails(CarCreateDto $dto, array $sessionData): array
    {
        return [
            'category_code' => (string)($sessionData['category_code'] ?? ''),
            'permissible_max_weight' => $sessionData['permissible_max_weight'] ?? $dto->volume,
            'engine_capacity' => $sessionData['engine_capacity'] ?? $dto->volume,
            'max_power_measure' => $sessionData['max_power_measure'] ?? $dto->volume,
            'is_truck' => (int)($sessionData['is_truck'] ?? $dto->semi_truck),
            'is_electric' => (int)($sessionData['is_electric'] ?? $dto->is_electric),
            'temp_import' => (bool)($sessionData['is_temporary_importation'] ?? false),
            'kap_log_id' => $sessionData['kap_log_id'] ?? null,
        ];
    }

    private function mapVehicleType(int $refCarTypeId, $refCarCatId, string $default): string
    {
        $typeMap = [
            Car::VEHICLE_TYPE_AGRO => [4, 5],
            Car::VEHICLE_TYPE_CARGO => [2],
            Car::VEHICLE_TYPE_PASSENGER => [1, 3],
        ];

        $catMap = [
            Car::VEHICLE_TYPE_AGRO => [13, 14],
            Car::VEHICLE_TYPE_CARGO => [3, 4, 5, 6, 7, 8],
            Car::VEHICLE_TYPE_PASSENGER => [1, 2, 9, 10, 11, 12],
        ];

        foreach ($typeMap as $type => $ids) {
            if (in_array($refCarTypeId, $ids, true)) return $type;
        }

        foreach ($catMap as $type => $ids) {
            if (in_array((int)$refCarCatId, $ids, true)) return $type;
        }

        return $default;
    }

    private function finalizeVin(string $vin, string $vehicleType, string $originalVin): string
    {
        if ($vehicleType === 'AGRO' && !str_contains($vin, '&')) {
            $parts = explode('&', $originalVin);
            return $this->vinService->sanitize((string)($parts[0] ?? '')) . '&' . ($parts[1] ?? '');
        }

        if (in_array($vehicleType, [Car::VEHICLE_TYPE_CARGO, Car::VEHICLE_TYPE_PASSENGER], true)) {
            if (mb_strlen($vin) > 17) return $originalVin;
        }

        return $vin;
    }

    private function toTimestamp($date): int
    {
        if (!$date) return 0;
        return is_numeric($date) ? (int)$date : strtotime((string)$date);
    }

    /**
     * @throws AppException
     */
    private function loadCarProfile(Car $car, $authUser): Profile
    {
        $profile = Profile::findFirstById((int)$car->profile_id);
        return $this->carValidator->assertProfileAccessible($profile, $authUser);
    }

    /**
     * @throws AppException
     */
    private function storeCar(Car $car, array $data, ?User $auth = null): Car
    {
        $this->carValidator->assert($data, $auth);
        $car->assign($data);

        if ($car->save() === false) {
            $errors = implode(', ', array_map(static fn($m) => $m->getMessage(), $car->getMessages()));
            throw new AppException('Ошибка сохранения ТС: ' . $errors);
        }

        return $car;
    }

    private function attachIntegrationData(array $data): array
    {
        $sessionData = $this->carSessionService->getCarData();
        if (!empty($sessionData)) {
            $sessionData['volume'] = $data['volume'] ?? ($sessionData['volume'] ?? null);
        }
        $data['integration_data'] = $sessionData;
        return $data;
    }

    private function cleanupCarFiles(Car $car, Profile $profile): void
    {
        $types = ['digitalpass' => 'epts_', 'spravka_epts' => 'spravka_'];

        foreach ($types as $type => $prefix) {
            $fileRecord = File::findFirst([
                "conditions" => "profile_id = :pid: AND visible = 1 AND type = :type:",
                "bind" => ["pid" => $profile->id, "type" => $type]
            ]);

            if ($fileRecord) {
                $filePath = APP_PATH . "/private/docs/{$fileRecord->id}.{$fileRecord->ext}";
                @unlink(APP_PATH . "/private/docs/epts_pdf/{$profile->id}/{$prefix}{$car->vin}.pdf");

                $remaining = glob(APP_PATH . "/private/docs/epts_pdf/{$profile->id}/{$prefix}*.pdf");
                if (count($remaining) > 0) {
                    exec("gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile={$filePath} " . implode(' ', $remaining));
                } else {
                    if (file_exists($filePath)) @unlink($filePath);
                    $fileRecord->delete();
                }
            }
        }
    }
}