<?php
session_start();
require_once '../db_connection.php';
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = (int) $_SESSION['employee_id'];
    $today = date('Y-m-d');
    $now_time = date('H:i:s');

    $fetch_today_sql = "SELECT id, check_in, check_out FROM attendance WHERE employee_id = ? AND work_date = ?";
    $fetch_today_stmt = $conn->prepare($fetch_today_sql);
    $fetch_today_stmt->bind_param('is', $employee_id, $today);
    $fetch_today_stmt->execute();
    $existing = $fetch_today_stmt->get_result()->fetch_assoc();

    if (!$existing || !$existing['check_in']) {
        echo json_encode(array('success' => false, 'error' => 'You need to check in before you can check out.'));
        exit;
    }

    if ($existing['check_out']) {
        echo json_encode(array('success' => false, 'error' => 'You have already checked out today.'));
        exit;
    }

    $check_out_sql = "UPDATE attendance SET check_out = ? WHERE id = ?";
    $check_out_stmt = $conn->prepare($check_out_sql);
    $check_out_stmt->bind_param('si', $now_time, $existing['id']);

    if ($check_out_stmt->execute()) {
        $notification_text = "{$_SESSION['username']} checked out.";
        send_notification($conn, $notification_text, 'attendance');
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $check_out_stmt->error));
    }
}
