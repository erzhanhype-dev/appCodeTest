<?php

class ProfileExpense extends \Phalcon\Mvc\Model
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
     * @Column(type="integer", length=11, nullable=false)
     */
    public $profile_id;

    /**
     *
     * @var string
     * @Column(type="string", length=12, nullable=false)
     */
    public $rnn_recipient;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $date_modified;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=false)
     */
    public $amount;

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProfileExpense[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProfileExpense
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
