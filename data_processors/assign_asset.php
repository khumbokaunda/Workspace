<?php
session_start();
require_once '../db_connection.php';
$admin_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_id = (int) $_POST['asset_id'];
    $employee_id = (int) $_POST['employee_id'];
    $assigned_date = trim($_POST['assigned_date']);
    $assigned_by = (int) $_SESSION['user_id'];

    $fetch_asset_sql = "SELECT asset_tag, asset_name, status FROM assets WHERE id = ?";
    $fetch_asset_stmt = $conn->prepare($fetch_asset_sql);
    $fetch_asset_stmt->bind_param('i', $asset_id);
    $fetch_asset_stmt->execute();
    $asset = $fetch_asset_stmt->get_result()->fetch_assoc();

    if (!$asset || $asset['status'] !== 'Available') {
        echo json_encode(array('success' => false, 'error' => 'This asset is not available to assign.'));
        exit;
    }

    $assign_asset_sql = "INSERT INTO asset_assignments (asset_id, employee_id, assigned_date, assigned_by) VALUES (?, ?, ?, ?)";
    $assign_asset_stmt = $conn->prepare($assign_asset_sql);
    $assign_asset_stmt->bind_param('iisi', $asset_id, $employee_id, $assigned_date, $assigned_by);

    if ($assign_asset_stmt->execute()) {
        $update_asset_sql = "UPDATE assets SET status = 'Assigned' WHERE id = ?";
        $update_asset_stmt = $conn->prepare($update_asset_sql);
        $update_asset_stmt->bind_param('i', $asset_id);
        $update_asset_stmt->execute();

        $notification_text = "{$asset['asset_name']} ({$asset['asset_tag']}) was assigned to an employee.";
        send_notification($conn, $notification_text, 'asset_management');
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $assign_asset_stmt->error));
    }
}
