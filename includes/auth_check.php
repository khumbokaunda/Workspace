<?php
// Processor-side auth guard. Include this at the top of every data processor,
// right after session_start() and the db_connection require.
//
// Set $admin_only = true; before including this file to also require the
// Admin role. Role checks in the UI alone are not enough, this is the real
// enforcement point.

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
