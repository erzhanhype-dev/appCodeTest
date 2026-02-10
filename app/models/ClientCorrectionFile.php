<?php

class ClientCorrectionFile extends \Phalcon\Mvc\Model
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
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $profile_id;

    /**
     *
     * @var string
     * @Column(type="string", length=45, nullable=true)
     */
    public $type;

    /**
     *
     * @var string
     * @Column(type="string", length=200, nullable=true)
     */
    public $original_name;

    /**
     *
     * @var string
     * @Column(type="string", length=15, nullable=true)
     */
    public $ext;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $ccp_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=4, nullable=false)
     */
    public $visible;

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ClientCorrectionFile[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ClientCorrectionFile
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
