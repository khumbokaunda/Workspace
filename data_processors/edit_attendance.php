<?php
session_start();
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
$org_manager_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $check_in = $_POST['check_in'] !== '' ? $_POST['check_in'] : null;
    $check_out = $_POST['check_out'] !== '' ? $_POST['check_out'] : null;
    $status = trim($_POST['status']);
    $notes = trim($_POST['notes']);

    $fetch_attendance_sql = "SELECT employee_id FROM attendance WHERE id = ?";
    $fetch_attendance_stmt = $conn->prepare($fetch_attendance_sql);
    $fetch_attendance_stmt->bind_param('i', $id);
    $fetch_attendance_stmt->execute();
    $attendance = $fetch_attendance_stmt->get_result()->fetch_assoc();

    $edit_attendance_sql = "UPDATE attendance SET check_in = ?, check_out = ?, status = ?, notes = ? WHERE id = ?";
    $edit_attendance_stmt = $conn->prepare($edit_attendance_sql);
    $edit_attendance_stmt->bind_param('ssssi', $check_in, $check_out, $status, $notes, $id);

    if ($edit_attendance_stmt->execute() && $attendance) {
        $notification_text = "An attendance record was corrected by a manager.";
        send_notification($conn, $notification_text, 'attendance', $attendance['employee_id'], true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $edit_attendance_stmt->error));
    }
}
