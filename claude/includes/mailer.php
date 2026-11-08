<?php
/**
 * Delivery of one-time sign-in codes to the user's government email.
 * See config/mail.php. While MAIL_ENABLED is false (development), the code is
 * kept in the session and displayed on the sign-in screen, clearly labelled.
 */
require_once dirname(__DIR__) . '/config/mail.php';

/** "j.bautista@indang.gov.ph" → "j•••••••@indang.gov.ph" */
function maskEmail(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($local, 0, 1) . str_repeat('•', max(3, mb_strlen($local) - 1)) . '@' . $domain;
}

/** Send a sign-in code. Returns true when it was handed to the mail system. */
function deliverOtp(array $user, string $code, int $minutes): bool
{
    unset($_SESSION['dev_otp_code']);
    if (!MAIL_ENABLED) {
        $_SESSION['dev_otp_code'] = $code;   // DEV-ONLY: shown on screen because no mail server is configured
        return false;
    }
    $subject = 'Your AIP system sign-in code';
    $body = "Hello {$user['name']},\r\n\r\nYour sign-in code is {$code}. It expires in {$minutes} minutes.\r\n\r\n"
          . "If you did not try to sign in, contact the MPDO system administrator.\r\n";
    $headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\nContent-Type: text/plain; charset=UTF-8";
    return mail($user['email'], $subject, $body, $headers);
}
