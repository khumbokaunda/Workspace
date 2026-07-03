<?php
require_once '../includes/session_boot.php';
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
$org_manager_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

// Employees are never hard deleted, this is a soft status change so that
// attendance, task, and certification history stays intact.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];

    $fetch_employee_sql = "SELECT first_name, last_name FROM employees WHERE id = ?";
    $fetch_employee_stmt = $conn->prepare($fetch_employee_sql);
    $fetch_employee_stmt->bind_param('i', $id);
    $fetch_employee_stmt->execute();
    $employee = $fetch_employee_stmt->get_result()->fetch_assoc();

    $delete_employee_sql = "UPDATE employees SET status = 'Terminated' WHERE id = ?";
    $delete_employee_stmt = $conn->prepare($delete_employee_sql);
    $delete_employee_stmt->bind_param('i', $id);

    if ($delete_employee_stmt->execute() && $employee) {
        $notification_text = "{$employee['first_name']} {$employee['last_name']} was marked as Terminated.";
        send_notification($conn, $notification_text, 'employee_management', $id, true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $delete_employee_stmt->error));
    }
}
