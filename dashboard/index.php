<?php
require_once '../includes/session_boot.php';
$_SESSION['page_name'] = "dashboard";
$_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
if (!isset($_SESSION['logged_in'])) {
    header("location:../");
    exit;
}
include "../db_connection.php";
require_once "../includes/csrf.php";
require_once "../includes/send_notification.php";

$can_manage = can_manage_org($_SESSION['role']);
$today = date('Y-m-d');
$employee_id = $_SESSION['employee_id'];

// Certification statuses can drift out of sync with expiry_date, so resync
// before computing any stats or lists that depend on them.
$conn->query("UPDATE certifications SET status = 'Expired' WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE() AND status != 'Expired'");

if ($can_manage) {
    $total_employees = $conn->query("SELECT COUNT(*) AS total FROM employees WHERE status != 'Terminated'")->fetch_assoc()['total'];
    $present_today = $conn->query("SELECT COUNT(*) AS total FROM attendance WHERE work_date = CURDATE() AND status IN ('Present', 'Late', 'Remote')")->fetch_assoc()['total'];
    $pending_leave = $conn->query("SELECT COUNT(*) AS total FROM leave_requests WHERE status = 'Pending'")->fetch_assoc()['total'];
    $open_tasks = $conn->query("SELECT COUNT(*) AS total FROM tasks WHERE status != 'Done'")->fetch_assoc()['total'];
    $assets_assigned = $conn->query("SELECT COUNT(*) AS total FROM assets WHERE status = 'Assigned'")->fetch_assoc()['total'];
    $expiring_certs_count = $conn->query("SELECT COUNT(*) AS total FROM certifications WHERE expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND status != 'Expired'")->fetch_assoc()['total'];

    $expiring_certs_sql = "SELECT c.cert_name, c.cert_code, c.expiry_date, e.first_name, e.last_name
                            FROM certifications c
                            JOIN employees e ON e.id = c.employee_id
                            WHERE c.expiry_date IS NOT NULL AND c.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND c.status != 'Expired'
                            ORDER BY c.expiry_date ASC";
    $expiring_certs_result = $conn->query($expiring_certs_sql);
} else {
    $days_present_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM attendance WHERE employee_id = ? AND status IN ('Present', 'Late', 'Remote') AND work_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $days_present_stmt->bind_param('i', $employee_id);
    $days_present_stmt->execute();
    $days_present = $days_present_stmt->get_result()->fetch_assoc()['total'];

    $pending_leave_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM leave_requests WHERE employee_id = ? AND status = 'Pending'");
    $pending_leave_stmt->bind_param('i', $employee_id);
    $pending_leave_stmt->execute();
    $pending_leave = $pending_leave_stmt->get_result()->fetch_assoc()['total'];

    $open_tasks_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM tasks WHERE assigned_to = ? AND status != 'Done'");
    $open_tasks_stmt->bind_param('i', $employee_id);
    $open_tasks_stmt->execute();
    $open_tasks = $open_tasks_stmt->get_result()->fetch_assoc()['total'];

    $my_assets_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM asset_assignments WHERE employee_id = ? AND returned_date IS NULL");
    $my_assets_stmt->bind_param('i', $employee_id);
    $my_assets_stmt->execute();
    $assets_assigned = $my_assets_stmt->get_result()->fetch_assoc()['total'];

    $expiring_certs_count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM certifications WHERE employee_id = ? AND expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND status != 'Expired'");
    $expiring_certs_count_stmt->bind_param('i', $employee_id);
    $expiring_certs_count_stmt->execute();
    $expiring_certs_count = $expiring_certs_count_stmt->get_result()->fetch_assoc()['total'];

    $expiring_certs_stmt = $conn->prepare("SELECT cert_name, cert_code, expiry_date FROM certifications WHERE employee_id = ? AND expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND status != 'Expired' ORDER BY expiry_date ASC");
    $expiring_certs_stmt->bind_param('i', $employee_id);
    $expiring_certs_stmt->execute();
    $expiring_certs_result = $expiring_certs_stmt->get_result();
}

$notifications_sql = "SELECT * FROM notifications WHERE " . notifications_visibility_sql($_SESSION['role'], $employee_id) . " ORDER BY time_stamp DESC LIMIT 10";
$latest_notifications_result = $conn->query($notifications_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
    <title>WorkDesk | Dashboard</title>
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
            <h1 class="comfortaa-bold fs-3 mb-1">Dashboard</h1>
            <p class="text-white-50 mb-4">
                Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>.
                <?php echo $can_manage ? "Here's how the organization is doing." : "Here's where things stand for you."; ?>
            </p>

            <div class="row g-3 mb-4">
                <?php if ($can_manage) { ?>
                <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="stat-card bg-222">
                        <i class="fa-solid fa-users text-success mb-2"></i>
                        <div class="stat-value comfortaa-bold"><?php echo (int) $total_employees; ?></div>
                        <div class="text-white-50 small">Total Employees</div>
                    </div>
                </div>
                <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="stat-card bg-222">
                        <i class="fa-solid fa-clock text-info mb-2"></i>
                        <div class="stat-value comfortaa-bold"><?php echo (int) $present_today; ?></div>
                        <div class="text-white-50 small">Present Today</div>
                    </div>
                </div>
                <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="stat-card bg-222">
                        <i class="fa-solid fa-plane-departure text-warning mb-2"></i>
                        <div class="stat-value comfortaa-bold"><?php echo (int) $pending_leave; ?></div>
                        <div class="text-white-50 small">Pending Leave Requests</div>
                    </div>
                </div>
                <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="stat-card bg-222">
                        <i class="fa-solid fa-list-check text-info mb-2"></i>
                        <div class="stat-value comfortaa-bold"><?php echo (int) $open_tasks; ?></div>
                        <div class="text-white-50 small">Open Tasks</div>
                    </div>
                </div>
                <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="stat-card bg-222">
                        <i class="fa-solid fa-laptop text-success mb-2"></i>
                        <div class="stat-value comfortaa-bold"><?php echo (int) $assets_assigned; ?></div>
                        <div class="text-white-50 small">Assets Assigned</div>
                    </div>
                </div>
                <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="stat-card bg-222">
                        <i class="fa-solid fa-certificate text-danger mb-2"></i>
                        <div class="stat-value comfortaa-bold"><?php echo (int) $expiring_certs_count; ?></div>
                        <div class="text-white-50 small">Certifications Expiring Soon</div>
                    </div>
                </div>
                <?php } else { ?>
                <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                    <div class="stat-card bg-222">
                        <i class="fa-solid fa-clock text-info mb-2"></i>
                        <div class="stat-value comfortaa-bold"><?php echo (int) $days_present; ?></div>
                        <div class="text-white-50 small">Days Present This Month</div>
                    </div>
                </div>
                <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                    <div class="stat-card bg-222">
                        <i class="fa-solid fa-plane-departure text-warning mb-2"></i>
                        <div class="stat-value comfortaa-bold"><?php echo (int) $pending_leave; ?></div>
                        <div class="text-white-50 small">My Pending Leave Requests</div>
                    </div>
                </div>
                <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                    <div class="stat-card bg-222">
                        <i class="fa-solid fa-list-check text-info mb-2"></i>
                        <div class="stat-value comfortaa-bold"><?php echo (int) $open_tasks; ?></div>
                        <div class="text-white-50 small">My Open Tasks</div>
                    </div>
                </div>
                <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                    <div class="stat-card bg-222">
                        <i class="fa-solid fa-laptop text-success mb-2"></i>
                        <div class="stat-value comfortaa-bold"><?php echo (int) $assets_assigned; ?></div>
                        <div class="text-white-50 small">My Assigned Assets</div>
                    </div>
                </div>
                <?php } ?>
            </div>

            <div class="row g-3">
                <div class="col-xxl-6 col-xl-6 col-lg-12 col-md-12 col-sm-12 col-12">
                    <div class="bg-222 rounded-3 p-3 p-md-4 h-100">
                        <h2 class="fs-5 comfortaa-bold mb-3">Certifications Expiring Within 90 Days</h2>
                        <div class="table-responsive">
                            <table id="expiring_certs_table" class="table table-hover align-middle w-100">
                                <thead>
                                    <tr>
                                        <?php if ($can_manage) { ?><th>Employee</th><?php } ?>
                                        <th>Certification</th>
                                        <th>Code</th>
                                        <th>Expiry Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($cert = $expiring_certs_result->fetch_assoc()) { ?>
                                    <tr>
                                        <?php if ($can_manage) { ?>
                                        <td><?php echo htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']); ?></td>
                                        <?php } ?>
                                        <td><?php echo htmlspecialchars($cert['cert_name']); ?></td>
                                        <td><?php echo htmlspecialchars($cert['cert_code'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($cert['expiry_date']); ?></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-6 col-xl-6 col-lg-12 col-md-12 col-sm-12 col-12">
                    <div class="bg-222 rounded-3 p-3 p-md-4 h-100">
                        <h2 class="fs-5 comfortaa-bold mb-3">Latest Notifications</h2>
                        <ul class="list-unstyled mb-0">
                            <?php while ($notification = $latest_notifications_result->fetch_assoc()) { ?>
                            <li class="border-bottom border-secondary py-2">
                                <div><?php echo htmlspecialchars($notification['notification']); ?></div>
                                <div class="text-white-50 small">
                                    <span class="badge bg-333"><?php echo htmlspecialchars($notification['association']); ?></span>
                                    <?php echo htmlspecialchars($notification['time_stamp']); ?>
                                </div>
                            </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../src/script.js"></script>
    <script defer>
        let expiring_certs_table = new DataTable('#expiring_certs_table', { order: [] });
    </script>
</body>
</html>
