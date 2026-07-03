<?php
// Shared notification helper. Data processors call send_notification() after
// a state-changing action to log an in-app notification and, when a
// recipient email is supplied, send an email via PHPMailer.

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_notification($conn, $notification, $association, $recipient_email = null, $email_subject = null) {
    $add_notification_sql = "INSERT INTO notifications (notification, association, time_stamp) VALUES (?, ?, NOW())";
    $add_notification_stmt = $conn->prepare($add_notification_sql);
    $add_notification_stmt->bind_param('ss', $notification, $association);
    $add_notification_stmt->execute();

    if ($recipient_email !== null && $recipient_email !== '') {
        $subject = $email_subject !== null ? $email_subject : 'WorkDesk Notification';
        send_email($recipient_email, $subject, $notification);
    }
}

function send_email($to_email, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = 'tls';
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = '<p class="comfortaa-regular">' . nl2br(htmlspecialchars($body)) . '</p>';
        $mail->AltBody = $body;

        $mail->send();
    } catch (Exception $e) {
        // Email delivery issues should never break the main action, mostly
        // this happens when SMTP settings are not configured yet.
        error_log('WorkDesk email notification failed: ' . $mail->ErrorInfo);
    }
}
