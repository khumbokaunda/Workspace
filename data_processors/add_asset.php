<?php
session_start();
require_once '../db_connection.php';
$admin_only = true;
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_tag = trim($_POST['asset_tag']);
    $asset_name = trim($_POST['asset_name']);
    $category = trim($_POST['category']);
    $serial_number = trim($_POST['serial_number']);
    $purchase_date = $_POST['purchase_date'] !== '' ? trim($_POST['purchase_date']) : null;
    $warranty_expiry = $_POST['warranty_expiry'] !== '' ? trim($_POST['warranty_expiry']) : null;
    $notes = trim($_POST['notes']);

    $add_asset_sql = "INSERT INTO assets (asset_tag, asset_name, category, serial_number, purchase_date, warranty_expiry, status, notes)
                       VALUES (?, ?, ?, ?, ?, ?, 'Available', ?)";
    $add_asset_stmt = $conn->prepare($add_asset_sql);
    $add_asset_stmt->bind_param('sssssss', $asset_tag, $asset_name, $category, $serial_number, $purchase_date, $warranty_expiry, $notes);

    if ($add_asset_stmt->execute()) {
        $notification_text = "A new asset, {$asset_name} ({$asset_tag}), was added to inventory.";
        send_notification($conn, $notification_text, 'asset_management');
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $add_asset_stmt->error));
    }
}
