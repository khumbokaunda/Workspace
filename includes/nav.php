<?php
// Top nav, shared across every page. Collapses to icons only on small
// screens and exposes the sidebar links via an offcanvas on mobile.
$active_page = isset($_SESSION['page_name']) ? $_SESSION['page_name'] : '';
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
?>
<header class="navbar navbar-expand-lg bg-222 shadow sticky-top px-3 py-2">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-light d-lg-none border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i class="fa-solid fa-bars"></i>
        </button>
        <a href="../dashboard" class="d-flex align-items-center gap-2">
            <span class="comfortaa-bold fs-4 brand-title">WorkDesk</span>
        </a>
        <span class="text-white-50 small d-none d-md-inline ms-2">Workplace Management System</span>
    </div>

    <div class="ms-auto d-flex align-items-center gap-3">
        <a href="../notifications" class="text-light position-relative" title="Notifications">
            <i class="fa-solid fa-bell fs-5"></i>
        </a>
        <div class="dropdown">
            <button class="btn btn-333 bg-333 text-light border-0 dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                <i class="fa-solid fa-circle-user fs-5"></i>
                <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end bg-222">
                <li><span class="dropdown-item-text text-white-50 small"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="../" method="POST">
                        <button type="submit" name="logout" class="dropdown-item text-danger">
                            <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<div class="offcanvas offcanvas-start bg-222 text-light" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header">
        <span class="comfortaa-bold brand-title">WorkDesk</span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-2">
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
</div>
