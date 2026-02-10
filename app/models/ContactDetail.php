<?php

class ContactDetail extends \Phalcon\Mvc\Model
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
     * @var string
     */
    public $reg_city;

    /**
     *
     * @var string
     */
    public $reg_address;

    /**
     *
     * @var string
     */
    public $reg_zipcode;

    /**
     *
     * @var string
     */
    public $city;

    /**
     *
     * @var string
     */
    public $address;

    /**
     *
     * @var string
     */
    public $zipcode;

    /**
     *
     * @var string
     */
    public $phone;

    /**
     *
     * @var string
     */
    public $mobile_phone;

    /**
     *
     * @var integer
     */
    public $ref_reg_country_id;

    /**
     *
     * @var integer
     */
    public $ref_country_id;

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ContactDetail[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ContactDetail
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
