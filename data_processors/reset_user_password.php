<?php
require_once '../includes/session_boot.php';
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
$admin_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';
require_once '../includes/password_policy.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $password = $_POST['password'];

    $policy_error = validate_password_policy($password);
    if ($policy_error !== null) {
        echo json_encode(array('success' => false, 'error' => $policy_error));
        exit;
    }

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
        send_notification($conn, $notification_text, 'user_management', null, true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $reset_password_stmt->error));
    }
}
