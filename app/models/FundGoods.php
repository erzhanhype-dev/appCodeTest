<?php

class FundGoods extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var integer
     */
    public $goods_id;

    /**
     *
     * @var integer
     */
    public $fund_id;

    /**
     *
     * @var string
     */
    public $ref_tn;

    /**
     *
     * @var integer
     */
    public $date_produce;

    /**
     *
     * @var double
     */
    public $cost;

    /**
     *
     * @var integer
     */
    public $calculate_method;

    /**
     *
     * @var integer
     */
    public $profile_id;

    /**
     *
     * @var float
     */
    public $weight;

    /**
     *
     * @var string
     */
    public $tn_code;

    /**
     * @var string
     */
    public $entity_type;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("fund_goods");

        $this->belongsTo(
            'ref_tn',
            RefTnCode::class,
            'id',
            [
                'alias' => 'ref_tn_code',
                'reusable' => true,
            ]
        );

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
