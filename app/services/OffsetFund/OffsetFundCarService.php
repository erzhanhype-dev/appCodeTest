<?php

namespace App\Services\OffsetFund;

use App\Exceptions\AppException;
use App\Repositories\OffsetFundRepository;
use App\Services\Car\CarCalculationService;
use App\Services\Car\CarIntegrationDataService;
use App\Services\OffsetFund\Dto\CreateOffsetFundCarDto;
use OffsetFund;
use OffsetFundCar;
use RefCarCat;
use RefCarType;
use RefCountry;
use User;

class OffsetFundCarService
{
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
    public function getFundOrFail(int $id): OffsetFund
    {
        $fund = OffsetFund::findFirst($id);
        if (!$fund) {
            throw new AppException('Заявка не найдена');
        }

        return $fund;
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
     * Подготовка данных для форм (New и Edit)
     * @throws AppException
     */
    public function prepareFormData(int $fund_id, array $query_params, ?int $car_id = null): array
    {
        $offset_fund = $this->getFundOrFail($fund_id);
        $ref_fund_key = $offset_fund->ref_fund_key;
        $is_special_machinery = $ref_fund_key && $this->isAgroFundKey($ref_fund_key->name);

        $vehicle_type = $query_params['vehicle_type'] ?? 'UNKNOWN';
        if ($is_special_machinery) {
            $vehicle_type = 'AGRO';
        }

        // $user = User::findFirstById($offset_fund->user_id); // Не использовался в оригинале явно, но оставил для контекста
        $car = null;
        $integration_data = [];
        $id_code = '';
        $body_code = '';

        if ($car_id) {
            $car = $this->getCarOrFail($car_id);
            if ($car->offset_fund_id !== $fund_id) {
                throw new AppException("ТС не найден");
            }
            $vin = $car->vin;

            if($vehicle_type == 'AGRO'){
                $parts = explode('&', $car->vin);
                $id_code = $parts[0];
                $body_code = $parts[1];
                $vin = preg_replace('/[^\p{L}\p{N}]+/u', '', $id_code) . '&' . $body_code;
            }

            $current_data = [
                'vin' => $vin,
                'ref_car_cat_id' => $car->ref_car_cat_id,
                'ref_country_id' => $car->ref_country_id,
                'volume' => $car->volume,
                'date_import' => date('d.m.Y', $car->import_at),
                'production_year' => $car->manufacture_year,
                'is_truck' => $car->ref_st_type_id,
                'is_electric' => $car->is_electric,
                'vehicle_type' => $car->vehicle_type,
                'id_code' => $id_code,
                'body_code' => $body_code,
            ];
        } else {

            if($vehicle_type == 'AGRO'){
                $id_code = $query_params['id_code'] ?? '';
                $body_code = $query_params['body_code'] ?? '';
                $vin = preg_replace('/[^\p{L}\p{N}]+/u', '', $id_code) . '&' . $body_code;
            }else{
                $vin = $query_params['vin'] ?? '';
            }

            $current_data = [
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
                'vehicle_type' => $vehicle_type
            ];
        }

        if ($is_special_machinery) {
            $ref_car_cats = RefCarCat::find(["conditions" => "id IN (13, 14)"]);
            $ref_car_types = RefCarType::find(["conditions" => "id IN (4, 5)"]);
        } else {
            $ref_car_cats = RefCarCat::find(["conditions" => "id NOT IN (13, 14)"]);
            $ref_car_types = RefCarType::find(["conditions" => "id NOT IN (4, 5)"]);

            if (!$car_id && !empty($current_data['vin'])) {
                if (OffsetFundCar::findFirstByVin($current_data['vin'])) {
                    throw new AppException("ТС с таким VIN кодом уже существует.");
                }
            }
        }

        if (!empty($current_data['vin'])) {
            if (!empty($integration_data)) {
                $current_data['date_import'] = $integration_data['operation_date'] ?? $current_data['date_import'];
                $current_data['production_year'] = $integration_data['year'] ?? $current_data['production_year'];
                $current_data['ref_car_cat_id'] = $integration_data['ref_car_cat_id'] ?? $current_data['ref_car_cat_id'];
                $current_data['ref_country_id'] = $integration_data['ref_country_id'] ?? $current_data['ref_country_id'];
                $current_data['is_electric'] = $integration_data['is_electric'] ?? $current_data['is_electric'];
                $current_data['is_truck'] = $integration_data['is_truck'] ?? $current_data['is_truck'];

                $ref_car_cat_obj = RefCarCat::findFirstById($current_data['ref_car_cat_id']);
                $current_data['volume'] = $this->calculation_service->getCarVolume(
                    $ref_car_cat_obj,
                    null,
                    $integration_data['engine_capacity'] ?? 0,
                    $integration_data['permissible_max_weight'] ?? 0,
                    $integration_data['max_power_measure'] ?? 0,
                    $current_data['vehicle_type']
                );
            }
        }

        return [
            'offset_fund' => $offset_fund,
            'car' => $car_id ? $car : null,
            'countries' => RefCountry::find(['id NOT IN (1, 201)']),
            'ref_car_cat' => $ref_car_cats,
            'ref_car_type' => $ref_car_types,
            'is_special' => $is_special_machinery,
            'data' => $current_data
        ];
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
     * @throws \Exception
     */
    public function addCar(CreateOffsetFundCarDto $dto): OffsetFundCar
    {
        $car = new OffsetFundCar();
        $this->mapDtoToModel($dto, $car);
        $car->created_at = time();

        $offset_fund = $this->getFundOrFail((int)$car->offset_fund_id);
        $this->checkOffsetFundCarLimit($offset_fund, $car->volume);

        if (!$car->save()) {
            throw new AppException("Не удалось добавить ТС");
        }

        return $car;
    }

    /**
     * @throws AppException
     * @throws \Exception
     */
    public function updateCar(int $id, CreateOffsetFundCarDto $dto): OffsetFundCar
    {
        $car = $this->getCarOrFail($id);

        $this->checkOffsetFundCarLimit($car->offset_fund, $dto->volume, $car->id);
        $this->mapDtoToModel($dto, $car);

        if (!$car->save()) {
            throw new AppException("Не удалось сохранить ТС");
        }

        return $car;
    }

    /**
     * @throws \Exception
     */
    public function deleteCar(int $id): int
    {
        $car = $this->getCarOrFail($id);

        $fund_id = $car->offset_fund_id;

        if (!$car->delete()) {
            throw new AppException('Не удалось удалить ТС');
        }

        return $fund_id;
    }

    private function mapDtoToModel(CreateOffsetFundCarDto $dto, OffsetFundCar $car): void
    {
        $car->offset_fund_id = $dto->offset_fund_id;
        if($dto->ref_car_cat_id === 13 || $dto->ref_car_cat_id === 14){
            $vehicle_type = "AGRO";
            $car->vin = mb_strtoupper($dto->id_code . '&' . $dto->body_code);
        }else{
            $vehicle_type = $dto->vehicle_type;
            $car->vin = mb_strtoupper($dto->vin);
        }
        $car->ref_car_cat_id = $dto->ref_car_cat_id;
        $car->volume = number_format($dto->volume, 3, '.', '');
        $car->import_at = $dto->import_at;
        $car->manufacture_year = $dto->manufacture_year;
        $car->is_electric = $dto->is_electric;
        $car->ref_country_id = $dto->ref_country_id;
        $car->ref_st_type_id = $dto->ref_st_type_id;
        $car->vehicle_type = $vehicle_type;
    }

    /**
     * @throws AppException
     */
    public function checkOffsetFundCarLimit($offset_fund, $current_value = 0, $car_id = null): void
    {
        if (!$current_value) {
            return;
        }

        $offset_fund_cars_total_volume = $this->repository->getUsedCarVolume(
            (int) $offset_fund->id,
            $car_id !== null ? (int) $car_id : null
        );

        $available_value = (float) $offset_fund->total_value - $offset_fund_cars_total_volume;

        if ($available_value < 0) {
            $available_value = 0;
        }

        if ((float) $offset_fund->total_value < ($offset_fund_cars_total_volume + (float) $current_value)) {
            throw new AppException("Лимит превышен! Доступно: " . $available_value);
        }
    }

    private function isAgroFundKey(string $fundKeyName): bool
    {
        return str_contains($fundKeyName, 'TRACTOR') || str_contains($fundKeyName, 'COMBAIN');
    }
}