<?php

class CompanyDetail extends \Phalcon\Mvc\Model
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
    public $bin;

    /**
     *
     * @var string
     */
    public $name;

    /**
     *
     * @var integer
     */
    public $reg_date;

    /**
     *
     * @var string
     */
    public $iban;

    /**
     *
     * @var integer
     */
    public $ref_bank_id;

    /**
     *
     * @var integer
     */
    public $ref_kbe_id;

    /**
     *
     * @var string
     */
    public $b_region;

    /**
     *
     * @var integer
     */
    public $b_size;

    /**
     *
     * @var string
     */
    public $reg_num;

    /**
     *
     * @var string
     */
    public $oked;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("company_detail");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return CompanyDetail[]|CompanyDetail|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return CompanyDetail|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
