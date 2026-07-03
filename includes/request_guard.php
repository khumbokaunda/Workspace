<?php
// Shared guard for data processors. htaccess rules only help on Apache, so
// this enforces the same policy at the PHP layer regardless of web server:
// processors are only ever reached by the app's own AJAX POSTs, never by
// typing a URL into the address bar or by a cross-site form.
//
// Include this at the top of every file in data_processors/ immediately
// after the session is started. Known exceptions, each with its own
// protection:
//   verify_user.php           the login itself, guarded by the rate limiter
//   download_cv.php           a GET download link by design, guarded by its
//                             own session and ownership checks
//   cert_expiry_reminders.php CLI-first cron file with its own gating

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(array('success' => false, 'error' => 'Forbidden.'));
    exit;
}

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(array('success' => false, 'error' => 'Forbidden.'));
    exit;
}

// CSRF synchronizer token check. Every AJAX call carries the token in the
// X-CSRF-Token header via the ajaxSetup block in src/script.js.
require_once __DIR__ . '/csrf.php';

if (!csrf_verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(array('success' => false, 'error' => 'Your session has expired. Please refresh the page and try again.'));
    exit;
}
