<?php

namespace App\Controllers;

use ControllerBase;
use Phalcon\Http\ResponseInterface;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Role;
use Session;
use User;
use UserLogs;
use UserType;

class UsersController extends ControllerBase
{
    public function indexAction()
    {
        $auth = User::getUserBySession();

        if ($this->request->getQuery('clear', 'int', 0) === 1) {
            $this->session->remove('filter_idnum');
            $this->session->remove('filter_name');
            $this->session->remove('filter_id');
            $this->session->remove('filter_role_id');
            $this->session->remove('filter_is_employee');
            $this->session->remove('filter_is_active');
        }

        if ($this->request->isPost()) {
            $this->session->set('filter_idnum', trim((string)$this->request->getPost('idnum', 'string', '')));
            $this->session->set('filter_name', trim((string)$this->request->getPost('name', 'string', '')));
            $this->session->set('filter_id', trim((string)$this->request->getPost('id', 'string', '')));

            // селекты читаем как строки и нормализуем
            $roleRaw = $this->request->getPost('role_id', 'string', '');
            $empRaw = $this->request->getPost('is_employee', 'string', '');
            $actRaw = $this->request->getPost('is_active', 'string', '');

            $this->session->set('filter_role_id', ($roleRaw === '' ? null : (int)$roleRaw));
            $this->session->set('filter_is_employee', ($empRaw === '' ? null : (int)$empRaw));
            $this->session->set('filter_is_active', ($actRaw === '' ? null : (int)$actRaw));
        }

        // значения фильтров
        $idnum = (string)($this->session->get('filter_idnum') ?? '');
        $name = (string)($this->session->get('filter_name') ?? '');
        $id = (string)($this->session->get('filter_id') ?? '');
        $role_id = $this->session->get('filter_role_id');
        $is_employee = $this->session->get('filter_is_employee');
        $is_active = $this->session->get('filter_is_active');

        $conds = ['u.idnum <> 0'];
        $bind = [];

        if ($id !== '' && ctype_digit($id)) {
            $conds[] = 'u.id = :p_id:';
            $bind['p_id'] = (int)$id;
        }
        if ($idnum !== '') {
            $conds[] = 'u.idnum LIKE :p_idnum:';
            $bind['p_idnum'] = '%' . $idnum . '%';
        }
        if ($name !== '') {
            // два разных плейсхолдера
            $conds[] = '(u.fio LIKE :p_nm1: OR u.org_name LIKE :p_nm2:)';
            $bind['p_nm1'] = '%' . $name . '%';
            $bind['p_nm2'] = '%' . $name . '%';
        }
        if ($role_id !== null) {
            $conds[] = 'u.role_id = :p_rid:';
            $bind['p_rid'] = (int)$role_id;
        }
        if ($is_employee !== null) {
            $conds[] = 'u.is_employee = :p_emp:';
            $bind['p_emp'] = (int)$is_employee;
        }
        if ($is_active !== null) {
            $conds[] = 'u.active = :p_act:';
            $bind['p_act'] = (int)$is_active;
        }

        $expr = implode(' AND ', $conds);

        $builder = $this->modelsManager->createBuilder()
            ->from(['u' => User::class])
            ->where($expr, $bind)
            ->orderBy('u.id DESC');
        // пагинация
        $page = $this->request->getQuery('page', 'int', 1);
        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit' => 20,
            'page' => $page,
        ]);
        $pageObj = $paginator->paginate();

        // роли
        $roles = Role::find([
            'conditions' => 'id != :id:',
            'bind' => ['id' => 2],
            'order' => 'weight ASC',
        ]);

        // во view
        $this->view->setVars([
            'page' => $pageObj,
            'isAdmin' => ($auth->isAdminSoft() || $auth->isAdminSec() || $auth->isAdmin()),
            'roles' => $roles,
            'filters' => [
                'idnum' => $idnum,
                'name' => $name,
                'id' => $id,
                'role_id' => $role_id,
                'is_employee' => $is_employee,
                'is_active' => $is_active,
            ],
        ]);
    }

    /**
     * Displays the creation form
     */
    public function newAction()
    {
        $auth = User::getUserBySession();
        $types = UserType::find();
        $roles = Role::find([
            'conditions' => 'priority > :value:',
            'bind' => [
                'value' => $auth->role->priority,
            ],
        ]);
        $this->view->setVar("types", $types);
        $this->view->setVar("roles", $roles);
    }

    /**
     * Edits a user
     *
     * @param string $id
     */
    public function editAction($id)
    {
        if (!$this->request->isPost()) {
            $user = User::findFirstByid($id);
            $auth = User::getUserBySession();
            if (!$user) {
                $this->flash->error("Пользователь не найден.");
                return $this->response->redirect("/users/index/");
            }

            $this->view->id = $user->id;
            $user_types_list = UserType::find();

            $roles = Role::find([
                'conditions' => 'priority > :value:',
                'bind' => [
                    'value' => $auth->role->priority,
                ],
            ]);

            $this->view->setVars([
                "user_types_list" => $user_types_list,
                "roles" => $roles,
                "current_user" => [
                    "id" => $user->id,
                    "login" => $user->login,
                    "idnum" => $user->idnum,
                    "password" => "",
                    "email" => $user->email,
                    "active" => $user->active,
                    "is_employee" => $user->is_employee,
                    "view_mode" => $user->view_mode,
                    "fund_stage" => $user->fund_stage,
                    "pir_stage" => $user->pir_stage,
                    "lang" => $user->lang,
                    "last_login" => date("d.m.Y H:i:s", $user->last_login),
                    "user_type_id" => $user->user_type_id,
                    "role_id" => $user->role_id,
                ]
            ]);
        }
    }

    /**
     * Creates a new user
     */
    public function createAction()
    {
        $auth = User::getUserBySession();

        if (!$this->request->isPost()) {
            return $this->response->redirect("/users/index/");
        }

        $existingUser = User::findFirst([
            'conditions' => 'email = :email:',
            'bind' => [
                'email' => $this->request->getPost("email"),
            ]
        ]);

        if ($existingUser) {
            $this->flash->error('Пользователь с таким email уже существует, введите другой email адрес!');
            $this->logAction('Пользователь с таким email уже существует, введите другой email адрес!','account');
            return $this->response->redirect("/users/new/");
        }

        $user = new User();

        $user->login = $this->request->getPost("login");
        $user->idnum = $this->request->getPost("idnum");
        $password = $this->request->getPost("password");
        $user->password = password_hash(getenv('NEW_SALT') . $password, PASSWORD_DEFAULT);
        $user->email = $this->request->getPost("email", "email");
        $user->active = $this->request->getPost("active");
        $user->role_id = $this->request->getPost("role_id");
        $user->view_mode = $this->request->getPost("view_mode");
        $user->lang = $this->request->getPost("lang");
        $user->user_type_id = $this->request->getPost("user_type_id");
        $user->lastip = $this->request->getClientAddress();
        $user->last_login = time();

        if (!$user->save()) {
            if (count($user->getMessages()) > 0) {
                foreach ($user->getMessages() as $message) {
                    $this->flash->error($message);
                    $this->logAction($message, 'account');
                }
            }
            return $this->response->redirect("/users/new/");
        }

        $ul = new UserLogs();
        $ul->user_id = $auth->id;
        $ul->action = 'CREATE';
        $ul->affected_user_id = $user->id;
        $ul->dt = time();
        $ul->info = json_encode(array($user));
        $ul->ip = $this->request->getClientAddress();
        $ul->save();

        $logString = json_encode($ul->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $this->logAction($logString,'account');

        $this->flash->success("Пользователь создан успешно.");
        return $this->response->redirect("/users/index/");
    }

    /**
     * Saves a user edited
     *
     */
    public function saveAction(): \Phalcon\Http\ResponseInterface
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect("/users/index/");
        }

        $auth = User::getUserBySession();
        $id   = $this->request->getPost("id", "int");
        $user = User::findFirstById($id);
        if (!$user) {
            return $this->response->redirect("/users/index/");
        }

        // Белый список полей
        $fields = [
            'login'        => 'trim',
            'idnum'        => 'trim',
            'email'        => 'email',
            'active'       => 'int',
            'role_id'      => 'int',
            'view_mode'    => 'int',
            'fund_stage'   => 'trim',
            'lang'         => 'trim',
            'user_type_id' => 'int',
        ];

        // Снимок "до"
        $before = $user->toArray(array_keys($fields));

        // Сбор данных
        $input = [];
        foreach ($fields as $k => $filter) {
            $input[$k] = $this->request->getPost($k, $filter);
        }

        // Присвоение одной операцией
        $user->assign($input, array_keys($fields));

        // Пароль отдельно
        $pwd = $this->request->getPost('password', 'trim');
        $password_changed = false;
        if ($pwd !== null && $pwd !== '') {
            $user->password = password_hash(getenv('NEW_SALT') . $pwd, PASSWORD_DEFAULT);
            $password_changed = true;
        }

        // Сохранение
        if (!$user->save()) {
            foreach ($user->getMessages() as $m) {
                $this->flash->error($m);
                $this->logAction($m, 'account');
            }
            return $this->response->redirect("/users/edit/{$user->id}");
        }

        // Снимок "после" и список изменений
        $after   = $user->toArray(array_keys($fields));
        $changes = [];

        foreach ($fields as $k => $_) {
            if (($before[$k] ?? null) != ($after[$k] ?? null)) {
                $changes[] = strtoupper($k);
            }
        }
        if ($password_changed) {
            $changes[] = 'PASSWORD';
        }
        // Подмена метки для роли
        if (in_array('ROLE_ID', $changes, true)) {
            $role = Role::findFirstById($after['role_id']);
            $name = $role ? (' ' . $role->name) : '';
            $changes[array_search('ROLE_ID', $changes, true)] = 'ROLE' . $name;
        }

        // Лог
        $ul = new UserLogs();
        $ul->user_id          = $auth->id;
        $ul->affected_user_id = $user->id;
        $ul->action           = 'CHANGE' . (empty($changes) ? '' : ' ' . implode(' ', $changes));
        $ul->dt               = time();
        $ul->info             = json_encode(
            ['before' => $before, 'after' => $after, 'password_status' => $password_changed ? 'changed' : 'unchanged'],
            JSON_UNESCAPED_UNICODE
        );
        $ul->ip               = $this->request->getClientAddress();
        $ul->save();

        $logString = json_encode($ul->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $this->logAction($logString,'account');
        $this->flash->success("Сохранено успешно.");

        return $this->response->redirect("/users/index/");
    }

    public function shadowAction($uid): ResponseInterface
    {
        // Валидация ввода
        if (!is_numeric($uid) || $uid <= 0) {
            return $this->response->redirect("/users");
        }

        // Получение текущего пользователя
        $auth = User::getUserBySession();

        // Права доступа
        if (!$auth->isAdminSoft()) {
            $this->logAction("Попытка просмотр пользователя в режиме тени: {$uid}", 'security', 'ALERT');
            return $this->response->redirect("/users");
        }

        // Получение пользователя по UID
        $user = User::findFirstById($uid);
        if ($user === false) {
            return $this->response->redirect("/users");
        }

        $this->logAction("Просмотр пользователя в режиме тени: {$user->id}", 'account');

        // Создание записи в логах
        $ul = new UserLogs();
        $ul->user_id = $auth->id;
        $ul->action = 'SHADOW';
        $ul->affected_user_id = $user->id;
        $ul->dt = time();
        $ul->info = json_encode([$user]);
        $ul->ip = $this->request->getClientAddress();
        $ul->save();
        $logString = json_encode($ul->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $this->logAction($logString,'account');

        // Обновление сессий и установка данных
        $this->session->set("__settings", (object)[
            'iin' => htmlspecialchars($user->idnum),
            'bin' => htmlspecialchars($user->bin),
        ]);

        $session = Session::findFirstBySessionId($this->session->get("auth")['session_id']);
        $session->user_id = $user->id;
        $session->save();

        $this->session->set("auth", [
            'session_id' => $this->session->get("auth")['session_id'],
            "id" => $user->id,
            "email" => htmlspecialchars($user->email), // Защита от XSS
            "shadow_mode" => true,
            "type" => $user->user_type_id,
            "view_mode" => $user->view_mode,
            "fund_stage" => $user->fund_stage,
            "pir_stage" => $user->pir_stage,
            "fund_user" => $user->fund_user,
            "lang" => $user->lang,
        ]);

        return $this->response->redirect("/home/");
    }


    public function resetMailAction($uid): ResponseInterface
    {
        $user = User::findFirstById($uid);
        $user->email = NULL;
        $user->email_verified = 0;
        $user->login_attempts = NULL;
        $user->last_attempt = NULL;
        $user->last_reset = NULL;
        $user->password_expiry = NULL;
        $user->save();

        $auth = User::getUserBySession();
        if(!$auth->isAdminSoft()){
            $this->logAction("Попытка сброса почтового адреса пользователя: " . $user->id, 'security', 'ALERT');
        }

        $this->logAction("Сброс почтового адреса пользователя: " . $user->id, 'account');

        $this->flash->success("Почта сброшена.");
        return $this->response->redirect("/users/edit/" . $uid);
    }

    public function resetPasswordAction($uid): ResponseInterface
    {
        $auth = User::getUserBySession();
        $user = User::findFirstById($uid);
        $link = HTTP_ADDRESS . "/session/restore/" . genAppHash($user->id . $user->email . SALT) . "/" . $user->id;
        $this->generateMail($user->email, $this->translator->_("restore-subject"), null, $link, "restore");
        if(!$auth->isAdminSoft()){
            $this->logAction("Попытка отправки письмо для сброса пароля: " . $user->id, 'security', 'ALERT');
        }
        $this->logAction("Письмо для сброса пароля отправлен: " . $user->id, 'account');

        $this->flash->success("Письмо для сброса пароля отправлен.");
        return $this->response->redirect("/users/edit/" . $uid);
    }

    private function generateMail($email, $subject, $body, $link, $template): bool
    {
        $t_html = file(APP_PATH . '/app/templates/mail/' . $template . '.html');
        $t_html = implode('', $t_html);
        $t_text = file(APP_PATH . '/app/templates/mail/' . $template . '.txt');
        $t_text = implode('', $t_text);
        if ($link) {
            $t_html = str_replace('TEMPLATE_LINK', $link, $t_html);
            $t_text = str_replace('TEMPLATE_LINK', $link, $t_text);
        }
        if ($body) {
            $t_html = str_replace('TEMPLATE_BODY', $body, $t_html);
            $t_text = str_replace('TEMPLATE_BODY', $body, $t_text);
        }
        return $this->mailService->sendMail($email, $subject, $t_text, null, $t_html);
    }

}
