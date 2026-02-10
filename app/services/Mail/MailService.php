<?php
namespace App\Services\Mail;

use App\Helpers\LogTrait;
use Exception;
use Phalcon\Di\Injectable;
use PHPMailer\PHPMailer\PHPMailer;

class MailService extends Injectable
{
    use LogTrait;
    /**
     * @throws Exception
     */
    function sendMail($email, $subject, $body, $attachment_file_path = null, $template = null): bool
    {
        $primaryHost = $this->config->mail->host;
        $backupIp = $this->config->mail->ip;
        // создаем экземпляр класса для работы с почтой и настраиваем его
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mail->Timeout = 10;
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = 'html';
        $mail->Host = "$primaryHost; $backupIp";
        $mail->Port = $this->config->mail->port;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->SMTPAutoTLS = true;
        $mail->SMTPAuth = true;
        $mail->Username = $this->config->mail->username;
        $mail->Password = $this->config->mail->password;
        $mail->setFrom($this->config->mail->username, 'app.recycle.kz');
        $mail->addAddress($email);
        $mail->Subject = $subject;

        if($template) {
            $mail->msgHTML($template);
            $mail->AltBody = strip_tags($template); // Альтернативное текстовое тело для клиентов без поддержки HTML
        } else {
            $mail->isHTML(false);
            $mail->Body = $body;
            $mail->AltBody = $body; // На случай, если тело письма содержит HTML
        }

        if($attachment_file_path != null && file_exists($attachment_file_path)) {
            $mail->AddAttachment($attachment_file_path);
        }

        if (!$mail->send()) {
            $this->writeLog("PHPMailer Error: " . $mail->ErrorInfo, 'action', 'WARNING');
            return false;
        }

        return true;
    }

    /**
     * @throws Exception
     */
    function sendMails($emails, $subject, $body, $attachment_file_path = null, $template = null): bool
    {
        $primaryHost = $this->config->mail->host;
        $backupIp = $this->config->mail->ip;
        // создаем экземпляр класса для работы с почтой и настраиваем его
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mail->Timeout = 10;
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = 'html';
        $mail->Host = "$primaryHost; $backupIp";
        $mail->Port = $this->config->mail->port;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->SMTPAutoTLS = true;
        $mail->SMTPAuth = true;
        $mail->Username = $this->config->mail->username;
        $mail->Password = $this->config->mail->password;
        $mail->setFrom($this->config->mail->username, 'app.recycle.kz');
        foreach ($emails as $email) {
            $mail->addAddress($email);
        }
        $mail->Subject = $subject;

        if($template) {
            $mail->msgHTML($template);
            $mail->AltBody = strip_tags($template); // Альтернативное текстовое тело для клиентов без поддержки HTML
        } else {
            $mail->isHTML(false);
            $mail->Body = $body;
            $mail->AltBody = $body; // На случай, если тело письма содержит HTML
        }

        if($attachment_file_path != null && file_exists($attachment_file_path)) {
            $mail->AddAttachment($attachment_file_path);
        }

        if (!$mail->send()) {
            $this->writeLog("PHPMailer Error: " . $mail->ErrorInfo, 'action', 'WARNING');
            return false;
        }

        return true;
    }
}
