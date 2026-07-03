<?php
require_once '../includes/session_boot.php';
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
$admin_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];

    if ($id === (int) $_SESSION['user_id']) {
        echo json_encode(array('success' => false, 'error' => 'You cannot delete your own account while logged in.'));
        exit;
    }

    $fetch_user_sql = "SELECT username FROM users WHERE id = ?";
    $fetch_user_stmt = $conn->prepare($fetch_user_sql);
    $fetch_user_stmt->bind_param('i', $id);
    $fetch_user_stmt->execute();
    $user = $fetch_user_stmt->get_result()->fetch_assoc();

    $delete_user_sql = "DELETE FROM users WHERE id = ?";
    $delete_user_stmt = $conn->prepare($delete_user_sql);
    $delete_user_stmt->bind_param('i', $id);

    if ($delete_user_stmt->execute() && $user) {
        $notification_text = "The user account {$user['username']} was deleted.";
        send_notification($conn, $notification_text, 'user_management', null, true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $delete_user_stmt->error));
    }
}
