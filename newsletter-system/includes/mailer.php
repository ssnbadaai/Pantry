<?php

declare(strict_types=1);

function send_newsletter_mail(string $to, string $subject, string $html, string $text = '', bool $isTest = false): array
{
    $senderEmail = trim((string) setting('sender_email', '')) ?: 'hello@omqpro.com';
    $senderName = trim((string) setting('sender_name', '')) ?: 'OMQ';
    $replyTo = trim((string) setting('reply_to', '')) ?: $senderEmail;
    $smtp = google_smtp_settings(setting('smtp', []), $senderEmail);
    $subject = $isTest ? '[TEST EMAIL] ' . $subject : $subject;

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            if (!empty($smtp['host'])) {
                $mail->isSMTP();
                $mail->Host = $smtp['host'];
                $mail->Port = (int) ($smtp['port'] ?? 587);
                $mail->SMTPAuth = !empty($smtp['username']);
                $mail->Username = $smtp['username'] ?? '';
                $mail->Password = $smtp['password'] ?? '';
                $mail->SMTPSecure = $smtp['encryption'] ?? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($senderEmail, $senderName);
            if ($replyTo) {
                $mail->addReplyTo($replyTo);
            }
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text ?: trim(strip_tags($html));
            $mail->send();
            return ['ok' => true, 'message' => 'Sent'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . mb_encode_mimeheader($senderName) . ' <' . $senderEmail . '>',
        'Reply-To: ' . $replyTo,
    ];
    $sent = mail($to, $subject, $html, implode("\r\n", $headers));
    return ['ok' => $sent, 'message' => $sent ? 'Sent with PHP mail().' : 'PHP mail() failed. Install PHPMailer for SMTP.'];
}

function google_smtp_settings(array $smtp, string $senderEmail): array
{
    return [
        'host' => trim((string) ($smtp['host'] ?? '')) ?: 'smtp.gmail.com',
        'port' => (int) ($smtp['port'] ?? 587) ?: 587,
        'username' => trim((string) ($smtp['username'] ?? '')) ?: $senderEmail,
        'password' => (string) ($smtp['password'] ?? ''),
        'encryption' => trim((string) ($smtp['encryption'] ?? '')) ?: 'tls',
        'batch_size' => (int) ($smtp['batch_size'] ?? 25) ?: 25,
        'batch_delay_seconds' => (int) ($smtp['batch_delay_seconds'] ?? 60) ?: 60,
    ];
}
