<?php

class PirLine extends \Phalcon\Mvc\Model
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
    public $pir_id;

    /**
     *
     * @var string
     */
    public $idnum;

    /**
     *
     * @var string
     */
    public $title;

    /**
     *
     * @var string
     */
    public $ref_tn_code;

    /**
     *
     * @var double
     */
    public $weight;

    /**
     *
     * @var double
     */
    public $amount;

    /**
     *
     * @var string
     */
    public $basis;

    /**
     *
     * @var integer
     */
    public $basis_date;

    /**
     *
     * @var integer
     */
    public $goods_id;

    /**
     *
     * @var integer
     */
    public $to_law;

    /**
     *
     * @var string
     */
    public $city;

    /**
     *
     * @var string
     */
    public $address;

    /**
     *
     * @var string
     */
    public $reference;

    /**
     *
     * @var string
     */
    public $meta;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("pir_line");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return PirLine[]|PirLine|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return PirLine|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
