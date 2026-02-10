<?php

class UserLogs extends \Phalcon\Mvc\Model
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
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $user_id;

    /**
     *
     * @var string
     * @Column(type="string", length=128, nullable=true)
     */
    public $action;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $dt;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $affected_user_id;

    /**
     *
     * @var string
     * @Column(type="string", nullable=true)
     */
    public $info;

   /**
    *
    * @var string
    * @Column(type="string", nullable=true)
    */
   public $ip;

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return UserLogs[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return UserLogs
     */
    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

}
