<?php

class RefTnCode extends \Phalcon\Mvc\Model
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
     * @Column(type="string", length=10, nullable=true)
     */
    public $code;

    /**
     *
     * @var string
     * @Column(type="string", length=200, nullable=true)
     */
    public $group;

    /**
     *
     * @var string
     * @Column(type="string", length=400, nullable=true)
     */
    public $name;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=true)
     */
    public $price_1;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=false)
     */
    public $price_2;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=false)
     */
    public $price_3;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=false)
     */
    public $price_4;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=false)
     */
    public $price_5;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=false)
     */
    public $price_6;

    /**
     *
     * @var double
     * @Column(type="double", length=12, nullable=false)
     */
    public $price_7;

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $is_active;

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $is_correct;


    /**
     *
     * @var string
     * @Column(type="string", length=45, nullable=true)
     */
    public $type;

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return RefTnCode[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return RefTnCode
     */
    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

}
