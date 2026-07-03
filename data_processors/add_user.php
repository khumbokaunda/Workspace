<?php
require_once '../includes/session_boot.php';
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
$admin_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';
require_once '../includes/password_policy.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = trim($_POST['role']);
    $employee_id = !empty($_POST['employee_id']) ? (int) $_POST['employee_id'] : null;

    $policy_error = validate_password_policy($password);
    if ($policy_error !== null) {
        echo json_encode(array('success' => false, 'error' => $policy_error));
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    $add_user_sql = "INSERT INTO users (username, password, role, employee_id) VALUES (?, ?, ?, ?)";
    $add_user_stmt = $conn->prepare($add_user_sql);
    $add_user_stmt->bind_param('sssi', $username, $password_hash, $role, $employee_id);

    if ($add_user_stmt->execute()) {
        $notification_text = "A new user account, {$username}, was created with the {$role} role.";
        send_notification($conn, $notification_text, 'user_management', $employee_id, true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $add_user_stmt->error));
    }
}
