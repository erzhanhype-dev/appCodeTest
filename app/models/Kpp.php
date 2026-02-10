<?php

class Kpp extends \Phalcon\Mvc\Model
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
    public $profile_id;

    /**
     *
     * @var integer
     */
    public $ref_tn;

    /**
     *
     * @var integer
     */
    public $ref_country;

    /**
     *
     * @var integer
     */
    public $date_import;

    /**
     *
     * @var double
     */
    public $weight;

    /**
     *
     * @var double
     */
    public $invoice_sum;

    /**
     *
     * @var string
     */
    public $currency_type;

    /**
     *
     * @var string
     */
    public $currency;

    /**
     *
     * @var double
     */
    public $invoice_sum_currency;

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
     * @var string
     */
    public $status;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("kpp");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Kpp[]|Kpp|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Kpp|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
