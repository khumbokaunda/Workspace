<?php
require_once '../includes/session_boot.php';
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = (int) $_SESSION['employee_id'];
    $leave_type = trim($_POST['leave_type']);
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    $reason = trim($_POST['reason']);

    if ($end_date < $start_date) {
        echo json_encode(array('success' => false, 'error' => 'The end date must be on or after the start date.'));
        exit;
    }

    if ($start_date < date('Y-m-d')) {
        echo json_encode(array('success' => false, 'error' => 'The start date cannot be in the past.'));
        exit;
    }

    // Ranges beyond 90 days are almost always input mistakes, so they are
    // rejected with a clear message rather than silently accepted.
    $range_days = (int) ((strtotime($end_date) - strtotime($start_date)) / 86400) + 1;
    if ($range_days > 90) {
        echo json_encode(array('success' => false, 'error' => 'Leave requests cannot cover more than 90 days. Please split longer absences into separate requests.'));
        exit;
    }

    $submit_leave_sql = "INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status)
                          VALUES (?, ?, ?, ?, ?, 'Pending')";
    $submit_leave_stmt = $conn->prepare($submit_leave_sql);
    $submit_leave_stmt->bind_param('issss', $employee_id, $leave_type, $start_date, $end_date, $reason);

    if ($submit_leave_stmt->execute()) {
        $fetch_manager_sql = "SELECT manager_id FROM employees WHERE id = ?";
        $fetch_manager_stmt = $conn->prepare($fetch_manager_sql);
        $fetch_manager_stmt->bind_param('i', $employee_id);
        $fetch_manager_stmt->execute();
        $employee = $fetch_manager_stmt->get_result()->fetch_assoc();

        $notification_text = "{$_SESSION['username']} submitted a {$leave_type} leave request.";
        // Routed to the requester's manager so approval reaches the right
        // person, falling back to the management tier when no manager is set.
        send_notification($conn, $notification_text, 'leave_management', $employee['manager_id'], true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $submit_leave_stmt->error));
    }
}
