<?php

class ZdBank extends \Phalcon\Mvc\Model
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
    public $account_num;

    /**
     *
     * @var string
     */
    public $iban_to;

    /**
     *
     * @var string
     */
    public $iban_from;

    /**
     *
     * @var double
     */
    public $amount;

    /**
     *
     * @var integer
     */
    public $paid;

    /**
     *
     * @var string
     */
    public $comment;

    /**
     *
     * @var integer
     */
    public $transaction_id;

    /**
     *
     * @var string
     */
    public $transactions;

    /**
     *
     * @var string
     */
    public $name_sender;

    /**
     *
     * @var string
     */
    public $rnn_sender;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("zd_bank");
    }

}
