<?php

use Phalcon\Mvc\Model;

class LoginAttempt extends Model
{
   public $id;
   public $user_id;
   public $device_info;
   public $login_time;
   public $geolocation_info;
   public $ip;
   public $status;

   public function initialize()
   {
      $this->setSource('login_attempts');
   }
}
?>
