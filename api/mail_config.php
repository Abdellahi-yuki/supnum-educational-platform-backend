<?php
// SMTP Configuration
// For Gmail, use App Password, not your real password
// Enable 2FA on Gmail -> Search "App Passwords" -> Create one called "PHP" -> Copy the 16 char code

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', '24227@supnum.mr'); // REPLACE THIS
define('SMTP_PASS', 'wbmmebwtouqpruch'); // REPLACE THIS
define('SMTP_PORT', 587); // or 465
define('SMTP_SECURE', 'tls'); // or 'ssl'
define('SMTP_FROM_EMAIL', 'noreply@supnum.mr');
define('SMTP_FROM_NAME', 'SupNum Dashboard');
?>
