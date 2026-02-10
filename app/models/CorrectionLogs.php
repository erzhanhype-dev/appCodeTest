<?php

class CorrectionLogs extends \Phalcon\Mvc\Model
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
    public $profile_id;

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
    public $vin_before;

    /**
     *
     * @var string
     */
    public $vin_after;

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
     *
     * @var integer
     */
    public $accountant_id;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("correction_logs");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return CorrectionLogs[]|CorrectionLogs|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return CorrectionLogs|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

}
