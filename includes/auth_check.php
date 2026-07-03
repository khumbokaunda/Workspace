<?php
// Processor-side auth guard. Include this at the top of every data processor,
// right after session_start() and the db_connection require.
//
// Set one of these before including this file to also require a role tier.
// Role checks in the UI alone are not enough, this is the real enforcement
// point:
//   $admin_only = true;         literal Admin only (user accounts, etc.)
//   $org_manager_only = true;   Admin, Managing Director, Technical Manager
//   $line_manager_only = true;  Managing Director, Technical Manager only

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(array('success' => false, 'error' => 'You must be logged in to do that.'));
    exit;
}

if (isset($admin_only) && $admin_only === true) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
        echo json_encode(array('success' => false, 'error' => 'You do not have permission to do that.'));
        exit;
    }
}

if (isset($org_manager_only) && $org_manager_only === true) {
    if (!isset($_SESSION['role']) || !can_manage_org($_SESSION['role'])) {
        echo json_encode(array('success' => false, 'error' => 'You do not have permission to do that.'));
        exit;
    }
}

if (isset($line_manager_only) && $line_manager_only === true) {
    if (!isset($_SESSION['role']) || !is_manager_tier($_SESSION['role'])) {
        echo json_encode(array('success' => false, 'error' => 'You do not have permission to do that.'));
        exit;
    }
}
