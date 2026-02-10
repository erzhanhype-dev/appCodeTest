<?php

class RefInitiator extends \Phalcon\Mvc\Model
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
   public $name;
   /**
    *
    * @var string
    */
   public $desc;

    public function initialize(): void
    {
        $this->setSource("ref_initiators");
    }
}