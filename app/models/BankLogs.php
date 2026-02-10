<?php

class BankLogs extends \Phalcon\Mvc\Model
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
     * @var string
     * @Column(type="string", length=50, nullable=false)
     */
    public $login;

    /**
     *
     * @var string
     * @Column(type="string", length=25, nullable=false)
     */
    public $action;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $payment_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $profile_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $dt;

    /**
     *
     * @var string
     * @Column(type="string", length=512, nullable=false)
     */
    public $meta;

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return BankLogs[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return BankLogs
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
