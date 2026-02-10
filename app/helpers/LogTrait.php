<?php

namespace App\Helpers;

use App\Services\Log\EventLogService;
use User;
use Phalcon\Cli\Dispatcher as CliDispatcher;

trait LogTrait
{
    protected function writeLog(string $message, string $type = 'action', string $level = 'INFO'): void
    {
        $typeMap = [
            'security' => 'security',
            'auth'     => 'auth',
            'account'  => 'account',
            'access'   => 'access',
            'action'   => 'action',
        ];
        $logType = $typeMap[$type] ?? $type;

        // normalize level
        $level = strtoupper(trim($level));
        $allowedLevels = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];
        if (!in_array($level, $allowedLevels, true)) {
            $level = 'INFO';
        }

        /** @var EventLogService $logService */
        $logService = $this->di->getShared('eventLogService');

        $dispatcher = $this->di->has('dispatcher') ? $this->di->get('dispatcher') : null;
        $request    = $this->di->has('request') ? $this->di->get('request') : null;
        $controller = 'unknown';
        $action = 'unknown';
        if ($dispatcher) {
            // Если мы в консоли (CLI)
            if ($dispatcher instanceof CliDispatcher) {
                $controller = $dispatcher->getTaskName(); // В CLI это Task
            } else {
                // Если мы в вебе (MVC)
                $controller = $dispatcher->getControllerName(); // В Web это Controller
            }
            $action = $dispatcher->getActionName();
        }

        $uri = $request ? (string)$request->getURI() : '';

        $query  = $request ? $request->getQuery() : [];
        $post   = $request ? $request->getPost() : [];
        $params = $query + $post;

        unset(
            $params['csrfToken'],
            $params['_csrf'],
            $params['_token'],
            $params['csrf_token'],
            $params['password'],
            $params['pem'],
            $params['_url'],
            $params['restore_pass'],
            $params['restore_pass_again'],
            $params['reg_pass'],
            $params['reg_pass_again'],
            $params['profileSign'],
            $params['profileHash'],
            $params['sign']
        );

        $paramsText = json_encode($params, JSON_UNESCAPED_UNICODE);

        $startDt = $this->di->getShared('requestStartDt');
        $start   = $startDt->format('Y-m-d H:i:s.u');

        $endDt = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
        $end   = $endDt->format('Y-m-d H:i:s.u');

        $secureSession = $this->session->get('secure');

        $userId = 'Unauthorized';
        $auth = User::getUserBySession();
        if ($auth) {
            $userId = $auth->id;
        } else {
            if (is_array($secureSession) && isset($secureSession['uid'])) {
                $userId = $secureSession['uid'];
            }
        }

        $ip = $request ? (string)$request->getClientAddress() : '';

        $logService->store(
            $controller . '::' . $action,
            (string)$userId,
            $ip,
            $start,
            $end,
            $message,
            $uri,
            (string)$paramsText,
            $logType,
            $level
        );
    }
}
