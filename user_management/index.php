<?php
session_start();
$_SESSION['page_name'] = "user_management";
$_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
if (!isset($_SESSION['logged_in'])) {
    header("location:../");
    exit;
}
if ($_SESSION['role'] !== 'Admin') {
    header("location:../dashboard");
    exit;
}
include "../db_connection.php";
require_once "../includes/csrf.php";

$users_sql = "SELECT u.id, u.username, u.role, u.employee_id, u.created_at,
                     e.first_name, e.last_name
              FROM users u
              LEFT JOIN employees e ON e.id = u.employee_id
              ORDER BY u.created_at DESC";
$users_result = $conn->query($users_sql);

$employees_sql = "SELECT id, first_name, last_name FROM employees WHERE status != 'Terminated' ORDER BY first_name, last_name";
$employees_result = $conn->query($employees_sql);
$employees_list = array();
while ($row = $employees_result->fetch_assoc()) {
    $employees_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
    <title>WorkDesk | User Accounts</title>
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
                    <h1 class="comfortaa-bold fs-3 mb-1">User Accounts</h1>
                    <p class="text-white-50 mb-0">Manage who can log in to WorkDesk, mostly this covers linking accounts to employees.</p>
                </div>
                <button class="btn btn-success comfortaa-bold" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fa-solid fa-plus me-2"></i>Add User
                </button>
            </div>

            <div class="bg-222 rounded-3 p-3 p-md-4">
                <div class="table-responsive">
                    <table id="users_table" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Linked Employee</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users_result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <?php
                                    $role_badge = 'bg-secondary';
                                    if ($user['role'] === 'Admin') $role_badge = 'bg-success';
                                    if (is_manager_tier($user['role'])) $role_badge = 'bg-info text-dark';
                                    ?>
                                    <span class="badge <?php echo $role_badge; ?>">
                                        <?php echo htmlspecialchars($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $user['employee_id'] ? htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) : '<span class="text-white-50">Not linked</span>'; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-333 bg-333 text-light edit_user_btn"
                                            data-id="<?php echo $user['id']; ?>"
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                            data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                            data-employee_id="<?php echo (int) $user['employee_id']; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editUserModal"
                                            title="Edit account" data-tooltip="1">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="btn btn-sm btn-333 bg-333 text-light reset_password_btn"
                                            data-id="<?php echo $user['id']; ?>"
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                                            title="Reset password" data-tooltip="1">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete_user_btn" data-id="<?php echo $user['id']; ?>"
                                            title="Delete account permanently" data-tooltip="1">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Add User Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="add_user_form" data-parsley-validate novalidate>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" id="add_username" name="username" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                   required data-parsley-required-message="Please enter a username.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" id="add_password" name="password" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                       required data-parsley-minlength="8" data-parsley-required-message="Please choose a password."
                                       data-parsley-minlength-message="Password should be at least 8 characters.">
                                <button class="btn btn-333 bg-333 border-0 text-light" type="button" onclick="view_password('add_password', 'add_password_icon')">
                                    <i class="fa-solid fa-eye" id="add_password_icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" id="add_confirm_password" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                       required data-parsley-equalto="#add_password" data-parsley-required-message="Please confirm the password."
                                       data-parsley-equalto-message="Passwords do not match.">
                                <button class="btn btn-333 bg-333 border-0 text-light" type="button" onclick="view_password('add_confirm_password', 'add_confirm_password_icon')">
                                    <i class="fa-solid fa-eye" id="add_confirm_password_icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select id="add_role" name="role" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <?php foreach (all_roles() as $role_option) { ?>
                                <option value="<?php echo htmlspecialchars($role_option); ?>"><?php echo htmlspecialchars($role_option); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Linked Employee</label>
                            <select id="add_employee_id" name="employee_id" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="">Not linked</option>
                                <?php foreach ($employees_list as $employee) { ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success comfortaa-bold">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Edit User Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="edit_user_form" data-parsley-validate novalidate>
                    <input type="hidden" id="edit_id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" id="edit_username" name="username" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select id="edit_role" name="role" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <?php foreach (all_roles() as $role_option) { ?>
                                <option value="<?php echo htmlspecialchars($role_option); ?>"><?php echo htmlspecialchars($role_option); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Linked Employee</label>
                            <select id="edit_employee_id" name="employee_id" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="">Not linked</option>
                                <?php foreach ($employees_list as $employee) { ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
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

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Reset Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="reset_password_form" data-parsley-validate novalidate>
                    <input type="hidden" id="reset_id" name="id">
                    <div class="modal-body">
                        <p class="text-white-50">Setting a new password for <strong id="reset_username" class="text-light"></strong>.</p>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" id="reset_password" name="password" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                       required data-parsley-minlength="8" data-parsley-required-message="Please enter a new password."
                                       data-parsley-minlength-message="Password should be at least 8 characters.">
                                <button class="btn btn-333 bg-333 border-0 text-light" type="button" onclick="view_password('reset_password', 'reset_password_icon')">
                                    <i class="fa-solid fa-eye" id="reset_password_icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" id="reset_confirm_password" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                       required data-parsley-equalto="#reset_password" data-parsley-required-message="Please confirm the new password."
                                       data-parsley-equalto-message="Passwords do not match.">
                                <button class="btn btn-333 bg-333 border-0 text-light" type="button" onclick="view_password('reset_confirm_password', 'reset_confirm_password_icon')">
                                    <i class="fa-solid fa-eye" id="reset_confirm_password_icon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success comfortaa-bold">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="../src/parsely.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../src/script.js"></script>
    <script defer>
        let users_table = new DataTable('#users_table');

        $('#add_user_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }

            const data = {
                username: DOMPurify.sanitize($('#add_username').val()).trim(),
                password: DOMPurify.sanitize($('#add_password').val()).trim(),
                role: DOMPurify.sanitize($('#add_role').val()).trim(),
                employee_id: DOMPurify.sanitize($('#add_employee_id').val()).trim()
            };

            $.ajax({
                url: '../data_processors/add_user.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('User account created successfully.');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        show_error_toast(response.error || 'Could not create the user account.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('.edit_user_btn').on('click', function () {
            $('#edit_id').val($(this).data('id'));
            $('#edit_username').val($(this).data('username'));
            $('#edit_role').val($(this).data('role'));
            $('#edit_employee_id').val($(this).data('employee_id'));
        });

        $('#edit_user_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }

            const data = {
                id: $('#edit_id').val(),
                username: DOMPurify.sanitize($('#edit_username').val()).trim(),
                role: DOMPurify.sanitize($('#edit_role').val()).trim(),
                employee_id: DOMPurify.sanitize($('#edit_employee_id').val()).trim()
            };

            $.ajax({
                url: '../data_processors/edit_user.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('User account updated successfully.');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        show_error_toast(response.error || 'Could not update the user account.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('.reset_password_btn').on('click', function () {
            $('#reset_id').val($(this).data('id'));
            $('#reset_username').text($(this).data('username'));
        });

        $('#reset_password_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }

            const data = {
                id: $('#reset_id').val(),
                password: DOMPurify.sanitize($('#reset_password').val()).trim()
            };

            $.ajax({
                url: '../data_processors/reset_user_password.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Password reset successfully.');
                        $('#resetPasswordModal').modal('hide');
                        $('#reset_password_form')[0].reset();
                    } else {
                        show_error_toast(response.error || 'Could not reset the password.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('.delete_user_btn').on('click', function () {
            const user_id = $(this).data('id');

            Swal.fire({
                title: 'Delete this user account?',
                text: 'This will permanently remove their ability to log in. This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../data_processors/delete_user.php',
                        type: 'POST',
                        data: { id: user_id },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                show_success_toast('User account deleted.');
                                setTimeout(() => window.location.reload(), 1200);
                            } else {
                                show_error_toast(response.error || 'Could not delete the user account.');
                            }
                        },
                        error: function () {
                            show_error_toast('Something went wrong. Please try again.');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
