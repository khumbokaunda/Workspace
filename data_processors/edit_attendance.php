<?php
session_start();
require_once '../db_connection.php';
$admin_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $check_in = $_POST['check_in'] !== '' ? $_POST['check_in'] : null;
    $check_out = $_POST['check_out'] !== '' ? $_POST['check_out'] : null;
    $status = trim($_POST['status']);
    $notes = trim($_POST['notes']);

    $edit_attendance_sql = "UPDATE attendance SET check_in = ?, check_out = ?, status = ?, notes = ? WHERE id = ?";
    $edit_attendance_stmt = $conn->prepare($edit_attendance_sql);
    $edit_attendance_stmt->bind_param('ssssi', $check_in, $check_out, $status, $notes, $id);

    if ($edit_attendance_stmt->execute()) {
        $notification_text = "An attendance record was corrected by an administrator.";
        send_notification($conn, $notification_text, 'attendance');
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $edit_attendance_stmt->error));
    }
}
