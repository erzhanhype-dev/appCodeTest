<?php


class RefFundKeys extends \Phalcon\Mvc\Model
{
    /**
     *
     * @var integer
     */
    public int $id;

    /**
     *
     * @var string
     */
    public string $type;

    /**
     *
     * @var string
     */
    public string $name;

    /**
     *
     * @var string
     */
    public string $description;

    /**
     * @var string
     */
    public string $entity_type;

    public function initialize(): void
    {
        $this->setSource("ref_fund_keys");
    }
}
