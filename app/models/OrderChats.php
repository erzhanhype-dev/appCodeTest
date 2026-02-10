<?php

class OrderChats extends \Phalcon\Mvc\Model
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
    public $p_id;

      /**
     *
     * @var integer
     */
    public $user_id;

    /**
     *
     * @var string
     */
    public $message;

      /**
     *
     * @var string
     */
    public $username;

     /**
     *
     * @var string
     */
    public $dt;

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return OrderChats[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return AgentBasement
     */
    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

}
