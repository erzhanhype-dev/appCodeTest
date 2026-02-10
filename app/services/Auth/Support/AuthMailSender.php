<?php

namespace App\Services\Auth\Support;

use PHPMailer\PHPMailer\Exception;

/**
 * Auth mail sender based on text templates (verify / restore).
 * Responsibility: build email body and call mailService.
 */
final class AuthMailSender
{
    private object $translator;
    private object $mailService;
    private string $appPath;

    public function __construct(object $translator, object $mailService, string $appPath)
    {
        $this->translator = $translator;
        $this->mailService = $mailService;
        $this->appPath = rtrim($appPath, '/');
    }

    /**
     * @throws Exception
     */
    public function sendVerification(string $email, string $code): bool
    {
        $subject = (string)$this->translator->query('verify-subject');
        $template = $this->loadTemplate('/app/templates/mail/verify.txt');
        $body = str_replace('TEMPLATE_BODY', $code, $template);

        return (bool)$this->mailService->sendMail($email, $subject, $body, null, null);
    }

    /**
     * @throws Exception
     */
    public function sendPasswordReset(string $email, string $link): bool
    {
        $subject = (string)$this->translator->query('restore-subject');
        $template = $this->loadTemplate('/app/templates/mail/restore.txt');
        $body = str_replace('TEMPLATE_LINK', $link, $template);

        return (bool)$this->mailService->sendMail($email, $subject, $body, null, null);
    }

    private function loadTemplate(string $relativePath): string
    {
        $path = $this->appPath . $relativePath;
        $content = @file_get_contents($path);
        return is_string($content) ? $content : '';
    }
}
