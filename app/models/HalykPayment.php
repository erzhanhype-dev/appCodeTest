<?php

use Phalcon\Mvc\Model;

class HalykPayment extends Model
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var string
     */
    public $document_number;

    /**
     *
     * @var string
     */
    public $idnum_invoice;

    /**
     *
     * @var string
     */
    public $statement_reference;

    /**
     *
     * @var double
     */
    public $amount_sender;

    /**
     *
     * @var double
     */
    public $amount_recipient;

    /**
     *
     * @var string
     */
    public $name_sender;

    /**
     *
     * @var string
     */
    public $name_recipient;

    /**
     *
     * @var string
     */
    public $idnum_sender;

   /**
    *
    * @var string
    */
   public $idnum_owner;

    /**
     *
     * @var string
     */
    public $idnum_recipient;

    /**
     *
     * @var string
     */
    public $account_sender;

    /**
     *
     * @var string
     */
    public $account_recipient;

    /**
     *
     * @var string
     */
    public $knp_code;

    /**
     *
     * @var integer
     */
    public $date_sender;

    /**
     *
     * @var integer
     */
    public $date_recipient;

    /**
     *
     * @var string
     */
    public $payment_purpose;

    /**
     *
     * @var string
     */
    public $mfo_sender;

    /**
     *
     * @var string
     */
    public $mfo_recipient;

    /**
     *
     * @var string
     */
    public $currency;

    /**
     *
     * @var integer
     */
    public $order_number;

    /**
     *
     * @var string
     */
    public $bank_unique_id;

    /**
     *
     * @var integer
     */
    public $is_third_party_payer;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("halyk_payments");
    }
    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return HalykPayment[]|HalykPayment|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return HalykPayment|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

}
