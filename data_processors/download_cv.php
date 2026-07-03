<?php
session_start();
require_once '../db_connection.php';
require_once '../includes/auth_check.php';

// Files are served through this script rather than a direct URL, and Staff
// can only ever download their own CV, checked here against the session.
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$fetch_cv_sql = "SELECT employee_id, file_path, file_name_original FROM cv_records WHERE id = ?";
$fetch_cv_stmt = $conn->prepare($fetch_cv_sql);
$fetch_cv_stmt->bind_param('i', $id);
$fetch_cv_stmt->execute();
$cv = $fetch_cv_stmt->get_result()->fetch_assoc();

if (!$cv) {
    http_response_code(404);
    exit('File not found.');
}

$is_admin = $_SESSION['role'] === 'Admin';
if (!$is_admin && (int) $cv['employee_id'] !== (int) $_SESSION['employee_id']) {
    http_response_code(403);
    exit('You do not have permission to download that file.');
}

$file_path = __DIR__ . '/../uploads/cv_files/' . $cv['file_path'];
if (!file_exists($file_path)) {
    http_response_code(404);
    exit('File not found.');
}

$extension = strtolower(pathinfo($cv['file_path'], PATHINFO_EXTENSION));
$mime_type = $extension === 'pdf'
    ? 'application/pdf'
    : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . basename($cv['file_name_original']) . '"');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;
