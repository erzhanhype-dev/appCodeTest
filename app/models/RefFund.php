<?php

class RefFund extends \Phalcon\Mvc\Model
{
    public ?int $id = null;

    /**
     *
     * @var string
     */
    public string $idnum;

    /**
     *
     * @var integer
     */
    public int $year;

    /**
     *
     * @var string
     */
    public string $key;

    /**
     *
     * @var double
     */
    public float $value;

    /**
     * @var string
     */
    public string $entity_type;

    /**
     * Initialize method for model.
     */
    public function initialize(): void
    {
        $this->setSource("ref_fund");
    }
}
