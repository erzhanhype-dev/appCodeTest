<?php

class UniqueContract extends \Phalcon\Mvc\Model
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
    public $bin;

    /**
     *
     * @var string
     */
    public $contract;


    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return UniqueContract[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return UniqueContract
     */
    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

}
