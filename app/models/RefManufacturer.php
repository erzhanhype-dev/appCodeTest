<?php

class RefManufacturer extends \Phalcon\Mvc\Model
{
    /**
     *
     * @var integer| null
     */
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
    public int $created_at;

    /**
     *
     * @var integer
     */
    public int $created_by;

    /**
     *
     * @var integer|null
     */
    public ?int $deleted_at = null;

    /**
     *
     * @var integer|null
     */
    public ?int $deleted_by = null;

    /**
     *
     * @var string
     */
    public string $status;

    /**
     * Initialize method for model.
     */
    public function initialize(): void
    {
        $this->setSource("ref_manufacturers");
    }
}
