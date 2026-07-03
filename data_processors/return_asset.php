<?php
session_start();
require_once '../db_connection.php';
$org_manager_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_id = (int) $_POST['asset_id'];

    $fetch_assignment_sql = "SELECT id, employee_id FROM asset_assignments WHERE asset_id = ? AND returned_date IS NULL ORDER BY assigned_date DESC LIMIT 1";
    $fetch_assignment_stmt = $conn->prepare($fetch_assignment_sql);
    $fetch_assignment_stmt->bind_param('i', $asset_id);
    $fetch_assignment_stmt->execute();
    $assignment = $fetch_assignment_stmt->get_result()->fetch_assoc();

    if (!$assignment) {
        echo json_encode(array('success' => false, 'error' => 'No open assignment was found for this asset.'));
        exit;
    }

    $return_asset_sql = "UPDATE asset_assignments SET returned_date = CURDATE() WHERE id = ?";
    $return_asset_stmt = $conn->prepare($return_asset_sql);
    $return_asset_stmt->bind_param('i', $assignment['id']);

    if ($return_asset_stmt->execute()) {
        $update_asset_sql = "UPDATE assets SET status = 'Available' WHERE id = ?";
        $update_asset_stmt = $conn->prepare($update_asset_sql);
        $update_asset_stmt->bind_param('i', $asset_id);
        $update_asset_stmt->execute();

        $notification_text = "An asset was marked as returned and is now available.";
        send_notification($conn, $notification_text, 'asset_management', $assignment['employee_id'], true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $return_asset_stmt->error));
    }
}
