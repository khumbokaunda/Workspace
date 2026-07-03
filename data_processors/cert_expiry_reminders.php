<?php
// Designed to be run daily by cron on the command line. The intended cron
// entry is:
//   0 7 * * * php /path/to/wms/data_processors/cert_expiry_reminders.php
//
// Finds certifications expiring in exactly 90, 30, or 7 days and emails the
// holder a reminder, and clears old login_attempts rows.
//
// Web execution is disabled by default. It only runs over the web when the
// deployment's config.php defines ALLOW_WEB_CRON as true, and even then it
// demands an AJAX POST with a valid CSRF token from a logged-in Admin.
// Rely on the CLI cron for the real runs.
require_once __DIR__ . '/../db_connection.php';

if (PHP_SAPI !== 'cli') {
    if (!defined('ALLOW_WEB_CRON') || ALLOW_WEB_CRON !== true) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'error' => 'Forbidden.'));
        exit;
    }
    require_once __DIR__ . '/../includes/session_boot.php';
    require_once __DIR__ . '/../includes/request_guard.php';
    $admin_only = true;
    require_once __DIR__ . '/../includes/auth_check.php';
}

require_once __DIR__ . '/../includes/send_notification.php';

// Housekeeping: login attempt rows older than a day are no longer needed
// for throttling, so the daily cron run clears them out.
$conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

$reminder_windows = array(90, 30, 7);
$reminders_sent = 0;

foreach ($reminder_windows as $days_out) {
    $fetch_expiring_sql = "SELECT c.employee_id, c.cert_name, c.expiry_date, e.first_name, e.last_name, e.email
                            FROM certifications c
                            JOIN employees e ON e.id = c.employee_id
                            WHERE c.expiry_date = DATE_ADD(CURDATE(), INTERVAL ? DAY)
                              AND c.status != 'Expired'";
    $fetch_expiring_stmt = $conn->prepare($fetch_expiring_sql);
    $fetch_expiring_stmt->bind_param('i', $days_out);
    $fetch_expiring_stmt->execute();
    $fetch_expiring_result = $fetch_expiring_stmt->get_result();

    while ($cert = $fetch_expiring_result->fetch_assoc()) {
        $notification_text = "{$cert['first_name']} {$cert['last_name']}'s {$cert['cert_name']} certification expires in {$days_out} days, on {$cert['expiry_date']}.";
        send_notification(
            $conn,
            $notification_text,
            'certification_management',
            $cert['employee_id'],
            false,
            $cert['email'],
            "Your certification expires in {$days_out} days"
        );
        $reminders_sent++;
    }
}

if (PHP_SAPI === 'cli') {
    echo "Sent {$reminders_sent} certification expiry reminder(s)." . PHP_EOL;
} else {
    echo json_encode(array('success' => true, 'reminders_sent' => $reminders_sent));
}
