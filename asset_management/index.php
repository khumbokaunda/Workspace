<?php
session_start();
$_SESSION['page_name'] = "asset_management";
$_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
if (!isset($_SESSION['logged_in'])) {
    header("location:../");
    exit;
}
include "../db_connection.php";

$is_admin = $_SESSION['role'] === 'Admin';

function asset_status_badge($status) {
    switch ($status) {
        case 'Available': return 'bg-success';
        case 'Assigned': return 'bg-info text-dark';
        case 'In Repair': return 'bg-warning text-dark';
        case 'Retired': return 'bg-secondary';
        default: return 'bg-secondary';
    }
}

if ($is_admin) {
    $assets_sql = "SELECT * FROM assets ORDER BY asset_name ASC";
    $assets_result = $conn->query($assets_sql);
    $assets_list = array();
    while ($row = $assets_result->fetch_assoc()) {
        $assets_list[] = $row;
    }

    $history_sql = "SELECT aa.asset_id, aa.assigned_date, aa.returned_date, e.first_name, e.last_name
                     FROM asset_assignments aa
                     JOIN employees e ON e.id = aa.employee_id
                     ORDER BY aa.assigned_date DESC";
    $history_result = $conn->query($history_sql);
    $history_by_asset = array();
    while ($row = $history_result->fetch_assoc()) {
        $history_by_asset[$row['asset_id']][] = array(
            'employee' => $row['first_name'] . ' ' . $row['last_name'],
            'assigned_date' => $row['assigned_date'],
            'returned_date' => $row['returned_date']
        );
    }

    $employees_sql = "SELECT id, first_name, last_name FROM employees WHERE status = 'Active' ORDER BY first_name, last_name";
    $employees_result = $conn->query($employees_sql);
    $employees_list = array();
    while ($row = $employees_result->fetch_assoc()) {
        $employees_list[] = $row;
    }
} else {
    $employee_id = $_SESSION['employee_id'];
    $my_assets_sql = "SELECT a.*, aa.assigned_date
                       FROM asset_assignments aa
                       JOIN assets a ON a.id = aa.asset_id
                       WHERE aa.employee_id = ? AND aa.returned_date IS NULL
                       ORDER BY aa.assigned_date DESC";
    $my_assets_stmt = $conn->prepare($my_assets_sql);
    $my_assets_stmt->bind_param('i', $employee_id);
    $my_assets_stmt->execute();
    $my_assets_result = $my_assets_stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkDesk | Assets</title>
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
                    <h1 class="comfortaa-bold fs-3 mb-1">Assets</h1>
                    <p class="text-white-50 mb-0">
                        <?php echo $is_admin ? "Track equipment and manage assignments." : "Equipment currently assigned to you."; ?>
                    </p>
                </div>
                <?php if ($is_admin) { ?>
                <button class="btn btn-success comfortaa-bold" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                    <i class="fa-solid fa-plus me-2"></i>Add Asset
                </button>
                <?php } ?>
            </div>

            <div class="bg-222 rounded-3 p-3 p-md-4">
                <div class="table-responsive">
                    <?php if ($is_admin) { ?>
                    <table id="assets_table" class="table table-dark table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Tag</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Serial</th>
                                <th>Warranty Expiry</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assets_list as $asset) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($asset['asset_tag']); ?></td>
                                <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                <td><?php echo htmlspecialchars($asset['category']); ?></td>
                                <td><?php echo htmlspecialchars($asset['serial_number'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($asset['warranty_expiry'] ?? '-'); ?></td>
                                <td><span class="badge <?php echo asset_status_badge($asset['status']); ?>"><?php echo htmlspecialchars($asset['status']); ?></span></td>
                                <td class="text-nowrap">
                                    <button class="btn btn-sm btn-333 bg-333 text-light edit_asset_btn"
                                            data-id="<?php echo $asset['id']; ?>"
                                            data-asset_tag="<?php echo htmlspecialchars($asset['asset_tag']); ?>"
                                            data-asset_name="<?php echo htmlspecialchars($asset['asset_name']); ?>"
                                            data-category="<?php echo htmlspecialchars($asset['category']); ?>"
                                            data-serial_number="<?php echo htmlspecialchars($asset['serial_number'] ?? ''); ?>"
                                            data-purchase_date="<?php echo htmlspecialchars($asset['purchase_date'] ?? ''); ?>"
                                            data-warranty_expiry="<?php echo htmlspecialchars($asset['warranty_expiry'] ?? ''); ?>"
                                            data-status="<?php echo htmlspecialchars($asset['status']); ?>"
                                            data-notes="<?php echo htmlspecialchars($asset['notes'] ?? ''); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editAssetModal">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <?php if ($asset['status'] === 'Available') { ?>
                                    <button class="btn btn-sm btn-info text-dark assign_asset_btn"
                                            data-id="<?php echo $asset['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($asset['asset_name']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#assignAssetModal">
                                        <i class="fa-solid fa-user-plus"></i>
                                    </button>
                                    <?php } ?>
                                    <?php if ($asset['status'] === 'Assigned') { ?>
                                    <button class="btn btn-sm btn-warning text-dark return_asset_btn" data-id="<?php echo $asset['id']; ?>">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                    <?php } ?>
                                    <button class="btn btn-sm btn-333 bg-333 text-light history_asset_btn"
                                            data-name="<?php echo htmlspecialchars($asset['asset_name']); ?>"
                                            data-history='<?php echo htmlspecialchars(json_encode($history_by_asset[$asset['id']] ?? array())); ?>'
                                            data-bs-toggle="modal" data-bs-target="#historyAssetModal">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete_asset_btn" data-id="<?php echo $asset['id']; ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <?php } else { ?>
                    <table id="assets_table" class="table table-dark table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Tag</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Assigned Since</th>
                                <th>Warranty Expiry</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($asset = $my_assets_result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($asset['asset_tag']); ?></td>
                                <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                <td><?php echo htmlspecialchars($asset['category']); ?></td>
                                <td><?php echo htmlspecialchars($asset['assigned_date']); ?></td>
                                <td><?php echo htmlspecialchars($asset['warranty_expiry'] ?? '-'); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <?php } ?>
                </div>
            </div>
        </main>
    </div>

    <?php if ($is_admin) { ?>
    <!-- Add Asset Modal -->
    <div class="modal fade" id="addAssetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Add Asset</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="add_asset_form" data-parsley-validate novalidate>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Asset Tag</label>
                            <input type="text" id="add_asset_tag" name="asset_tag" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                   required data-parsley-required-message="Please enter an asset tag.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" id="add_asset_name" name="asset_name" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                   required data-parsley-required-message="Please enter an asset name.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select id="add_category" name="category" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="Laptop">Laptop</option>
                                <option value="Desktop">Desktop</option>
                                <option value="Network Device">Network Device</option>
                                <option value="Server">Server</option>
                                <option value="Peripheral">Peripheral</option>
                                <option value="Software License">Software License</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Serial Number</label>
                            <input type="text" id="add_serial_number" name="serial_number" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" id="add_purchase_date" name="purchase_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Warranty Expiry</label>
                            <input type="date" id="add_warranty_expiry" name="warranty_expiry" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea id="add_notes" name="notes" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success comfortaa-bold">Save Asset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Asset Modal -->
    <div class="modal fade" id="editAssetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Edit Asset</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="edit_asset_form" data-parsley-validate novalidate>
                    <input type="hidden" id="edit_asset_id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Asset Tag</label>
                            <input type="text" id="edit_asset_tag" name="asset_tag" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" id="edit_asset_name" name="asset_name" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select id="edit_category" name="category" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="Laptop">Laptop</option>
                                <option value="Desktop">Desktop</option>
                                <option value="Network Device">Network Device</option>
                                <option value="Server">Server</option>
                                <option value="Peripheral">Peripheral</option>
                                <option value="Software License">Software License</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Serial Number</label>
                            <input type="text" id="edit_serial_number" name="serial_number" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" id="edit_purchase_date" name="purchase_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Warranty Expiry</label>
                            <input type="date" id="edit_warranty_expiry" name="warranty_expiry" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select id="edit_asset_status" name="status" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3">
                                <option value="Available">Available</option>
                                <option value="Assigned">Assigned</option>
                                <option value="In Repair">In Repair</option>
                                <option value="Retired">Retired</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea id="edit_notes" name="notes" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" rows="2"></textarea>
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

    <!-- Assign Asset Modal -->
    <div class="modal fade" id="assignAssetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Assign Asset</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="assign_asset_form" data-parsley-validate novalidate>
                    <input type="hidden" id="assign_asset_id" name="asset_id">
                    <div class="modal-body">
                        <p class="text-white-50">Assigning <strong id="assign_asset_name" class="text-light"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <select id="assign_employee_id" name="employee_id" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3" required>
                                <?php foreach ($employees_list as $employee) { ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assigned Date</label>
                            <input type="date" id="assign_assigned_date" name="assigned_date" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-333 bg-333 text-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success comfortaa-bold">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Asset History Modal -->
    <div class="modal fade" id="historyAssetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-222 text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title comfortaa-bold">Assignment History &mdash; <span id="history_asset_name"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="history_asset_body" class="text-white-50">No assignment history yet.</div>
                </div>
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
    <?php if ($is_admin) { ?>
    <script defer>
        let assets_table = new DataTable('#assets_table');

        $('#add_asset_form').on('submit', function (event) {
            event.preventDefault();

            const data = {
                asset_tag: DOMPurify.sanitize($('#add_asset_tag').val()).trim(),
                asset_name: DOMPurify.sanitize($('#add_asset_name').val()).trim(),
                category: DOMPurify.sanitize($('#add_category').val()).trim(),
                serial_number: DOMPurify.sanitize($('#add_serial_number').val()).trim(),
                purchase_date: DOMPurify.sanitize($('#add_purchase_date').val()).trim(),
                warranty_expiry: DOMPurify.sanitize($('#add_warranty_expiry').val()).trim(),
                notes: DOMPurify.sanitize($('#add_notes').val()).trim()
            };

            $.ajax({
                url: '../data_processors/add_asset.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Asset added successfully.');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        show_error_toast(response.error || 'Could not add the asset.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('.edit_asset_btn').on('click', function () {
            $('#edit_asset_id').val($(this).data('id'));
            $('#edit_asset_tag').val($(this).data('asset_tag'));
            $('#edit_asset_name').val($(this).data('asset_name'));
            $('#edit_category').val($(this).data('category'));
            $('#edit_serial_number').val($(this).data('serial_number'));
            $('#edit_purchase_date').val($(this).data('purchase_date'));
            $('#edit_warranty_expiry').val($(this).data('warranty_expiry'));
            $('#edit_asset_status').val($(this).data('status'));
            $('#edit_notes').val($(this).data('notes'));
        });

        $('#edit_asset_form').on('submit', function (event) {
            event.preventDefault();

            const data = {
                id: $('#edit_asset_id').val(),
                asset_tag: DOMPurify.sanitize($('#edit_asset_tag').val()).trim(),
                asset_name: DOMPurify.sanitize($('#edit_asset_name').val()).trim(),
                category: DOMPurify.sanitize($('#edit_category').val()).trim(),
                serial_number: DOMPurify.sanitize($('#edit_serial_number').val()).trim(),
                purchase_date: DOMPurify.sanitize($('#edit_purchase_date').val()).trim(),
                warranty_expiry: DOMPurify.sanitize($('#edit_warranty_expiry').val()).trim(),
                status: DOMPurify.sanitize($('#edit_asset_status').val()).trim(),
                notes: DOMPurify.sanitize($('#edit_notes').val()).trim()
            };

            $.ajax({
                url: '../data_processors/edit_asset.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Asset updated successfully.');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        show_error_toast(response.error || 'Could not update the asset.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('.assign_asset_btn').on('click', function () {
            $('#assign_asset_id').val($(this).data('id'));
            $('#assign_asset_name').text($(this).data('name'));
        });

        $('#assign_asset_form').on('submit', function (event) {
            event.preventDefault();

            const data = {
                asset_id: $('#assign_asset_id').val(),
                employee_id: DOMPurify.sanitize($('#assign_employee_id').val()).trim(),
                assigned_date: DOMPurify.sanitize($('#assign_assigned_date').val()).trim()
            };

            $.ajax({
                url: '../data_processors/assign_asset.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        show_success_toast('Asset assigned successfully.');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        show_error_toast(response.error || 'Could not assign the asset.');
                    }
                },
                error: function () {
                    show_error_toast('Something went wrong. Please try again.');
                }
            });
        });

        $('.return_asset_btn').on('click', function () {
            const asset_id = $(this).data('id');

            Swal.fire({
                title: 'Mark this asset as returned?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, returned',
                confirmButtonColor: '#198754'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../data_processors/return_asset.php',
                        type: 'POST',
                        data: { asset_id: asset_id },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                show_success_toast('Asset marked as returned.');
                                setTimeout(() => window.location.reload(), 1200);
                            } else {
                                show_error_toast(response.error || 'Could not return the asset.');
                            }
                        },
                        error: function () {
                            show_error_toast('Something went wrong. Please try again.');
                        }
                    });
                }
            });
        });

        $('.history_asset_btn').on('click', function () {
            $('#history_asset_name').text($(this).data('name'));
            const history = $(this).data('history') || [];

            if (history.length === 0) {
                $('#history_asset_body').html('<p class="text-white-50 mb-0">No assignment history yet.</p>');
                return;
            }

            let rows = '<table class="table table-dark table-sm"><thead><tr><th>Employee</th><th>Assigned</th><th>Returned</th></tr></thead><tbody>';
            history.forEach(function (entry) {
                rows += '<tr><td>' + escape_html(entry.employee) + '</td><td>' + escape_html(entry.assigned_date) + '</td><td>' + escape_html(entry.returned_date || 'Still assigned') + '</td></tr>';
            });
            rows += '</tbody></table>';
            $('#history_asset_body').html(rows);
        });

        $('.delete_asset_btn').on('click', function () {
            const asset_id = $(this).data('id');

            Swal.fire({
                title: 'Delete this asset?',
                text: 'This will also remove its assignment history. This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../data_processors/delete_asset.php',
                        type: 'POST',
                        data: { id: asset_id },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                show_success_toast('Asset deleted.');
                                setTimeout(() => window.location.reload(), 1200);
                            } else {
                                show_error_toast(response.error || 'Could not delete the asset.');
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
    <?php } else { ?>
    <script defer>
        let assets_table = new DataTable('#assets_table');
    </script>
    <?php } ?>
</body>
</html>
