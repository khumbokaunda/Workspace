<?php
session_start();
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

// Same manager-only rule as approve_leave_request.php.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $reviewer_role = $_SESSION['role'];
    $reviewer_employee_id = isset($_SESSION['employee_id']) ? $_SESSION['employee_id'] : null;

    $fetch_request_sql = "SELECT l.employee_id, e.manager_id
                           FROM leave_requests l
                           JOIN employees e ON e.id = l.employee_id
                           WHERE l.id = ?";
    $fetch_request_stmt = $conn->prepare($fetch_request_sql);
    $fetch_request_stmt->bind_param('i', $id);
    $fetch_request_stmt->execute();
    $request = $fetch_request_stmt->get_result()->fetch_assoc();

    if (!$request) {
        echo json_encode(array('success' => false, 'error' => 'That leave request could not be found.'));
        exit;
    }

    $is_direct_manager = $reviewer_employee_id !== null && (int) $request['manager_id'] === (int) $reviewer_employee_id;
    $is_top_of_chain_fallback = $request['manager_id'] === null && $reviewer_role === 'Managing Director';

    if (!$is_direct_manager && !$is_top_of_chain_fallback) {
        echo json_encode(array('success' => false, 'error' => "Only this employee's manager can review their leave request."));
        exit;
    }

    $reviewed_by = (int) $_SESSION['user_id'];

    $reject_leave_sql = "UPDATE leave_requests SET status = 'Rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
    $reject_leave_stmt = $conn->prepare($reject_leave_sql);
    $reject_leave_stmt->bind_param('ii', $reviewed_by, $id);

    if ($reject_leave_stmt->execute()) {
        $fetch_details_sql = "SELECT l.leave_type, l.start_date, l.end_date, e.first_name, e.last_name, e.email
                               FROM leave_requests l
                               JOIN employees e ON e.id = l.employee_id
                               WHERE l.id = ?";
        $fetch_details_stmt = $conn->prepare($fetch_details_sql);
        $fetch_details_stmt->bind_param('i', $id);
        $fetch_details_stmt->execute();
        $details = $fetch_details_stmt->get_result()->fetch_assoc();

        $notification_text = "{$details['first_name']} {$details['last_name']}'s {$details['leave_type']} leave request was rejected.";
        send_notification(
            $conn,
            $notification_text,
            'leave_management',
            $request['employee_id'],
            false,
            $details['email'],
            'Your leave request has been rejected'
        );
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $reject_leave_stmt->error));
    }
}
