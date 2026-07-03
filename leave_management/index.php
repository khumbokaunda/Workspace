<?php
require_once '../includes/session_boot.php';
$_SESSION['page_name'] = "leave_management";
$_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
if (!isset($_SESSION['logged_in'])) {
    header("location:../");
    exit;
}
include "../db_connection.php";
require_once "../includes/csrf.php";

$role = $_SESSION['role'];
$is_admin = $role === 'Admin';
$is_manager = is_manager_tier($role);

if ($is_admin) {
    // Admin has no approval authority, this view is read-only oversight.
    $leave_requests_sql = "SELECT l.*, e.first_name, e.last_name, e.email
                            FROM leave_requests l
                            JOIN employees e ON e.id = l.employee_id
                            ORDER BY l.created_at DESC";
    $leave_requests_result = $conn->query($leave_requests_sql);
} elseif ($is_manager) {
    $employee_id = $_SESSION['employee_id'];

    $own_requests_sql = "SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY created_at DESC";
    $own_requests_stmt = $conn->prepare($own_requests_sql);
    $own_requests_stmt->bind_param('i', $employee_id);
    $own_requests_stmt->execute();
    $own_requests_result = $own_requests_stmt->get_result();

    $team_requests_sql = "SELECT l.*, e.first_name, e.last_name
                           FROM leave_requests l
                           JOIN employees e ON e.id = l.employee_id
                           WHERE e.manager_id = ?
                              OR (e.manager_id IS NULL AND e.id != ? AND ? = 'Managing Director')
                           ORDER BY l.created_at DESC";
    $team_requests_stmt = $conn->prepare($team_requests_sql);
    $team_requests_stmt->bind_param('iis', $employee_id, $employee_id, $role);
    $team_requests_stmt->execute();
    $team_requests_result = $team_requests_stmt->get_result();
} else {
    $employee_id = $_SESSION['employee_id'];
    $leave_requests_sql = "SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY created_at DESC";
    $leave_requests_stmt = $conn->prepare($leave_requests_sql);
    $leave_requests_stmt->bind_param('i', $employee_id);
    $leave_requests_stmt->execute();
    $leave_requests_result = $leave_requests_stmt->get_result();
}

function leave_status_badge($status) {
    if ($status === 'Approved') return 'bg-success';
    if ($status === 'Rejected') return 'bg-danger';
    return 'bg-warning text-dark';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
    <title>WorkDesk | Leave</title>
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
                    <h1 class="comfortaa-bold fs-3 mb-1">Leave Management</h1>
                    <p class="text-white-50 mb-0">
                        <?php
                        if ($is_admin) {
                            echo "Read-only oversight, mostly for visibility. Approval is handled by each employee's manager, not Admin.";
                        } elseif ($is_manager) {
                            echo "Approve requests from your direct reports, and track your own.";
                        } else {
                            echo "Submit and track your leave requests.";
                        }
                        ?>
                    </p>
                </div>
                <?php if (!$is_admin) { ?>
                <button class="btn btn-success comfortaa-bold" data-bs-toggle="modal" data-bs-target="#requestLeaveModal">
                    <i class="fa-solid fa-plus me-2"></i>Request Leave
                </button>
                <?php } ?>
            </div>

            <?php if ($is_manager) { ?>
            <div class="bg-222 rounded-3 p-3 p-md-4 mb-4">
                <h2 class="fs-5 comfortaa-bold mb-3">Team Requests Awaiting Your Review</h2>
                <div class="table-responsive">
                    <table id="team_leave_table" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $team_requests_result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                <td><?php echo htmlspecialchars($request['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($request['end_date']); ?></td>
                                <td><?php echo htmlspecialchars($request['reason'] ?? '-'); ?></td>
                                <td><span class="badge <?php echo leave_status_badge($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span></td>
                                <td>
                                    <?php if ($request['status'] === 'Pending') { ?>
                                    <button class="btn btn-sm btn-success approve_leave_btn" data-id="<?php echo $request['id']; ?>"
                                            title="Approve this request" data-tooltip="1">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger reject_leave_btn" data-id="<?php echo $request['id']; ?>"
                                            title="Reject this request" data-tooltip="1">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                    <?php } else { ?>
                                    <span class="text-white-50">-</span>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-222 rounded-3 p-3 p-md-4">
                <h2 class="fs-5 comfortaa-bold mb-3">My Requests</h2>
                <div class="table-responsive">
                    <table id="own_leave_table" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $own_requests_result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                <td><?php echo htmlspecialchars($request['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($request['end_date']); ?></td>
                                <td><?php echo htmlspecialchars($request['reason'] ?? '-'); ?></td>
                                <td><span class="badge <?php echo leave_status_badge($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } else { ?>
            <div class="bg-222 rounded-3 p-3 p-md-4">
                <div class="table-responsive">
                    <table id="leave_table" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <?php if ($is_admin) { ?><th>Employee</th><?php } ?>
                                <th>Type</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $leave_requests_result->fetch_assoc()) { ?>
                            <tr>
                                <?php if ($is_admin) { ?>
                                <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                <?php } ?>
                                <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                <td><?php echo htmlspecialchars($request['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($request['end_date']); ?></td>
                                <td><?php echo htmlspecialchars($request['reason'] ?? '-'); ?></td>
                                <td><span class="badge <?php echo leave_status_badge($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } ?>
        </main>
    </div>

    <?php if (!$is_admin) { ?>
    <!-- Request Leave Modal -->
    <div class="modal fade" id="requestLeaveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Request Leave</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="request_leave_form" data-parsley-validate novalidate>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Leave Type</label>
                            <select id="leave_type" name="leave_type" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="Annual">Annual</option>
                                <option value="Sick">Sick</option>
                                <option value="Compassionate">Compassionate</option>
                                <option value="Study">Study</option>
                                <option value="Unpaid">Unpaid</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                   required data-parsley-required-message="Please choose a start date.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                   required data-parsley-required-message="Please choose an end date."
                                   data-parsley-gte-field="start_date" data-parsley-gte-field-message="End date must be on or after the start date.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea id="reason" name="reason" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" rows="3"
                                      required data-parsley-required-message="Please tell us the reason for this leave."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success comfortaa-bold">Submit Request</button>
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
        window.Parsley.addValidator('gteField', {
            requirementType: 'string',
            validateString: function (value, fieldName) {
                const otherValue = $('#' + fieldName).val();
                if (!value || !otherValue) return true;
                return value >= otherValue;
            },
            messages: {
                en: 'This date must be on or after the other date.'
            }
        });

        <?php if ($is_manager) { ?>
        let team_leave_table = new DataTable('#team_leave_table', { order: [] });
        let own_leave_table = new DataTable('#own_leave_table', { order: [] });
        <?php } else { ?>
        let leave_table = new DataTable('#leave_table', { order: [] });
        <?php } ?>

        <?php if (!$is_admin) { ?>
        $('#request_leave_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }

            const data = {
                leave_type: DOMPurify.sanitize($('#leave_type').val()).trim(),
                start_date: DOMPurify.sanitize($('#start_date').val()).trim(),
                end_date: DOMPurify.sanitize($('#end_date').val()).trim(),
                reason: DOMPurify.sanitize($('#reason').val()).trim()
            };

            $.ajax({
                url: '../data_processors/submit_leave_request.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Leave request submitted.');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        show_error_toast(response.error || 'Could not submit the leave request.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });
        <?php } ?>

        <?php if ($is_manager) { ?>
        $('.approve_leave_btn').on('click', function () {
            const id = $(this).data('id');

            Swal.fire({
                title: 'Approve this leave request?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, approve',
                confirmButtonColor: '#198754'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../data_processors/approve_leave_request.php',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                show_success_toast('Leave request approved.');
                                setTimeout(() => window.location.reload(), 1200);
                            } else {
                                show_error_toast(response.error || 'Could not approve the request.');
                            }
                        },
                        error: function () {
                            show_error_toast('Something went wrong. Please try again.');
                        }
                    });
                }
            });
        });

        $('.reject_leave_btn').on('click', function () {
            const id = $(this).data('id');

            Swal.fire({
                title: 'Reject this leave request?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, reject',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../data_processors/reject_leave_request.php',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                show_success_toast('Leave request rejected.');
                                setTimeout(() => window.location.reload(), 1200);
                            } else {
                                show_error_toast(response.error || 'Could not reject the request.');
                            }
                        },
                        error: function () {
                            show_error_toast('Something went wrong. Please try again.');
                        }
                    });
                }
            });
        });
        <?php } ?>
    </script>
</body>
</html>
