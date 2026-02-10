<?php

use App\Services\Auth\AuthService;
use App\Services\Auth\SessionService;

class AdminController extends ControllerBase
{

    private $maxAttempts = 5;
    private $lockoutTime = 900; // 30 минут

    public function indexAction()
    {
    }

    /**
     * @throws DateMalformedStringException
     */
    public function loginAction()
    {
        $loginService = new AuthService();
        $pass = $this->request->getPost("password");
        $user = User::findFirst([
            'conditions' => 'login = :login:',
            'bind' => [
                'login' => 'admin',
            ]
        ]);

        if ($this->isAccountLocked($user)) {
            $this->flash->error('Аккаунт заблокирован. Попробуйте позже.');
            $this->logAction("Авторизация пользователя, Аккаунт заблокирован: ".$user->id);
            return $this->response->redirect("/admin");
        }

        if (password_verify(getenv('NEW_SALT') . $pass, $user->password)) {
            $this->session->set('can_change_password', true);
            if($this->isPasswordExpired($user->password_expiry)){
                $this->flash->error('Срок действия пароля истек. Пожалуйста, смените пароль.');
                return $this->response->redirect("/admin/registration");
            }
            $session = (new SessionService())->create($user);
            $this->_registerSession($user, $session);
            $user->login_attempts = 0;
            $user->last_login = time();
            $user->save();
            $this->logAction("Авторизация пользователя: " . $user->id);
            $loginService->logLoginAttempt($user->id, 'success');

            $settings = (object)[
                'iin' => 0,
            ];
            $this->session->set("__settings", $settings);
            $this->session->remove('can_change_password');

            return $this->response->redirect("/");
        } else {
            $this->incrementLoginAttempts($user->id);
            $this->flash->error('Пароль неверный!');
            $loginService->logLoginAttempt($user->id, 'failure');
            $this->logAction("Авторизация пользователя: Пароль неверный: " . $user->id);
            return $this->response->redirect("/admin/index");
        }
    }

    public function registrationAction()
    {
        if (!$this->session->get('can_change_password')) {
            return $this->response->redirect('/session');
        }
    }

    public function signupAction(){
        if ($this->request->isPost()) {
            $password = $this->request->getPost("reg_pass");
            $password_confirm = $this->request->getPost("reg_pass_again");
            $this->session->set("registration", [
                "password" => $password,
                "password_confirm" => $password_confirm,
            ]);

            if ($password == '') {
                $this->flash->error('Пароль не заполнен!');
                return $this->response->redirect("/admin/registration");
            }

            if ($password !== $password_confirm) {
                $this->flash->error('Подтверждаемый пароль неверный!');
                return $this->response->redirect("/admin/registration");
            }

            // Проверка сложности пароля
            if (!$this->validatePassword($password)) {
                $this->flash->error('Пароль не соответствует требованиям безопасности!');
                return $this->response->redirect("/admin/registration");
            }

            $user = User::findFirst([
                'conditions' => 'login = :login:',
                'bind' => [
                    'login' => 'admin',
                ]
            ]);

            $recentPasswords = PasswordHistory::find([
                'conditions' => 'user_id = ?1',
                'bind' => [1 => $user->id],
                'order' => 'created_at DESC',
                'limit' => 3
            ]);

            // Проверка уникальности нового пароля
            if (count($recentPasswords) > 0) {
                foreach ($recentPasswords as $oldPassword) {
                    if (password_verify(getenv('NEW_SALT') . $password, $oldPassword->password)) {
                        $this->flash->error('Пароль использовался недавно. Пожалуйста, введите другой пароль.');
                        return $this->response->redirect("/admin/registration");
                    }
                }
            }


            $expiryDate = date('Y-m-d', strtotime(PASSWORD_EXPIRY_DAYS . " days"));
            $passwordHash = password_hash(getenv('NEW_SALT') . $password, PASSWORD_DEFAULT);
            $user->password = $passwordHash;
            $user->password_expiry = $expiryDate;
            $user->active = 1;
            $user->save();

            $passwordHistory = new PasswordHistory();
            $passwordHistory->user_id = $user->id;
            $passwordHistory->password = $passwordHash;
            $passwordHistory->save();

            $this->cleanupOldPasswords($user->id);

            $this->session->set("secure", [
                "uid" => $user->id,
            ]);

            unset($_POST['reg_pass']);
            unset($_POST['reg_pass_again']);


            return $this->response->redirect("/admin");
        }
    }

    private function validatePassword($password): bool
    {
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number = preg_match('@[0-9]@', $password);
        $specialChars = preg_match('@[^\w]@', $password);

        if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
            return false;
        }
        return true;
    }

    private function incrementLoginAttempts($userId): void
    {
        $phql = "UPDATE User SET login_attempts = login_attempts + 1, last_attempt = :last_attempt:, last_login = :last_login: WHERE id = :id:";
        $this->modelsManager->executeQuery(
            $phql,
            [
                'id' => $userId,
                'last_attempt' => time(),
                'last_login' => time()
            ]
        );
    }

    /**
     * @throws DateMalformedStringException
     */
    private function isPasswordExpired($date): bool
    {
        $expiryDate = new DateTime($date);
        $now = new DateTime();
        return $expiryDate < $now;
    }

    private function _registerSession($user, $session): void
    {
        // стандартный код
        $this->session->set("auth", array(
            'session_id' => $session->session_id,
            "id" => $user->id,
            "type" => $user->user_type_id,
            "fund_stage" => $user->fund_stage,
            "pir_stage" => $user->pir_stage,
            "view_mode" => $user->view_mode,
            "lang" => $user->lang,
        ));
    }

    private function cleanupOldPasswords($userId)
    {
        // Получение всех записей паролей для пользователя, отсортированных по id в порядке возрастания
        $passwords = PasswordHistory::find([
            'conditions' => 'user_id = :user_id:',
            'bind' => [
                'user_id' => $userId,
            ],
            'order' => 'id ASC',
        ]);

        // Проверка, нужно ли удалять старые записи
        $totalPasswords = count($passwords);
        if ($totalPasswords > 3) {
            // Оставить только три последние записи
            $passwordsToDelete = [];
            for ($i = 0; $i < $totalPasswords - 3; $i++) {
                $passwordsToDelete[] = $passwords[$i];
            }

            // Удаление старых записей
            foreach ($passwordsToDelete as $password) {
                $password->delete();
            }
        }
    }

    private function isAccountLocked($user): bool
    {
        if ($user->login_attempts >= $this->maxAttempts) {
            $now = new DateTime();
            $diff = $now->getTimestamp() - $user->last_attempt;
            if ($diff < $this->lockoutTime) {
                return true;
            } else {
                $user->login_attempts = 0;
                $user->save();
            }
        }
        return false;
    }

}