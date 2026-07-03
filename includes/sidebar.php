<?php
// Desktop sidebar. Included inside a page's body, after session_start() and
// after $_SESSION['page_name'] has been set by that page's index.php.
$active_page = isset($_SESSION['page_name']) ? $_SESSION['page_name'] : '';
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
?>
<div class="sidebar bg-222 d-none d-lg-flex flex-column p-3 flex-shrink-0">
    <nav class="nav flex-column gap-1">
        <a class="link <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>" href="../dashboard">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
        <a class="link <?php echo $active_page === 'employee_management' ? 'active' : ''; ?>" href="../employee_management">
            <i class="fa-solid fa-users"></i> Employees
        </a>
        <a class="link <?php echo $active_page === 'attendance' ? 'active' : ''; ?>" href="../attendance">
            <i class="fa-solid fa-clock"></i> Attendance
        </a>
        <a class="link <?php echo $active_page === 'leave_management' ? 'active' : ''; ?>" href="../leave_management">
            <i class="fa-solid fa-plane-departure"></i> Leave
        </a>
        <a class="link <?php echo $active_page === 'task_management' ? 'active' : ''; ?>" href="../task_management">
            <i class="fa-solid fa-list-check"></i> Tasks
        </a>
        <a class="link <?php echo $active_page === 'asset_management' ? 'active' : ''; ?>" href="../asset_management">
            <i class="fa-solid fa-laptop"></i> Assets
        </a>
        <a class="link <?php echo $active_page === 'certification_management' ? 'active' : ''; ?>" href="../certification_management">
            <i class="fa-solid fa-certificate"></i> Certifications
        </a>
        <a class="link <?php echo $active_page === 'cv_management' ? 'active' : ''; ?>" href="../cv_management">
            <i class="fa-solid fa-file-lines"></i> CVs
        </a>
        <a class="link <?php echo $active_page === 'notifications' ? 'active' : ''; ?>" href="../notifications">
            <i class="fa-solid fa-bell"></i> Notifications
        </a>
        <?php if ($is_admin) { ?>
        <hr class="text-secondary my-2">
        <a class="link <?php echo $active_page === 'user_management' ? 'active' : ''; ?>" href="../user_management">
            <i class="fa-solid fa-user-shield"></i> User Accounts
        </a>
        <?php } ?>
    </nav>
</div>
