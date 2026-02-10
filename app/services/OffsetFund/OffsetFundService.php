<?php

namespace App\Services\OffsetFund;

use App\Exceptions\AppException;
use App\Repositories\OffsetFundRepository;
use App\Services\OffsetFund\Dto\CreateOffsetFundDto;
use OffsetFund;
use OffsetFundCar;
use Phalcon\Paginator\RepositoryInterface;
use RefCountry;
use RefFund;
use RefFundKeys;
use RefTnCode;
use RefCarCat;
use Exception;
use Phalcon\Paginator\Adapter\QueryBuilder;
use User;

class OffsetFundService
{
    protected OffsetFundRepository $repository;

    public function __construct(OffsetFundRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Получение списка заявок с пагинацией (для indexAction)
     */
    public function getPaginator(array $filters, int $limit, int $page): RepositoryInterface
    {
        $paginator_query = $this->repository->search($filters, $limit, $page);
        return $paginator_query->paginate();
    }

    /**
     * Получение данных одной заявки с пагинацией машин (для viewAction)
     * @throws AppException
     */
    public function getOffsetFundCars(int $id)
    {
        $offset_fund = OffsetFund::findFirst($id);

        if (!$offset_fund) {
            throw new AppException("Заявка не найдена");
        }

        $builder = $offset_fund->getModelsManager()->createBuilder()
            ->from(['c' => OffsetFundCar::class])
            ->where('c.offset_fund_id = :id:', ['id' => $id])
            ->orderBy('c.id DESC');

        $cars = $builder->getQuery()->execute();

        return $cars;
    }

    /**
     * Подготовка данных для формы создания (для newAction)
     * Возвращает массив переменных для View
     * @throws AppException
     * @throws Exception
     */
    public function prepareCreateForm($user, array $query_params): array
    {
        // 1. Проверка доступности финансирования
        $this->checkFundingAvailability();

        // 2. Проверка наличия базовых лимитов у пользователя
        $ref_fund = RefFund::findFirst([
            "conditions" => "year = :year: AND idnum = :idnum:",
            "bind" => [
                "year" => date('Y'),
                "idnum" => $user->idnum
            ]
        ]);

        if (!$ref_fund) {
            throw new AppException("Лимиты отсутствуют");
        }

        // 3. Сбор параметров из GET запроса
        $entity_type = $query_params['object'] ?? 'car'; // по умолчанию 'car', так безопаснее
        $type = $query_params['type'] ?? 'INS';
        $total_value = $query_params['total_value'] ?? '';
        $amt_float = (float)str_replace(',', '.', $total_value);
        $ref_fund_key_id = isset($query_params['ref_fund_key_id']) ? (int)$query_params['ref_fund_key_id'] : null;
        $start_date = $query_params['period_start_at'] ?? null;
        $end_date = $query_params['period_end_at'] ?? null;

        // 4. Логика выборки справочников (INS vs EXP)
        if ($type == 'INS') {
            $ref_country = RefCountry::find(["conditions" => "id = 71"]); // Казахстан?
            $ref_fund_keys = RefFundKeys::find([
                "conditions" => "name NOT IN ('START_EXP') AND type = 'INS' AND entity_type = :entity_type:",
                "bind" => ["entity_type" => $entity_type]
            ]);
        } else {
            $ref_country = RefCountry::find(["conditions" => "id <> 71 AND id NOT IN (1, 201)"]);
            $ref_fund_keys = RefFundKeys::find([
                "conditions" => "name NOT IN ('START_EXP') AND type = 'EXP' AND entity_type = :entity_type:",
                "bind" => ["entity_type" => $entity_type]
            ]);
        }

        // 5. Логика специфичная для типа сущности (Goods vs Car)
        $ref_tn_code = [];
        $ref_car_cat = [];

        if ($entity_type == 'goods') {
            $ref_tn_code = RefTnCode::find([
                "conditions" => "code IN ({code:array})",
                "bind" => ["code" => OffsetFund::GOODS_TN_CODES]
            ]);
        } elseif ($entity_type == 'car') {
            $ref_car_cat = RefCarCat::find();
        }

        // 6. Проверка конкретного лимита, если выбран ключ
        $limit_obj = null;
        $warning_message = null;
        $error_message = null;

        if($ref_fund_key_id) {
            $this->checkOffsetFundLimit($user, $ref_fund_key_id, $amt_float);
        }

        return [
            "ref_country" => $ref_country,
            "ref_fund_keys" => $ref_fund_keys,
            "ref_tn_code" => $ref_tn_code,
            "ref_car_cat" => $ref_car_cat,
            "entity_type" => $entity_type,
            "period_start_at" => $start_date,
            "period_end_at" => $end_date,
            "type" => $type,
            "total_value" => $total_value,
            "limit_obj" => $limit_obj,
            "warning_msg" => $warning_message,
            "error_msg" => $error_message
        ];
    }

    /**
     * Создание новой заявки (для addAction)
     * @throws AppException
     */
    public function createFund(CreateOffsetFundDto $dto, User $user): OffsetFund
    {
        // 1. Проверка доступности
        $this->checkFundingAvailability();

        // 2. Валидация значений DTO
        if ($dto->total_value <= 0) {
            throw new AppException("Сумма или вес должны быть больше нуля.");
        }
        if ($dto->total_value > 9999999999.99) {
            throw new AppException("Введенное значение слишком велико.");
        }
        if (empty($dto->period_start_at) || empty($dto->period_end_at)) {
            throw new AppException("Необходимо указать даты.");
        }

        $total_value = number_format($dto->total_value, 3, '.', '');

        if ($dto->ref_fund_key_id) {
            $this->checkOffsetFundLimit($user, $dto->ref_fund_key_id, $dto->total_value);
        }

        $offset_fund = new OffsetFund();
        $offset_fund->user_id = $dto->user_id;
        $offset_fund->ref_fund_key_id = $dto->ref_fund_key_id;
        $offset_fund->total_value = $total_value;
        $offset_fund->type = $dto->type;
        $offset_fund->entity_type = $dto->entity_type; // Уже в UpperCase из DTO
        $offset_fund->status = OffsetFund::STATUS_NEW;
        $offset_fund->is_funded = 1;
        $offset_fund->period_start_at = $dto->period_start_at;
        $offset_fund->period_end_at = $dto->period_end_at;
        $offset_fund->created_at = time();

        if (!$offset_fund->save()) {
            $messages = [];
            foreach ($offset_fund->getMessages() as $message) {
                $messages[] = (string)$message;
            }
            throw new AppException(implode(', ', $messages));
        }

        return $offset_fund;
    }

    /**
     * Проверка глобального флага доступности финансирования
     * @throws AppException
     */
    private function checkFundingAvailability(): void
    {
        $fund_start_str = getenv('FUND_START_DATE');
        if ($fund_start_str && strtotime(date('Y-m-d')) < strtotime($fund_start_str)) {
            throw new AppException("Финансирование отключено");
        }
    }

    /**
     * Удаление заявки и всех связанных сущностей
     * @throws AppException
     * @throws \Exception
     */
    public function delete(int $fund_id, User $user): void
    {
        $offset_fund = OffsetFund::findFirst($fund_id);

        if (!$offset_fund) {
            throw new AppException("Заявка #{$fund_id} не найдена.");
        }

        if ($offset_fund->user_id !== $user->id) {
            throw new AppException("У вас нет прав на удаление этой заявки.");
        }

        if ($offset_fund->status !== OffsetFund::STATUS_NEW) {
            throw new AppException("Нельзя удалить заявку в статусе '{$offset_fund->getStatusName()}'.");
        }

        try {
            foreach ($offset_fund->cars as $car) {
                if (!$car->delete()) {
                    throw new AppException("Ошибка при удалении машины VIN {$car->vin}");
                }
            }

            if (!$offset_fund->delete()) {
                throw new AppException("Ошибка при удалении заявки");
            }

        } catch (\Exception $e) {
            throw new AppException($e->getMessage());
        }
    }

    /**
     * @throws AppException
     */
    private function checkOffsetFundLimit($user, $ref_fund_key_id, $total_value): void
    {
        $limit_obj = $this->repository->findLimit($ref_fund_key_id, $user->idnum);
        if (!$limit_obj) {
            throw new AppException('Лимит для данной категории отсутствует.');
        }
        $offset_fund_total_values = 0.0;

        $funds = OffsetFund::find([
            'columns' => 'id, total_value',
            'conditions' => 'ref_fund_key_id = :k: AND user_id = :u:',
            'bind' => ['k' => $ref_fund_key_id, 'u' => $user->id],
        ]);

        foreach ($funds as $f) {
            $offset_fund_total_values += (float)str_replace(',', '.',  $f->total_value);
        }

        $limit_value = $limit_obj->value;

        $available_value = $limit_value - $offset_fund_total_values;

        if($available_value < 0){
            $available_value = 0;
        }

        if($total_value > $limit_value){
            throw new AppException("Лимит превышен! Доступно: " . $available_value);
        }

        if($limit_value < $offset_fund_total_values){
            throw new AppException("Лимит превышен! Доступно: " . $available_value);
        }
    }
}