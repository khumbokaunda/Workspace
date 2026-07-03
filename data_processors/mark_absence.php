<?php
session_start();
require_once '../db_connection.php';
$org_manager_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = (int) $_POST['employee_id'];
    $work_date = trim($_POST['work_date']);
    $notes = trim($_POST['notes']);

    $mark_absence_sql = "INSERT INTO attendance (employee_id, work_date, status, notes)
                          VALUES (?, ?, 'Absent', ?)
                          ON DUPLICATE KEY UPDATE status = 'Absent', notes = VALUES(notes)";
    $mark_absence_stmt = $conn->prepare($mark_absence_sql);
    $mark_absence_stmt->bind_param('iss', $employee_id, $work_date, $notes);

    if ($mark_absence_stmt->execute()) {
        $notification_text = "An absence was recorded for {$work_date}.";
        send_notification($conn, $notification_text, 'attendance', $employee_id, true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $mark_absence_stmt->error));
    }
}
