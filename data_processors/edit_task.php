<?php
require_once '../includes/session_boot.php';
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
$line_manager_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $assigned_to = (int) $_POST['assigned_to'];
    $priority = trim($_POST['priority']);
    $status = trim($_POST['status']);
    $due_date = $_POST['due_date'] !== '' ? trim($_POST['due_date']) : null;
    $completed_at = $status === 'Done' ? date('Y-m-d H:i:s') : null;

    $edit_task_sql = "UPDATE tasks
                       SET title = ?, description = ?, assigned_to = ?, priority = ?, status = ?, due_date = ?, completed_at = ?
                       WHERE id = ?";
    $edit_task_stmt = $conn->prepare($edit_task_sql);
    $edit_task_stmt->bind_param('ssissssi', $title, $description, $assigned_to, $priority, $status, $due_date, $completed_at, $id);

    if ($edit_task_stmt->execute()) {
        $notification_text = "The task \"{$title}\" was updated by a manager.";
        send_notification($conn, $notification_text, 'task_management', $assigned_to, true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $edit_task_stmt->error));
    }
}
