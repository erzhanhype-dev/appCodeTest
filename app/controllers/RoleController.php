<?php

namespace App\Controllers;

use ControllerBase;
use Role;
use User;

class RoleController extends ControllerBase
{
    public function indexAction()
    {
        $roles = Role::find([
            'conditions' => 'id != :id:',
            'bind' => ['id' => 2],
            'order' => 'weight ASC',
        ]);
        $user = User::getUserBySession();

        $this->view->roles = $roles;
        $this->view->user = $user;
    }

    public function editAction($id)
    {
        $role = Role::findFirstById($id);
        $user = User::getUserBySession();
        $permissions = $role->getPermissions()->toArray();
        $groupedPermissions = [];

        foreach ($permissions as $permission) {
            $roleName = $role->description;
            $controller = $permission['controller'];
            $action = $permission['action'];

            if (!isset($groupedPermissions[$roleName])) {
                $groupedPermissions[$roleName] = [];
            }

            $groupName = $this->generatePermissionGroupName($controller, $action);

            if (!in_array($groupName, $groupedPermissions[$roleName])) {
                $groupedPermissions[$roleName][] = $groupName;
            }
        }

        if ($user->role->priority > $role->priority) {
            $this->flash->error("Вы не можете управлять ролью выше по приоритету.");
            return $this->response->redirect('role/index');
        }

        if (!$role) {
            $this->flash->error("Роль не найдена.");
            return $this->response->redirect('role/index');
        }

        if ($this->request->isPost()) {
            // Обновляем данные из формы
            $role->name = $this->request->getPost('name', 'string');
            $role->description = $this->request->getPost('description', 'string');
            $role->priority = $this->request->getPost('priority', 'int');

            if ($role->save()) {
                $this->flash->success("Роль успешно обновлена.");
                return $this->response->redirect('role/index');
            } else {
                $this->flash->error("Ошибка при обновлении роли.");
            }
        }

        // Передаем роль в представление
        $this->view->role = $role;
        $this->view->groupedPermissions = $groupedPermissions;
    }

    private function generatePermissionGroupName($controller, $action): string
    {
        $groupDefinitions = [
            'Полный доступ' => [
                ['controller' => 'role', 'action' => '*'],
                ['controller' => 'role_permission', 'action' => '*'],
            ],
            'Просмотр реестра поступивших платежей' => [
                ['controller' => 'admin_bank', 'action' => '*'],
                ['controller' => 'zd_admin_bank', 'action' => '*'],
                ['controller' => 'operator_bank', 'action' => '*'],
                ['controller' => 'zd_operator_bank', 'action' => '*'],
            ],
            'Просмотр реестра заявок на внесение утилизационного платежа' => [
                ['controller' => 'moderator_order', 'action' => 'view'],
                ['controller' => 'order', 'action' => 'view'],
            ],
            'Просмотр логов корректировок' => [
                ['controller' => 'correction_logs', 'action' => '*'],
            ],
            'Просмотр логов загруженных файлов' => [
                ['controller' => 'correction_logs', 'action' => 'file_logs'],
            ],
            'Просмотр логов лимитов по финансированию' => [
                ['controller' => 'correction_logs', 'action' => 'ref_fund_logs'],
            ],
            'Поиск VIN-кода в реестрах заявок' => [
                ['controller' => 'moderator_order', 'action' => 'check_order'],
            ],
            'Рассмотрение заявок на внесение утилизационного платежа' => [
                ['controller' => 'moderator_order', 'action' => '*'],
            ],
            'Рассмотрение заявок на стимулирование' => [
                ['controller' => 'moderator_fund', 'action' => '*'],
            ],
            'Создание заявок на внесение утилизационного платежа' => [
                ['controller' => 'order', 'action' => '*'],
            ],
            'Создание заявок на стимулирование' => [
                ['controller' => 'fund', 'action' => '*'],
            ],
            'Корректировка заявок на внесение утилизационного платежа' => [
                ['controller' => 'correction_request', 'action' => '*'],
            ],
            'Выгрузка отчетов по реализации' => [
                ['controller' => 'report_realization', 'action' => '*'],
            ],
            'Выгрузка отчетов администратора' => [
                ['controller' => 'report_admin', 'action' => '*'],
            ],
            'Выгрузка общих отчетов в АИС' => [
                ['controller' => 'report_importer', 'action' => '*'],
            ],
            'Направление запроса по VIN-коду посредством интеграционного сервиса КАП МВД РК' => [
                ['controller' => 'kap_request', 'action' => '*'],
            ],
            'Направление запроса по VIN-коду посредством интеграционного сервиса ЭПТС' => [
                ['controller' => 'epts', 'action' => '*'],
            ],
            'Редактирование сведений в справочниках АИС' => [
                ['controller' => 'ref_car_model', 'action' => '*'],
                ['controller' => 'ref_car_type', 'action' => '*'],
                ['controller' => 'ref_fund', 'action' => '*'],
                ['controller' => 'ref_manufacturers', 'action' => '*'],
                ['controller' => 'ref_tn_code', 'action' => '*'],
                ['controller' => 'ref_vin_mask', 'action' => '*'],
            ],
        ];

        foreach ($groupDefinitions as $groupName => $definitions) {
            foreach ($definitions as $definition) {
                if (
                    $definition['controller'] === $controller &&
                    ($definition['action'] === $action)
                ) {
                    return $groupName;
                }
            }
        }

        return "";
    }
}
