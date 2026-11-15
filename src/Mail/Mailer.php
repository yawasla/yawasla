<?php

declare(strict_types=1);

namespace Yawasla\Mail;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Yawasla\Core\Config;

final class Mailer
{
    public function send(string $to, string $subject, string $body): bool
    {
        $config = Config::getInstance();
        $driver = strtolower(trim($config->get('MAIL_DRIVER')));
        $from = trim($config->get('MAIL_FROM'));

        if ($from === '') {
            trigger_error('MAIL_FROM is not set in .env: security emails cannot be sent', E_USER_WARNING);
        }

        if ($driver === 'log') {
            if ($config->get('APP_ENV') === 'dev') {
                return $this->log($to, $subject, $body);
            }

            trigger_error('MAIL_DRIVER=log is ignored outside APP_ENV=dev: the mail is sent with mail()', E_USER_WARNING);
        }

        if ($from === '') {
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            if ($driver === 'smtp') {
                $mail->isSMTP();
                $mail->Host = $config->get('SMTP_HOST');
                $mail->Port = (int) $config->get('SMTP_PORT', '465');
                $mail->SMTPSecure = $mail->Port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->SMTPAuth = $config->get('SMTP_USER') !== '';
                $mail->Username = $config->get('SMTP_USER');
                $mail->Password = $config->get('SMTP_PASSWORD');
            }

            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($from);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();

            return true;
        } catch (Exception $e) {
            trigger_error('Mail failed: ' . $e->getMessage(), E_USER_WARNING);

            return false;
        }
    }

    private function log(string $to, string $subject, string $body): bool
    {
        $entry = '==== ' . date('Y-m-d H:i:s') . " ====\nTo: " . $to . "\nSubject: " . $subject . "\n\n" . $body . "\n";

        return file_put_contents(dirname(__DIR__, 2) . '/data/logs/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }
}
