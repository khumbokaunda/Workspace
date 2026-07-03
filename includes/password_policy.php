<?php
// Password policy, following modern NIST SP 800-63B guidance: length over
// composition rules. Minimum 12 characters plus a small blocklist of the
// obvious choices. No arbitrary character-class requirements.

define('PASSWORD_MIN_LENGTH', 12);

// Returns null when the password passes, or a user-facing error string.
function validate_password_policy($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    }

    $blocklist = array(
        'password1234',
        'password12345',
        '123456789012',
        'qwertyuiop12',
        'workdesk1234',
        'letmein12345',
        'welcome12345',
        'changeme1234',
        'administrator',
        'malawi123456'
    );

    if (in_array(strtolower($password), $blocklist, true)) {
        return 'That password is too common. Please choose something less guessable.';
    }

    return null;
}
