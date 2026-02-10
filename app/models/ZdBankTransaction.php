<?php

class ZdBankTransaction extends \Phalcon\Mvc\Model
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
   public $profile_id;

   /**
    *
    * @var integer
    */
   public $transaction_id;

   /**
    *
    * @var integer
    */
   public $zd_bank_id;

   /**
    *
    * @var integer
    */
   public $dt;

   /**
    * Initialize method for model.
    */
   public function initialize()
   {
      $this->setSchema("recycle");
      $this->setSource("zd_bank_transaction");
   }

   /**
    * Allows to query a set of records that match the specified conditions
    *
    * @param mixed $parameters
    * @return ZdBank[]|ZdBank|\Phalcon\Mvc\Model\ResultSetInterface
    */
   public static function find($parameters = null)
   {
      return parent::find($parameters);
   }

   /**
    * Allows to query the first record that match the specified conditions
    *
    * @param mixed $parameters
    * @return ZdBank|\Phalcon\Mvc\Model\ResultInterface
    */
   public static function findFirst($parameters = null): mixed
   {
      return parent::findFirst($parameters);
   }

}
