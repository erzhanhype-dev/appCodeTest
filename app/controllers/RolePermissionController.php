<?php
namespace App\Controllers;

use ControllerBase;
use Permission;
use Role;
use RolePermission;

class RolePermissionController extends ControllerBase
{
    public function newAction($roleId)
    {
        $roleModel = Role::findFirstById($roleId);
        $rolePermissionsArray = $roleModel->getPermissions()->toArray();
        $rolePermissionIds = array_column($rolePermissionsArray, 'id');

        $permissions = Permission::query()
            ->orderBy('controller') // Сортировка по полю name
            ->execute();

        $this->view->permissions = $permissions;
        $this->view->rolePermissions = $rolePermissionIds;
    }


    public function addAction($roleId)
    {
        // Проверяем, был ли отправлен POST-запрос
        if ($this->request->isPost()) {
            $permissionId = $this->request->getPost('permission_id', 'int');
            // Создаем объект RolePermission и устанавливаем данные
            $rolePermission = new RolePermission();
            $rolePermission->permission_id = $permissionId;
            $rolePermission->role_id = $roleId;
            $role = Role::findFirst($roleId);

            // Пытаемся сохранить данные
            if ($rolePermission->save()) {
                $permission = Permission::findFirst($rolePermission->permission_id);

                // Если успешно, добавляем сообщение об успехе
                $this->logAction("Доступ к ресурсу $permission->controller добавлен в роль $role->description", 'account');
                $this->flash->success("Доступ к ресурсу добавлен в роль.");
            } else {
                // Если не удалось сохранить, выводим ошибки
                $messages = $rolePermission->getMessages();
                foreach ($messages as $message) {
                    $this->flash->error($message);
                    $this->logAction($message);
                }
            }
        }

        // Перенаправляем на страницу редактирования роли
        return $this->response->redirect('/role/edit/' . $roleId);
    }

    public function deleteAction($roleId, $permissionId)
    {
        // Находим запись RolePermission по role_id и permission_id
        $rolePermission = RolePermission::findFirst([
            'conditions' => 'role_id = :roleId: AND permission_id = :permissionId:',
            'bind' => [
                'roleId' => $roleId,
                'permissionId' => $permissionId
            ]
        ]);
        $permission = Permission::findFirst($rolePermission->permission_id);
        $role = Role::findFirst($roleId);
        // Проверяем, найдена ли запись
        if ($rolePermission) {
            // Удаляем запись
            if ($rolePermission->delete()) {
                $this->flash->success("Доступ к ресурсу удален из роли.");
                $this->logAction("Доступ к ресурсу $permission->controller удален из роли $role->description", 'account');
            } else {
                // Если удаление не удалось, выводим ошибки
                $messages = $rolePermission->getMessages();
                foreach ($messages as $message) {
                    $this->flash->error($message);
                    $this->logAction($message);

                }
            }
        } else {
            $this->flash->error("Доступ к ресурсу не найдено.");
            $this->logAction("Доступ к ресурсу не найдено.");
        }

        // Перенаправляем обратно на страницу редактирования роли
        return $this->response->redirect('/role/edit/' . $roleId);
    }

}
