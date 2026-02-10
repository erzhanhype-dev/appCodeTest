<?php

class RequestChangeStatus extends \Phalcon\Mvc\Model
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
    public $user_id;

    /**
     *
     * @var integer
     */
    public $user_type;

    /**
     *
     * @var integer
     */
    public $date_request;

    /**
     *
     * @var integer
     */
    public $approved;

    /**
     *
     * @var integer
     */
    public $approver;

    /**
     *
     * @var integer
     */
    public $date_approve;

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return RequestChangeStatus[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return RequestChangeStatus
     */
    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

}
