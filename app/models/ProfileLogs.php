<?php


class ProfileLogs extends \Phalcon\Mvc\Model
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
     * @Column(type="string", length=50, nullable=false)
     */
    public $action;

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
     * @Column(type="string", nullable=false)
     */
    public $meta_before;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $meta_after;

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProfileLogs[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProfileLogs
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }
}
