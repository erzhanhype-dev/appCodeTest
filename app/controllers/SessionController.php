<?php

namespace App\Controllers;

use App\Exceptions\AppException;
use App\Services\Auth\AuthService;
use App\Services\Auth\DTO\SecureRequestDto;
use ControllerBase;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\View;
use PHPMailer\PHPMailer\Exception;
/**
 * @property AuthService $authService
 */
class SessionController extends ControllerBase
{
    public function indexAction(): View
    {
        $this->view->setVars([
            'hash' => $this->authService->generateServerHash()
        ]);

        return $this->view->pick('session/index');
    }

    public function errorAction(): View
    {
        return $this->view->pick('session/error');
    }

    public function expiredAction(): View
    {
        return $this->view->pick('session/expired');
    }

    public function verificationAction(): View
    {
        $secureSession = $this->authService->getSecureSession();
        $this->view->setVars([
            'secure' => $secureSession,
        ]);

        return $this->view->pick('session/verification');
    }

    public function authAction(): View|ResponseInterface
    {
        if (!$this->authService->hasSecureSession()) {
            return $this->response->redirect("/session");
        }

        $this->view->setVars([
            'secure' => $this->authService->getSecureSession()
        ]);

        return $this->view->pick('session/auth');
    }

    public function updateAction(): View
    {
        $email = '';
        $secureSession = $this->authService->getSecureSession();

        if ($secureSession) {
            $user_data = $secureSession['user_data'];
            $user = $this->userService->findUserBySecureSession($user_data);
            $email = $user ? $user->email : '';
        }

        $this->view->setVars([
            'email' => $email
        ]);

        return $this->view->pick('session/update');
    }

    public function resetAction($md = null, $id = null): View|ResponseInterface
    {
        if (!$md || !$id) {
            $this->flash->error("Некорректная ссылка для восстановления пароля!");
            $this->logAction("Некорректная ссылка для восстановления пароля: $md / $id", 'auth');
            return $this->response->redirect("/session/error");
        }

        $user = $this->userService->findUserById($id);

        if (!$user) {
            $this->flash->error("Пользователь не найден!");
            $this->logAction("Пользователь не найден: $id", 'auth');
            return $this->response->redirect("/session/error");
        }

        $check = $this->authService->generateRestoreHash($user);
        $result = ($md == $check);

        if(!$result){
            $this->logAction("Некорректная ссылка для восстановления пароля: $check", 'auth');
        }

        $this->view->setVars([
            "result" => $result,
            "md" => $md,
            "id" => $id
        ]);

        return $this->view->pick('session/reset');
    }

    public function forgotPasswordAction()
    {
        $secureSession = $this->authService->getSecureSession();
        if (!$secureSession) {
            $this->logAction("Сессия истекла", 'auth');
            return $this->response->redirect("/session");
        }

        $user = $this->userService->findUserBySecureSession($secureSession['user_data']);
        $this->view->setVars([
            'email' => $user ? $user->email : ''
        ]);

        return $this->view->pick('session/forgot');
    }

    public function resetEdsAction(): View|ResponseInterface
    {
        // POST: проверка подписи + сброс доступа
        if ($this->request->isPost()) {
            $hash = (string)$this->request->getPost('hash', 'string', '');
            $sign = (string)$this->request->getPost('sign', 'string', '');

            $result = $this->authService->resetAccessFromEds($hash, $sign);

            if (!$result->success) {
                $this->flash->error($result->message);
                return $this->response->redirect($result->redirectUrl ?? '/session/reset_eds');
            }

            $this->logAction('Сброс доступа через ЭЦП выполнен успешно', 'auth');
            return $this->response->redirect($result->redirectUrl ?? '/session/registration');
        }else{
            $this->view->setVar('hash', $this->authService->buildResetEdsHashForView());
        }

        return $this->view;
    }

    public function registrationAction()
    {
        if (!$this->authService->hasSecureSession()) {
            $this->logAction("Сессия истекло", 'auth');
            return $this->response->redirect("/session/");
        }

        $user = $this->authService->getSecureSessionUser();
        if ($user && $user->email && $user->email_verified) {
            return $this->response->redirect("/session/login");
        }

        $registrationSession = $this->authService->getRegistrationSession();
        $this->view->setVars([
            'registration' => $registrationSession
        ]);

        return $this->view->pick('session/registration');
    }

    /**
     * @throws AppException
     */
    public function secureAction(): ResponseInterface
    {
        try {
            $pem = (string)$this->request->getPost('pem', 'string', '');
            $hash = (string)$this->request->getPost('hash', 'string', '');

            $dto = new SecureRequestDto(
                pemCertificate: $pem,
                authHash: $hash
            );
        } catch (AppException $e) {
            $this->logAction($e->getMessage(), 'auth');
            $this->flash->error($e->getMessage());
            return $this->response->redirect("/secure/error");
        }

        $secure = $this->authService->secure($dto);

        if (!$secure->success) {
            $this->flash->error($secure->message);
            return $this->response->redirect("/session/error");
        }

        $this->logAction('Авторизация по ЭЦП прошло успешно', 'auth');

        return $this->response->redirect($secure->redirectUrl);
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function loginAction()
    {
        $password = $this->request->getPost("password");
        $authenticate = $this->authService->authenticate($password);
        if (!$authenticate->success) {
            $this->flash->error($authenticate->message);
            return $this->response->redirect($authenticate->redirectUrl);
        }

        $this->logAction('Авторизация по паролю прошло успешно', 'auth');
        return $this->response->redirect("/");
    }

    /**
     * @throws Exception
     */
    public function signupAction(): ResponseInterface
    {
        if ($this->request->isPost()) {
            $password = $this->request->getPost("reg_pass");
            $password_confirm = $this->request->getPost("reg_pass_again");
            $email = $this->request->getPost("reg_email");
            $registration = $this->authService->registration($email, $password, $password_confirm);

            if (!$registration->success) {
                $this->flash->error($registration->message);
                return $this->response->redirect("/session/registration");
            }
        }

        $this->logAction('Регистрация пользователя прошло успешно', 'auth');
        return $this->response->redirect("/session/verification");
    }

    public function verifyAction(): ResponseInterface
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect("/session/verification");
        }

        $code = $this->request->getPost('code');
        $verify = $this->authService->verify($code);
        if (!$verify->success) {
            $this->flash->error($verify->message);
            return $this->response->redirect("/session/verification");
        }

        $this->logAction('Верификация пользователя прошло успешно', 'auth');
        return $this->response->redirect("/session/auth");
    }

    public function resetPasswordAction($md, $id)
    {
        $user = $this->userService->findUserById($id);
        $password = $this->request->getPost("restore_pass");
        $password_confirm = $this->request->getPost("restore_pass_again");
        $reset = $this->authService->resetPassword($user, $password, $password_confirm, $md);

        if (!$reset->success) {
            $this->flash->error($reset->message);
        } else {
            $this->flash->success($reset->message);
            return $this->response->redirect("/session/auth");
        }

        return $this->response->redirect("/session/reset/$md/$id");
    }

    /**
     * @throws Exception
     */
    public function sendPasswordResetLinkAction(): ResponseInterface
    {
        $email = $this->request->getPost("forgot_email");

        $reset = $this->authService->sendPasswordResetLink($email);

        if (!$reset->success) {
            $this->flash->error($reset->message);
        } else {
            $this->flash->success($reset->message);
        }

        return $this->response->redirect("/session/forgot_password");
    }

    public function signoutAction(): ResponseInterface
    {
        $user = $this->authService->getCurrentUser();
        if ($user) {
            $this->logAction("Выход из системы пользователя: " . $user->id, 'auth');
        } else {
            $this->logAction("Попытка выхода из системы (сессия уже неактивна)", 'auth');
        }
        $this->authService->logout();
        return $this->response->redirect("/session");
    }
}
