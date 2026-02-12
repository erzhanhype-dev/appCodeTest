<?php

namespace App\Services\OffsetFund;

use App\Exceptions\AppException;
use App\Helpers\LogTrait;
use App\Helpers\TransactionTrait;
use App\Repositories\OffsetFundRepository;
use App\Services\OffsetFund\Dto\CreateOffsetFundDto;
use Exception;
use OffsetFund;
use OffsetFundCar;
use OffsetFundGoods;
use Phalcon\Paginator\RepositoryInterface;
use RefCarCat;
use RefCountry;
use RefFund;
use RefFundKeys;
use RefTnCode;
use User;

class OffsetFundService
{
    use LogTrait, TransactionTrait;

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

        return $builder->getQuery()->execute();
    }

    /**
     * @throws AppException
     */
    public function getOffsetFundGoods(int $id)
    {
        $offset_fund = OffsetFund::findFirst($id);

        if (!$offset_fund) {
            throw new AppException("Заявка не найдена");
        }

        $builder = $offset_fund->getModelsManager()->createBuilder()
            ->from(['c' => OffsetFundGoods::class])
            ->where('c.offset_fund_id = :id:', ['id' => $id])
            ->orderBy('c.id DESC');

        return $builder->getQuery()->execute();
    }

    /**
     * Подготовка данных для формы создания (для newAction)
     * Возвращает массив переменных для View
     * @throws AppException
     * @throws Exception
     */
    public function prepareCreateForm($user, array $query_params): array
    {
        $this->checkFundingAvailability();

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

        $entity_type = $query_params['object'] ?? 'car';
        $type = $query_params['type'] ?? 'INS';
        $total_value = $query_params['total_value'] ?? '';
        $amt_float = (float)str_replace(',', '.', $total_value);
        $ref_fund_key_id = isset($query_params['ref_fund_key_id']) ? (int)$query_params['ref_fund_key_id'] : null;
        $start_date = $query_params['period_start_at'] ?? null;
        $end_date = $query_params['period_end_at'] ?? null;

        if ($type === 'INS') {
            $ref_country = RefCountry::find(["conditions" => "id = 71"]);
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

        $ref_tn_code = [];
        $ref_car_cat = [];

        if ($entity_type === 'goods') {
            $ref_tn_code = RefTnCode::find([
                "conditions" => "code IN ({code:array})",
                "bind" => ["code" => OffsetFund::GOODS_TN_CODES]
            ]);
        } elseif ($entity_type === 'car') {
            $ref_car_cat = RefCarCat::find();
        }

        $limit_obj = null;
        $warning_message = null;
        $error_message = null;

        if ($ref_fund_key_id) {
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
        return $this->runInTransaction(function () use ($dto, $user) {
            $this->checkFundingAvailability();
            $this->validateCreateFundDtoRequired($dto);

            $totalValue = (float)number_format($dto->total_value, 3, '.', '');

            if ($dto->ref_fund_key_id > 0) {
                $this->checkOffsetFundLimit($user, $dto->ref_fund_key_id, $totalValue);
            }

            $offsetFund = new OffsetFund();
            $offsetFund->user_id = $dto->user_id;
            $offsetFund->ref_fund_key_id = $dto->ref_fund_key_id;
            $offsetFund->total_value = $totalValue;
            $offsetFund->type = $dto->type;
            $offsetFund->entity_type = $dto->entity_type;
            $offsetFund->status = OffsetFund::STATUS_NEW;
            $offsetFund->is_funded = 1;
            $offsetFund->period_start_at = $dto->period_start_at;
            $offsetFund->period_end_at = $dto->period_end_at;
            $offsetFund->created_at = time();

            if (!$offsetFund->save()) {
                $messages = [];
                foreach ($offsetFund->getMessages() as $message) {
                    $messages[] = (string)$message;
                }

                $details = $messages ? ': ' . implode('; ', $messages) : '';
                throw new AppException('Не удалось создать заявку' . $details);
            }

            return $offsetFund;
        }, 'Ошибка транзакции при создании заявки');
    }

    /**
     * Удаление заявки и всех связанных сущностей
     * @throws AppException
     */
    public function delete(int $fund_id, User $user): void
    {
        $this->runInTransaction(function () use ($fund_id, $user) {
            $offset_fund = OffsetFund::findFirst($fund_id);

            if (!$offset_fund) {
                throw new AppException("Заявка #{$fund_id} не найдена.");
            }

            if ((int)$offset_fund->user_id !== (int)$user->id) {
                throw new AppException("У вас нет прав на удаление этой заявки.");
            }

            if ($offset_fund->status !== OffsetFund::STATUS_NEW) {
                throw new AppException("Нельзя удалить заявку в статусе '{$offset_fund->getStatusName()}'.");
            }

            foreach ($offset_fund->cars as $car) {
                if (!$car->delete()) {
                    throw new AppException("Ошибка при удалении машины VIN {$car->vin}");
                }
            }

            foreach ($offset_fund->goods as $goods) {
                if (!$goods->delete()) {
                    throw new AppException("Ошибка при удалении товара #{$goods->id}");
                }
            }

            if (!$offset_fund->delete()) {
                throw new AppException("Ошибка при удалении заявки");
            }

            return null;
        }, 'Ошибка транзакции при удалении заявки');
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
     * @throws AppException
     */
    private function checkOffsetFundLimit($user, $ref_fund_key_id, $total_value): void
    {
        $limit_obj = $this->repository->findLimit($ref_fund_key_id, $user->idnum);
        if (!$limit_obj) {
            throw new AppException('Лимит для данной категории отсутствует.');
        }

        $offset_fund_total_values = $this->repository->getUsedLimitValue((int)$ref_fund_key_id, (int)$user->id);
        $limit_value = (float)$limit_obj->value;
        $available_value = $limit_value - (float)$offset_fund_total_values;

        if ($available_value < 0) {
            $available_value = 0;
        }

        if ((float)$total_value > $limit_value) {
            throw new AppException("Лимит превышен! Доступно: " . $available_value);
        }

        if ($limit_value < (float)$offset_fund_total_values) {
            throw new AppException("Лимит превышен! Доступно: " . $available_value);
        }
    }

    /**
     * @throws AppException
     */
    private function validateCreateFundDtoRequired(CreateOffsetFundDto $dto): void
    {
        if ((int)$dto->user_id <= 0) {
            throw new AppException('Не заполнено обязательное поле: user_id');
        }

        if ((int)$dto->ref_fund_key_id <= 0) {
            throw new AppException('Не заполнено обязательное поле: ref_fund_key_id');
        }

        if (!isset($dto->total_value) || (float)$dto->total_value <= 0) {
            throw new AppException('Сумма или вес должны быть больше нуля.');
        }

        if ((float)$dto->total_value > 9999999999.99) {
            throw new AppException('Введенное значение слишком велико.');
        }

        if (trim((string)$dto->type) === '') {
            throw new AppException('Не заполнено обязательное поле: type');
        }

        if (trim((string)$dto->entity_type) === '') {
            throw new AppException('Не заполнено обязательное поле: entity_type');
        }

        if (empty($dto->period_start_at) || (int)$dto->period_start_at <= 0) {
            throw new AppException('Необходимо указать корректную дату начала периода.');
        }

        if (empty($dto->period_end_at) || (int)$dto->period_end_at <= 0) {
            throw new AppException('Необходимо указать корректную дату окончания периода.');
        }

        if ((int)$dto->period_end_at < (int)$dto->period_start_at) {
            throw new AppException('Дата окончания периода не может быть раньше даты начала.');
        }
    }
}
