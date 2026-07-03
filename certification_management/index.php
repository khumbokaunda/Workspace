<?php
session_start();
$_SESSION['page_name'] = "certification_management";
$_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
if (!isset($_SESSION['logged_in'])) {
    header("location:../");
    exit;
}
include "../db_connection.php";

$can_manage = can_manage_org($_SESSION['role']);

// Statuses drift out of sync with expiry_date over time, so every page load
// resyncs them before anything is displayed.
$sync_status_sql = "UPDATE certifications SET status = 'Expired' WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE() AND status != 'Expired'";
$conn->query($sync_status_sql);

if ($can_manage) {
    $certifications_sql = "SELECT c.*, e.first_name, e.last_name,
                                   CASE WHEN c.expiry_date IS NOT NULL AND c.expiry_date < CURDATE() THEN 'Expired' ELSE c.status END AS computed_status
                            FROM certifications c
                            JOIN employees e ON e.id = c.employee_id
                            ORDER BY c.date_earned DESC";
    $certifications_result = $conn->query($certifications_sql);

    $employees_sql = "SELECT id, first_name, last_name FROM employees WHERE status = 'Active' ORDER BY first_name, last_name";
    $employees_result = $conn->query($employees_sql);
    $employees_list = array();
    while ($row = $employees_result->fetch_assoc()) {
        $employees_list[] = $row;
    }

    // Skills matrix: employees down the rows, distinct cert_code across the columns.
    $codes_sql = "SELECT DISTINCT cert_code FROM certifications WHERE cert_code IS NOT NULL AND cert_code != '' ORDER BY cert_code ASC";
    $codes_result = $conn->query($codes_sql);
    $cert_codes = array();
    while ($row = $codes_result->fetch_assoc()) {
        $cert_codes[] = $row['cert_code'];
    }

    $matrix_sql = "SELECT c.employee_id, c.cert_code
                    FROM certifications c
                    WHERE c.cert_code IS NOT NULL AND c.cert_code != ''
                      AND (c.expiry_date IS NULL OR c.expiry_date >= CURDATE())
                      AND c.status != 'Expired'";
    $matrix_result = $conn->query($matrix_sql);
    $held_codes = array();
    while ($row = $matrix_result->fetch_assoc()) {
        $held_codes[$row['employee_id']][$row['cert_code']] = true;
    }

    $all_employees_sql = "SELECT id, first_name, last_name FROM employees WHERE status != 'Terminated' ORDER BY first_name, last_name";
    $all_employees_result = $conn->query($all_employees_sql);
    $matrix_employees = array();
    while ($row = $all_employees_result->fetch_assoc()) {
        $matrix_employees[] = $row;
    }
} else {
    $employee_id = $_SESSION['employee_id'];
    $certifications_sql = "SELECT c.*,
                                   CASE WHEN c.expiry_date IS NOT NULL AND c.expiry_date < CURDATE() THEN 'Expired' ELSE c.status END AS computed_status
                            FROM certifications c
                            WHERE c.employee_id = ?
                            ORDER BY c.date_earned DESC";
    $certifications_stmt = $conn->prepare($certifications_sql);
    $certifications_stmt->bind_param('i', $employee_id);
    $certifications_stmt->execute();
    $certifications_result = $certifications_stmt->get_result();
}

function cert_status_badge($status) {
    switch ($status) {
        case 'Active': return 'bg-success';
        case 'Expired': return 'bg-danger';
        case 'In Progress': return 'bg-info text-dark';
        default: return 'bg-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkDesk | Certifications</title>
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
                    <h1 class="comfortaa-bold fs-3 mb-1">Certifications</h1>
                    <p class="text-white-50 mb-0">
                        <?php echo $can_manage ? "Track every certification across the team, especially the ones about to expire." : "Track your own certifications and their expiry dates."; ?>
                    </p>
                </div>
                <button class="btn btn-success comfortaa-bold" data-bs-toggle="modal" data-bs-target="#addCertificationModal">
                    <i class="fa-solid fa-plus me-2"></i>Add Certification
                </button>
            </div>

            <?php if ($can_manage) { ?>
            <ul class="nav nav-tabs border-secondary mb-3" id="certTabs">
                <li class="nav-item">
                    <button class="nav-link active bg-222 text-light border-secondary" data-bs-toggle="tab" data-bs-target="#certListPane" type="button">All Certifications</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link bg-222 text-light border-secondary" data-bs-toggle="tab" data-bs-target="#skillsMatrixPane" type="button">Skills Matrix</button>
                </li>
            </ul>
            <div class="tab-content">
            <div class="tab-pane fade show active" id="certListPane">
            <?php } ?>

            <div class="bg-222 rounded-3 p-3 p-md-4">
                <div class="table-responsive">
                    <table id="certifications_table" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <?php if ($can_manage) { ?><th>Employee</th><?php } ?>
                                <th>Certification</th>
                                <th>Issuing Body</th>
                                <th>Code</th>
                                <th>Earned</th>
                                <th>Expiry</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($cert = $certifications_result->fetch_assoc()) { ?>
                            <tr>
                                <?php if ($can_manage) { ?>
                                <td><?php echo htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']); ?></td>
                                <?php } ?>
                                <td>
                                    <?php echo htmlspecialchars($cert['cert_name']); ?>
                                    <?php if ($cert['verification_url']) { ?>
                                    <a href="<?php echo htmlspecialchars($cert['verification_url']); ?>" target="_blank" rel="noopener" class="ms-1" title="Verify">
                                        <i class="fa-solid fa-arrow-up-right-from-square small"></i>
                                    </a>
                                    <?php } ?>
                                </td>
                                <td><?php echo htmlspecialchars($cert['issuing_body'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($cert['cert_code'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($cert['date_earned']); ?></td>
                                <td><?php echo htmlspecialchars($cert['expiry_date'] ?? '-'); ?></td>
                                <td><span class="badge <?php echo cert_status_badge($cert['computed_status']); ?>"><?php echo htmlspecialchars($cert['computed_status']); ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-333 bg-333 text-light edit_cert_btn"
                                            data-id="<?php echo $cert['id']; ?>"
                                            data-cert_name="<?php echo htmlspecialchars($cert['cert_name']); ?>"
                                            data-issuing_body="<?php echo htmlspecialchars($cert['issuing_body'] ?? ''); ?>"
                                            data-cert_code="<?php echo htmlspecialchars($cert['cert_code'] ?? ''); ?>"
                                            data-date_earned="<?php echo htmlspecialchars($cert['date_earned']); ?>"
                                            data-expiry_date="<?php echo htmlspecialchars($cert['expiry_date'] ?? ''); ?>"
                                            data-credential_id="<?php echo htmlspecialchars($cert['credential_id'] ?? ''); ?>"
                                            data-verification_url="<?php echo htmlspecialchars($cert['verification_url'] ?? ''); ?>"
                                            data-status="<?php echo htmlspecialchars($cert['status']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editCertificationModal"
                                            title="Edit certification" data-tooltip="1">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete_cert_btn" data-id="<?php echo $cert['id']; ?>"
                                            title="Delete certification" data-tooltip="1">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($can_manage) { ?>
            </div>
            <div class="tab-pane fade" id="skillsMatrixPane">
                <div class="bg-222 rounded-3 p-3 p-md-4">
                    <p class="text-white-50">A check mark means the employee currently holds an active certification with that code.</p>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle w-100">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <?php foreach ($cert_codes as $code) { ?>
                                    <th class="text-center"><?php echo htmlspecialchars($code); ?></th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matrix_employees as $employee) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                                    <?php foreach ($cert_codes as $code) { ?>
                                    <td class="text-center">
                                        <?php if (isset($held_codes[$employee['id']][$code])) { ?>
                                        <i class="fa-solid fa-check text-success"></i>
                                        <?php } else { ?>
                                        <span class="text-white-50">-</span>
                                        <?php } ?>
                                    </td>
                                    <?php } ?>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            </div>
            <?php } ?>
        </main>
    </div>

    <!-- Add Certification Modal -->
    <div class="modal fade" id="addCertificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Add Certification</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="add_certification_form" data-parsley-validate novalidate>
                    <div class="modal-body">
                        <?php if ($can_manage) { ?>
                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <select id="add_cert_employee_id" name="employee_id" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                                <?php foreach ($employees_list as $employee) { ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php } ?>
                        <div class="mb-3">
                            <label class="form-label">Certification Name</label>
                            <input type="text" id="add_cert_name" name="cert_name" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                   required data-parsley-required-message="Please enter a certification name.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Issuing Body</label>
                            <input type="text" id="add_issuing_body" name="issuing_body" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Certification Code</label>
                            <input type="text" id="add_cert_code" name="cert_code" placeholder="e.g. CCNA, CCSE, HCIA" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date Earned</label>
                            <input type="date" id="add_date_earned" name="date_earned" max="<?php echo date('Y-m-d'); ?>"
                                   class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                   required data-parsley-required-message="Please choose the date this was earned."
                                   data-parsley-max="<?php echo date('Y-m-d'); ?>" data-parsley-max-message="The date earned cannot be in the future.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" id="add_expiry_date" name="expiry_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Credential ID</label>
                            <input type="text" id="add_credential_id" name="credential_id" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Verification URL</label>
                            <input type="url" id="add_verification_url" name="verification_url" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success comfortaa-bold">Save Certification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Certification Modal -->
    <div class="modal fade" id="editCertificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Edit Certification</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="edit_certification_form" data-parsley-validate novalidate>
                    <input type="hidden" id="edit_cert_id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Certification Name</label>
                            <input type="text" id="edit_cert_name" name="cert_name" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Issuing Body</label>
                            <input type="text" id="edit_issuing_body" name="issuing_body" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Certification Code</label>
                            <input type="text" id="edit_cert_code" name="cert_code" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date Earned</label>
                            <input type="date" id="edit_date_earned" name="date_earned" max="<?php echo date('Y-m-d'); ?>"
                                   class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required
                                   data-parsley-max="<?php echo date('Y-m-d'); ?>" data-parsley-max-message="The date earned cannot be in the future.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" id="edit_expiry_date" name="expiry_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Credential ID</label>
                            <input type="text" id="edit_credential_id" name="credential_id" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Verification URL</label>
                            <input type="url" id="edit_verification_url" name="verification_url" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select id="edit_cert_status" name="status" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="Active">Active</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Expired">Expired</option>
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="../src/parsely.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../src/script.js"></script>
    <script defer>
        let certifications_table = new DataTable('#certifications_table');

        $('#add_certification_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }

            const data = {
                employee_id: $('#add_cert_employee_id').length ? DOMPurify.sanitize($('#add_cert_employee_id').val()).trim() : '',
                cert_name: DOMPurify.sanitize($('#add_cert_name').val()).trim(),
                issuing_body: DOMPurify.sanitize($('#add_issuing_body').val()).trim(),
                cert_code: DOMPurify.sanitize($('#add_cert_code').val()).trim(),
                date_earned: DOMPurify.sanitize($('#add_date_earned').val()).trim(),
                expiry_date: DOMPurify.sanitize($('#add_expiry_date').val()).trim(),
                credential_id: DOMPurify.sanitize($('#add_credential_id').val()).trim(),
                verification_url: DOMPurify.sanitize($('#add_verification_url').val()).trim()
            };

            $.ajax({
                url: '../data_processors/add_certification.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Certification added successfully.');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        show_error_toast(response.error || 'Could not add the certification.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('.edit_cert_btn').on('click', function () {
            $('#edit_cert_id').val($(this).data('id'));
            $('#edit_cert_name').val($(this).data('cert_name'));
            $('#edit_issuing_body').val($(this).data('issuing_body'));
            $('#edit_cert_code').val($(this).data('cert_code'));
            $('#edit_date_earned').val($(this).data('date_earned'));
            $('#edit_expiry_date').val($(this).data('expiry_date'));
            $('#edit_credential_id').val($(this).data('credential_id'));
            $('#edit_verification_url').val($(this).data('verification_url'));
            $('#edit_cert_status').val($(this).data('status'));
        });

        $('#edit_certification_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }

            const data = {
                id: $('#edit_cert_id').val(),
                cert_name: DOMPurify.sanitize($('#edit_cert_name').val()).trim(),
                issuing_body: DOMPurify.sanitize($('#edit_issuing_body').val()).trim(),
                cert_code: DOMPurify.sanitize($('#edit_cert_code').val()).trim(),
                date_earned: DOMPurify.sanitize($('#edit_date_earned').val()).trim(),
                expiry_date: DOMPurify.sanitize($('#edit_expiry_date').val()).trim(),
                credential_id: DOMPurify.sanitize($('#edit_credential_id').val()).trim(),
                verification_url: DOMPurify.sanitize($('#edit_verification_url').val()).trim(),
                status: DOMPurify.sanitize($('#edit_cert_status').val()).trim()
            };

            $.ajax({
                url: '../data_processors/edit_certification.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Certification updated successfully.');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        show_error_toast(response.error || 'Could not update the certification.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('.delete_cert_btn').on('click', function () {
            const id = $(this).data('id');

            Swal.fire({
                title: 'Delete this certification?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../data_processors/delete_certification.php',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                show_success_toast('Certification deleted.');
                                setTimeout(() => window.location.reload(), 1200);
                            } else {
                                show_error_toast(response.error || 'Could not delete the certification.');
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
