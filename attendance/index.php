<?php
require_once '../includes/session_boot.php';
$_SESSION['page_name'] = "attendance";
$_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
if (!isset($_SESSION['logged_in'])) {
    header("location:../");
    exit;
}
include "../db_connection.php";
require_once "../includes/csrf.php";

$can_manage = can_manage_org($_SESSION['role']);

if ($can_manage) {
    $start_date = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : date('Y-m-d');

    $attendance_sql = "SELECT a.*, e.first_name, e.last_name
                        FROM attendance a
                        JOIN employees e ON e.id = a.employee_id
                        WHERE a.work_date BETWEEN ? AND ?
                        ORDER BY a.work_date DESC, e.first_name ASC";
    $attendance_stmt = $conn->prepare($attendance_sql);
    $attendance_stmt->bind_param('ss', $start_date, $end_date);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result();

    $employees_sql = "SELECT id, first_name, last_name FROM employees WHERE status = 'Active' ORDER BY first_name, last_name";
    $employees_result = $conn->query($employees_sql);
    $employees_list = array();
    while ($row = $employees_result->fetch_assoc()) {
        $employees_list[] = $row;
    }
} else {
    $employee_id = $_SESSION['employee_id'];
    $today = date('Y-m-d');

    $fetch_today_sql = "SELECT * FROM attendance WHERE employee_id = ? AND work_date = ?";
    $fetch_today_stmt = $conn->prepare($fetch_today_sql);
    $fetch_today_stmt->bind_param('is', $employee_id, $today);
    $fetch_today_stmt->execute();
    $today_attendance = $fetch_today_stmt->get_result()->fetch_assoc();

    $history_sql = "SELECT * FROM attendance WHERE employee_id = ? ORDER BY work_date DESC LIMIT 90";
    $history_stmt = $conn->prepare($history_sql);
    $history_stmt->bind_param('i', $employee_id);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
    <title>WorkDesk | Attendance</title>
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
            <h1 class="comfortaa-bold fs-3 mb-1">Attendance</h1>
            <p class="text-white-50 mb-4">
                <?php echo $can_manage ? "Review and correct attendance across the organization." : "Check in and check out, and review your history."; ?>
            </p>

            <?php if (!$can_manage) { ?>
            <div class="bg-222 rounded-3 p-4 mb-4">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div>
                        <p class="text-white-50 mb-1">Today, <?php echo date('l, F j, Y'); ?></p>
                        <?php if ($today_attendance && $today_attendance['check_in']) { ?>
                            <p class="mb-0">Checked in at <strong><?php echo htmlspecialchars($today_attendance['check_in']); ?></strong>
                            <?php if ($today_attendance['status'] === 'Late') { ?><span class="badge bg-warning text-dark ms-1">Late</span><?php } ?>
                            <?php if ($today_attendance['check_out']) { ?>
                                &middot; Checked out at <strong><?php echo htmlspecialchars($today_attendance['check_out']); ?></strong>
                            <?php } ?>
                            </p>
                        <?php } else { ?>
                            <p class="mb-0 text-white-50">You have not checked in yet today.</p>
                        <?php } ?>
                    </div>
                    <div class="ms-md-auto d-flex gap-2">
                        <button id="check_in_btn" class="btn btn-success comfortaa-bold" <?php echo ($today_attendance && $today_attendance['check_in']) ? 'disabled' : ''; ?>>
                            <i class="fa-solid fa-right-to-bracket me-2"></i>Check In
                        </button>
                        <button id="check_out_btn" class="btn btn-333 bg-333 text-light comfortaa-bold"
                                <?php echo (!$today_attendance || !$today_attendance['check_in'] || $today_attendance['check_out']) ? 'disabled' : ''; ?>>
                            <i class="fa-solid fa-right-from-bracket me-2"></i>Check Out
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-222 rounded-3 p-3 p-md-4">
                <h2 class="fs-5 comfortaa-bold mb-3">My History</h2>
                <div class="table-responsive">
                    <table id="attendance_table" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($record = $history_result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['work_date']); ?></td>
                                <td><?php echo htmlspecialchars($record['check_in'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($record['check_out'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    $badge = 'bg-success';
                                    if ($record['status'] === 'Late') $badge = 'bg-warning text-dark';
                                    if ($record['status'] === 'Absent') $badge = 'bg-danger';
                                    if ($record['status'] === 'Remote') $badge = 'bg-info text-dark';
                                    ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($record['status']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($record['notes'] ?? '-'); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } else { ?>
            <div class="bg-222 rounded-3 p-3 p-md-4 mb-4">
                <form id="date_range_form" class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label mb-1">From</label>
                        <input type="date" name="start_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-2" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-1">To</label>
                        <input type="date" name="end_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-2" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-success">Filter</button>
                    </div>
                    <div class="col-auto ms-auto">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-toggle="modal" data-bs-target="#markAbsenceModal">
                            <i class="fa-solid fa-calendar-xmark me-2"></i>Mark Absence
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-222 rounded-3 p-3 p-md-4">
                <div class="table-responsive">
                    <table id="attendance_table" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($record = $attendance_result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['work_date']); ?></td>
                                <td><?php echo htmlspecialchars($record['check_in'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($record['check_out'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    $badge = 'bg-success';
                                    if ($record['status'] === 'Late') $badge = 'bg-warning text-dark';
                                    if ($record['status'] === 'Absent') $badge = 'bg-danger';
                                    if ($record['status'] === 'Remote') $badge = 'bg-info text-dark';
                                    ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($record['status']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($record['notes'] ?? '-'); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-333 bg-333 text-light edit_attendance_btn"
                                            data-id="<?php echo $record['id']; ?>"
                                            data-check_in="<?php echo htmlspecialchars($record['check_in'] ?? ''); ?>"
                                            data-check_out="<?php echo htmlspecialchars($record['check_out'] ?? ''); ?>"
                                            data-status="<?php echo htmlspecialchars($record['status']); ?>"
                                            data-notes="<?php echo htmlspecialchars($record['notes'] ?? ''); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editAttendanceModal"
                                            title="Correct this attendance record" data-tooltip="1">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } ?>
        </main>
    </div>

    <?php if ($can_manage) { ?>
    <!-- Mark Absence Modal -->
    <div class="modal fade" id="markAbsenceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Mark Absence</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="mark_absence_form" data-parsley-validate novalidate>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <select id="absence_employee_id" name="employee_id" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                                <?php foreach ($employees_list as $employee) { ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" id="absence_date" name="work_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea id="absence_notes" name="notes" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger comfortaa-bold">Mark Absent</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Attendance Modal -->
    <div class="modal fade" id="editAttendanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Correct Attendance Record</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="edit_attendance_form" data-parsley-validate novalidate>
                    <input type="hidden" id="edit_attendance_id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Check In</label>
                            <input type="time" id="edit_check_in" name="check_in" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Check Out</label>
                            <input type="time" id="edit_check_out" name="check_out" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select id="edit_attendance_status" name="status" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="Present">Present</option>
                                <option value="Late">Late</option>
                                <option value="Absent">Absent</option>
                                <option value="Remote">Remote</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea id="edit_attendance_notes" name="notes" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success comfortaa-bold">Save Correction</button>
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
        let attendance_table = new DataTable('#attendance_table', { order: [] });

        <?php if (!$can_manage) { ?>
        $('#check_in_btn').on('click', function () {
            $.ajax({
                url: '../data_processors/check_in.php',
                type: 'POST',
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Checked in successfully.');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        show_error_toast(response.error || 'Could not check in.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('#check_out_btn').on('click', function () {
            $.ajax({
                url: '../data_processors/check_out.php',
                type: 'POST',
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Checked out successfully.');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        show_error_toast(response.error || 'Could not check out.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });
        <?php } else { ?>
        $('.edit_attendance_btn').on('click', function () {
            $('#edit_attendance_id').val($(this).data('id'));
            $('#edit_check_in').val($(this).data('check_in'));
            $('#edit_check_out').val($(this).data('check_out'));
            $('#edit_attendance_status').val($(this).data('status'));
            $('#edit_attendance_notes').val($(this).data('notes'));
        });

        $('#edit_attendance_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }

            const data = {
                id: $('#edit_attendance_id').val(),
                check_in: DOMPurify.sanitize($('#edit_check_in').val()).trim(),
                check_out: DOMPurify.sanitize($('#edit_check_out').val()).trim(),
                status: DOMPurify.sanitize($('#edit_attendance_status').val()).trim(),
                notes: DOMPurify.sanitize($('#edit_attendance_notes').val()).trim()
            };

            $.ajax({
                url: '../data_processors/edit_attendance.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Attendance record updated.');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        show_error_toast(response.error || 'Could not update the record.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('#mark_absence_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }

            const data = {
                employee_id: DOMPurify.sanitize($('#absence_employee_id').val()).trim(),
                work_date: DOMPurify.sanitize($('#absence_date').val()).trim(),
                notes: DOMPurify.sanitize($('#absence_notes').val()).trim()
            };

            $.ajax({
                url: '../data_processors/mark_absence.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Absence recorded.');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        show_error_toast(response.error || 'Could not record the absence.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });
        <?php } ?>
    </script>
</body>
</html>
