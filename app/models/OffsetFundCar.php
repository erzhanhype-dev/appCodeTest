<?php

use Phalcon\Mvc\Model;
use Phalcon\Filter\Validation;
use Phalcon\Filter\Validation\Validator\PresenceOf;
use Phalcon\Filter\Validation\Validator\StringLength;

class OffsetFundCar extends Model
{
    public ?int $id = null;
    public int $offset_fund_id;
    public string $vin;
    public string $volume = '0.00';
    public int $ref_car_cat_id;
    public int $ref_country_id = 201;
    public int $ref_st_type_id = 0;
    public int $is_electric = 0;
    public string $manufacture_year;
    public int $import_at;
    public int $created_at;

    public string $vehicle_type = 'UNKNOWN';


    public function initialize(): void
    {
        $this->setSchema("recycle");
        $this->setSource("offset_fund_cars");

        $this->belongsTo(
            'ref_car_cat_id',
            RefCarCat::class,
            'id',
            [
                'alias' => 'ref_car_cat',
                'reusable' => true
            ]
        );

        $this->belongsTo(
            'ref_country_id',
            RefCountry::class,
            'id',
            [
                'alias' => 'ref_country',
                'reusable' => true
            ]
        );

        $this->belongsTo(
            'offset_fund_id',
            OffsetFund::class,
            'id',
            [
                'alias' => 'offset_fund',
                'reusable' => true
            ]
        );
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add(
            'vin',
            new PresenceOf(['message' => 'VIN-код обязателен для заполнения'])
        );

        $validator->add(
            'vin',
            new StringLength([
                'min' => 3,
                'message' => 'VIN-код обязателен для заполнения'
            ])
        );

        return $this->validate($validator);
    }
}