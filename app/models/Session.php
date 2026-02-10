<?php
class Session extends \Phalcon\Mvc\Model
{
   public $session_id;
   public $user_id;
   public $login_time;

   public function initialize()
   {
      $this->setSchema("recycle");
      $this->setSource("sessions");
   }
}
