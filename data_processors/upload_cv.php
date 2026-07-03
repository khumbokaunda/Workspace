<?php
session_start();
require_once '../db_connection.php';
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

define('CV_MAX_BYTES', 5 * 1024 * 1024);
define('CV_ALLOWED_EXTENSIONS', array('pdf', 'docx'));
define('CV_ALLOWED_MIME_TYPES', array(
    'pdf' => array('application/pdf'),
    'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip')
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_admin = $_SESSION['role'] === 'Admin';
    $employee_id = $is_admin ? (int) $_POST['employee_id'] : (int) $_SESSION['employee_id'];
    $version_label = trim($_POST['version_label']);
    $notes = trim($_POST['notes']);

    if (!isset($_FILES['cv_file']) || $_FILES['cv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(array('success' => false, 'error' => 'Please choose a file to upload.'));
        exit;
    }

    $file = $_FILES['cv_file'];

    if ($file['size'] > CV_MAX_BYTES) {
        echo json_encode(array('success' => false, 'error' => 'The file is larger than the 5 MB limit.'));
        exit;
    }

    $original_name = $file['name'];
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    if (!in_array($extension, CV_ALLOWED_EXTENSIONS, true)) {
        echo json_encode(array('success' => false, 'error' => 'Only PDF and DOCX files are allowed.'));
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected_mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($detected_mime, CV_ALLOWED_MIME_TYPES[$extension], true)) {
        echo json_encode(array('success' => false, 'error' => 'The file content does not match a PDF or DOCX file.'));
        exit;
    }

    $hashed_filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = __DIR__ . '/../uploads/cv_files/' . $hashed_filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        echo json_encode(array('success' => false, 'error' => 'Could not save the uploaded file.'));
        exit;
    }

    $uploaded_by = (int) $_SESSION['user_id'];

    $upload_cv_sql = "INSERT INTO cv_records (employee_id, version_label, file_path, file_name_original, uploaded_by, notes)
                       VALUES (?, ?, ?, ?, ?, ?)";
    $upload_cv_stmt = $conn->prepare($upload_cv_sql);
    $upload_cv_stmt->bind_param('isssis', $employee_id, $version_label, $hashed_filename, $original_name, $uploaded_by, $notes);

    if ($upload_cv_stmt->execute()) {
        $notification_text = "A new CV was uploaded" . ($version_label !== '' ? " ({$version_label})" : '') . ".";
        send_notification($conn, $notification_text, 'cv_management', $employee_id, false);
        echo json_encode(array('success' => true));
    } else {
        unlink($destination);
        echo json_encode(array('success' => false, 'error' => $upload_cv_stmt->error));
    }
}
