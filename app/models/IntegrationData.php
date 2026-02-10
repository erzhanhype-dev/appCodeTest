<?php

use Phalcon\Mvc\Model;

class IntegrationData extends Model
{
    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("integration_data");
    }
}