<?php

class RefBank extends \Phalcon\Mvc\Model
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
    public $bik;

    /**
     *
     * @var string
     */
    public $name;

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return RefBank[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return RefBank
     */
    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }
}
