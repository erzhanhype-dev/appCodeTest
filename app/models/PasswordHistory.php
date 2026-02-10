<?php
use Phalcon\Mvc\Model;

class PasswordHistory extends Model
{
   public $id;
   public $user_id;
   public $password;
   public $created_at;

   public function initialize()
   {
      $this->setSchema("recycle");
      $this->setSource("password_history");
   }

   public static function find($parameters = null)
   {
      return parent::find($parameters);
   }
}
