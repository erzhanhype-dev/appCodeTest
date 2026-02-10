<?php

use Phalcon\Mvc\Model;

class TaskLog extends Model
{
    public $id;
    public $name;
    public $status;
    public $payload;
    public $created;
    public $filename;
    public $completed;

    public function initialize(): void
    {
        $this->setSchema("recycle");
        $this->setSource("task_log");
    }

    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

    public static function findFirstById(int $id): ?self
    {
        return self::findFirst([
            'conditions' => 'id = :id:',
            'bind'       => [
                'id' => $id,
            ],
        ]);
    }
}