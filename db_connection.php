<?php
// Config location: a config.php one directory ABOVE the webroot is preferred
// so credentials sit outside anything the web server can serve. When that is
// not possible on the host, the copy inside the webroot is used and the root
// .htaccess denies direct access to it as the fallback. Whichever file is
// found first wins.
if (file_exists(dirname(__DIR__) . '/config.php')) {
    require_once dirname(__DIR__) . '/config.php';
} else {
    require_once __DIR__ . '/config.php';
}

$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    echo "<script>alert('Unable to connect to the database. Please try again later.');</script>";
    exit;
}

$conn->set_charset('utf8mb4');

// Timezone is pinned explicitly so the Late flag and every date comparison
// behave the same regardless of server locale. Africa/Blantyre is CAT,
// UTC+2 with no daylight saving, so the MySQL session offset matches.
date_default_timezone_set('Africa/Blantyre');
$conn->query("SET time_zone = '+02:00'");

// Check-ins after this time of day are auto-flagged as Late. Kept as a
// single constant so attendance and dashboard logic never drift apart.
define('LATE_THRESHOLD_TIME', '08:15:00');

// Login brute-force policy, all in one place. The window is how far back
// failed attempts count; lockout duration is progressive, doubling per
// lockout level up to the cap so an attacker cannot permanently lock a
// real staff member out.
define('LOGIN_WINDOW_MINUTES', 15);
define('LOGIN_MAX_FAILS', 5);
define('LOGIN_MAX_FAILS_ADMIN', 3);
define('LOGIN_MAX_FAILS_IP', 20);
define('LOGIN_LOCKOUT_BASE_SECONDS', 60);
define('LOGIN_LOCKOUT_CAP_SECONDS', 900);

// A fixed throwaway bcrypt hash. When a login is attempted against a
// username that does not exist, password_verify runs against this instead
// so the response time does not reveal whether the account is real.
define('LOGIN_DUMMY_HASH', '$2y$12$.PX9uKk6T2X/2goBQk5aYerfGVAghFjN4diSEculBvCmocUBVMWma');

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
