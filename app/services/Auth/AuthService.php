<?php

namespace App\Services\Auth;

use App\Exceptions\AppException;
use App\Helpers\LogTrait;
use App\Services\Auth\DTO\AuthResponseDTO;
use App\Services\Auth\DTO\SecureRequestDto;
use LoginAttempt;
use Phalcon\Di\Injectable;
use PHPMailer\PHPMailer\Exception;
use UsedEdsHash;
use User;
use UserLogs;
class AuthService extends Injectable
{
    use LogTrait;

    private const string SESSION_REGISTRATION_KEY = 'registration';
    private const string SESSION_SECURE_KEY = 'secure';
    private array $secureSession;
    private array $registrationSession;
    private string $serverHash;
    private int $maxAttempts;
    private int $lockoutTime;

    public function __construct()
    {
        $this->maxAttempts = 5;
        $this->lockoutTime = 900; // 15 минут
        $this->serverHash = getenv('NEW_SALT') . 'AUTH01' . ZHASYL_DAMU_BIN;
        $this->secureSession = (array)$this->session->get(self::SESSION_SECURE_KEY, []);
        $this->registrationSession = (array)$this->session->get(self::SESSION_REGISTRATION_KEY, []);
    }

    /**
     * @throws AppException
     */
    public function secure(SecureRequestDto $dto): AuthResponseDTO
    {
        $pemCertificate = $dto->pemCertificate;
        $authHash = $dto->authHash;

        // 1) Валидация входных данных
        if (!$this->validateAuthRequest($pemCertificate, $authHash)) {
            return $this->errorResponse('Некорректные данные запроса.');
        }

        // 2) Проверка повторного использования хэша (если важно — можно делать до CMS-запроса)
        if ($this->isHashUsed($authHash)) {
            return $this->errorResponse('Данные для входа некорректны. Попробуйте еще раз.');
        }

        $plain = base64_decode($authHash, true);
        if ($plain === false || !password_verify($this->serverHash, $plain)) {
            return $this->errorResponse('ЭЦП не прошел валидацию. Попробуйте еще раз.');
        }

        // 4) Обращение к CMS
        $authResult = $this->cmsService->auth($authHash, $pemCertificate);
        if (!is_array($authResult) || empty($authResult['success'])) {
            $msg = is_array($authResult) && !empty($authResult['message'])
                ? (string)$authResult['message']
                : 'Сервис авторизации временно недоступен.';
            return $this->errorResponse($msg);
        }

        // 5) Парсинг данных пользователя
        $parsedUserData = $this->cmsService->parseUserData($authResult['data'] ?? null);
        $user = $this->getUserByParsedData($parsedUserData);

        if ($user && $this->userService->isEmployeeUserBlocked($user)) {
            return $this->errorResponse('Ваша учетная запись была заблокирована. Обратитесь администратору.');
        }

        // 6) Обновление/создание пользователя и установка сессии
        $user = $this->userService->updateOrCreateUserFromEdsData($parsedUserData);
        $this->storeSecureSession($user, $parsedUserData);

        // 7) Помечаем hash как использованный
        $this->saveUsedHash($authHash);

        // 8) Роутинг по результату готовности
        if ($this->isUserReadyForAuth($user)) {
            return $this->successResponse('', '/session/auth');
        }

        return $this->successResponse('', '/session/registration');
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function authenticate($password): AuthResponseDTO
    {
        $secure_session = $this->getSecureSession();

        $uid = (int)($secure_session['uid'] ?? 0);
        if ($uid <= 0) {
        return $this->errorResponse('Сессия истекла.', '/session/auth');
        }

        $user = $this->userService->findUserById($uid);
        if (!$user) {
        return $this->errorResponse('Пользователь не найден.', '/session/auth');
        }

        if ($this->isAccountLocked($user)) {
            return $this->errorResponse('Аккаунт заблокирован. Попробуйте позже.', '/session/auth');
        }

        if (!password_verify(getenv('NEW_SALT') . $password, $user->password)) {
            $this->logLoginAttempt($user->id, 'failure');
            $this->userService->incrementLoginAttempts($user);
            return $this->errorResponse('Пароль неверный.', '/session/auth');
        }

        if (!$this->userService->isUserEmployee($user)) {
            return $this->errorResponse('Для вход в систему используйте ЭЦП Жасыл Даму.', '/session/auth');
        }

        if ($this->userService->isUserPasswordExpired($user)) {
            return $this->errorResponse('Срок действия пароля истек. Пожалуйста, смените пароль.', '/session/auth');
        }

        $session = $this->sessionService->create($user);
        $this->setAuthSessionData($user, $session);

        $this->userService->resetUserLoginState($user);

        $this->logLoginAttempt($user->id, 'success');

        if (in_array($user->role->name, ['admin_soft', 'auditor', 'moderator', 'super_moderator'])) {
            $this->authCoreService($user->idnum, $user->email, $user->role->id);
        }

        return $this->successResponse();
    }

    /**
     * @throws Exception
     */
    public function registration($email, $password, $password_confirm): AuthResponseDTO
    {
        $this->setRegistrationSessionData($email, $password, $password_confirm);

        if (!$this->passwordService->validatePassword($password)) {
            return $this->errorResponse('Пароль не заполнен.');
        }

        if (!$this->passwordService->validatePasswordConfirmation($password, $password_confirm)) {
            return $this->errorResponse('Подтверждаемый пароль неверный.');
        }

        if (!$this->passwordService->isPasswordSecure($password)) {
            return $this->errorResponse('Пароль не соответствует требованиям безопасности.');
        }

        $secure_session = $this->getSecureSession();
        $uid = $secure_session['uid'];
        if ($this->userService->emailExists($email, $uid)) {
            return $this->errorResponse('Пользователь с таким email уже существует, введите другой email адрес.');
        }

        $user = $this->userService->findUserById($uid);
        if (!$user) {
            return $this->errorResponse('Пользователь не найден.');
        }

        $wait = $this->sessionRateLimitWait('auth_verification');
        if ($wait > 0) {
            return $this->errorResponse("Частая отправка почты. Повторите через {$wait} сек.");
        }

        $verification_code = $this->verificationService->generateCode();

        if ($this->sendVerificationEmail($email, $verification_code)) {
            $this->updateSecureSessionWithVerificationCode($verification_code);
            $this->sessionRateLimitCommit('auth_verification');
        } else {
            return $this->errorResponse('Не удалось отправить подтверждение на указанный email, попробуйте повторить позже');
        }

        return $this->successResponse();
    }

    public function verify($value): AuthResponseDTO
    {
        $code = trim((string)$value);
        if (!$this->verificationService->validateCode($code)) {
            return $this->errorResponse('Код подтверждения должен содержать ровно 6 цифр.');
        }

        $secure = $this->getSecureSession();
        $storedCode = (string)($secure['verification_code'] ?? '');

        if (!$this->verificationService->verifyCode($code, $storedCode)) {
            return $this->errorResponse('Неверный код подтверждения.');
        }

        $uid = (int)($secure['uid'] ?? 0);
        if ($uid <= 0) {
            return $this->errorResponse('Сессия истекла.');
        }

        $user = $this->userService->findUserById($uid);
        if (!$user) {
            return $this->errorResponse('Пользователь не найден.');
        }

        $reg = $this->getRegistrationSession();

        $this->userService->completeUserRegistration(
            $user,
            (string)($reg['email'] ?? ''),
            (string)($reg['password'] ?? '')
        );

        $this->session->remove(self::SESSION_REGISTRATION_KEY);

        return $this->successResponse();
    }

    /**
     * @throws Exception
     */
    /**
     * Отправка ссылки на восстановление пароля.
     *
     * @throws Exception
     */
    public function sendPasswordResetLink($email): AuthResponseDTO
    {
        $email = trim((string)$email);
        if ($email === '') {
            return $this->errorResponse('Введите ваш email');
        }

        $secure = $this->getSecureSession();
        if (empty($secure['user_data'])) {
            return $this->errorResponse('Сессия истекла.');
        }

        $userData = $this->userService->findUserBySecureSession($secure['user_data']);
        $user = $this->userService->findUserByEmailAndIdnum($email, $userData->idnum);

        if (!$user) {
            return $this->errorResponse((string)$this->translator->query('email-not-verify'));
        }

        if ($user->last_reset && !$this->canRequestPasswordReset($user->last_reset)) {
            return $this->errorResponse('Сброс пароля возможен не чаще чем через 15 минут!');
        }

        $wait = $this->sessionRateLimitWait('password_reset');
        if ($wait > 0) {
            return $this->errorResponse("Частая отправка почты. Повторите через {$wait} сек.");
        }

        $link = $this->generatePasswordResetLink($user);

        if (!$this->sendPasswordResetEmail((string)$user->email, $link)) {
            return $this->errorResponse('Не удалось отправить. Попробуйте позже.');
        }

        $this->sessionRateLimitCommit('password_reset');

        return $this->successResponse(
            (string)$this->translator->query('email-was-sended') . ' ' . $user->email . '. Проверьте свой почтовый ящик.'
        );
    }

    public function resetPassword($user, $password, $password_confirm, $hash): AuthResponseDTO
    {
        $check = $this->generateRestoreHash($user);

        if ($hash !== $check) {
            return $this->errorResponse('Ссылка на воостановление пароля не корректная.');
        }

        if ($password === '' || $password_confirm === '' || $password !== $password_confirm) {
            return $this->errorResponse($this->translator->query("bad-or-wrong-password"));
        }

        if ($this->passwordService->isRecentPassword($user, $password)) {
            return $this->errorResponse('Пароль использовался недавно. Пожалуйста, выберите другой пароль.');
        }

        if (!$this->passwordService->isPasswordSecure($password)) {
            return $this->errorResponse('Пароль не соответствует требованиям безопасности.');
        }

        $this->passwordService->updatePassword($user, $password);

        return $this->successResponse($this->translator->query("password-is-changed"));
    }

    public function getSecureSession(): array
    {
        return (array)$this->session->get(self::SESSION_SECURE_KEY, []);
    }

    private function isUserReadyForAuth(User $user): bool
    {
        return $this->userService->hasValidPassword($user)
            && $this->userService->hasEmail($user)
            && $this->userService->isEmailVerified($user);
    }

    public function generateServerHash(): string
    {
        $hash = password_hash(getenv('NEW_SALT') . 'AUTH01' . ZHASYL_DAMU_BIN, PASSWORD_DEFAULT);
        return base64_encode($hash);
    }

    private function validateAuthRequest(string $pem, string $hash): bool
    {
        return $this->validatePem($pem) && $this->validateHash($hash);
    }

    public function hasSecureSession(): bool
    {
        return $this->session->has(self::SESSION_SECURE_KEY);
    }

    public function getSecureSessionUser(): ?User
    {
        $secure = $this->getSecureSession();
        $uid = (int)($secure['uid'] ?? 0);
        return $uid > 0 ? User::findFirstById($uid) : null;
    }

    private function storeSecureSession(User $user, array $user_data): void
    {
        $this->session->set("secure", [
            "uid" => $user->id,
            'user_data' => $user_data,
            'is_employee' => $user->is_employee
        ]);

        $this->session->set("__settings", $user_data);
    }

    public function updateSecureSessionWithVerificationCode(string $code): void
    {
        $secure_session = $this->session->get('secure');
        $secure_session['verification_code'] = $code;
        $this->session->set("secure", $secure_session);
    }

    public function getCurrentUser(): ?User
    {
        return $this->sessionService->getAuthenticatedUser();
    }

    public function getRegistrationSession(): array
    {
        return (array)$this->session->get(self::SESSION_REGISTRATION_KEY, []);
    }

    public function logout(): void
    {
        $this->sessionService->delete();
    }

    public function isAccountLocked(User $user): bool
    {
        if ($user->login_attempts >= $this->maxAttempts) {
            $diff = time() - $user->last_attempt;
            if ($diff < $this->lockoutTime) {
                return true;
            } else {
                $user->login_attempts = 0;
                $user->save();
            }
        }
        return false;
    }

    public function generateRestoreHash(User $user): string
    {
        return genAppHash($user->id . $user->email . SALT);
    }

    public function generatePasswordResetLink(User $user): string
    {
        return HTTP_ADDRESS . "/session/reset/" . $this->generateRestoreHash($user) . "/" . $user->id;
    }

    private function validatePem(string $pem): bool
    {
        return (bool)preg_match("/-----BEGIN CMS-----([A-Za-z0-9+\/=\s]+)-----END CMS-----/", $pem);
    }

    private function validateHash(string $hash): bool
    {
        // ожидается base64-строка, полученная из generateServerHash()
        return (bool)preg_match('/^[A-Za-z0-9+\/=]+$/', $hash);
    }

    private function setAuthSessionData(User $user, $session): void
    {
        $this->session->set("auth", [
            'session_id' => $session->session_id,
            "id" => $user->id,
            "type" => $user->user_type_id,
            "fund_stage" => $user->fund_stage,
            "pir_stage" => $user->pir_stage,
            "view_mode" => $user->view_mode,
            "lang" => $user->lang,
        ]);
    }

    private function setRegistrationSessionData($email, $password, $password_confirm): void
    {
        $this->session->set("registration", [
            "email" => $email,
            "password" => $password,
            "password_confirm" => $password_confirm,
        ]);
    }

    private function getDeviceInfo(): string
    {
        return $this->request->getUserAgent();
    }

    private function getGeolocationInfoSecure(): string
    {
        $ip = (string)$this->request->getClientAddress(true);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'Local/Private IP';
        }

        // Кеш на сутки, чтобы не упираться в лимиты ipinfo
        $cacheDir = '/var/www/recycle/storage/ipinfo';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $cacheFile = $cacheDir . '/' . preg_replace('~[^0-9a-fA-F:\.]~', '_', $ip) . '.json';

        if (is_file($cacheFile) && filemtime($cacheFile) > time() - 86400) {
            $cached = file_get_contents($cacheFile);
            $details = json_decode($cached ?: '', true);
            if (is_array($details) && !empty($details)) {
                return $this->formatIpInfo($details);
            }
            // если кеш битый — идём в сеть
        }

        $url = "https://ipinfo.io/{$ip}/json";

        $token = getenv('IPINFO_TOKEN');
        if ($token) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'token=' . rawurlencode($token);
        }

        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 2,
                'ignore_errors' => true, // чтобы получить body даже при 4xx/5xx
                'header'        => "User-Agent: recycle-app\r\nAccept: application/json\r\n",
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $json = @file_get_contents($url, false, $context);

        $status = 0;
        if (isset($http_response_header[0]) && preg_match('~HTTP/\S+\s+(\d+)~', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }

        if ($json === false) {
            return 'Геолокация недоступна';
        }

        if ($status === 429) {
            return 'Геолокация временно недоступна (лимит запросов)';
        }

        if ($status >= 400) {
            return 'Геолокация недоступна';
        }

        $details = json_decode($json, true);
        if (!is_array($details) || empty($details)) {
            return 'Некорректный ответ API';
        }

        // Сохраняем в кеш
        @file_put_contents($cacheFile, $json);

        return $this->formatIpInfo($details);
    }

    private function formatIpInfo(array $details): string
    {
        $data = [];
        foreach (['city', 'region', 'country', 'org'] as $key) {
            if (!empty($details[$key]) && is_string($details[$key])) {
                $data[] = $details[$key];
            }
        }
        return $data ? implode(', ', $data) : 'Геолокация недоступна';
    }

    public function logLoginAttempt($userId, $status)
    {
        $loginAttempt = new LoginAttempt();
        $loginAttempt->user_id = $userId;
        $loginAttempt->device_info = $this->getDeviceInfo();
        $loginAttempt->login_time = date('Y-m-d H:i:s');
        $loginAttempt->geolocation_info = $this->getGeolocationInfoSecure();
        $loginAttempt->ip = getUserIP();
        $loginAttempt->status = $status;
        $loginAttempt->save();

        $logString = json_encode($loginAttempt->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $this->writeLog($logString,'account');
    }

    public function getLastLoginAttempts($userId)
    {
        return LoginAttempt::find([
            'conditions' => 'user_id = :user_id:',
            'bind' => [
                'user_id' => $userId
            ],
            'order' => 'login_time DESC',
            'limit' => 3
        ]);
    }

    private function getUserByParsedData(array $parsed_user_data): ?User
    {
        $idnum = $this->userService->getIdNum(
            $parsed_user_data['iin'],
            $parsed_user_data['bin'],
            $parsed_user_data['eku']
        );

        return $this->userService->getUserByIdnum($idnum);
    }

    /**
     * @throws Exception
     */
    public function sendVerificationEmail(string $email, string $code): bool
    {
        $t_text = file(APP_PATH . '/app/templates/mail/verify.txt');
        $t_text = implode('', $t_text);
        $subject = $this->translator->query('verify-subject');
        $t_text = str_replace('TEMPLATE_BODY', $code, $t_text);

        return $this->mailService->sendMail($email, $subject, $t_text, null, null);
    }

    /**
     * @throws Exception
     */
    public function sendPasswordResetEmail(string $email, string $link): bool
    {
        $subject = $this->translator->query('restore-subject');
        $t_text = file(APP_PATH . '/app/templates/mail/restore.txt');
        $t_text = implode('', $t_text);
        $t_text = str_replace('TEMPLATE_LINK', $link, $t_text);
        return $this->mailService->sendMail($email, $subject, $t_text, null, null);
    }

    private function saveUsedHash($clientHash): void
    {
        $newUsedHash = new UsedEdsHash();
        $newUsedHash->hash = $clientHash;
        $newUsedHash->created = time();
        $newUsedHash->save();
    }

    private function isHashUsed($clientHash): bool
    {
        return UsedEdsHash::count([
                'conditions' => 'hash = :hash:',
                'bind'       => ['hash' => $clientHash]
            ]) > 0;
    }

    public function canRequestPasswordReset($last_reset): bool
    {
        if (is_null($last_reset)) {
            return true;
        }

        $currentTime = time();
        $interval = $currentTime - $last_reset;

        return $interval >= 15 * 60; // 15 минут в секундах
    }

    private function authCoreService($login, $email, $role_id): string
    {
        $data = [
            'name' => $login,
            'password' => $this->config->core_service->password,
        ];

        if ($email !== null) {
            $data['email'] = $email;
            $data['role_id'] = $role_id;
        }

        $ch = curl_init($this->config->core_service->base_url . '/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            new AppException('Ошибка cURL: ' . $error);
            return '';
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            new AppException('Ошибка HTTP: ' . $httpCode . ' Ответ: ' . $response);
            $this->writeLog('Ошибка HTTP: ' . $httpCode . ' Ответ: ' . $response, 'auth', 'WARNING');
            return '';
        }

        $json = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            new AppException('Ошибка декодирования JSON: ' . json_last_error_msg());
            $this->writeLog('Ошибка декодирования JSON: ' . json_last_error_msg(), 'auth', 'WARNING');
            return '';
        }

        if (!isset($json['token']) || !is_string($json['token']) || $json['token'] === '') {
            new AppException('В ответе нет корректного поля token. Ответ: ' . $response);
            $this->writeLog('В ответе нет корректного поля token. Ответ: ' . $response, 'auth', 'WARNING');
            return '';
        }

        $this->session->set('sanctum_token', $json['token']);

        return $json['token'];
    }

    private function sessionRateLimitWait(string $context = 'auth_verification'): int
    {
        $key = 'rl:last:' . $context;
        $last = (int)($_SESSION[$key] ?? 0);
        $diff = time() - $last;

        return ($diff < 300) ? (300 - $diff) : 0;
    }

    private function sessionRateLimitCommit(string $context = 'auth_verification'): void
    {
        $_SESSION['rl:last:' . $context] = time();
    }

    public function resetAccessFromEds(string $hash, string $sign): AuthResponseDTO
    {
        $hash = (string)$hash;
        $sign = (string)$sign;

        $secure = $this->getSecureSession();
        if (empty($secure)) {
            return $this->errorResponse('Сессия истекла.', '/session');
        }

        $result = $this->cmsService->check($hash, $sign);
        if (!is_array($result) || empty($result['success'])) {
            $msg = is_array($result) && !empty($result['message'])
                ? (string)$result['message']
                : 'Не удалось сбросить пароль через ЭЦП';
            return $this->errorResponse($msg, '/session/reset_eds');
        }

        $userData = (array)($result['data'] ?? []);

        $user = User::findFirstByIdnum((string)($userData['iin'] ?? ''));
        if (!empty($userData['bin']) && (string)$userData['bin'] !== (string)ZHASYL_DAMU_BIN) {
            $user = User::findFirstByIdnum((string)$userData['bin']);
        }

        if (!$user) {
            return $this->errorResponse('Пользователь не найден в системе.', '/session/reset_eds');
        }

        if ($user->last_reset && !$this->canRequestPasswordReset($user->last_reset)) {
            return $this->errorResponse('Сброс пароля возможен не чаще чем через 15 минут!', '/session/forgot_password');
        }

        // Сброс доступа
        $user->password = null;
        $user->email = null;
        $user->email_verified = 0;
        $user->login_attempts = null;
        $user->password_expiry = null;
        $user->user_reset_hash = $hash;
        $user->user_reset_sign = $sign;
        $user->last_reset = time();

        if ($user->save()) {
            $this->writeUserResetLog($user, $sign);
        }

        // Обновляем secure-сессию
        $secure['user_data'] = $userData;
        $secure['uid'] = $user->id;
        $this->session->set(self::SESSION_SECURE_KEY, $secure);

        return $this->successResponse('', '/session/registration');
    }

    /**
     * Подготовка hash для страницы reset_eds (GET).
     * Возвращает пустую строку, если hash не должен отображаться.
     */
    public function buildResetEdsHashForView(): string
    {
        $secure = $this->getSecureSession();

        if (empty($secure['user_data'])) {
            return '';
        }

        $userData = (array)$secure['user_data'];

        if (!empty($userData['company']) && (int)($secure['is_employee'] ?? 0) === 0) {
            $user = User::findFirstByIdnum((string)($userData['bin'] ?? ''));
        } else {
            $user = User::findFirstByIdnum((string)($userData['iin'] ?? ''));
        }

        if (!$user) {
            return '';
        }

        $text = 'type: сброс учетной записи через ЭЦП;'
            . 'user_id:' . $user->id
            . ';subject_dn:' . (string)($userData['subject_dn'] ?? '')
            . ';reset_time:' . time();

        return base64_encode($text);
    }

    private function writeUserResetLog(User $user, string $sign): void
    {
        try {
            $ul = new UserLogs();
            $ul->user_id = $user->id;
            $ul->action = 'RESET_ACCESS_FROM_EDS';
            $ul->affected_user_id = $user->id;
            $ul->dt = time();

            $arr = $user->toArray();
            $arr['sign'] = $sign;
            unset($arr['password']);

            $ul->info = json_encode($arr, JSON_UNESCAPED_UNICODE);
            $ul->ip = (string)$this->request->getClientAddress();
            $ul->save();

            $logString = json_encode($ul->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
            $this->writeLog($logString,'account');
        } catch (\Throwable $e) {
            // логирование не должно ломать основной поток
        }
    }

    private function errorResponse(string $message = '', ?string $redirectUrl = '/session'): AuthResponseDTO
    {
        $this->writeLog($message, 'auth', 'WARNING');
        return AuthResponseDTO::error($message, $redirectUrl);
    }

    private function successResponse(string $message = '', ?string $url = '/home'): AuthResponseDTO
    {
        $this->writeLog($message, 'auth');
        return AuthResponseDTO::success($message, null, $url);
    }
}
