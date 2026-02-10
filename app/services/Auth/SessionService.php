<?php

namespace App\Services\Auth;

use Phalcon\Di\Injectable;
use Session;
use User;

/**
 * SRP: работа с серверной сессией пользователя (таблица Session) и
 * синхронизация с PHP-сессией.
 */
class SessionService extends Injectable
{
    public function getAuthenticatedUser(): ?User
    {
        $auth = $this->session->get('auth');
        if (!is_array($auth) || empty($auth['id'])) {
            return null;
        }

        return User::findFirstById((int)$auth['id']);
    }

    public function create(User $user): Session
    {
        // удаляем прежнюю запись для пользователя, если есть
        $existing = Session::findFirst([
            'conditions' => 'user_id = :user_id:',
            'bind' => ['user_id' => (int)$user->id],
        ]);

        if ($existing) {
            $existing->delete();
        }

        $session = new Session();
        $session->session_id = (string)session_id();
        $session->user_id = (int)$user->id;
        $session->save();

        return $session;
    }

    public function delete(): void
    {
        $sessionId = (string)session_id();
        if ($sessionId === '') {
            return;
        }

        $session = Session::findFirst([
            'conditions' => 'session_id = :session_id:',
            'bind' => ['session_id' => $sessionId],
        ]);

        if ($session) {
            $session->delete();
        }

        // очищаем auth-данные в PHP-сессии
        if ($this->session->has('auth')) {
            $this->session->remove('auth');
        }
    }
}
