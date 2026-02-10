<?php

namespace App\Services\OffsetFund\Dto;

use Phalcon\Http\RequestInterface;

class CreateOffsetFundDto
{
    public int $user_id;
    public int $ref_fund_key_id;
    public float $total_value;
    public string $type;
    public string $entity_type;
    public int $period_start_at;
    public int $period_end_at;

    public function __construct(
        int $user_id,
        int $ref_fund_key_id,
        float $total_value,
        string $type,
        string $entity_type,
        int $period_start_at,
        int $period_end_at
    ) {
        $this->user_id = $user_id;
        $this->ref_fund_key_id = $ref_fund_key_id;
        $this->total_value = $total_value;
        $this->type = $type;
        $this->entity_type = $entity_type;
        $this->period_start_at = $period_start_at;
        $this->period_end_at = $period_end_at;
    }

    /**
     * Фабричный метод для создания DTO из реквеста.
     * Здесь изолирована вся логика парсинга "грязных" данных.
     */
    public static function fromRequest(RequestInterface $request, int $userId): self
    {
        $rawTotalValue = $request->getPost('total_value');
        // Логика очистки числа перенесена сюда
        $totalValue = (float)str_replace(',', '.', $rawTotalValue);

        $startStr = $request->getPost('period_start_at');
        $endStr = $request->getPost('period_end_at');

        return new self(
            $userId,
            (int)$request->getPost('ref_fund_key_id', 'int'),
            $totalValue,
            $request->getPost('type', 'string'),
            strtoupper($request->getPost('entity_type', 'string')),
            $startStr ? strtotime($startStr) : 0,
            $endStr ? strtotime($endStr) : 0
        );
    }
}