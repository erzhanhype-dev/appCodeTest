<?php

class RopBankProfile extends \Phalcon\Mvc\Model
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
    public $bank_id;

    /**
     *
     * @var integer
     */
    public $profile_id;

    /**
     *
     * @var string
     */
    public $reference;

    /**
     *
     * @var integer
     */
    public $paid;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("rop_bank_profile");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return RopBankProfile[]|RopBankProfile|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return RopBankProfile|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

}
