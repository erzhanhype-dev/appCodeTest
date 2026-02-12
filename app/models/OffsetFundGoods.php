<?php

use Phalcon\Mvc\Model;

class OffsetFundGoods extends Model
{
    public ?int $id = null;
    public int $offset_fund_id;
    public string $weight = '0.00';
    public int $ref_tn_code_id;
    public int $ref_country_id;
    public int $basis_at;
    public string $basis;
    public int $created_at;


    public function initialize(): void
    {
        $this->setSchema("recycle");
        $this->setSource("offset_fund_goods");

        $this->belongsTo(
            'ref_country_id',
            RefCountry::class,
            'id',
            [
                'alias' => 'ref_country',
                'reusable' => true
            ]
        );

        $this->belongsTo(
            'ref_tn_code_id',
            RefTnCode::class,
            'id',
            [
                'alias' => 'ref_tn_code',
                'reusable' => true
            ]
        );

        $this->belongsTo(
            'offset_fund_id',
            OffsetFund::class,
            'id',
            [
                'alias' => 'offset_fund',
                'reusable' => true
            ]
        );
    }
}