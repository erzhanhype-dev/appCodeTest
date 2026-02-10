<?php

namespace App\Services\Auth\Support;

use LoginAttempt;
use Phalcon\Http\RequestInterface;

/**
 * Logs authentication attempts.
 * Responsibility: persist LoginAttempt record.
 */
final class LoginAttemptLogger
{
    private RequestInterface $request;
    private IpInfoService $ipInfoService;

    public function __construct(RequestInterface $request, IpInfoService $ipInfoService)
    {
        $this->request = $request;
        $this->ipInfoService = $ipInfoService;
    }

    public function log(int $userId, string $status): void
    {
        $loginAttempt = new LoginAttempt();
        $loginAttempt->user_id = $userId;
        $loginAttempt->device_info = (string)$this->request->getUserAgent();
        $loginAttempt->login_time = date('Y-m-d H:i:s');
        $loginAttempt->geolocation_info = $this->ipInfoService->resolveCurrentIpLocation();
        $loginAttempt->ip = getUserIP();
        $loginAttempt->status = $status;
        $loginAttempt->save();
    }
}
