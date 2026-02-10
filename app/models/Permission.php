<?php

use Phalcon\Mvc\Model;

class Permission extends Model
{

    public $id;
    public $role_id;
    public $controller;
    public $action;

    public function initialize()
    {

        $this->setSchema("recycle");
        $this->setSource("permissions");

        $this->hasManyToMany(
            'id',
            RolePermission::class,
            'permission_id', 'role_id',
            Role::class,
            'id',
            [
                'alias' => 'roles'
            ]
        );
    }
}
