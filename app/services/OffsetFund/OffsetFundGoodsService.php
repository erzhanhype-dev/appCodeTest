<?php

namespace App\Services\OffsetFund;

use App\Exceptions\AppException;
use OffsetFund;
use OffsetFundGoods;
use RefCountry;
use User;

class OffsetFundGoodsService
{

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
}