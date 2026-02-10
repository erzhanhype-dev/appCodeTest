<?php

class ClientCorrectionGoods extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $profile_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $good_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $ccp_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $ref_tn;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $ref_country;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $date_import;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $weight;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $price;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $amount;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $goods_type;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $up_type;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $up_tn;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $date_report;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
     */
    public $basis;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $ref_tn_add;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
     */
    public $status;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $goods_cost;

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Goods[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Goods
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
