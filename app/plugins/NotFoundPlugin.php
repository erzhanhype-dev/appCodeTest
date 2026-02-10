<?php
namespace App\Plugins;

use Phalcon\Di\Injectable;
use Phalcon\Events\EventInterface;
use Phalcon\Mvc\Dispatcher as MvcDispatcher;
use Phalcon\Mvc\Dispatcher\Exception as DispatcherException;

final class NotFoundPlugin extends Injectable
{
    public function beforeException(
        EventInterface $event,
        MvcDispatcher  $dispatcher,
        \Throwable     $exception
    ): bool {
        $auth      = $this->session->get('auth');
        $sessionId = \is_array($auth) ? ($auth['session_id'] ?? null) : null;

        // Проверяем, что исключение диспетчера
        if ($exception instanceof DispatcherException) {
            // Phalcon 5.9 возвращает обычный код 404 при not found
            $code = $exception->getCode();

            // 404 — контроллер или экшен не найден
            if ($code === 404) {
                $this->handleForwarding($dispatcher, $sessionId);
                return false;
            }
        }

        // Остальные исключения — стандартная обработка
        return true;
    }

    private function handleForwarding(MvcDispatcher $dispatcher, ?string $sessionId): void
    {
        $dispatcher->forward([
            'controller' => $sessionId ? 'index' : 'session',
            'action'     => $sessionId ? 'route404' : 'index',
        ]);
    }
}
