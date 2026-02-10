<?php

use Phalcon\Mvc\Model;

class Kato extends Model
{
    public $id;
    public $name_ru;
    public $name_kz;
    public $kato_code;
    public $parent_id;
    public $level;
    public $region;
    public $city;
    public $district;


    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("kato");
    }

    public static function findFirstByKatoCode(?string $code): ?self
    {
        return self::findFirst([
            'conditions' => 'kato_code = :code:',
            'bind'       => [
                'code' => $code,
            ],
        ]);
    }
}