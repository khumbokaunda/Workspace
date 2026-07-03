<?php
session_start();
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
$org_manager_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $position = trim($_POST['position']);
    $department = trim($_POST['department']);
    $specialization = trim($_POST['specialization']);
    $manager_id = !empty($_POST['manager_id']) ? (int) $_POST['manager_id'] : null;
    $hire_date = trim($_POST['hire_date']);

    $add_employee_sql = "INSERT INTO employees (first_name, last_name, email, phone, position, department, specialization, manager_id, hire_date, status)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";
    $add_employee_stmt = $conn->prepare($add_employee_sql);
    $add_employee_stmt->bind_param('sssssssis', $first_name, $last_name, $email, $phone, $position, $department, $specialization, $manager_id, $hire_date);

    if ($add_employee_stmt->execute()) {
        $notification_text = "A new employee, {$first_name} {$last_name}, was added to the system.";
        send_notification($conn, $notification_text, 'employee_management', null, true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $add_employee_stmt->error));
    }
}
