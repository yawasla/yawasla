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
        $mail = new PHPMailer(true);

        try {
            if ($config->get('MAIL_DRIVER') === 'smtp') {
                $mail->isSMTP();
                $mail->Host = $config->get('SMTP_HOST');
                $mail->Port = (int) $config->get('SMTP_PORT', '465');
                $mail->SMTPSecure = $mail->Port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->SMTPAuth = $config->get('SMTP_USER') !== '';
                $mail->Username = $config->get('SMTP_USER');
                $mail->Password = $config->get('SMTP_PASSWORD');
            }

            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($config->get('MAIL_FROM'));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();

            return true;
        } catch (Exception $e) {
            error_log('Mail failed: ' . $e->getMessage());

            return false;
        }
    }
}
