<?php
// Copy this file to config.php and fill in real values.
// config.php is gitignored and must never be committed.

define('DB_HOST', 'localhost');
define('DB_USERNAME', 'wms_user');
define('DB_PASSWORD', 'change_me');
define('DB_NAME', 'wms');

// SMTP settings used by includes/send_notification.php via PHPMailer
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'notifications@example.com');
define('SMTP_PASSWORD', 'change_me');
define('SMTP_FROM_EMAIL', 'notifications@example.com');
define('SMTP_FROM_NAME', 'WorkDesk');
