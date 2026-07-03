<?php
// Shared notification helper. Data processors call send_notification() after
// a state-changing action to log an in-app notification and, when a
// recipient email is supplied, send an email via PHPMailer.
//
// Visibility is scoped so staff never see notifications about someone
// else's personal records:
//   $recipient_employee_id  the notification always shows for this employee
//   $management_only        also show it to Admin/Managing Director/Technical Manager
// A notification with both left at their defaults (null, false) is a
// general announcement visible to everyone.

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_notification($conn, $notification, $association, $recipient_employee_id = null, $management_only = false, $recipient_email = null, $email_subject = null) {
    $management_only_flag = $management_only ? 1 : 0;

    $add_notification_sql = "INSERT INTO notifications (notification, association, recipient_employee_id, management_only, time_stamp) VALUES (?, ?, ?, ?, NOW())";
    $add_notification_stmt = $conn->prepare($add_notification_sql);
    $add_notification_stmt->bind_param('ssii', $notification, $association, $recipient_employee_id, $management_only_flag);
    $add_notification_stmt->execute();

    if ($recipient_email !== null && $recipient_email !== '') {
        $subject = $email_subject !== null ? $email_subject : 'WorkDesk Notification';
        send_email($recipient_email, $subject, $notification);
    }
}

// The visibility rule shared by notifications/index.php and the dashboard:
// a general announcement (no recipient, not management-only) shows to
// everyone, a personal one shows only to its recipient, and a
// management-only one shows to the org-management tier.
function notifications_visibility_sql($role, $employee_id) {
    $conditions = array("(recipient_employee_id IS NULL AND management_only = 0)");

    if ($employee_id !== null) {
        $conditions[] = "recipient_employee_id = " . (int) $employee_id;
    }

    if (can_manage_org($role)) {
        $conditions[] = "management_only = 1";
    }

    return '(' . implode(' OR ', $conditions) . ')';
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
