<?php
require_once '../includes/session_boot.php';
$_SESSION['page_name'] = "task_management";
$_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
if (!isset($_SESSION['logged_in'])) {
    header("location:../");
    exit;
}
include "../db_connection.php";
require_once "../includes/csrf.php";

$is_admin = $_SESSION['role'] === 'Admin';
$is_line_manager = is_manager_tier($_SESSION['role']);
$show_org_wide = $is_admin || $is_line_manager;

if ($show_org_wide) {
    $tasks_sql = "SELECT t.*, e.first_name, e.last_name
                  FROM tasks t
                  JOIN employees e ON e.id = t.assigned_to
                  ORDER BY t.due_date ASC, t.created_at DESC";
    $tasks_result = $conn->query($tasks_sql);

    if ($is_line_manager) {
        $employees_sql = "SELECT id, first_name, last_name FROM employees WHERE status = 'Active' ORDER BY first_name, last_name";
        $employees_result = $conn->query($employees_sql);
        $employees_list = array();
        while ($row = $employees_result->fetch_assoc()) {
            $employees_list[] = $row;
        }
    }
} else {
    $employee_id = $_SESSION['employee_id'];
    $tasks_sql = "SELECT * FROM tasks WHERE assigned_to = ? ORDER BY due_date ASC, created_at DESC";
    $tasks_stmt = $conn->prepare($tasks_sql);
    $tasks_stmt->bind_param('i', $employee_id);
    $tasks_stmt->execute();
    $tasks_result = $tasks_stmt->get_result();
}

function priority_badge_class($priority) {
    switch ($priority) {
        case 'Low': return 'bg-secondary';
        case 'Medium': return 'bg-info text-dark';
        case 'High': return 'bg-warning text-dark';
        case 'Critical': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function status_badge_class($status) {
    switch ($status) {
        case 'To Do': return 'bg-secondary';
        case 'In Progress': return 'bg-info text-dark';
        case 'Done': return 'bg-success';
        case 'Blocked': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function is_overdue($due_date, $status) {
    return $due_date && $due_date < date('Y-m-d') && $status !== 'Done';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
    <title>WorkDesk | Tasks</title>
    <link rel="icon" href="../images/favicon.svg" type="image/svg+xml">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="../src/css/style.css" rel="stylesheet">
    <link href="../src/css/parsely.css" rel="stylesheet">
</head>
<body class="comfortaa-regular bg-111 text-light">
    <?php include "../includes/nav.php"; ?>

    <div class="d-flex">
        <?php include "../includes/sidebar.php"; ?>

        <main class="flex-grow-1 p-3 p-md-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h1 class="comfortaa-bold fs-3 mb-1">Tasks</h1>
                    <p class="text-white-50 mb-0">
                        <?php
                        if ($is_line_manager) {
                            echo "Assign work and track progress across the team.";
                        } elseif ($is_admin) {
                            echo "Read-only oversight, task assignment belongs to Managing Director and Technical Manager.";
                        } else {
                            echo "Your assigned tasks. Overdue tasks are marked in red.";
                        }
                        ?>
                    </p>
                </div>
                <?php if ($is_line_manager) { ?>
                <button class="btn btn-success comfortaa-bold" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                    <i class="fa-solid fa-plus me-2"></i>Assign Task
                </button>
                <?php } ?>
            </div>

            <div class="bg-222 rounded-3 p-3 p-md-4">
                <div class="table-responsive">
                    <table id="tasks_table" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <?php if ($show_org_wide) { ?><th>Assigned To</th><?php } ?>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Due Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($task = $tasks_result->fetch_assoc()) { ?>
                            <tr class="<?php echo is_overdue($task['due_date'], $task['status']) ? 'overdue-row' : ''; ?>">
                                <td>
                                    <?php echo htmlspecialchars($task['title']); ?>
                                    <?php if ($task['description']) { ?>
                                    <div class="text-white-50 small"><?php echo nl2br(htmlspecialchars($task['description'])); ?></div>
                                    <?php } ?>
                                </td>
                                <?php if ($show_org_wide) { ?>
                                <td><?php echo htmlspecialchars($task['first_name'] . ' ' . $task['last_name']); ?></td>
                                <?php } ?>
                                <td><span class="badge <?php echo priority_badge_class($task['priority']); ?>"><?php echo htmlspecialchars($task['priority']); ?></span></td>
                                <td><span class="badge <?php echo status_badge_class($task['status']); ?>"><?php echo htmlspecialchars($task['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($task['due_date'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($is_line_manager) { ?>
                                    <button class="btn btn-sm btn-333 bg-333 text-light edit_task_btn"
                                            data-id="<?php echo $task['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($task['title']); ?>"
                                            data-description="<?php echo htmlspecialchars($task['description'] ?? ''); ?>"
                                            data-assigned_to="<?php echo $task['assigned_to']; ?>"
                                            data-priority="<?php echo htmlspecialchars($task['priority']); ?>"
                                            data-status="<?php echo htmlspecialchars($task['status']); ?>"
                                            data-due_date="<?php echo htmlspecialchars($task['due_date'] ?? ''); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editTaskModal"
                                            title="Edit task" data-tooltip="1">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <?php } elseif ($is_admin) { ?>
                                        <span class="text-white-50">-</span>
                                    <?php } else { ?>
                                        <?php if ($task['status'] === 'To Do') { ?>
                                        <button class="btn btn-sm btn-info text-dark start_task_btn" data-id="<?php echo $task['id']; ?>">Start</button>
                                        <?php } ?>
                                        <?php if ($task['status'] === 'In Progress') { ?>
                                        <button class="btn btn-sm btn-success complete_task_btn" data-id="<?php echo $task['id']; ?>">Complete</button>
                                        <button class="btn btn-sm btn-danger block_task_btn" data-id="<?php echo $task['id']; ?>">Block</button>
                                        <?php } ?>
                                        <?php if ($task['status'] === 'Blocked') { ?>
                                        <button class="btn btn-sm btn-info text-dark start_task_btn" data-id="<?php echo $task['id']; ?>">Resume</button>
                                        <?php } ?>
                                        <?php if ($task['status'] === 'Done') { ?>
                                        <span class="text-white-50">-</span>
                                        <?php } ?>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <?php if ($is_line_manager) { ?>
    <!-- Add Task Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Assign Task</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="add_task_form" data-parsley-validate novalidate>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" id="add_title" name="title" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                   required data-parsley-required-message="Please enter a task title.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea id="add_description" name="description" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign To</label>
                            <select id="add_assigned_to" name="assigned_to" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                                <?php foreach ($employees_list as $employee) { ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select id="add_priority" name="priority" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" id="add_due_date" name="due_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success comfortaa-bold">Assign Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div class="modal fade" id="editTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Edit Task</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="edit_task_form" data-parsley-validate novalidate>
                    <input type="hidden" id="edit_task_id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" id="edit_title" name="title" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea id="edit_description" name="description" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign To</label>
                            <select id="edit_assigned_to" name="assigned_to" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                                <?php foreach ($employees_list as $employee) { ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select id="edit_priority" name="priority" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select id="edit_task_status" name="status" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="To Do">To Do</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Done">Done</option>
                                <option value="Blocked">Blocked</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" id="edit_due_date" name="due_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success comfortaa-bold">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php } elseif (!$show_org_wide) { ?>
    <!-- Block Task Modal -->
    <div class="modal fade" id="blockTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Report a Blocker</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="block_task_form" data-parsley-validate novalidate>
                    <input type="hidden" id="block_task_id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">What's blocking this task?</label>
                            <textarea id="block_note" name="note" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" rows="3"
                                      required data-parsley-required-message="Please tell us what's blocking this task."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger comfortaa-bold">Mark Blocked</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php } ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="../src/parsely.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../src/script.js"></script>
    <script defer>
        let tasks_table = new DataTable('#tasks_table', { order: [] });

        <?php if ($is_line_manager) { ?>
        $('#add_task_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }

            const data = {
                title: DOMPurify.sanitize($('#add_title').val()).trim(),
                description: DOMPurify.sanitize($('#add_description').val()).trim(),
                assigned_to: DOMPurify.sanitize($('#add_assigned_to').val()).trim(),
                priority: DOMPurify.sanitize($('#add_priority').val()).trim(),
                due_date: DOMPurify.sanitize($('#add_due_date').val()).trim()
            };

            $.ajax({
                url: '../data_processors/assign_task.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Task assigned successfully.');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        show_error_toast(response.error || 'Could not assign the task.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('.edit_task_btn').on('click', function () {
            $('#edit_task_id').val($(this).data('id'));
            $('#edit_title').val($(this).data('title'));
            $('#edit_description').val($(this).data('description'));
            $('#edit_assigned_to').val($(this).data('assigned_to'));
            $('#edit_priority').val($(this).data('priority'));
            $('#edit_task_status').val($(this).data('status'));
            $('#edit_due_date').val($(this).data('due_date'));
        });

        $('#edit_task_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }

            const data = {
                id: $('#edit_task_id').val(),
                title: DOMPurify.sanitize($('#edit_title').val()).trim(),
                description: DOMPurify.sanitize($('#edit_description').val()).trim(),
                assigned_to: DOMPurify.sanitize($('#edit_assigned_to').val()).trim(),
                priority: DOMPurify.sanitize($('#edit_priority').val()).trim(),
                status: DOMPurify.sanitize($('#edit_task_status').val()).trim(),
                due_date: DOMPurify.sanitize($('#edit_due_date').val()).trim()
            };

            $.ajax({
                url: '../data_processors/edit_task.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Task updated successfully.');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        show_error_toast(response.error || 'Could not update the task.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });
        <?php } elseif (!$show_org_wide) { ?>
        function update_task_status(id, status, note) {
            $.ajax({
                url: '../data_processors/update_task_status.php',
                type: 'POST',
                data: { id: id, status: status, note: note || '' },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Task updated.');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        show_error_toast(response.error || 'Could not update the task.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        }

        $('.start_task_btn').on('click', function () {
            update_task_status($(this).data('id'), 'In Progress');
        });

        $('.complete_task_btn').on('click', function () {
            update_task_status($(this).data('id'), 'Done');
        });

        $('.block_task_btn').on('click', function () {
            $('#block_task_id').val($(this).data('id'));
            $('#blockTaskModal').modal('show');
        });

        $('#block_task_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }
            const note = DOMPurify.sanitize($('#block_note').val()).trim();
            $('#blockTaskModal').modal('hide');
            update_task_status($('#block_task_id').val(), 'Blocked', note);
        });
        <?php } ?>
    </script>
</body>
</html>
