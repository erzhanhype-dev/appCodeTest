<?php

namespace App\Services\OffsetFund;

use App\Exceptions\AppException;
use App\Helpers\LogTrait;
use App\Helpers\TransactionTrait;
use App\Repositories\OffsetFundRepository;
use App\Services\OffsetFund\Dto\CreateOffsetFundGoodsDto;
use OffsetFund;
use OffsetFundGoods;
use Phalcon\Db\Enum;
use RefTnCode;

class OffsetFundGoodsService
{

    use LogTrait, TransactionTrait;

    public function __construct(
        protected OffsetFundRepository $repository,
    )
    {
    }

    /**
     * @throws AppException
     */
    public function addGoods(CreateOffsetFundGoodsDto $dto): OffsetFundGoods
    {
        return $this->runInTransaction(function () use ($dto) {
            $this->validateGoodsDtoRequired($dto);

            $goods = new OffsetFundGoods();
            $this->fillGoods($goods, $dto);
            $goods->created_at = time();

            $fund = $this->lockFundOrFail((int)$goods->offset_fund_id);
            $this->assertGoodsLimit($fund, (float)$goods->weight);

            if (!$goods->save()) {
                $messages = method_exists($goods, 'getMessages') ? $goods->getMessages() : [];
                $details = [];

                foreach ($messages as $m) {
                    $details[] = (string)$m;
                }

                $suffix = $details ? ': ' . implode('; ', $details) : '';
                throw new AppException('Не удалось добавить товар' . $suffix);
            }

            return $goods;
        }, 'Ошибка транзакции при добавлении товара');
    }

    /**
     * @throws AppException
     */
    public function updateGoods(int $id, CreateOffsetFundGoodsDto $dto): OffsetFundGoods
    {
        return $this->runInTransaction(function () use ($id, $dto) {
            $goods = $this->getGoodsOrFail($id);

            $targetFundId = $dto->offset_fund_id;
            $currentFundId = $goods->offset_fund_id;

            if ($targetFundId !== $currentFundId) {
                $this->lockFundOrFail(min($currentFundId, $targetFundId));
                $fund = $this->lockFundOrFail(max($currentFundId, $targetFundId));
            } else {
                $fund = $this->lockFundOrFail($targetFundId);
            }

            $this->assertGoodsLimit($fund, (float)$dto->weight, (int)$goods->id);

            $this->fillGoods($goods, $dto);

            if (!$goods->save()) {
                throw new AppException('Не удалось сохранить товар');
            }

            return $goods;
        }, 'Ошибка транзакции при сохранении товара');
    }

    /**
     * @throws AppException
     */
    public function deleteGoods(int $id): int
    {
        return $this->runInTransaction(function () use ($id) {
            $goods = $this->getGoodsOrFail($id);

            // Блокируем фонд для консистентности конкурентных операций
            $this->lockFundOrFail((int)$goods->offset_fund_id);

            $fundId = (int)$goods->offset_fund_id;

            if (!$goods->delete()) {
                throw new AppException('Не удалось удалить товар');
            }

            return $fundId;
        }, 'Ошибка транзакции при удалении товара');
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
    public function getGoodsOrFail(int $id): OffsetFundGoods
    {
        $goods = OffsetFundGoods::findFirst($id);
        if (!$goods) {
            throw new AppException('Товар не найден');
        }

        return $goods;
    }

    /**
     * Импорт товаров из CSV.
     * @throws AppException
     */
    public function importGoodsFromCsv(int $offsetFundId, string $tmpFilePath): array
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
                    $ref_tn_code = RefTnCode::findFirstByCode((string)$row[0]);
                    $dto = new CreateOffsetFundGoodsDto();
                    $dto->offset_fund_id = $offsetFundId;
                    $dto->ref_tn_code_id = $ref_tn_code ? $ref_tn_code->id : null;
                    $dto->ref_country_id = (int)$row[4];
                    $dto->weight = (float)$row[1];
                    $dto->basis = trim((string)$row[2]);

                    $basisAtRaw = trim((string)$row[3]);
                    $dto->basis_at = strtotime($basisAtRaw);

                    $this->validateGoodsDtoRequired($dto);

                    $goods = new OffsetFundGoods();
                    $this->fillGoods($goods, $dto);
                    $goods->created_at = time();

                    $this->assertGoodsLimit($fund, (float)$goods->weight);

                    if (!$goods->save()) {
                        $messages = method_exists($goods, 'getMessages') ? $goods->getMessages() : [];
                        $details = [];
                        foreach ($messages as $m) {
                            $details[] = (string)$m;
                        }
                        $suffix = $details ? ': ' . implode('; ', $details) : '';
                        throw new AppException('Не удалось добавить товар' . $suffix);
                    }

                    $created++;
                }  catch (\Throwable $e) {
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

    private function fillGoods(OffsetFundGoods $goods, CreateOffsetFundGoodsDto $dto): void
    {
        $goods->offset_fund_id = $dto->offset_fund_id;
        $goods->weight = number_format($dto->weight, 3, '.', '');
        $goods->ref_country_id = $dto->ref_country_id;
        $goods->ref_tn_code_id = $dto->ref_tn_code_id;
        $goods->basis_at = $dto->basis_at;
        $goods->basis = $dto->basis;
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

        return $this->getFundOrFail($fundId);
    }

    /**
     * @throws AppException
     */
    private function assertGoodsLimit(OffsetFund $fund, float $currentWeight, ?int $excludeGoodsId = null): void
    {
        if ($currentWeight <= 0) {
            return;
        }

        $used = (float)$this->repository->getUsedGoodsWeight((int)$fund->id, $excludeGoodsId);
        $total = (float)$fund->total_value;
        $available = max(0.0, $total - $used);

        if ($total < ($used + $currentWeight)) {
            throw new AppException('Лимит превышен! Доступно: ' . $available);
        }
    }

    /**
     * @throws AppException
     */
    private function validateGoodsDtoRequired(CreateOffsetFundGoodsDto $dto): void
    {
        if ((int)$dto->offset_fund_id <= 0) {
            throw new AppException('Не заполнено обязательное поле: offset_fund_id');
        }

        if ((int)$dto->ref_tn_code_id <= 0) {
            throw new AppException('Не заполнено обязательное поле: ref_tn_code_id');
        }

        if ((int)$dto->ref_country_id <= 0) {
            throw new AppException('Не заполнено обязательное поле: ref_country_id');
        }

        if (trim((string)$dto->basis) === '') {
            throw new AppException('Не заполнено обязательное поле: basis');
        }

        // Если по бизнес-логике вес обязателен
        if (!isset($dto->weight) || (float)$dto->weight <= 0) {
            throw new AppException('Не заполнено обязательное поле: weight (должно быть > 0)');
        }

        // Дата основания должна быть валидным timestamp (если используется в обязательной логике)
        if (!isset($dto->basis_at) || (int)$dto->basis_at <= 0) {
            throw new AppException('Некорректное значение поля: basis_at');
        }
    }
}
