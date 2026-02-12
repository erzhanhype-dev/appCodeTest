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

    public static function fromRequest(RequestInterface $request, int $userId): self
    {
        $refFundKeyId = (int)$request->getPost('ref_fund_key_id', 'int');

        $rawTotalValue = (string)$request->getPost('total_value');
        $totalValue = (float)str_replace(',', '.', $rawTotalValue);

        $type = trim((string)$request->getPost('type', 'string'));
        $entityType = strtoupper(trim((string)$request->getPost('entity_type', 'string')));

        $startStr = trim((string)$request->getPost('period_start_at'));
        $endStr = trim((string)$request->getPost('period_end_at'));

        $periodStartAt = strtotime($startStr);
        $periodEndAt = strtotime($endStr);


        return new self(
            $userId,
            $refFundKeyId,
            $totalValue,
            $type,
            $entityType,
            $periodStartAt,
            $periodEndAt
        );
    }
}
