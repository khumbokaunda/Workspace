<?php
session_start();
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
$admin_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $username = trim($_POST['username']);
    $role = trim($_POST['role']);
    $employee_id = !empty($_POST['employee_id']) ? (int) $_POST['employee_id'] : null;

    $edit_user_sql = "UPDATE users SET username = ?, role = ?, employee_id = ? WHERE id = ?";
    $edit_user_stmt = $conn->prepare($edit_user_sql);
    $edit_user_stmt->bind_param('ssii', $username, $role, $employee_id, $id);

    if ($edit_user_stmt->execute()) {
        $notification_text = "The user account {$username} was updated.";
        send_notification($conn, $notification_text, 'user_management', $employee_id, true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $edit_user_stmt->error));
    }
}
