<?php
/**
 * Email delivery settings for sign-in codes (OTP).
 *
 * PLACEHOLDER: delivery is OFF in this development version. With it off, the
 * sign-in code is shown on the sign-in screen instead of being emailed, and the
 * screen says so. To send real emails, configure an SMTP relay for PHP (for
 * XAMPP: php.ini [mail function] / sendmail.ini) and set MAIL_ENABLED to true.
 * Never commit SMTP usernames or passwords to this file or the repository.
 */
const MAIL_ENABLED = false;
const MAIL_FROM = 'no-reply@indang.gov.ph';
const MAIL_FROM_NAME = 'AIP Management System (MPDO Indang)';
