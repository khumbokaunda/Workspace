<?php
session_start();
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $can_manage = can_manage_org($_SESSION['role']);
    $id = (int) $_POST['id'];

    $fetch_cert_sql = "SELECT employee_id FROM certifications WHERE id = ?";
    $fetch_cert_stmt = $conn->prepare($fetch_cert_sql);
    $fetch_cert_stmt->bind_param('i', $id);
    $fetch_cert_stmt->execute();
    $existing = $fetch_cert_stmt->get_result()->fetch_assoc();

    if (!$existing) {
        echo json_encode(array('success' => false, 'error' => 'That certification could not be found.'));
        exit;
    }

    if (!$can_manage && (int) $existing['employee_id'] !== (int) $_SESSION['employee_id']) {
        echo json_encode(array('success' => false, 'error' => 'You do not have permission to edit that certification.'));
        exit;
    }

    $cert_name = trim($_POST['cert_name']);
    $issuing_body = trim($_POST['issuing_body']);
    $cert_code = trim($_POST['cert_code']);
    $date_earned = trim($_POST['date_earned']);
    $expiry_date = $_POST['expiry_date'] !== '' ? trim($_POST['expiry_date']) : null;
    $credential_id = trim($_POST['credential_id']);
    $verification_url = trim($_POST['verification_url']);
    $status = trim($_POST['status']);

    if ($date_earned > date('Y-m-d')) {
        echo json_encode(array('success' => false, 'error' => 'The date earned cannot be in the future.'));
        exit;
    }

    if ($expiry_date !== null && $expiry_date < date('Y-m-d')) {
        $status = 'Expired';
    }

    $edit_cert_sql = "UPDATE certifications
                       SET cert_name = ?, issuing_body = ?, cert_code = ?, date_earned = ?, expiry_date = ?, credential_id = ?, verification_url = ?, status = ?
                       WHERE id = ?";
    $edit_cert_stmt = $conn->prepare($edit_cert_sql);
    $edit_cert_stmt->bind_param('ssssssssi', $cert_name, $issuing_body, $cert_code, $date_earned, $expiry_date, $credential_id, $verification_url, $status, $id);

    if ($edit_cert_stmt->execute()) {
        $notification_text = "The certification {$cert_name} was updated.";
        send_notification($conn, $notification_text, 'certification_management', $existing['employee_id'], false);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $edit_cert_stmt->error));
    }
}
