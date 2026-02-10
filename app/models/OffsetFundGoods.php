<?php

use Phalcon\Mvc\Model;

class OffsetFundGoods extends Model
{
    public int $id;
    public int $offset_fund_id;
    public string $ref_tn_code_id;
    public int $ref_country_id;

    public string $weight = '0.00';

    public string $basis;
    public int $basis_at;

    public function initialize(): void
    {
        $this->setSchema("recycle");
        $this->setSource("offset_fund_goods");
    }
}