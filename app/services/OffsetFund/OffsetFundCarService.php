<?php

namespace App\Services\OffsetFund;

use App\Exceptions\AppException;
use App\Helpers\LogTrait;
use App\Helpers\TransactionTrait;
use App\Repositories\OffsetFundRepository;
use App\Services\Car\CarCalculationService;
use App\Services\Car\CarIntegrationDataService;
use App\Services\OffsetFund\Dto\CreateOffsetFundCarDto;
use OffsetFund;
use OffsetFundCar;
use Phalcon\Db\Enum;
use RefCarCat;
use RefCarType;
use RefCountry;
use User;

class OffsetFundCarService
{
    use LogTrait, TransactionTrait;

    protected OffsetFundRepository $repository;
    protected CarIntegrationDataService $integration_service;
    protected CarCalculationService $calculation_service;
    protected OffsetFundService $offset_fund_service;

    public function __construct(
        OffsetFundRepository      $repository,
        CarIntegrationDataService $integration_service,
        CarCalculationService     $calculation_service,
        OffsetFundService         $offset_fund_service
    )
    {
        $this->repository = $repository;
        $this->integration_service = $integration_service;
        $this->calculation_service = $calculation_service;
        $this->offset_fund_service = $offset_fund_service;
    }

    /**
     * @throws AppException
     */
    public function prepareNewFormData(int $fund_id, User $current_user, array $query_params): array
    {
        $view_data = $this->prepareFormData($fund_id, $query_params);
        $this->assertLimitObj($view_data['data'] ?? []);
        return $view_data;
    }

    /**
     * @throws AppException
     */
    public function assertLimitObj(array $data): void
    {
        if (array_key_exists('limit_obj', $data) && !$data['limit_obj']) {
            throw new AppException('Лимит для данной категории отсутствует.');
        }
    }

    /**
     * @throws AppException
     */
    public function prepareFormData(int $fund_id, array $query_params, ?int $car_id = null): array
    {
        $offset_fund = $this->getFundOrFail($fund_id);
        $is_special_machinery = $this->isSpecialMachineryFund($offset_fund);
        $vehicle_type = $is_special_machinery
            ? 'AGRO'
            : ($query_params['vehicle_type'] ?? 'UNKNOWN');

        $user = User::findFirstById($offset_fund->user_id);
        $auth = User::getUserBySession();

        $car = null;
        $current_data = $car_id
            ? $this->buildCurrentDataFromExistingCar($car_id, $fund_id, $vehicle_type, $car)
            : $this->buildCurrentDataFromQuery($query_params, $vehicle_type);

        [$ref_car_cats, $ref_car_types] = $this->getReferenceData($is_special_machinery);

        if (!$is_special_machinery && !$car_id && !empty($current_data['vin'])) {
            $this->assertVinIsUnique($current_data['vin']);
        }

        if (!empty($current_data['vin'])) {
            if ($car_id) {
                $integration_data = $this->integration_service->getCarDataFromStorage($current_data['vin']);
            } else {
                $integration_data = $this->integration_service->getCarData($user, $current_data['vin'], $auth);
            }
            $current_data = $this->mergeIntegrationData($current_data, $integration_data);
        }

        return [
            'offset_fund' => $offset_fund,
            'car' => $car,
            'countries' => RefCountry::find(['conditions' => 'id NOT IN (1, 201)']),
            'ref_car_cat' => $ref_car_cats,
            'ref_car_type' => $ref_car_types,
            'is_special' => $is_special_machinery,
            'data' => $current_data,
        ];
    }

    private function isSpecialMachineryFund($offset_fund): bool
    {
        $ref_fund_key = $offset_fund->ref_fund_key;
        return $ref_fund_key && $this->isAgroFundKey($ref_fund_key->name);
    }

    private function buildCurrentDataFromExistingCar(int $car_id, int $fund_id, string $vehicle_type, ?OffsetFundCar &$car): array
    {
        $car = $this->getCarOrFail($car_id);

        if ((int)$car->offset_fund_id !== $fund_id) {
            throw new AppException("ТС не найден");
        }

        $vin = (string)$car->vin;
        $id_code = '';
        $body_code = '';

        if ($vehicle_type === 'AGRO') {
            [$id_code, $body_code] = $this->splitAgroVin($vin);
            $vin = $this->buildAgroVin($id_code, $body_code);
        }

        return [
            'vin' => $vin,
            'ref_car_cat_id' => $car->ref_car_cat_id,
            'ref_country_id' => $car->ref_country_id,
            'volume' => $car->volume,
            'date_import' => $car->import_at ? date('d.m.Y', (int)$car->import_at) : null,
            'production_year' => $car->manufacture_year,
            'is_truck' => $car->ref_st_type_id,
            'is_electric' => (bool)$car->is_electric,
            'vehicle_type' => $car->vehicle_type ?: $vehicle_type,
            'id_code' => $id_code,
            'body_code' => $body_code,
        ];
    }

    private function buildCurrentDataFromQuery(array $query_params, string $vehicle_type): array
    {
        $id_code = '';
        $body_code = '';
        $vin = '';

        if ($vehicle_type === 'AGRO') {
            $id_code = (string)($query_params['id_code'] ?? '');
            $body_code = (string)($query_params['body_code'] ?? '');
            $vin = $this->buildAgroVin($id_code, $body_code);
        } else {
            $vin = (string)($query_params['vin'] ?? '');
        }

        return [
            'vin' => $vin,
            'id_code' => $id_code,
            'body_code' => $body_code,
            'volume' => '0',
            'is_electric' => false,
            'is_truck' => false,
            'date_import' => null,
            'production_year' => null,
            'ref_car_cat_id' => null,
            'ref_country_id' => null,
            'vehicle_type' => $vehicle_type,
        ];
    }

    private function getReferenceData(bool $is_special_machinery): array
    {
        if ($is_special_machinery) {
            $ref_car_cats = RefCarCat::find(["conditions" => "id IN (13, 14)"]);
            $ref_car_types = RefCarType::find(["conditions" => "id IN (4, 5)"]);
        } else {
            $ref_car_cats = RefCarCat::find(["conditions" => "id NOT IN (13, 14)"]);
            $ref_car_types = RefCarType::find(["conditions" => "id NOT IN (4, 5)"]);
        }

        return [$ref_car_cats, $ref_car_types];
    }

    private function assertVinIsUnique(string $vin): void
    {
        if (OffsetFundCar::findFirstByVin($vin)) {
            throw new AppException("ТС с таким VIN кодом уже существует.");
        }
    }

    private function mergeIntegrationData(array $current_data, array $integration_data): array
    {
        if (empty($integration_data)) {
            return $current_data;
        }

        $current_data['date_import'] = $integration_data['operation_date'] ?? $current_data['date_import'];
        $current_data['production_year'] = $integration_data['year'] ?? $current_data['production_year'];
        $current_data['ref_car_cat_id'] = $integration_data['ref_car_cat_id'] ?? $current_data['ref_car_cat_id'];
        $current_data['ref_country_id'] = $integration_data['ref_country_id'] ?? $current_data['ref_country_id'];
        $current_data['is_electric'] = $integration_data['is_electric'] ?? $current_data['is_electric'];
        $current_data['is_truck'] = $integration_data['is_truck'] ?? $current_data['is_truck'];

        $ref_car_cat_obj = null;
        if (!empty($current_data['ref_car_cat_id'])) {
            $ref_car_cat_obj = RefCarCat::findFirstById($current_data['ref_car_cat_id']);
        }

        $current_data['volume'] = $this->calculation_service->getCarVolume(
            $ref_car_cat_obj,
            null,
            $integration_data['engine_capacity'] ?? 0,
            $integration_data['permissible_max_weight'] ?? 0,
            $integration_data['max_power_measure'] ?? 0,
            $current_data['vehicle_type']
        );

        return $current_data;
    }

    private function splitAgroVin(string $vin): array
    {
        $parts = explode('&', $vin, 2);
        $id_code = $parts[0] ?? '';
        $body_code = $parts[1] ?? '';
        return [$id_code, $body_code];
    }

    private function buildAgroVin(string $id_code, string $body_code): string
    {
        $clean_id_code = preg_replace('/[^\p{L}\p{N}]+/u', '', $id_code) ?? '';
        return $clean_id_code . '&' . $body_code;
    }


    /**
     * @throws AppException
     */
    public function getCarOrFail(int $id): OffsetFundCar
    {
        $car = OffsetFundCar::findFirst($id);
        if (!$car) {
            throw new AppException('ТС не найден');
        }

        return $car;
    }

    /**
     * @throws AppException
     */
    public function addCar(CreateOffsetFundCarDto $dto): OffsetFundCar
    {
        return $this->runInTransaction(function () use ($dto) {
            $this->validateCarDtoRequired($dto);

            $car = new OffsetFundCar();
            $this->mapDtoToModel($dto, $car);
            $car->created_at = time();

            $fund = $this->lockFundOrFail((int)$car->offset_fund_id);
            $this->checkOffsetFundCarLimit($fund, (float)$car->volume);

            if (!$car->save()) {
                $messages = method_exists($car, 'getMessages') ? $car->getMessages() : [];
                $details = [];

                foreach ($messages as $m) {
                    $details[] = (string)$m;
                }

                $suffix = $details ? ': ' . implode('; ', $details) : '';
                throw new AppException('Не удалось добавить ТС' . $suffix);
            }

            return $car;
        }, 'Ошибка транзакции при добавлении ТС');
    }

    /**
     * @throws AppException
     */
    public function updateCar(int $id, CreateOffsetFundCarDto $dto): OffsetFundCar
    {
        return $this->runInTransaction(function () use ($id, $dto) {
            $car = $this->getCarOrFail($id);

            $targetFundId = (int)$dto->offset_fund_id;
            $currentFundId = (int)$car->offset_fund_id;

            if ($targetFundId !== $currentFundId) {
                $this->lockFundOrFail(min($currentFundId, $targetFundId));
                $fund = $this->lockFundOrFail(max($currentFundId, $targetFundId));
            } else {
                $fund = $this->lockFundOrFail($targetFundId);
            }

            $this->checkOffsetFundCarLimit($fund, (float)$dto->volume, (int)$car->id);
            $this->mapDtoToModel($dto, $car);

            if (!$car->save()) {
                throw new AppException('Не удалось сохранить ТС');
            }

            return $car;
        }, 'Ошибка транзакции при сохранении ТС');
    }

    /**
     * @throws AppException
     */
    public function deleteCar(int $id): int
    {
        return $this->runInTransaction(function () use ($id) {
            $car = $this->getCarOrFail($id);

            $this->lockFundOrFail((int)$car->offset_fund_id);

            $fund_id = (int)$car->offset_fund_id;
            if (!$car->delete()) {
                throw new AppException('Не удалось удалить ТС');
            }

            return $fund_id;
        }, 'Ошибка транзакции при удалении ТС');
    }


    /**
     * Импорт товаров из CSV.
     * @throws AppException
     */
    public function importCarFromCsv(int $offsetFundId, string $tmpFilePath): array
    {
        return $this->runInTransaction(function () use ($offsetFundId, $tmpFilePath) {
            $fund = $this->lockFundOrFail($offsetFundId);

            if ((int)$fund->status !== (int)$fund::STATUS_NEW) {
                throw new AppException('Импорт доступен только для заявок в статусе "Новая заявка"');
            }

            $rows = $this->parseCsvFile($tmpFilePath);

            $created = 0;
            $skipped = 0;
            $errors = [];
            $hasLimitErrors = false;

            foreach ($rows as $index => $row) {
                $lineNo = $index + 2;

                try {
                    $basisAtRaw = trim((string)$row[4]);

                    $dto = new CreateOffsetFundCarDto();
                    $dto->offset_fund_id = $offsetFundId;
                    $dto->vin = (string)$row[0];
                    $dto->ref_car_cat_id = (int)$row[1];
                    $dto->volume = (float)$row[2];
                    $dto->manufacture_year = (int)$row[3];
                    $dto->import_at = strtotime($basisAtRaw);
                    $dto->ref_st_type_id = (int)$row[5];
                    $dto->is_electric = (int)$row[6];
                    $dto->ref_country_id = (int)$row[7];
                    $dto->vehicle_type = 'PASSENGER';


                    $this->validateCarDtoRequired($dto);

                    $car = new OffsetFundCar();

                    $this->mapDtoToModel($dto, $car);
                    $car->created_at = time();

                    $this->checkOffsetFundCarLimit($fund, $dto->volume);

                    if (!$car->save()) {
                        $messages = method_exists($car, 'getMessages') ? $car->getMessages() : [];
                        $details = [];
                        foreach ($messages as $m) {
                            $details[] = (string)$m;
                        }
                        $suffix = $details ? ': ' . implode('; ', $details) : '';
                        throw new AppException('Не удалось добавить ТС' . $suffix);
                    }

                    $created++;
                } catch (\Throwable $e) {
                    $skipped++;
                    $msg = $e->getMessage();
                    $errors[] = "Строка {$lineNo}: {$msg}";

                    if (mb_stripos($msg, 'Лимит превышен') !== false) {
                        $hasLimitErrors = true;
                    }
                }
            }

            return [
                'created' => $created,
                'skipped' => $skipped,
                'errors' => $errors,
                'has_limit_errors' => $hasLimitErrors,
            ];
        }, 'Ошибка транзакции при импорте CSV');
    }

    /**
     * @throws AppException
     */
    private function parseCsvFile(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new AppException('CSV файл недоступен для чтения');
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            throw new AppException('Не удалось открыть CSV файл');
        }

        try {
            // Попытка читать с BOM
            $header = fgetcsv($handle, 0, ';');
            if ($header === false) {
                throw new AppException('CSV файл пустой');
            }

            // Если разделитель не ;, пробуем ,
            if (count($header) < 2) {
                rewind($handle);
                $header = fgetcsv($handle, 0, ',');
                if ($header === false) {
                    throw new AppException('CSV файл пустой');
                }
                $delimiter = ',';
            } else {
                $delimiter = ';';
            }

            // Уберем BOM у первого заголовка
            $header[0] = preg_replace('/^\xEF\xBB\xBF/u', '', (string)$header[0]);

            $header = array_map(static fn($v) => trim((string)$v), $header);

            $rows = [];
            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                $allEmpty = true;
                foreach ($data as $cell) {
                    if (trim((string)$cell) !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if ($allEmpty) {
                    continue;
                }

                // выравниваем длину строки под заголовок
                if (count($data) < count($header)) {
                    $data = array_pad($data, count($header), '');
                }

                $assoc = array_combine($header, array_slice($data, 0, count($header)));
                if (!is_array($assoc)) {
                    throw new AppException('Ошибка разбора CSV строки');
                }

                $rows[] = array_slice($data, 0, count($header));
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function mapDtoToModel(CreateOffsetFundCarDto $dto, OffsetFundCar $car): void
    {
        $car->offset_fund_id = (int)$dto->offset_fund_id;

        if ((int)$dto->ref_car_cat_id === 13 || (int)$dto->ref_car_cat_id === 14) {
            $vehicle_type = 'AGRO';
            $car->vin = mb_strtoupper($dto->id_code . '&' . $dto->body_code);
        } else {
            $vehicle_type = $dto->vehicle_type;
            $car->vin = mb_strtoupper($dto->vin);
        }

        $car->ref_car_cat_id = (int)$dto->ref_car_cat_id;
        $car->volume = number_format((float)$dto->volume, 3, '.', '');
        $car->import_at = $dto->import_at;
        $car->manufacture_year = $dto->manufacture_year;
        $car->is_electric = (int)$dto->is_electric;
        $car->ref_country_id = (int)$dto->ref_country_id;
        $car->ref_st_type_id = (int)$dto->ref_st_type_id;
        $car->vehicle_type = $vehicle_type;
    }

    /**
     * @throws AppException
     */
    public function checkOffsetFundCarLimit($offset_fund, $current_value = 0, $car_id = null): void
    {
        if (!(float)$current_value) {
            return;
        }

        $offset_fund_cars_total_volume = $this->repository->getUsedCarVolume(
            (int)$offset_fund->id,
            $car_id !== null ? (int)$car_id : null
        );

        $available_value = (float)$offset_fund->total_value - (float)$offset_fund_cars_total_volume;
        if ($available_value < 0) {
            $available_value = 0;
        }

        if ((float)$offset_fund->total_value < ((float)$offset_fund_cars_total_volume + (float)$current_value)) {
            throw new AppException('Лимит превышен! Доступно: ' . $available_value);
        }
    }

    /**
     * @throws AppException
     */
    private function lockFundOrFail(int $fundId): OffsetFund
    {
        $row = $this->db()->fetchOne(
            'SELECT id FROM offset_funds WHERE id = :id FOR UPDATE',
            Enum::FETCH_ASSOC,
            ['id' => $fundId]
        );

        if (!$row) {
            throw new AppException('Заявка не найдена');
        }

        return $this->offset_fund_service->getFundOrFail($fundId);
    }

    private function isAgroFundKey(string $fundKeyName): bool
    {
        return str_contains($fundKeyName, 'TRACTOR') || str_contains($fundKeyName, 'COMBAIN');
    }

    /**
     * @throws AppException
     */
    private function validateCarDtoRequired(CreateOffsetFundCarDto $dto): void
    {
        if ((int)$dto->offset_fund_id <= 0) {
            throw new AppException('Не заполнено обязательное поле: offset_fund_id');
        }

        if ((int)$dto->ref_car_cat_id <= 0) {
            throw new AppException('Не заполнено обязательное поле: ref_car_cat_id');
        }

        if ((int)$dto->ref_country_id <= 0) {
            throw new AppException('Не заполнено обязательное поле: ref_country_id');
        }

        if (trim((string)$dto->manufacture_year) === '') {
            throw new AppException('Не заполнено обязательное поле: production_year');
        }

        if (trim((string)$dto->vehicle_type) === '') {
            throw new AppException('Не заполнено обязательное поле: vehicle_type');
        }

        // Хотя бы один идентификатор ТС должен быть заполнен
        $vin = trim((string)($dto->vin ?? ''));
        $idCode = trim((string)($dto->id_code ?? ''));
        $bodyCode = trim((string)($dto->body_code ?? ''));

        if ($vin === '' && $idCode === '' && $bodyCode === '') {
            throw new AppException('Заполните хотя бы одно поле: vin, id_code или body_code');
        }

        // Если volume обязателен по бизнес-логике
        if (!isset($dto->volume) || (float)$dto->volume <= 0) {
            throw new AppException('Не заполнено обязательное поле: volume (должно быть > 0)');
        }

        // Если import_at обязателен как валидная дата (когда не проставляется автоматически)
        if (isset($dto->import_at) && (int)$dto->import_at <= 0) {
            throw new AppException('Некорректное значение поля: import_at');
        }
    }
}
