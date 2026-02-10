<?php

class FundCorrectionLogs extends \Phalcon\Mvc\Model
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
    public $type;

    /**
     *
     * @var integer
     */
    public $fund_id;

    /**
     *
     * @var integer
     */
    public $object_id;

    /**
     *
     * @var integer
     */
    public $user_id;

    /**
     *
     * @var string
     */
    public $iin;

    /**
     *
     * @var string
     */
    public $action;

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
     *
     * @var string
     */
    public $comment;

    /**
     *
     * @var string
     */
    public $file;

    /**
     *
     * @var string
     */
    public $sign;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("fund_correction_logs");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return FundCorrectionLogs[]|FundCorrectionLogs|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return FundCorrectionLogs|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
