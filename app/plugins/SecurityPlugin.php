<?php

defined('APP_PATH') || define('APP_PATH', str_replace('/app/plugins', '', realpath('.')));

use Phalcon\Di\Di;
use Phalcon\Di\Injectable;
use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;

class SecurityPlugin extends Injectable
{
    private int $sessionLifetime = 18000;

    public function beforeExecuteRoute(Event $event, Dispatcher $dispatcher): bool
    {
        $controller = $dispatcher->getControllerName();
        $action = $dispatcher->getActionName();
        $auth = $this->session->get('auth');
        $user = ($auth) ? User::findFirstById($auth['id']) : null;

        if (in_array($controller, ['session', 'admin', 'api', 'index', 'api_kap'])) {
            return true;
        }

        if ($this->isActiveSession()) {
            if(!str_contains($_SERVER['HTTP_HOST'], 'localhost') && $this->isSessionExpired()) {
                $this->redirectTo('session', 'index');
                return false;
            }

            if ($this->isAdminUser($user) && !$this->isAllowedIp()) {
                $isInternal = $this->isProdIp();
                if (!$isInternal) {
                    if (!$this->isAllowedFromExternal($controller, $action)) {
                        $this->denyAccess('Доступ из внешней сети ограничен. Полный доступ разрешён только из внутренних сетей.');
                        return false;
                    }
                }
            }

            if ($this->isExcludedRoute($controller, $action)) {
                return true;
            }

            if ($this->isAdminUser($user) && strtotime(date('d.m.Y')) >= strtotime('20.07.2026')) {
                $this->flash->warning("Скоро истекает срок действия ЭЦП для подписания документов(28.07.2026)");
            }

            if (!$this->isProfileComplete($user) && $user->isClient()) {
                $this->flash->warning("Вам необходимо полностью заполнить профиль и загрузить документы.");
                $this->redirectTo('settings', 'index');
                return false;
            }

            if ($user && !$this->hasPermission($user, $controller, $action)) {
                $this->flash->error('У вас нет доступа к этому ресурсу.');
                $this->redirectTo('settings', 'index');
                return false;
            }

            if (!$this->hasSufficientFreeSpace() && $controller !== 'home') {
                $this->flash->error("Недостаточно свободного места!");
                $this->redirectTo('home', 'index');
                return false;
            }

            if ($this->request->isPost()) {
                $token = $this->request->getHeader('X-CSRF-Token')
                    ?: $this->request->getPost('csrfToken', 'string');

                $sessionToken = $this->session->get('csrfToken');

                if (!$token || !$sessionToken || !hash_equals($sessionToken, (string)$token)) {
                    $this->flash->error('CSRF токен недействителен или отсутствует.');
                    $this->redirectTo('home', 'index');
                    return false;
                }
            }

            return true;
        }

        $this->redirectTo('session', 'index');
        return false;
    }

    public function isActiveSession(): bool
    {
        $user = $this->session->get('auth');

        if (is_null($user) || empty($user['session_id'])) {
            return false;
        }

        $session = $this->getSessionFromDatabase($user['session_id'], $user['id']);

        if (!$session) {
            $this->session->remove('auth');
            return false;
        }

        return true;
    }

    public function isSessionExpired(): bool
    {
        $user = $this->session->get('auth');
        $session = $this->getSessionFromDatabase($user['session_id'], $user['id']);
        if ($session && (time() - strtotime($session->login_time)) > $this->sessionLifetime) {
            $this->session->remove('auth');
            return true;
        }
        return false;
    }

    private function getSessionFromDatabase($sessionId, $userId): ?Session
    {
        return Session::findFirst([
            'conditions' => 'session_id = :session_id: AND user_id = :user_id:',
            'bind' => [
                'session_id' => $sessionId,
                'user_id' => $userId
            ]
        ]) ?: null;
    }

    private function isAdminUser($user): bool
    {
        return $user && ($user->isAdmin() || $user->isAdminSoft() || $user->isAdminSec());
    }

    private function isExcludedRoute($controller, $action): bool
    {
        $excludedControllers = ['session', 'index', 'home', 'post', 'certificate', 'api', 'api_kap', 'api_epts', 'settings', 'admin', 'shep_test'];
        $excludedRoutes = [
            ['main', 'certificate'],
            ['main', 'certificate_kz'],
            ['pay', 'post'],
            ['index', 'ajax_check']
        ];

        return in_array($controller, $excludedControllers) || in_array([$controller, $action], $excludedRoutes);
    }

    private function redirectTo($controller, $action)
    {
        $dispatcher = Di::getDefault()->getShared('dispatcher');
        $dispatcher->forward([
            'controller' => $controller,
            'action' => $action
        ]);
    }

    private function denyAccess($message)
    {
        $this->response->setStatusCode(403, 'Forbidden')->sendHeaders();
        echo $message;
        exit;
    }

    private function getPermissionsByRole($role)
    {
        $auth = User::getUserBySession();
        $roleModel = Role::findFirstByName($role);

        if ($auth->login === 'admin') {
            return $this->getAdminPermission($auth);
        }

        if (!$roleModel) {
            return [];
        }

        $conditions = [];
        $bind = [];

        if ($auth->fund_user != 1) {
            if ($auth->pir_stage != 'STAGE_NOT_SET') {
                $conditions[] = '[Permission].id NOT IN ({ids:array})';
                $bind['ids'] = [91, 13];
            } else {
                $conditions[] = '[Permission].id != :id:';
                $bind['id'] = 13;
            }
        }

        return $roleModel->getPermissions([
            'conditions' => implode(' AND ', $conditions),
            'bind' => $bind,
        ]);
    }

    private function hasPermission($user, $controller, $action): bool
    {
        $roleName = $user->role->name;
        $permissions = $this->getPermissionsByRole($roleName);

        foreach ($permissions as $permission) {
            if ($permission->controller === $controller &&
                ($permission->action === '*' || $permission->action === $action)) {
                return true;
            }
        }

        return false;
    }

    private function isAllowedIp(): bool
    {
        $userIp = $this->getClientIp();

        $allowedIps = array_map('trim', explode(',', getenv('ADMIN_ALLOWED_IP_LIST')));

        foreach ($allowedIps as $allowedIp) {
            if ($this->ipInRange($userIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    private function ipInRange($ip, $range): bool
    {
        if (strpos($range, '/') !== false) {
            list($subnet, $mask) = explode('/', $range);
            return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) === ip2long($subnet);
        }

        return $ip === $range;
    }

    private function isProfileComplete($user): bool
    {
        $auth = $this->session->get('auth');
        if (isset($auth['shadow_mode'])) {
            return true;
        }

        if ($user) {
            $ct = ContactDetail::findFirst([
                "user_id = :id:",
                "bind" => ["id" => $user->id]
            ]);

            if ($user->user_type_id == PERSON) {
                $dt = PersonDetail::findFirst([
                    "user_id = :id:",
                    "bind" => ["id" => $user->id]
                ]);
                if ($dt->iin || $ct->reg_address) {
                    return true;
                }
            } else {
                $dt = CompanyDetail::findFirst([
                    "user_id = :id:",
                    "bind" => ["id" => $user->id]
                ]);
                if ($dt->bin && $ct->reg_address) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasSufficientFreeSpace(): bool
    {
        $disk_free_space = disk_free_space(APP_PATH . '/private/');
        $requiredSpace = 1 * 1024 * 1024 * 1024;

        return $disk_free_space > $requiredSpace;
    }


    private function getAdminPermission($user): array
    {
        $role = (object)[
            'id' => 1,
            'name' => 'admin',
            'description' => 'Мастер Администратор',
            'priority' => 1,
            'weight' => 1
        ];
        $user->role = $role;

        $rolePermissions = ADMIN_PERMISSIONS;

        $permissions = [];
        foreach ($rolePermissions as $rolePermission) {
            $permission = Permission::findFirst([
                'conditions' => 'id = :id:',
                'bind' => [
                    'id' => $rolePermission['permission_id'],
                ]
            ]);

            if ($permission) {
                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    private function getClientIp(): string
    {
        $ip = '0.0.0.0';

        if (!empty($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    private function isAllowedFromExternal(string $controller, string $action): bool
    {
        // Белый список того, что доступно снаружи (пример)
        $allowed = [
            'sqlexport'  => ['*'],
            'settings' => ['*'],
            'moderator_main' => ['*'],
            'home' => ['*'],
        ];

        if (!isset($allowed[$controller])) {
            return false;
        }
        return in_array('*', $allowed[$controller], true) || in_array($action, $allowed[$controller], true);
    }

    public function isProdIp(): bool
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 2
            ]
        ]);
        $ip = @file_get_contents('https://ifconfig.me/ip', false, $ctx);
        if ($ip != getenv('DEV_HOST_IP')) {
            return false;
        }
        return true;
    }
}
