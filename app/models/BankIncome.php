<?php

class BankIncome extends \Phalcon\Mvc\Model
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
    public $statement_reference;

    /**
     *
     * @var double
     */
    public $amount;

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
    public $rnn_sender;

    /**
     *
     * @var string
     */
    public $rnn_recipient;

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
     * @var string
     */
    public $document_number;

    /**
     *
     * @var string
     */
    public $bank_unique_id;
    
     /**
     *
     * @var integer
     */
    public $identificator;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("bank_income");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return BankIncome[]|BankIncome|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return BankIncome|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
