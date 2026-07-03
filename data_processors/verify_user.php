<?php
session_start();
require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $fetch_user_sql = "SELECT id, username, password, role, employee_id FROM users WHERE username = ?";
    $fetch_user_stmt = $conn->prepare($fetch_user_sql);
    $fetch_user_stmt->bind_param('s', $username);
    $fetch_user_stmt->execute();
    $fetch_user_result = $fetch_user_stmt->get_result();

    // The same generic error is returned whether the username or the
    // password was wrong, so a caller cannot tell which one was incorrect.
    $generic_error = 'Incorrect username or password.';

    if ($fetch_user_result->num_rows === 1) {
        $user = $fetch_user_result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['employee_id'] = $user['employee_id'];

            echo json_encode(array('success' => true));
        } else {
            echo json_encode(array('success' => false, 'error' => $generic_error));
        }
    } else {
        echo json_encode(array('success' => false, 'error' => $generic_error));
    }
}
