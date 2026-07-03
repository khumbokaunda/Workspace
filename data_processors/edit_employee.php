<?php
session_start();
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
$org_manager_only = true;
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
    $specialization = trim($_POST['specialization']);
    $manager_id = !empty($_POST['manager_id']) ? (int) $_POST['manager_id'] : null;
    $hire_date = trim($_POST['hire_date']);
    $status = trim($_POST['status']);

    if ($manager_id !== null && $manager_id === $id) {
        echo json_encode(array('success' => false, 'error' => 'An employee cannot be their own manager.'));
        exit;
    }

    $edit_employee_sql = "UPDATE employees
                           SET first_name = ?, last_name = ?, email = ?, phone = ?, position = ?, department = ?, specialization = ?, manager_id = ?, hire_date = ?, status = ?
                           WHERE id = ?";
    $edit_employee_stmt = $conn->prepare($edit_employee_sql);
    $edit_employee_stmt->bind_param('sssssssissi', $first_name, $last_name, $email, $phone, $position, $department, $specialization, $manager_id, $hire_date, $status, $id);

    if ($edit_employee_stmt->execute()) {
        $notification_text = "The employee record for {$first_name} {$last_name} was updated.";
        send_notification($conn, $notification_text, 'employee_management', $id, true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $edit_employee_stmt->error));
    }
}
