<?php

class FundLogs extends \Phalcon\Mvc\Model
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
    public $login;

    /**
     *
     * @var string
     */
    public $action;

    /**
     *
     * @var integer
     */
    public $fund_id;

    /**
     *
     * @var integer
     */
    public $dt;

    /**
     *
     * @var string
     */
    public $meta_before;

    /**
     *
     * @var string
     */
    public $meta_after;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("fund_logs");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return FundLogs[]|FundLogs|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return FundLogs|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

}
