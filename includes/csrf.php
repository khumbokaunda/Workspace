<?php
// Synchronizer token helpers. The token lives in the session, is delivered
// to the client through a meta tag on every page, attached to every AJAX
// call via ajaxSetup in src/script.js, and verified centrally in
// request_guard.php so every processor is covered by the one include.

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify($token) {
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Called on login and any privilege change so a token minted before the
// change cannot be replayed after it.
function csrf_regenerate() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
