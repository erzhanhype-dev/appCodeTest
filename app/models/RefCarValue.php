<?php

class RefCarValue extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer|null
     */
    public ?int $id = null;

    /**
     *
     * @var integer
     */
    public int $car_type;

    /**
     *
     * @var integer
     */
    public int $volume_start;

    /**
     *
     * @var integer
     */
    public int $volume_end;

    /**
     *
     * @var double|null
     */
    public ?float $price = null;

    /**
     *
     * @var double|null
     */
    public ?float $k = null;

    /**
     *
     * @var double|null
     */
    public ?float $ko = null;

    /**
     *
     * @var double|null
     */
    public ?float $k_2022 = null;

    /**
     * Initialize method for model.
     */
    public function initialize(): void
    {
        $this->setSource("ref_car_value");
    }
}
