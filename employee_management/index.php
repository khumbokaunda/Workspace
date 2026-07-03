<?php
require_once '../includes/session_boot.php';
$_SESSION['page_name'] = "employee_management";
$_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
if (!isset($_SESSION['logged_in'])) {
    header("location:../");
    exit;
}
include "../db_connection.php";
require_once "../includes/csrf.php";

$can_manage = can_manage_org($_SESSION['role']);

if ($can_manage) {
    $employees_sql = "SELECT e.*, m.first_name AS manager_first_name, m.last_name AS manager_last_name
                       FROM employees e
                       LEFT JOIN employees m ON m.id = e.manager_id
                       ORDER BY e.created_at DESC";
    $employees_result = $conn->query($employees_sql);

    $managers_sql = "SELECT id, first_name, last_name FROM employees WHERE status != 'Terminated' ORDER BY first_name, last_name";
    $managers_result = $conn->query($managers_sql);
    $managers_list = array();
    while ($row = $managers_result->fetch_assoc()) {
        $managers_list[] = $row;
    }
} else {
    $employee_id = $_SESSION['employee_id'];
    $fetch_own_employee_sql = "SELECT e.*, m.first_name AS manager_first_name, m.last_name AS manager_last_name
                                FROM employees e
                                LEFT JOIN employees m ON m.id = e.manager_id
                                WHERE e.id = ?";
    $fetch_own_employee_stmt = $conn->prepare($fetch_own_employee_sql);
    $fetch_own_employee_stmt->bind_param('i', $employee_id);
    $fetch_own_employee_stmt->execute();
    $own_employee_result = $fetch_own_employee_stmt->get_result();
    $own_employee = $own_employee_result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
    <title>WorkDesk | Employees</title>
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
                    <h1 class="comfortaa-bold fs-3 mb-1">Employees</h1>
                    <p class="text-white-50 mb-0">
                        <?php echo $can_manage ? "Manage your organization's employee records." : "Your employee profile."; ?>
                    </p>
                </div>
                <?php if ($can_manage) { ?>
                <button class="btn btn-success comfortaa-bold" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                    <i class="fa-solid fa-plus me-2"></i>Add Employee
                </button>
                <?php } ?>
            </div>

            <?php if ($can_manage) { ?>
            <div class="bg-222 rounded-3 p-3 p-md-4">
                <div class="table-responsive">
                    <table id="employees_table" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Specialization</th>
                                <th>Manager</th>
                                <th>Hire Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($employee = $employees_result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                <td><?php echo htmlspecialchars($employee['phone'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($employee['position'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($employee['department'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($employee['specialization'] ?? '-'); ?></td>
                                <td><?php echo $employee['manager_id'] ? htmlspecialchars($employee['manager_first_name'] . ' ' . $employee['manager_last_name']) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($employee['hire_date'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    $status_badge = 'bg-success';
                                    if ($employee['status'] === 'On Leave') $status_badge = 'bg-warning text-dark';
                                    if ($employee['status'] === 'Terminated') $status_badge = 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $status_badge; ?>"><?php echo htmlspecialchars($employee['status']); ?></span>
                                </td>
                                <td class="text-nowrap">
                                    <button class="btn btn-sm btn-333 bg-333 text-light edit_employee_btn"
                                            data-id="<?php echo $employee['id']; ?>"
                                            data-first_name="<?php echo htmlspecialchars($employee['first_name']); ?>"
                                            data-last_name="<?php echo htmlspecialchars($employee['last_name']); ?>"
                                            data-email="<?php echo htmlspecialchars($employee['email']); ?>"
                                            data-phone="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>"
                                            data-position="<?php echo htmlspecialchars($employee['position'] ?? ''); ?>"
                                            data-department="<?php echo htmlspecialchars($employee['department'] ?? ''); ?>"
                                            data-specialization="<?php echo htmlspecialchars($employee['specialization'] ?? ''); ?>"
                                            data-manager_id="<?php echo (int) $employee['manager_id']; ?>"
                                            data-hire_date="<?php echo htmlspecialchars($employee['hire_date'] ?? ''); ?>"
                                            data-status="<?php echo htmlspecialchars($employee['status']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editEmployeeModal"
                                            title="Edit employee" data-tooltip="1">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <?php if ($employee['status'] !== 'Terminated') { ?>
                                    <button class="btn btn-sm btn-danger delete_employee_btn" data-id="<?php echo $employee['id']; ?>"
                                            title="Terminate employee (sets status to Terminated)" data-tooltip="1">
                                        <i class="fa-solid fa-user-slash"></i>
                                    </button>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } else { ?>
            <div class="bg-222 rounded-3 p-4" style="max-width: 640px;">
                <?php if ($own_employee) { ?>
                <div class="d-flex align-items-center gap-3 mb-4">
                    <i class="fa-solid fa-circle-user" style="font-size: 3rem; color: #2fce6c;"></i>
                    <div>
                        <h2 class="comfortaa-bold fs-4 mb-0"><?php echo htmlspecialchars($own_employee['first_name'] . ' ' . $own_employee['last_name']); ?></h2>
                        <span class="text-white-50"><?php echo htmlspecialchars($own_employee['position'] ?? '-'); ?></span>
                    </div>
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-white-50">Email</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($own_employee['email']); ?></dd>
                    <dt class="col-sm-4 text-white-50">Phone</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($own_employee['phone'] ?? '-'); ?></dd>
                    <dt class="col-sm-4 text-white-50">Department</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($own_employee['department'] ?? '-'); ?></dd>
                    <dt class="col-sm-4 text-white-50">Specialization</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($own_employee['specialization'] ?? '-'); ?></dd>
                    <dt class="col-sm-4 text-white-50">Manager</dt>
                    <dd class="col-sm-8"><?php echo $own_employee['manager_id'] ? htmlspecialchars($own_employee['manager_first_name'] . ' ' . $own_employee['manager_last_name']) : '-'; ?></dd>
                    <dt class="col-sm-4 text-white-50">Hire Date</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($own_employee['hire_date'] ?? '-'); ?></dd>
                    <dt class="col-sm-4 text-white-50">Status</dt>
                    <dd class="col-sm-8"><span class="badge bg-success"><?php echo htmlspecialchars($own_employee['status']); ?></span></dd>
                </dl>
                <?php } else { ?>
                <p class="text-white-50 mb-0">Your account is not linked to an employee record yet, especially if it was just created. Please contact an administrator.</p>
                <?php } ?>
            </div>
            <?php } ?>
        </main>
    </div>

    <?php if ($can_manage) { ?>
    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Add Employee</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="add_employee_form" data-parsley-validate novalidate>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" id="add_first_name" name="first_name" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                   required data-parsley-required-message="Please enter a first name.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" id="add_last_name" name="last_name" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                   required data-parsley-required-message="Please enter a last name.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" id="add_email" name="email" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                   required data-parsley-type="email" data-parsley-required-message="Please enter a valid email.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" id="add_phone" name="phone" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" id="add_position" name="position" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" id="add_department" name="department" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Specialization</label>
                            <input type="text" id="add_specialization" name="specialization" placeholder="e.g. Networks &amp; Security, Infrastructure"
                                   class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Manager</label>
                            <select id="add_manager_id" name="manager_id" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="">No manager</option>
                                <?php foreach ($managers_list as $manager) { ?>
                                <option value="<?php echo $manager['id']; ?>">
                                    <?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hire Date</label>
                            <input type="date" id="add_hire_date" name="hire_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                   required data-parsley-required-message="Please choose a hire date.">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success comfortaa-bold">Save Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Edit Employee</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="edit_employee_form" data-parsley-validate novalidate>
                    <input type="hidden" id="edit_id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" id="edit_first_name" name="first_name" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" id="edit_last_name" name="last_name" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" id="edit_email" name="email" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required data-parsley-type="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" id="edit_phone" name="phone" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" id="edit_position" name="position" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" id="edit_department" name="department" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Specialization</label>
                            <input type="text" id="edit_specialization" name="specialization" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Manager</label>
                            <select id="edit_manager_id" name="manager_id" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="">No manager</option>
                                <?php foreach ($managers_list as $manager) { ?>
                                <option value="<?php echo $manager['id']; ?>">
                                    <?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hire Date</label>
                            <input type="date" id="edit_hire_date" name="hire_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select id="edit_status" name="status" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="Active">Active</option>
                                <option value="On Leave">On Leave</option>
                                <option value="Terminated">Terminated</option>
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
    <?php } ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="../src/parsely.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../src/script.js"></script>
    <?php if ($can_manage) { ?>
    <script defer>
        let employees_table = new DataTable('#employees_table');

        $('#add_employee_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }

            const data = {
                first_name: DOMPurify.sanitize($('#add_first_name').val()).trim(),
                last_name: DOMPurify.sanitize($('#add_last_name').val()).trim(),
                email: DOMPurify.sanitize($('#add_email').val()).trim(),
                phone: DOMPurify.sanitize($('#add_phone').val()).trim(),
                position: DOMPurify.sanitize($('#add_position').val()).trim(),
                department: DOMPurify.sanitize($('#add_department').val()).trim(),
                specialization: DOMPurify.sanitize($('#add_specialization').val()).trim(),
                manager_id: DOMPurify.sanitize($('#add_manager_id').val()).trim(),
                hire_date: DOMPurify.sanitize($('#add_hire_date').val()).trim()
            };

            $.ajax({
                url: '../data_processors/add_employee.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Employee added successfully.');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        show_error_toast(response.error || 'Could not add employee.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('.edit_employee_btn').on('click', function () {
            $('#edit_id').val($(this).data('id'));
            $('#edit_first_name').val($(this).data('first_name'));
            $('#edit_last_name').val($(this).data('last_name'));
            $('#edit_email').val($(this).data('email'));
            $('#edit_phone').val($(this).data('phone'));
            $('#edit_position').val($(this).data('position'));
            $('#edit_department').val($(this).data('department'));
            $('#edit_specialization').val($(this).data('specialization'));
            $('#edit_manager_id').val($(this).data('manager_id'));
            $('#edit_hire_date').val($(this).data('hire_date'));
            $('#edit_status').val($(this).data('status'));
        });

        $('#edit_employee_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }

            const data = {
                id: $('#edit_id').val(),
                first_name: DOMPurify.sanitize($('#edit_first_name').val()).trim(),
                last_name: DOMPurify.sanitize($('#edit_last_name').val()).trim(),
                email: DOMPurify.sanitize($('#edit_email').val()).trim(),
                phone: DOMPurify.sanitize($('#edit_phone').val()).trim(),
                position: DOMPurify.sanitize($('#edit_position').val()).trim(),
                department: DOMPurify.sanitize($('#edit_department').val()).trim(),
                specialization: DOMPurify.sanitize($('#edit_specialization').val()).trim(),
                manager_id: DOMPurify.sanitize($('#edit_manager_id').val()).trim(),
                hire_date: DOMPurify.sanitize($('#edit_hire_date').val()).trim(),
                status: DOMPurify.sanitize($('#edit_status').val()).trim()
            };

            $.ajax({
                url: '../data_processors/edit_employee.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Employee updated successfully.');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        show_error_toast(response.error || 'Could not update employee.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('.delete_employee_btn').on('click', function () {
            const employee_id = $(this).data('id');

            Swal.fire({
                title: 'Terminate this employee?',
                text: 'Their status will be set to Terminated. This can be undone later by editing the record.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, terminate',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../data_processors/delete_employee.php',
                        type: 'POST',
                        data: { id: employee_id },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                show_success_toast('Employee terminated.');
                                setTimeout(() => window.location.reload(), 1200);
                            } else {
                                show_error_toast(response.error || 'Could not update employee.');
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
    <?php } ?>
</body>
</html>
