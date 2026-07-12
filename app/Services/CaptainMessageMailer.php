<?php
// app/Services/CaptainMessageMailer.php

use PHPMailer\PHPMailer\PHPMailer;

class CaptainMessageMailer
{
    public static function send(array $captain, string $message): bool
    {
        $toEmail = trim((string)($captain['email'] ?? ''));
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        if (defined('MAIL_ENCRYPTION') && MAIL_ENCRYPTION === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $captainName = (string)($captain['captain_name'] ?? '');

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $captainName);
        $mail->isHTML(true);
        $mail->Subject = 'Adults Swimming Academy - Captain Rules';
        $mail->Body = self::buildHtmlBody($captainName, $message);
        $mail->AltBody = $message;

        $mail->send();
        return true;
    }

    private static function buildHtmlBody(string $captainName, string $message): string
    {
        $safeName = htmlspecialchars($captainName ?: 'Captain', ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>Captain Rules</title>
</head>
<body style="margin:0;padding:24px;background:#f5f7fb;font-family:Arial,'Segoe UI',sans-serif;color:#172033;">
  <div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #dfe5ef;border-radius:10px;overflow:hidden;">
    <div style="padding:18px 22px;background:#102033;color:#ffffff;">
      <h1 style="margin:0;font-size:20px;">Adults Swimming Academy</h1>
      <p style="margin:6px 0 0;font-size:14px;">{$safeName}</p>
    </div>
    <div style="padding:22px;line-height:1.8;font-size:15px;white-space:normal;">
      {$safeMessage}
    </div>
  </div>
</body>
</html>
HTML;
    }
}
