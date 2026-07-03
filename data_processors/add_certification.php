<?php
session_start();
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

// Staff can only ever add a certification for themselves, the employee_id
// from the client is only honored when the requester can manage the org.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $can_manage = can_manage_org($_SESSION['role']);
    $employee_id = $can_manage ? (int) $_POST['employee_id'] : (int) $_SESSION['employee_id'];

    $cert_name = trim($_POST['cert_name']);
    $issuing_body = trim($_POST['issuing_body']);
    $cert_code = trim($_POST['cert_code']);
    $date_earned = trim($_POST['date_earned']);
    $expiry_date = $_POST['expiry_date'] !== '' ? trim($_POST['expiry_date']) : null;
    $credential_id = trim($_POST['credential_id']);
    $verification_url = trim($_POST['verification_url']);

    if ($date_earned > date('Y-m-d')) {
        echo json_encode(array('success' => false, 'error' => 'The date earned cannot be in the future.'));
        exit;
    }

    $status = ($expiry_date !== null && $expiry_date < date('Y-m-d')) ? 'Expired' : 'Active';

    $add_cert_sql = "INSERT INTO certifications
                      (employee_id, cert_name, issuing_body, cert_code, date_earned, expiry_date, credential_id, verification_url, status)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $add_cert_stmt = $conn->prepare($add_cert_sql);
    $add_cert_stmt->bind_param('issssssss', $employee_id, $cert_name, $issuing_body, $cert_code, $date_earned, $expiry_date, $credential_id, $verification_url, $status);

    if ($add_cert_stmt->execute()) {
        $notification_text = "A new certification, {$cert_name}, was added.";
        send_notification($conn, $notification_text, 'certification_management', $employee_id, false);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $add_cert_stmt->error));
    }
}
