<?php
require_once __DIR__ . '/config.php';

$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    echo "<script>alert('Unable to connect to the database. Please try again later.');</script>";
    exit;
}

$conn->set_charset('utf8mb4');

// Check-ins after this time of day are auto-flagged as Late. Kept as a
// single constant so attendance and dashboard logic never drift apart.
define('LATE_THRESHOLD_TIME', '08:15:00');

// The full set of login account roles, in the order they should appear in
// dropdowns. Kept in one place so every page lists the same roles.
function all_roles() {
    return array('Admin', 'Managing Director', 'Technical Manager', 'Engineer', 'Sales', 'Administration');
}

// Admin, Managing Director, and Technical Manager get org-wide visibility
// and management rights over Employee/Attendance/Asset/Certification
// records. This is the "system and records management" tier.
function can_manage_org($role) {
    return in_array($role, array('Admin', 'Managing Director', 'Technical Manager'), true);
}

// Managing Director and Technical Manager only, deliberately excluding
// Admin, this is the "line management" tier that assigns tasks and approves
// leave requests for the people who report to them.
function is_manager_tier($role) {
    return in_array($role, array('Managing Director', 'Technical Manager'), true);
}
