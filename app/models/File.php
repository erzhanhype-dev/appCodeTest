<?php

class File extends \Phalcon\Mvc\Model
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
    public $good_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $car_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=4, nullable=false)
     */
    public $visible;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $modified_at;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $modified_by;

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return File[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return File
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

    public function initialize()
    {
        $this->belongsTo(
            'modified_by',   // локальное поле
            PersonDetail::class, // модель, к которой привязано
            'user_id',       // поле в PersonDetail
            [
                'alias' => 'modifier'
            ]
        );
    }
}
