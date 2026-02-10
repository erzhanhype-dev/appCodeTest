<?php

namespace App\Services\OffsetFund\Dto;

use Phalcon\Http\RequestInterface;

class CreateOffsetFundGoodsDto
{
    public int $offset_fund_id;

    public static function fromRequest(RequestInterface $request, int $fundId, ?int $goodsId = null): self
    {
        $dto = new self();
        $dto->offset_fund_id = $fundId;
        return $dto;
    }
}