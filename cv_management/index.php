<?php
session_start();
$_SESSION['page_name'] = "cv_management";
$_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
if (!isset($_SESSION['logged_in'])) {
    header("location:../");
    exit;
}
include "../db_connection.php";
require_once "../includes/csrf.php";

$is_admin = $_SESSION['role'] === 'Admin';

if ($is_admin) {
    $cv_records_sql = "SELECT cv.*, e.first_name, e.last_name, u.username AS uploaded_by_username
                        FROM cv_records cv
                        JOIN employees e ON e.id = cv.employee_id
                        JOIN users u ON u.id = cv.uploaded_by
                        ORDER BY cv.uploaded_at DESC";
    $cv_records_result = $conn->query($cv_records_sql);

    $employees_sql = "SELECT id, first_name, last_name FROM employees WHERE status != 'Terminated' ORDER BY first_name, last_name";
    $employees_result = $conn->query($employees_sql);
    $employees_list = array();
    while ($row = $employees_result->fetch_assoc()) {
        $employees_list[] = $row;
    }
} else {
    $employee_id = $_SESSION['employee_id'];
    $cv_records_sql = "SELECT cv.*, u.username AS uploaded_by_username
                        FROM cv_records cv
                        JOIN users u ON u.id = cv.uploaded_by
                        WHERE cv.employee_id = ?
                        ORDER BY cv.uploaded_at DESC";
    $cv_records_stmt = $conn->prepare($cv_records_sql);
    $cv_records_stmt->bind_param('i', $employee_id);
    $cv_records_stmt->execute();
    $cv_records_result = $cv_records_stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
    <title>WorkDesk | CVs</title>
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
                    <h1 class="comfortaa-bold fs-3 mb-1">CVs</h1>
                    <p class="text-white-50 mb-0">
                        <?php echo $is_admin ? "Every CV on file, newest first." : "Your CV versions, newest first."; ?>
                    </p>
                </div>
                <button class="btn btn-success comfortaa-bold" data-bs-toggle="modal" data-bs-target="#uploadCvModal">
                    <i class="fa-solid fa-upload me-2"></i>Upload CV
                </button>
            </div>

            <div class="bg-222 rounded-3 p-3 p-md-4">
                <div class="table-responsive">
                    <table id="cv_table" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <?php if ($is_admin) { ?><th>Employee</th><?php } ?>
                                <th>Version</th>
                                <th>File</th>
                                <th>Uploaded By</th>
                                <th>Uploaded</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($cv = $cv_records_result->fetch_assoc()) { ?>
                            <tr>
                                <?php if ($is_admin) { ?>
                                <td><?php echo htmlspecialchars($cv['first_name'] . ' ' . $cv['last_name']); ?></td>
                                <?php } ?>
                                <td><?php echo htmlspecialchars($cv['version_label'] ?? '-'); ?></td>
                                <td><i class="fa-solid fa-file-lines me-1"></i><?php echo htmlspecialchars($cv['file_name_original']); ?></td>
                                <td><?php echo htmlspecialchars($cv['uploaded_by_username']); ?></td>
                                <td><?php echo htmlspecialchars($cv['uploaded_at']); ?></td>
                                <td><?php echo htmlspecialchars($cv['notes'] ?? '-'); ?></td>
                                <td>
                                    <a href="../data_processors/download_cv.php?id=<?php echo $cv['id']; ?>" class="btn btn-sm btn-333 bg-333 text-light"
                                       title="Download file" data-tooltip="1">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                    <?php if ($is_admin) { ?>
                                    <button class="btn btn-sm btn-danger delete_cv_btn" data-id="<?php echo $cv['id']; ?>"
                                            title="Delete file permanently" data-tooltip="1">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
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

    <!-- Upload CV Modal -->
    <div class="modal fade" id="uploadCvModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Upload CV</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="upload_cv_form" data-parsley-validate novalidate enctype="multipart/form-data">
                    <div class="modal-body">
                        <?php if ($is_admin) { ?>
                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <select id="upload_employee_id" name="employee_id" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                                <?php foreach ($employees_list as $employee) { ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php } ?>
                        <div class="mb-3">
                            <label class="form-label">Version Label</label>
                            <input type="text" id="upload_version_label" name="version_label" placeholder="e.g. 2026 Update" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File (PDF or DOCX, max 5 MB)</label>
                            <input type="file" id="upload_cv_file" name="cv_file" accept=".pdf,.docx" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea id="upload_notes" name="notes" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success comfortaa-bold">Upload</button>
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
        let cv_table = new DataTable('#cv_table');

        $('#upload_cv_form').on('submit', function (event) {
            event.preventDefault();
            if (!$(this).parsley().validate()) {
                return;
            }

            const form_data = new FormData();
            <?php if ($is_admin) { ?>
            form_data.append('employee_id', DOMPurify.sanitize($('#upload_employee_id').val()).trim());
            <?php } ?>
            form_data.append('version_label', DOMPurify.sanitize($('#upload_version_label').val()).trim());
            form_data.append('notes', DOMPurify.sanitize($('#upload_notes').val()).trim());
            form_data.append('cv_file', $('#upload_cv_file')[0].files[0]);

            $.ajax({
                url: '../data_processors/upload_cv.php',
                type: 'POST',
                data: form_data,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('CV uploaded successfully.');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        show_error_toast(response.error || 'Could not upload the CV.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('.delete_cv_btn').on('click', function () {
            const id = $(this).data('id');

            Swal.fire({
                title: 'Delete this CV file?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../data_processors/delete_cv.php',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                show_success_toast('CV deleted.');
                                setTimeout(() => window.location.reload(), 1200);
                            } else {
                                show_error_toast(response.error || 'Could not delete the CV.');
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
