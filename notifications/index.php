<?php
session_start();
$_SESSION['page_name'] = "notifications";
$_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
if (!isset($_SESSION['logged_in'])) {
    header("location:../");
    exit;
}
include "../db_connection.php";

$notifications_sql = "SELECT * FROM notifications ORDER BY time_stamp DESC";
$notifications_result = $conn->query($notifications_sql);

$associations_sql = "SELECT DISTINCT association FROM notifications ORDER BY association ASC";
$associations_result = $conn->query($associations_sql);
$associations_list = array();
while ($row = $associations_result->fetch_assoc()) {
    $associations_list[] = $row['association'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkDesk | Notifications</title>
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
            <h1 class="comfortaa-bold fs-3 mb-1">Notifications</h1>
            <p class="text-white-50 mb-4">Everything that's happened, newest first, mostly this is a running log of system activity.</p>

            <div class="bg-222 rounded-3 p-3 p-md-4">
                <div class="mb-3" style="max-width: 260px;">
                    <label class="form-label">Filter by Module</label>
                    <select id="association_filter" class="form-select bg-333 text-light border-0 focus-ring rounded-2 px-2 py-2">
                        <option value="">All Modules</option>
                        <?php foreach ($associations_list as $association) { ?>
                        <option value="<?php echo htmlspecialchars($association); ?>"><?php echo htmlspecialchars($association); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="table-responsive">
                    <table id="notifications_table" class="table table-dark table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Notification</th>
                                <th>Module</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($notification = $notifications_result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($notification['notification']); ?></td>
                                <td><span class="badge bg-333"><?php echo htmlspecialchars($notification['association']); ?></span></td>
                                <td><?php echo htmlspecialchars($notification['time_stamp']); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
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
        let notifications_table = new DataTable('#notifications_table', { order: [] });

        $('#association_filter').on('change', function () {
            notifications_table.column(1).search(this.value).draw();
        });
    </script>
</body>
</html>
