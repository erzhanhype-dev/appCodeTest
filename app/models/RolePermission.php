<?php
use Phalcon\Mvc\Model;

class RolePermission extends Model
{
    public $role_id;
    public $permission_id;

    public function initialize()
    {
        $this->setSource("role_permissions");

        $this->belongsTo(
            'role_id',
            Role::class,
            'id',
            [
                'alias' => 'role'
            ]
        );

        // Связь с разрешениями
        $this->belongsTo(
            'permission_id',
            Permission::class,
            'id',
            [
                'alias' => 'permission'
            ]
        );
    }
}
