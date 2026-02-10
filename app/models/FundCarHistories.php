<?php

class FundCarHistories extends \Phalcon\Mvc\Model
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
    public $car_id;

    /**
     *
     * @var double
     */
    public $volume;

    /**
     *
     * @var string
     */
    public $vin;

    /**
     *
     * @var integer
     */
    public $fund_id;

    /**
     *
     * @var integer
     */
    public $model_id;

    /**
     *
     * @var integer
     */
    public $ref_car_cat;

    /**
     *
     * @var integer
     */
    public $ref_car_type_id;

    /**
     *
     * @var integer
     */
    public $date_produce;

    /**
     *
     * @var double
     */
    public $cost;

    /**
     *
     * @var integer
     */
    public $ref_st_type;

    /**
     *
     * @var string
     */
    public $status;

     /**
     *
     * @var integer
     */
    public $dt;

    /**
     *
     * @var integer
     */
    public $user_id;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("fund_car_histories");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return FundCarHistories[]|FundCarHistories|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return FundCarHistories|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
