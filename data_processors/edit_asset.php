<?php
session_start();
require_once '../includes/request_guard.php';
require_once '../db_connection.php';
$org_manager_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $asset_tag = trim($_POST['asset_tag']);
    $asset_name = trim($_POST['asset_name']);
    $category = trim($_POST['category']);
    $serial_number = trim($_POST['serial_number']);
    $purchase_date = $_POST['purchase_date'] !== '' ? trim($_POST['purchase_date']) : null;
    $warranty_expiry = $_POST['warranty_expiry'] !== '' ? trim($_POST['warranty_expiry']) : null;
    $status = trim($_POST['status']);
    $notes = trim($_POST['notes']);

    $edit_asset_sql = "UPDATE assets
                        SET asset_tag = ?, asset_name = ?, category = ?, serial_number = ?, purchase_date = ?, warranty_expiry = ?, status = ?, notes = ?
                        WHERE id = ?";
    $edit_asset_stmt = $conn->prepare($edit_asset_sql);
    $edit_asset_stmt->bind_param('ssssssssi', $asset_tag, $asset_name, $category, $serial_number, $purchase_date, $warranty_expiry, $status, $notes, $id);

    if ($edit_asset_stmt->execute()) {
        $notification_text = "The asset {$asset_name} ({$asset_tag}) was updated.";
        send_notification($conn, $notification_text, 'asset_management', null, true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $edit_asset_stmt->error));
    }
}
