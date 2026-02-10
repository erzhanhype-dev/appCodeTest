<?php

use App\Exceptions\AppException;
use App\Helpers\LogTrait;
use Phalcon\Mvc\Controller;

class ControllerBase extends Controller
{
    use LogTrait;

    public function initialize()
    {
        // --- session & csrf ---
        if (!$this->session->exists()) {
            $this->session->start();
        }
        if (!$this->session->has('csrfToken')) {
            $this->session->set('csrfToken', bin2hex(random_bytes(32)));
        }
        $this->view->csrfToken = $this->session->get('csrfToken');

        // --- меню из конфигурации (ВАЖНО: файл должен делать return [...]) ---
        $menuItems = require APP_PATH . '/app/config/menu.php';
        if (!is_array($menuItems)) {
            $menuItems = [];
        }

        // --- текущий пользователь / роль / права (повторяем твою логику) ---
        $authId = $this->session->get('auth')['id'] ?? null;
        $user = $authId ? User::findFirstById($authId) : null;

        $role = null;
        $permissions = [];

        if ($user) {
            if ($user->login === 'admin') {
                $user = User::getAdminPermission($user);
                $role = $user->role;
                $permissions = $user->role->permissions ?? [];
            } else {
                $role = Role::findFirstById($user->role_id);
                if ($user->fund_user == 1) {
                    $permissions = $role ? $role->getPermissions() : [];
                } else {
                    $permissions = $role ? $role->getPermissions([
                        'conditions' => '[Permission].id != :id:',
                        'bind' => ['id' => 13],
                    ]) : [];
                }
            }
        }

        // --- нормализуем права в простой массив для быстрых проверок ---
        $permissionsList = $this->buildPermissionsList($permissions);

        // --- фильтрация меню (включая submenu) по правилам и правам ---
        $roleName = $role->name ?? null;
        $menuItems = $this->filterMenu($menuItems, $roleName, $permissionsList);

        // --- в шаблон отдаём уже готовые данные ---
        $this->view->setVars([
            'auth' => $user,
            'role' => $role,
            'permissions' => $permissions,      // оставлю, если нужно ещё где-то
            'menuItems' => $menuItems,        // УЖЕ отфильтрованное меню
        ]);

    }

    /**
     * Преобразует коллекцию Permission (модель/массив/Resultset) в простой массив:
     * [['controller' => '...', 'action' => '...'], ...]
     */
    private function buildPermissionsList($permissions): array
    {
        $out = [];

        if (is_iterable($permissions)) {
            foreach ($permissions as $p) {
                // поддержка и объекта, и массива
                $controller = is_object($p) ? (string)($p->controller ?? '') : (string)($p['controller'] ?? '');
                $action = is_object($p) ? (string)($p->action ?? '') : (string)($p['action'] ?? '');
                if ($controller !== '') {
                    $out[] = ['controller' => $controller, 'action' => $action];
                }
            }
        }

        return $out;
    }

    /**
     * Бизнес-правила скрытия пункта (аналог твоих if/unset).
     * Здесь не трогаем права — только "жёсткие" запреты по роли/неймам.
     */
    private function shouldHide(array $item, ?string $roleName): bool
    {
        // logs разрешён только admin/admin_soft/admin_sec
        if (!empty($item['name']) && $item['name'] === 'logs'
            && !in_array($roleName, ['admin', 'admin_soft', 'admin_sec'], true)) {
            return true;
        }

        // moderator_correction_request скрыть для client/agent
        if (!empty($item['name']) && $item['name'] === 'moderator_correction_request'
            && in_array($roleName, ['client', 'agent'], true)) {
            return true;
        }

        // correction_request скрыть для всех, КРОМЕ client/agent
        if (!empty($item['name']) && $item['name'] === 'correction_request'
            && !in_array($roleName, ['client', 'agent'], true)) {
            return true;
        }

        // kap_request index только для admin_soft
        if (!empty($item['controller']) && !empty($item['action'])
            && $item['controller'] === 'kap_request'
            && $item['action'] === 'index'
            && $roleName !== 'admin_soft') {
            return true;
        }

        // запрет 'order' для НЕ client/agent (то, что раньше было в шаблоне)
        if (!empty($item['controller']) && $item['controller'] === 'order'
            && !in_array($roleName, ['client', 'agent'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Проверка прав на пункт: совпадение по controller и action ('*' поддерживается).
     * Если у пункта нет controller — считаем, что сам по себе он не требует права (группа),
     * и его доступность определится наличием доступных детей.
     */
    private function hasPermission(array $item, array $permissionsList): bool
    {
        if (empty($item['controller'])) {
            return true; // группа без собственного контроллера
        }

        $controller = (string)$item['controller'];
        $action = isset($item['action']) ? (string)$item['action'] : '';

        foreach ($permissionsList as $p) {
            if ($p['controller'] === $controller && ($p['action'] === '*' || $p['action'] === $action)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Рекурсивная фильтрация submenu.
     * Возвращает массив детей, прошедших shouldHide + hasPermission + рекурсивный фильтр.
     */
    private function filterSubmenu(array $submenu, ?string $roleName, array $permissionsList): array
    {
        $out = [];

        foreach ($submenu as $child) {
            if (!is_array($child)) {
                continue;
            }

            // 1) жесткие бизнес-правила
            if ($this->shouldHide($child, $roleName)) {
                continue;
            }

            // 2) рекурсивно отфильтровать дочернее submenu, если есть
            if (isset($child['submenu']) && is_array($child['submenu'])) {
                $child['submenu'] = $this->filterSubmenu($child['submenu'], $roleName, $permissionsList);
            }

            $isGroup = empty($child['controller']) && empty($child['url']) && isset($child['submenu']);
            $allowedSelf = $this->hasPermission($child, $permissionsList);
            $hasChildren = !empty($child['submenu']);

            if ($isGroup) {
                // группу оставляем, только если у неё остались видимые дети
                if ($hasChildren) {
                    $out[] = $child;
                }
                continue;
            }

            // обычный пункт: допускаем либо по собственному праву, либо если остались видимые дети
            if ($allowedSelf || $hasChildren) {
                $out[] = $child;
            }
        }

        return array_values($out);
    }

    /**
     * Фильтрация меню верхнего уровня.
     * Учитывает shouldHide(), права, рекурсивно обрабатывает submenu, удаляет пустые группы.
     */
    private function filterMenu(array $items, ?string $roleName, array $permissionsList): array
    {
        $out = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            // 1) бизнес-правила
            if ($this->shouldHide($item, $roleName)) {
                continue;
            }

            // 2) если есть submenu — фильтруем его отдельно
            if (isset($item['submenu']) && is_array($item['submenu'])) {
                $item['submenu'] = $this->filterSubmenu($item['submenu'], $roleName, $permissionsList);
            }

            $isGroup = empty($item['controller']) && empty($item['url']) && isset($item['submenu']);
            $allowedSelf = $this->hasPermission($item, $permissionsList);
            $hasChildren = !empty($item['submenu']);

            if ($isGroup) {
                if ($hasChildren) {
                    $out[] = $item; // группа с живыми детьми — оставляем
                }
                continue; // иначе — выкидываем пустую группу
            }

            // пункт-лист: оставляем, если у него есть право, либо есть видимые дети
            if ($allowedSelf || $hasChildren) {
                $out[] = $item;
            }
        }

        return array_values($out);
    }

    /**
     * @throws AppException
     */
    protected function logAction($message, $type = 'action', $level = 'INFO'): void
    {
        $this->writeLog($message, $type, $level);
    }

    public function beforeExecuteRoute($dispatcher)
    {
        $this->view->startTime = microtime(true);
    }

    private function shortenString($string, $maxLength = 400)
    {
        return strlen($string) > $maxLength ? substr($string, 0, $maxLength) . '...' : $string;
    }
}
