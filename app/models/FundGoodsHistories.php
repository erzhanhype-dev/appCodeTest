<?php

class FundGoodsHistories extends \Phalcon\Mvc\Model
{
    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("fund_goods_histories");
        $this->belongsTo("ref_tn", "RefTnCode", "id", [
            'alias' => 'ref_tn_code'
        ]);

        $this->belongsTo("goods_id", "Goods", "id", [
            'alias' => 'goods'
        ]);
    }

    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

}
