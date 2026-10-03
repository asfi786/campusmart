<?php
/**
 * Outgoing email. Uses PHP mail() — the only thing guaranteed on free hosts.
 * Email verification as a whole is a config toggle (default OFF) because
 * many free hosts block outbound mail.
 */

function send_email(string $to, string $subject, string $htmlBody): bool
{
    if (config('mailer', 'mail') === 'none') {
        return false; // sending disabled by configuration
    }

    $from = config('mail_from', 'noreply@example.com');
    $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody)));

    // Multipart message so clients can pick HTML or plain text.
    $boundary = 'cm_' . md5(uniqid((string) mt_rand(), true));
    $headers  = "From: CampusMart <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $textBody . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $htmlBody . "\r\n";
    $body .= "--{$boundary}--";

    // The @ suppresses warnings on hosts where mail() is disabled;
    // the return value still tells us whether it was accepted.
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}
