<?php

declare(strict_types=1);

function send_newsletter_mail(string $to, string $subject, string $html, string $text = '', bool $isTest = false): array
{
    $senderEmail = (string) setting('sender_email', '');
    $senderName = (string) setting('sender_name', 'Newsletter');
    $replyTo = (string) setting('reply_to', $senderEmail);
    $smtp = setting('smtp', []);
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
