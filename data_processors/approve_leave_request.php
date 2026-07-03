<?php
session_start();
require_once '../db_connection.php';
$admin_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $reviewed_by = (int) $_SESSION['user_id'];

    $approve_leave_sql = "UPDATE leave_requests SET status = 'Approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
    $approve_leave_stmt = $conn->prepare($approve_leave_sql);
    $approve_leave_stmt->bind_param('ii', $reviewed_by, $id);

    if ($approve_leave_stmt->execute()) {
        $fetch_request_sql = "SELECT l.leave_type, l.start_date, l.end_date, e.first_name, e.last_name, e.email
                               FROM leave_requests l
                               JOIN employees e ON e.id = l.employee_id
                               WHERE l.id = ?";
        $fetch_request_stmt = $conn->prepare($fetch_request_sql);
        $fetch_request_stmt->bind_param('i', $id);
        $fetch_request_stmt->execute();
        $request = $fetch_request_stmt->get_result()->fetch_assoc();

        $notification_text = "{$request['first_name']} {$request['last_name']}'s {$request['leave_type']} leave request was approved.";
        send_notification(
            $conn,
            $notification_text,
            'leave_management',
            $request['email'],
            'Your leave request has been approved'
        );
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $approve_leave_stmt->error));
    }
}
