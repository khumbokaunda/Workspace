<?php
session_start();
require_once '../db_connection.php';
$admin_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];

    $fetch_cv_sql = "SELECT employee_id, file_path, file_name_original FROM cv_records WHERE id = ?";
    $fetch_cv_stmt = $conn->prepare($fetch_cv_sql);
    $fetch_cv_stmt->bind_param('i', $id);
    $fetch_cv_stmt->execute();
    $cv = $fetch_cv_stmt->get_result()->fetch_assoc();

    if (!$cv) {
        echo json_encode(array('success' => false, 'error' => 'That CV record could not be found.'));
        exit;
    }

    $delete_cv_sql = "DELETE FROM cv_records WHERE id = ?";
    $delete_cv_stmt = $conn->prepare($delete_cv_sql);
    $delete_cv_stmt->bind_param('i', $id);

    if ($delete_cv_stmt->execute()) {
        $stored_path = __DIR__ . '/../uploads/cv_files/' . $cv['file_path'];
        if (file_exists($stored_path)) {
            unlink($stored_path);
        }

        $notification_text = "The CV file {$cv['file_name_original']} was deleted.";
        send_notification($conn, $notification_text, 'cv_management', $cv['employee_id'], false);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $delete_cv_stmt->error));
    }
}
