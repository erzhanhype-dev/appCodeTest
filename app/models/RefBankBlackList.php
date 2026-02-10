<?php

class RefBankBlackList extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var string
     */
    public $idnum;

    /**
     *
     * @var integer
     */
    public $created_at;

    /**
     *
     * @var integer
     */
    public $created_by;

    /**
     *
     * @var integer
     */
    public $deleted_at;

    /**
     *
     * @var integer
     */
    public $deleted_by;

    /**
     *
     * @var string
     */
    public $status;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("ref_bank_black_list");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return RefBankBlackList[]|RefBankBlackList|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return RefBankBlackList|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

    public static function commaSeparatedList(): string
    {
        $list = [];

        $idnum_list = self::findByStatus('ACTIVE');

        if($idnum_list){
            foreach($idnum_list as $item){
                $list[] = "'$item->idnum'";
            }
        }

        return implode(', ', $list);
    }


}
