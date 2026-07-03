<?php
session_start();
require_once '../db_connection.php';
$admin_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $position = trim($_POST['position']);
    $department = trim($_POST['department']);
    $hire_date = trim($_POST['hire_date']);
    $status = trim($_POST['status']);

    $edit_employee_sql = "UPDATE employees
                           SET first_name = ?, last_name = ?, email = ?, phone = ?, position = ?, department = ?, hire_date = ?, status = ?
                           WHERE id = ?";
    $edit_employee_stmt = $conn->prepare($edit_employee_sql);
    $edit_employee_stmt->bind_param('ssssssssi', $first_name, $last_name, $email, $phone, $position, $department, $hire_date, $status, $id);

    if ($edit_employee_stmt->execute()) {
        $notification_text = "The employee record for {$first_name} {$last_name} was updated.";
        send_notification($conn, $notification_text, 'employee_management');
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $edit_employee_stmt->error));
    }
}
