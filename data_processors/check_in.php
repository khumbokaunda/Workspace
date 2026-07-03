<?php
require_once '../includes/session_boot.php';
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

// The employee_id always comes from the session, never from the client,
// so a staff member can only ever check in for themselves.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = (int) $_SESSION['employee_id'];
    $today = date('Y-m-d');
    $now_time = date('H:i:s');

    $fetch_today_sql = "SELECT id, check_in FROM attendance WHERE employee_id = ? AND work_date = ?";
    $fetch_today_stmt = $conn->prepare($fetch_today_sql);
    $fetch_today_stmt->bind_param('is', $employee_id, $today);
    $fetch_today_stmt->execute();
    $existing = $fetch_today_stmt->get_result()->fetch_assoc();

    if ($existing && $existing['check_in']) {
        echo json_encode(array('success' => false, 'error' => 'You have already checked in today.'));
        exit;
    }

    $status = $now_time > LATE_THRESHOLD_TIME ? 'Late' : 'Present';

    if ($existing) {
        $check_in_sql = "UPDATE attendance SET check_in = ?, status = ? WHERE id = ?";
        $check_in_stmt = $conn->prepare($check_in_sql);
        $check_in_stmt->bind_param('ssi', $now_time, $status, $existing['id']);
    } else {
        $check_in_sql = "INSERT INTO attendance (employee_id, work_date, check_in, status) VALUES (?, ?, ?, ?)";
        $check_in_stmt = $conn->prepare($check_in_sql);
        $check_in_stmt->bind_param('isss', $employee_id, $today, $now_time, $status);
    }

    if ($check_in_stmt->execute()) {
        $notification_text = "{$_SESSION['username']} checked in" . ($status === 'Late' ? ' late' : '') . ".";
        send_notification($conn, $notification_text, 'attendance', $employee_id, false);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $check_in_stmt->error));
    }
}
