<?php
// Central session bootstrap. Every page and processor requires this instead
// of calling session_start() directly, so cookie hardening and timeouts are
// applied in exactly one place.
//
// Theme preference is client-side localStorage and is not affected by
// anything here.

define('SESSION_IDLE_SECONDS', 30 * 60);
define('SESSION_ABSOLUTE_SECONDS', 8 * 60 * 60);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        // True automatically when served over HTTPS in production; stays
        // false on plain-HTTP dev setups like local XAMPP.
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ]);
    session_start();
}

// Idle and absolute timeouts. When either is exceeded the session is
// destroyed and a fresh empty one is started, so the downstream logged_in
// checks treat the request as logged out (pages redirect to login,
// processors return the auth error).
if (isset($_SESSION['logged_in'])) {
    $now = time();
    $last_activity = $_SESSION['last_activity'] ?? $now;
    $created = $_SESSION['created'] ?? $now;

    if (($now - $last_activity) > SESSION_IDLE_SECONDS || ($now - $created) > SESSION_ABSOLUTE_SECONDS) {
        session_unset();
        session_destroy();
        session_start();
    } else {
        $_SESSION['last_activity'] = $now;
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = $now;
        }
    }
}
