<?php
require_once '../includes/session_boot.php';
require_once '../db_connection.php';
require_once '../includes/csrf.php';

// This is the login itself, so it deliberately does not include
// request_guard.php or auth_check.php. Its protection is the brute-force
// throttle below: per-username and per-IP counters over a rolling window,
// with a progressive lockout that doubles per level up to a cap.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // REMOTE_ADDR is used directly. X-Forwarded-For is spoofable and is only
    // meaningful behind a known reverse proxy, which this deployment is not.
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // The same generic error is returned whether the username or the
    // password was wrong, so a caller cannot tell which one was incorrect.
    $generic_error = 'Incorrect username or password.';
    $throttle_error = 'Too many attempts. Please wait a few minutes and try again.';

    // Fetch the user first, mostly because Admin accounts get a stricter
    // threshold. This does not evaluate the password yet.
    $fetch_user_sql = "SELECT id, username, password, role, employee_id FROM users WHERE username = ?";
    $fetch_user_stmt = $conn->prepare($fetch_user_sql);
    $fetch_user_stmt->bind_param('s', $username);
    $fetch_user_stmt->execute();
    $fetch_user_result = $fetch_user_stmt->get_result();
    $user = $fetch_user_result->num_rows === 1 ? $fetch_user_result->fetch_assoc() : null;

    $fail_threshold = ($user && $user['role'] === 'Admin') ? LOGIN_MAX_FAILS_ADMIN : LOGIN_MAX_FAILS;

    // Failed attempts for this username inside the window, plus the time of
    // the most recent one, which anchors the progressive lockout.
    $count_user_fails_sql = "SELECT COUNT(*) AS fails, MAX(attempted_at) AS last_fail
                              FROM login_attempts
                              WHERE username = ? AND successful = 0
                                AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
    $count_user_fails_stmt = $conn->prepare($count_user_fails_sql);
    $window_minutes = LOGIN_WINDOW_MINUTES;
    $count_user_fails_stmt->bind_param('si', $username, $window_minutes);
    $count_user_fails_stmt->execute();
    $user_fails = $count_user_fails_stmt->get_result()->fetch_assoc();

    // Failed attempts from this IP across all usernames, which catches
    // password spraying.
    $count_ip_fails_sql = "SELECT COUNT(*) AS fails
                            FROM login_attempts
                            WHERE ip_address = ? AND successful = 0
                              AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
    $count_ip_fails_stmt = $conn->prepare($count_ip_fails_sql);
    $count_ip_fails_stmt->bind_param('si', $ip_address, $window_minutes);
    $count_ip_fails_stmt->execute();
    $ip_fails = (int) $count_ip_fails_stmt->get_result()->fetch_assoc()['fails'];

    $locked = false;

    if ($ip_fails >= LOGIN_MAX_FAILS_IP) {
        $locked = true;
    }

    $username_fail_count = (int) $user_fails['fails'];
    if (!$locked && $username_fail_count >= $fail_threshold) {
        // Lockout level 1 after the first threshold worth of failures,
        // level 2 after the second, and so on. Duration doubles per level
        // up to the cap, anchored to the most recent failure.
        $lockout_level = intdiv($username_fail_count, $fail_threshold);
        $lockout_seconds = min(
            LOGIN_LOCKOUT_BASE_SECONDS * (2 ** ($lockout_level - 1)),
            LOGIN_LOCKOUT_CAP_SECONDS
        );
        if (strtotime($user_fails['last_fail']) + $lockout_seconds > time()) {
            $locked = true;
        }
    }

    if ($locked) {
        // Record the blocked attempt, keep timing consistent with a real
        // credential check, and reject without evaluating the password.
        $record_attempt_sql = "INSERT INTO login_attempts (username, ip_address, successful) VALUES (?, ?, 0)";
        $record_attempt_stmt = $conn->prepare($record_attempt_sql);
        $record_attempt_stmt->bind_param('ss', $username, $ip_address);
        $record_attempt_stmt->execute();

        password_verify($password, LOGIN_DUMMY_HASH);
        echo json_encode(array('success' => false, 'error' => $throttle_error));
        exit;
    }

    // The dummy verify keeps the not-found path costing roughly the same as
    // a real bcrypt comparison so response time does not leak whether the
    // username exists.
    $password_ok = $user
        ? password_verify($password, $user['password'])
        : password_verify($password, LOGIN_DUMMY_HASH) && false;

    $attempt_outcome = $password_ok ? 1 : 0;
    $record_attempt_sql = "INSERT INTO login_attempts (username, ip_address, successful) VALUES (?, ?, ?)";
    $record_attempt_stmt = $conn->prepare($record_attempt_sql);
    $record_attempt_stmt->bind_param('ssi', $username, $ip_address, $attempt_outcome);
    $record_attempt_stmt->execute();

    if ($password_ok) {
        // A successful login resets the failure counter for this username.
        $clear_fails_sql = "DELETE FROM login_attempts WHERE username = ? AND successful = 0";
        $clear_fails_stmt = $conn->prepare($clear_fails_sql);
        $clear_fails_stmt->bind_param('s', $username);
        $clear_fails_stmt->execute();

        session_regenerate_id(true);
        csrf_regenerate();

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['created'] = time();
        $_SESSION['last_activity'] = time();

        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $generic_error));
    }
}
