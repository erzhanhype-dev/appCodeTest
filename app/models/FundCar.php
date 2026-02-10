<?php

class FundCar extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $id;

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
     * @var integer
     */
    public $calculate_method;

    /**
     *
     * @var integer
     */
    public $profile_id;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("fund_car");
    }

    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }
}
