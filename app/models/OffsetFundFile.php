<?php

use Phalcon\Filter\Validation;
use Phalcon\Filter\Validation\Validator\InclusionIn;
use Phalcon\Filter\Validation\Validator\PresenceOf;
use Phalcon\Mvc\Model;

class OffsetFundFile extends Model
{
    public ?int $id = null;
    public int $offset_fund_id;
    public string $original_name = 'UNKNOWN';
    public string $ext = 'UNKNOWN';
    public string $type = 'UNKNOWN';
    public int $visible = 1;
    public int $created_at;
    public int $created_by;

    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("offset_fund_files");
    }

    public function validation()
    {
        $validator = new Validation();

        // 1. Проверяем все обязательные поля одной командой
        $validator->add(
            ['offset_fund_id', 'original_name', 'ext', 'type'],
            new PresenceOf([
                'message' => 'Поле :field обязательно для заполнения'
            ])
        );

        // 2. Проверяем, что тип документа корректный (из вашего select)
        $validator->add(
            'type',
            new InclusionIn([
                'message' => 'Недопустимый тип документа',
                'domain'  => ['calculation_cost', 'other']
            ])
        );

        return $this->validate($validator);
    }
}