# WMS Hardening and Feature Build - Implementation Plan

This is a diagnosis and upgrade plan for the existing Workplace Management System. Feed this to Claude Code in the project root alongside the original CLAUDE.md. Work phase by phase, verify each phase runs before moving on, and do NOT change the technology stack (PHP procedural, mysqli prepared statements, Bootstrap 5, jQuery, DataTables, Parsley, SweetAlert2, PHPMailer). Keep the existing database schema intact except for the additive tables and columns explicitly listed here. Match the existing house style: dual dark/light theme, Comfortaa font, green accent, no em dashes anywhere.

## Diagnosis Summary (what is already fine, what is not)

The build is in better shape than a first glance suggests. Do not rip anything out. These are already correct and must be preserved:

* All queries use mysqli prepared statements with bound parameters
* Every data processor already includes auth_check.php (verify_user.php is the only exception, correctly, since it is the login itself)
* employee_id is taken from the session, never the client, in check_in, check_out, submit_leave_request, update_task_status, upload_cv
* Login returns a single generic error for both wrong username and wrong password
* session_regenerate_id(true) fires on successful login
* CV upload validates extension AND finfo MIME type AND size, stores under a random filename, and uploads/cv_files/ has a Deny from all htaccess
* Leave approval is correctly scoped to the requester's own manager, not a blanket role check

The real gaps, in priority order, are: no brute-force protection, no CSRF tokens, no session cookie hardening, web-reachable sensitive directories (database/, includes/, src/, and GET-reachable data_processors), and a handful of logic issues. Everything below addresses these plus the requested module builder.

## Phase 1: Directory and File Exposure (do this first, it is the fastest win)

The goal is that a browser can only ever reach the front-controller pages (the module index.php files and the root login), never the raw processors, includes, schema, or source folders.

### 1.1 Root .htaccess (Apache)

Create `.htaccess` in the project root with:

* `Options -Indexes` to kill directory listing everywhere
* A rule blocking direct web access to sensitive folders. Deny all access to `database/`, `includes/`, `config.php`, `config.example.php`, `composer.json`, `composer.lock`, and any `.md` or `.sql` file
* Force `.php` files in `data_processors/` to only be reachable via POST is not an Apache-native rule, so handle that in PHP (see 1.3), but still add a rule that blocks direct browser navigation to processor files by checking for the AJAX header at the PHP layer

Example root .htaccess:

```apache
Options -Indexes

# Block sensitive file types from direct access
<FilesMatch "\.(sql|md|json|lock|example\.php)$">
    Require all denied
</FilesMatch>

# Block config outright
<FilesMatch "^config\.php$">
    Require all denied
</FilesMatch>
```

### 1.2 Per-folder .htaccess for defense in depth

Drop a `.htaccess` containing `Require all denied` (Apache 2.4) into `database/` and `includes/`. These folders are only ever included from PHP, never served. The existing `uploads/cv_files/.htaccess` already does this, mirror it. Leave `src/` reachable (CSS and JS must load) but rely on `Options -Indexes` so the folder cannot be listed.

### 1.3 Processor guard (works regardless of web server)

htaccess only helps on Apache. For portability (and because the developer may run this behind nginx or php's built-in server during testing), add a shared guard. Create `includes/request_guard.php` that:

* confirms the request is a POST (processors are never GET)
* confirms an `X-Requested-With: XMLHttpRequest` header is present, which the jQuery AJAX calls already send by default but VERIFY this and add it explicitly to every $.ajax call as a header if not already global
* if either check fails, sends a 403 and exits

Include `request_guard.php` at the top of every file in `data_processors/` immediately after session_start(). This means typing a processor URL into the address bar (a GET) returns 403 instead of a blank page, and closes the folder to casual enumeration.

### 1.4 Move config out of webroot if the host allows it

Ideally `config.php` lives one directory ABOVE the webroot. If the hosting setup permits, update `db_connection.php` to `require_once __DIR__ . '/../config.php'` and document this in the README. If not possible, the htaccess denial in 1.1 is the fallback. State clearly in comments which option is active.

Verify Phase 1: browse directly to `/database/schema.sql`, `/config.php`, `/includes/nav.php`, `/data_processors/add_user.php`, and `/src/`. Each should return 403 or a forbidden/blocked response, none should return content or a directory listing.

## Phase 2: Login Brute-Force Protection

Follow OWASP Authentication guidance: throttle at BOTH the account level and the IP level, keep the generic error, use progressive lockout (not permanent) to avoid becoming a denial-of-service vector where an attacker locks out real staff, and apply stricter limits to Admin. Track everything in a new MySQL table so lockouts survive restarts and are auditable.

### 2.1 New table: login_attempts

Add to a NEW migration file `database/migrations/001_login_attempts.sql` (do not edit schema.sql's core tables; keep migrations separate and numbered so they are re-runnable and traceable):

```sql
CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    successful TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username_time (username, attempted_at),
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 Lockout policy (put the numbers in one config block in db_connection.php as constants)

* Observation window: 15 minutes
* Account lockout threshold: 5 failed attempts on a username within the window locks that username
* IP throttle threshold: 20 failed attempts from one IP within the window blocks that IP (catches spraying across many usernames)
* Lockout duration: progressive. First lockout 1 minute, then double each subsequent lockout for that username up to a 15 minute cap (mirrors the AWS Cognito pattern OWASP cites). Compute the current lockout level from how many prior lockout events exist in the window
* Admin accounts: stricter, threshold of 3 failed attempts
* Always keep the SAME generic error message. When locked, return a message like "Too many attempts. Please wait a few minutes and try again." but ensure the RESPONSE TIME is consistent (see 2.4) so timing does not leak whether the username exists

### 2.3 Wire into verify_user.php

Before checking credentials:

1. Record the client IP: prefer `$_SERVER['REMOTE_ADDR']`; only trust `X-Forwarded-For` if the app sits behind a known reverse proxy, otherwise ignore it (it is spoofable)
2. Query login_attempts for failed count on this username in the window, and separately for this IP
3. If either is over threshold OR the username is inside an active progressive lockout, reject with the throttle message and DO NOT evaluate the password
4. On every attempt (success or fail), INSERT a row into login_attempts with the outcome
5. On success, additionally clear or ignore prior failures (a successful login resets the counter for that username)

### 2.4 Timing consistency

To avoid user enumeration via response time, when the username does not exist, still run a dummy `password_verify()` against a fixed bcrypt hash constant so the failed path costs roughly the same time as the real one. This is a known OWASP pattern. Store one throwaway hash as a constant.

### 2.5 Cleanup

Add a small housekeeping DELETE (older than 24 hours) either at the top of verify_user.php on a random 1-in-N chance, or better, in the existing cron file, so the table does not grow without bound.

Verify Phase 2: fail login 5 times on one username, confirm the 6th is rejected as throttled even with the correct password, confirm it unlocks after the duration, confirm a different username from the same IP still works until the IP threshold, and confirm the login_attempts table logs rows.

## Phase 3: CSRF Protection

Every state-changing POST currently trusts the session cookie alone. Add a synchronizer token.

### 3.1 Token generation

In a new `includes/csrf.php`:

* `csrf_token()` returns `$_SESSION['csrf_token']`, generating it with `bin2hex(random_bytes(32))` on first call
* `csrf_verify($token)` does a `hash_equals()` comparison and returns bool

Generate the token at login (in verify_user.php after session_regenerate_id) and regenerate it on privilege changes.

### 3.2 Deliver the token to the client

On every module index.php, output the token in a meta tag in the head:

```php
<meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
```

### 3.3 Attach to every AJAX call

Set it globally once so you do not have to touch every call. In `src/script.js`, add:

```javascript
$.ajaxSetup({
    headers: { 'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content') }
});
```

Confirm script.js loads on every page before the page-specific inline scripts.

### 3.4 Verify server-side

In `includes/request_guard.php` (from Phase 1), after the POST and AJAX-header checks, also call `csrf_verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')` and 403 on failure. This way every processor gets CSRF enforcement for free through the one include, no need to edit 30 files individually. The login form is the one exception (no session yet), so verify_user.php does NOT include request_guard; its protection is the rate limiter from Phase 2.

Verify Phase 3: a POST to any processor without the header is rejected 403; normal in-app actions still work.

## Phase 4: Session and Transport Hardening

### 4.1 Central session bootstrap

Currently every page calls `session_start()` raw. Create `includes/session_boot.php` that sets cookie params BEFORE session_start:

```php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => isset($_SERVER['HTTPS'])  // true in production behind HTTPS
]);
session_start();
```

Replace the bare `session_start()` at the top of every page and processor with `require_once '.../includes/session_boot.php'`. Keep the relative paths correct per folder depth.

### 4.2 Idle and absolute session timeout

In session_boot.php, after start: track `$_SESSION['last_activity']`. If idle beyond 30 minutes, destroy the session and treat as logged out. Track `$_SESSION['created']` for an absolute cap (for example 8 hours) after which re-login is forced. These are cheap and align with the internal-tool nature of this app.

### 4.3 Password policy on account creation and reset

NIST SP 800-63B modern guidance favors length over complexity theatre. Enforce a minimum length (12 characters), reject a small blocklist of obvious passwords, and do NOT impose arbitrary composition rules. Apply the same policy in add_user, edit_user, and reset_user_password. Surface the rule in the UI via Parsley.

Verify Phase 4: inspect the session cookie in devtools (HttpOnly and SameSite set), confirm idle logout works, confirm a short password is rejected on user creation.

## Phase 5: Logic Fixes

These are the genuine correctness issues found in review. Keep them small and targeted.

### 5.1 Output escaping audit

Confirm every place user-entered data is echoed into HTML uses `htmlspecialchars()`. Sweep all module index.php files and especially anywhere a name, note, reason, or task title is printed into a table cell or modal. This is stored-XSS prevention and the one class of issue most likely to be lurking.

### 5.2 Task status notification target

In `update_task_status.php`, the notification currently broadcasts to the whole management tier (`send_notification(..., null, true)`). Change it to notify the task's `assigned_by` user specifically, so the manager who created the task hears about its progress and others are not spammed. Fetch assigned_by in the existing task lookup query.

### 5.3 Cron file web-trigger clarity

In `cert_expiry_reminders.php`, the web-triggered branch requires an Admin session, but Admin often has no employee_id and this file is meant for CLI. Simplify: if `PHP_SAPI !== 'cli'`, require a logged-in Admin session AND the CSRF/request guard, otherwise only allow CLI. Document the intended cron line. Better still, gate web execution off entirely unless a specific config flag is set, and rely on cron for the real runs.

### 5.4 Leave date sanity

`submit_leave_request.php` checks end >= start (good). Also reject start dates in the past beyond today, and cap unreasonable ranges (for example over 90 days) with a clear message, since these are almost always input errors.

### 5.5 Attendance double-submit and timezone

Confirm the server timezone is set explicitly (date_default_timezone_set for Malawi, Africa/Blantyre) in one central place so LATE_THRESHOLD_TIME comparisons are correct regardless of server locale. Right now a misconfigured server clock silently mis-flags Late.

Verify Phase 5: create a task as a manager, have the assignee move it, confirm only the assigning manager is notified; submit a leave request with a past start date and confirm rejection; confirm timezone is Africa/Blantyre.

## Phase 6: Module Builder (role default + per-person override)

This is the big feature. The requirement: tabs and dashboard widgets each employee sees should be customizable, driven by a role default that an Admin can override per individual. An Administration person might have the Certifications tab hidden, for example.

### 6.1 Data model (additive tables, schema untouched)

Migration `database/migrations/002_module_builder.sql`:

```sql
-- The catalog of toggleable modules/tabs. Seeded with the current fixed set.
CREATE TABLE IF NOT EXISTS modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_key VARCHAR(64) NOT NULL UNIQUE,   -- e.g. 'certification_management'
    display_name VARCHAR(128) NOT NULL,        -- e.g. 'Certifications'
    icon VARCHAR(64) NOT NULL,                 -- Font Awesome class, e.g. 'fa-certificate'
    sort_order INT NOT NULL DEFAULT 0,
    is_core TINYINT(1) NOT NULL DEFAULT 0      -- core modules (dashboard) cannot be hidden
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default visibility per role. This is the baseline every user of that role gets.
CREATE TABLE IF NOT EXISTS role_module_defaults (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(64) NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_role_module (role, module_id),
    CONSTRAINT fk_rmd_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-user override. NULL/absent means "inherit the role default".
CREATE TABLE IF NOT EXISTS user_module_overrides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    is_visible TINYINT(1) NOT NULL,
    UNIQUE KEY uq_user_module (user_id, module_id),
    CONSTRAINT fk_umo_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_umo_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Seed `modules` with the existing set: dashboard (is_core=1), employee_management, attendance, leave_management, task_management, asset_management, certification_management, cv_management, notifications, user_management. Seed `role_module_defaults` to reproduce the CURRENT behavior exactly (so nothing visibly changes until an Admin edits something): user_management visible only to Admin, management-tier modules per the existing can_manage_org logic, everything else visible to all. This "no visible change on day one" property is important, it means the refactor is safe.

### 6.2 Resolution logic (one function, single source of truth)

In a new `includes/modules.php`, write `get_visible_modules($conn, $user_id, $role)` that:

1. Loads all modules ordered by sort_order
2. Loads role_module_defaults for the role into a map
3. Loads user_module_overrides for the user into a map
4. For each module, effective visibility = override if present, else role default, else fall back to visible for non-admin-only modules. Core modules are always visible
5. Returns the ordered list of visible modules (key, display_name, icon)

Cache the result in the session per request is unnecessary; a single query set per page load is fine.

### 6.3 Make nav.php and sidebar.php data-driven

Replace the hardcoded links in `includes/nav.php` and `includes/sidebar.php` with a loop over `get_visible_modules()`. Preserve the exact existing markup, classes, active-state highlighting via $_SESSION['page_name'], and the mobile icon-collapse behavior. The only change is that the list is generated from the resolved module set instead of being static. This is what delivers the customization: hide Certifications for one Administration user and their nav simply stops rendering that link.

### 6.4 Enforce visibility server-side, not just in the nav

Hiding a nav link is cosmetic. Add a guard at the top of each module's index.php: after session boot and login check, call a helper `require_module_access($conn, $module_key)` that resolves the user's visible modules and, if this module is not among them, redirects to the dashboard with a flash message. This closes the hole where a user types the URL of a tab that was hidden from them. Core modules and Admin bypass as appropriate.

### 6.5 Admin UI for the builder

New page `module_builder/index.php` (Admin only, add it to the modules catalog as admin-only). Two management views:

* Role defaults: a table of roles (rows) by modules (columns) with visibility toggles. Saving writes role_module_defaults via a new processor `data_processors/save_role_modules.php`
* Per-user overrides: pick a user, see the effective resolved visibility (showing what is inherited vs overridden), toggle per-module overrides, with a "reset to role default" that deletes the override row. Processor `data_processors/save_user_modules.php`

Both processors follow the existing pattern: session_boot, request_guard (CSRF), auth_check with $admin_only, prepared statements, JSON response, SweetAlert2 feedback, and a notification row. Use DataTables or plain Bootstrap tables consistent with the house style.

### 6.6 Dashboard widget customization (same model, extended)

Apply the identical pattern to dashboard stat cards and panels: add dashboard widgets as rows in the modules table with a `widget` type flag, or a parallel `dashboard_widgets` catalog plus role/user visibility tables mirroring 6.1. Keep it simple: reuse the same three-table pattern rather than inventing a second mechanism. The dashboard then renders only the widgets resolved as visible for that user. Start with the existing cards (total employees, present today, pending leave, open tasks, assets assigned, certs expiring) as the seeded widget set.

Verify Phase 6: as Admin, hide Certifications for one Administration user; log in as that user and confirm the tab is gone from nav AND sidebar AND that typing /certification_management/ redirects them out; confirm a different Administration user still sees it (override is per-person); confirm changing the role default flips it for everyone of that role who has no override.

## Phase 7: Suggested Additional Features (implement the ones you want, in this order of value)

1. Audit log. A single `audit_log` table (user_id, action, entity, entity_id, detail, ip, created_at) written from a helper `log_action()` called in every state-changing processor. You already write notifications; this is the security-grade sibling. Invaluable for an MSSP-adjacent team and cheap to add. Add a read-only Admin viewer page with DataTables filtering.
2. Forced password change on first login. Add a `must_change_password` flag on users (default 1 for seeded/admin-created accounts). If set, every page redirects to a change-password screen until resolved. Closes the "admin sets a password and staff never change it" gap. OWASP-aligned.
3. Password reset self-service with single-use, time-limited, hashed tokens emailed via the existing PHPMailer, following the OWASP Forgot Password pattern (consistent response whether or not the account exists, token invalidated after use, no account state change until the token is presented). Reuses infrastructure you already have.
4. "Remember this device" / new-device email alert. Log a hash of a device cookie on successful login; email the user when a login comes from an unrecognized device. Lightweight, and it pairs naturally with the login_attempts work.
5. Optional TOTP 2FA for Admin and management roles. OWASP calls MFA the single highest-impact control. A PHP TOTP library is a single Composer package (does not change your stack, still just PHP). Make it opt-in per user, mandatory-configurable for Admin. This is the biggest security upgrade available if you want it.
6. Employee self-service profile edit with manager approval, so staff can propose changes to their own contact details rather than routing everything through Admin.
7. Export to CSV/PDF on the DataTables (DataTables Buttons extension for CSV; a small PDF path for reports). Useful for the CV/certification/attendance reporting your team does.
8. Dashboard "certifications expiring" already exists; extend the cron reminder to also notify the person's manager, not just the holder, so renewals are tracked at team level. Fits your certification-heavy team.

Items 1, 2, and 3 are the ones I would treat as near-mandatory for a system holding staff records; 5 is the highest-leverage optional.

## Build Order Recap

Phase 1 (exposure) and Phase 2 (brute-force) first, they are the stated priorities and the fastest risk reduction. Then 3 (CSRF) and 4 (sessions) since they share the request_guard include. Then 5 (logic). Then 6 (module builder, the largest piece). Then pick from 7. Verify each phase with the checks listed before moving on. Never skip the CSRF login exception note in 3.4. Keep the schema's core tables untouched; all new structure goes in numbered migration files under database/migrations/.

## Constraints Reminder

No stack changes. No em dashes in any code comment, UI string, commit message, or doc. Preserve the dual-theme Comfortaa house style. Preserve every existing working control listed in the diagnosis summary. Additive migrations only; do not rewrite schema.sql's core tables.
