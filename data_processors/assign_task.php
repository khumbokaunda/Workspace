<?php
require_once '../includes/session_boot.php';
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
$line_manager_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $assigned_to = (int) $_POST['assigned_to'];
    $assigned_by = (int) $_SESSION['user_id'];
    $priority = trim($_POST['priority']);
    $due_date = $_POST['due_date'] !== '' ? trim($_POST['due_date']) : null;

    $assign_task_sql = "INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, status, due_date)
                         VALUES (?, ?, ?, ?, ?, 'To Do', ?)";
    $assign_task_stmt = $conn->prepare($assign_task_sql);
    $assign_task_stmt->bind_param('ssiiss', $title, $description, $assigned_to, $assigned_by, $priority, $due_date);

    if ($assign_task_stmt->execute()) {
        $fetch_employee_sql = "SELECT first_name, last_name, email FROM employees WHERE id = ?";
        $fetch_employee_stmt = $conn->prepare($fetch_employee_sql);
        $fetch_employee_stmt->bind_param('i', $assigned_to);
        $fetch_employee_stmt->execute();
        $employee = $fetch_employee_stmt->get_result()->fetch_assoc();

        $notification_text = "A new {$priority} priority task, \"{$title}\", was assigned to {$employee['first_name']} {$employee['last_name']}.";
        send_notification(
            $conn,
            $notification_text,
            'task_management',
            $assigned_to,
            false,
            $employee['email'],
            'A new task has been assigned to you'
        );
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $assign_task_stmt->error));
    }
}
