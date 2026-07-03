<?php
session_start();
require_once '../db_connection.php';
require_once '../includes/auth_check.php';
require_once '../includes/send_notification.php';

// Staff can only update tasks assigned to their own employee_id, this is
// verified server-side against the session rather than trusting the client.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $status = trim($_POST['status']);
    $note = trim($_POST['note'] ?? '');
    $employee_id = (int) $_SESSION['employee_id'];

    $allowed_statuses = array('In Progress', 'Done', 'Blocked');
    if (!in_array($status, $allowed_statuses, true)) {
        echo json_encode(array('success' => false, 'error' => 'That status is not valid.'));
        exit;
    }

    $fetch_task_sql = "SELECT title, description, assigned_to FROM tasks WHERE id = ?";
    $fetch_task_stmt = $conn->prepare($fetch_task_sql);
    $fetch_task_stmt->bind_param('i', $id);
    $fetch_task_stmt->execute();
    $task = $fetch_task_stmt->get_result()->fetch_assoc();

    if (!$task || (int) $task['assigned_to'] !== $employee_id) {
        echo json_encode(array('success' => false, 'error' => 'You do not have permission to update that task.'));
        exit;
    }

    $description = $task['description'];
    if ($status === 'Blocked' && $note !== '') {
        $description = trim($description . "\n\nBlocked: " . $note);
    }

    $completed_at = $status === 'Done' ? date('Y-m-d H:i:s') : null;

    $update_status_sql = "UPDATE tasks SET status = ?, description = ?, completed_at = ? WHERE id = ?";
    $update_status_stmt = $conn->prepare($update_status_sql);
    $update_status_stmt->bind_param('sssi', $status, $description, $completed_at, $id);

    if ($update_status_stmt->execute()) {
        $notification_text = "{$_SESSION['username']} moved the task \"{$task['title']}\" to {$status}.";
        send_notification($conn, $notification_text, 'task_management', null, true);
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'error' => $update_status_stmt->error));
    }
}
