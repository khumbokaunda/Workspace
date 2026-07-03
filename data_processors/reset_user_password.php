<?php
session_start();
require_once '../db_connection.php';
$admin_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $password = $_POST['password'];
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    $reset_password_sql = "UPDATE users SET password = ? WHERE id = ?";
    $reset_password_stmt = $conn->prepare($reset_password_sql);
    $reset_password_stmt->bind_param('si', $password_hash, $id);

    if ($reset_password_stmt->execute()) {
        $fetch_username_sql = "SELECT username FROM users WHERE id = ?";
        $fetch_username_stmt = $conn->prepare($fetch_username_sql);
        $fetch_username_stmt->bind_param('i', $id);
        $fetch_username_stmt->execute();
        $user = $fetch_username_stmt->get_result()->fetch_assoc();

        $notification_text = "The password for {$user['username']} was reset by an administrator.";
        send_notification($conn, $notification_text, 'user_management');
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $reset_password_stmt->error));
    }
}
