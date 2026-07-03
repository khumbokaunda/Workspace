<?php
session_start();
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
$org_manager_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];

    $fetch_asset_sql = "SELECT asset_tag, asset_name FROM assets WHERE id = ?";
    $fetch_asset_stmt = $conn->prepare($fetch_asset_sql);
    $fetch_asset_stmt->bind_param('i', $id);
    $fetch_asset_stmt->execute();
    $asset = $fetch_asset_stmt->get_result()->fetch_assoc();

    $delete_asset_sql = "DELETE FROM assets WHERE id = ?";
    $delete_asset_stmt = $conn->prepare($delete_asset_sql);
    $delete_asset_stmt->bind_param('i', $id);

    if ($delete_asset_stmt->execute() && $asset) {
        $notification_text = "The asset {$asset['asset_name']} ({$asset['asset_tag']}) was removed from inventory.";
        send_notification($conn, $notification_text, 'asset_management', null, true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $delete_asset_stmt->error));
    }
}
