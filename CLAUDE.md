# WorkDesk WMS - Project Conventions

WorkDesk is a workplace management web application in PHP, JavaScript, Bootstrap 5 and MySQL. Every module is fully functional end to end. The developer maintaining this codebase writes in a specific style described below. Match it exactly. Do not introduce frameworks, ORMs, routing libraries, build tools, or OOP class hierarchies.

## Tech stack (fixed, do not substitute)

* PHP 8.x, procedural style, mysqli object API with prepared statements only
* MySQL 8.x (utf8mb4, InnoDB, foreign keys)
* Bootstrap 5.3.x via CDN
* jQuery 3.7.x via CDN, all server communication via $.ajax POST to data processor files returning JSON
* DataTables for all data tables
* Parsley.js for client-side form validation (vendored in src/parsely.js and src/css/parsely.css)
* DOMPurify to sanitize all user input in JS before sending via AJAX
* SweetAlert2 for delete confirmations and success/error feedback; no alert() except DB connection failure
* Font Awesome 6.5.x icons
* Google Font Comfortaa (classes comfortaa-regular, comfortaa-bold)
* PHPMailer via Composer for email notifications

## Coding conventions

* Procedural PHP, no classes or namespaces (except PHPMailer use statements)
* Every page directory contains a single index.php with the session guard at the top: session_start (now via includes/session_boot.php), $_SESSION['page_name'], redirect_url, logged_in check, then include db_connection.php
* Data processors live in data_processors/, one file per action, named verb_noun.php. Each starts the session, requires db_connection.php, includes includes/auth_check.php (with $admin_only, $org_manager_only, or $line_manager_only set first when a tier is required), reads $_POST into snake_case variables, uses prepared statements, echoes json_encode(array('success' => ...)), and calls send_notification() on state changes
* SQL variables named descriptively: $add_employee_sql, $add_employee_stmt, $fetch_user_stmt
* Passwords: password_hash($password, PASSWORD_BCRYPT) and password_verify()
* JS is inline in script defer blocks at the bottom of each page, with shared helpers only in src/script.js
* Form submits: event.preventDefault(), Parsley validate, DOMPurify.sanitize each field, then $.ajax
* Theme: dual dark/light via data-bs-theme on html, dark default, custom utility classes .bg-000/.bg-111/.bg-222/.bg-333, accent text-success/btn-success, persisted in localStorage key workdesk_theme

## Role hierarchy

Roles: Admin, Managing Director, Technical Manager, Engineer, Sales, Administration.

* Admin is a pure IT/system role: user accounts, employee records, assets, system settings. No leave or task approval authority
* Managing Director and Technical Manager are the line-management tier: they assign tasks and approve leave for their direct reports (routed via employees.manager_id)
* Admin, Managing Director, and Technical Manager share org-wide visibility on Employee/Attendance/Asset/Certification records (can_manage_org() in db_connection.php)
* Engineer, Sales, Administration are regular staff and see only their own records

## Word choice

Use "issues" not "constraints"; "mostly", "especially", "having said that". Never use the words "grid" or "compounds". No em dashes anywhere in code comments, UI text, commit messages, or documentation.

## Hardening work

The hardening and feature build is governed by WMS-hardening-implementation-plan.md and CLAUDE-hardening-addendum.md in this directory. Where those documents and this file appear to conflict, the addendum wins for the hardening build.
