<?php
session_start();
require_once '../db_connection.php';
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $can_manage = can_manage_org($_SESSION['role']);
    $id = (int) $_POST['id'];

    $fetch_cert_sql = "SELECT employee_id, cert_name FROM certifications WHERE id = ?";
    $fetch_cert_stmt = $conn->prepare($fetch_cert_sql);
    $fetch_cert_stmt->bind_param('i', $id);
    $fetch_cert_stmt->execute();
    $existing = $fetch_cert_stmt->get_result()->fetch_assoc();

    if (!$existing) {
        echo json_encode(array('success' => false, 'error' => 'That certification could not be found.'));
        exit;
    }

    if (!$can_manage && (int) $existing['employee_id'] !== (int) $_SESSION['employee_id']) {
        echo json_encode(array('success' => false, 'error' => 'You do not have permission to delete that certification.'));
        exit;
    }

    $delete_cert_sql = "DELETE FROM certifications WHERE id = ?";
    $delete_cert_stmt = $conn->prepare($delete_cert_sql);
    $delete_cert_stmt->bind_param('i', $id);

    if ($delete_cert_stmt->execute()) {
        $notification_text = "The certification {$existing['cert_name']} was deleted.";
        send_notification($conn, $notification_text, 'certification_management', $existing['employee_id'], false);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $delete_cert_stmt->error));
    }
}
