<?php

use Phalcon\Mvc\Model;

class Role extends Model
{

    /**
     *
     * @var string
     */
    public $name;

    public function initialize()
    {

        $this->setSchema("recycle");
        $this->setSource("roles");

        $this->hasManyToMany(
            'id',
            RolePermission::class,
            'role_id', 'permission_id',
            Permission::class,
            'id',
            [
                'alias' => 'permissions'
            ]
        );

    }

    public function setPermissions(array $permissions)
    {
        $this->permissions = $permissions;
    }
}
