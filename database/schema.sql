-- WorkDesk WMS database schema
-- Charset utf8mb4, InnoDB engine, foreign keys enforced throughout.
--
-- To create the database and load this schema:
--   mysql -u root -p -e "CREATE DATABASE wms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   mysql -u root -p wms < database/schema.sql
--
-- The seed admin user below has username `admin` and password `ChangeMe123!`
-- (bcrypt hashed). This password must be changed on first login.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------------------------
-- employees
-- --------------------------------------------------------------------------
CREATE TABLE employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) DEFAULT NULL,
    position VARCHAR(100) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    manager_id INT UNSIGNED DEFAULT NULL,
    specialization VARCHAR(100) DEFAULT NULL,
    hire_date DATE DEFAULT NULL,
    status ENUM('Active', 'On Leave', 'Terminated') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_employees_manager FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- users
-- --------------------------------------------------------------------------
-- Roles map onto the real organizational hierarchy rather than a generic
-- Admin/Staff split. Admin is a pure IT/system role (accounts, employee
-- records, assets, system settings) with no leave or task approval
-- authority. Managing Director and Technical Manager get org-wide
-- visibility on Employee/Attendance/Asset/Certification/Task and are the
-- only roles that assign tasks or approve leave, routed through each
-- employee's manager_id. Engineer/Sales/Administration are regular staff.
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Managing Director', 'Technical Manager', 'Engineer', 'Sales', 'Administration') NOT NULL DEFAULT 'Engineer',
    employee_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- attendance
-- --------------------------------------------------------------------------
CREATE TABLE attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    check_in TIME DEFAULT NULL,
    check_out TIME DEFAULT NULL,
    status ENUM('Present', 'Absent', 'Late', 'Remote') NOT NULL DEFAULT 'Present',
    notes VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uq_attendance_employee_date (employee_id, work_date),
    CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- leave_requests
-- --------------------------------------------------------------------------
CREATE TABLE leave_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    leave_type ENUM('Annual', 'Sick', 'Compassionate', 'Study', 'Unpaid') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT DEFAULT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_leave_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- tasks
-- --------------------------------------------------------------------------
CREATE TABLE tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    assigned_to INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NOT NULL,
    priority ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL DEFAULT 'Medium',
    status ENUM('To Do', 'In Progress', 'Done', 'Blocked') NOT NULL DEFAULT 'To Do',
    due_date DATE DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    CONSTRAINT fk_tasks_assigned_to FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- assets
-- --------------------------------------------------------------------------
CREATE TABLE assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_tag VARCHAR(50) NOT NULL UNIQUE,
    asset_name VARCHAR(150) NOT NULL,
    category ENUM('Laptop', 'Desktop', 'Network Device', 'Server', 'Peripheral', 'Software License', 'Other') NOT NULL,
    serial_number VARCHAR(150) DEFAULT NULL,
    purchase_date DATE DEFAULT NULL,
    warranty_expiry DATE DEFAULT NULL,
    status ENUM('Available', 'Assigned', 'In Repair', 'Retired') NOT NULL DEFAULT 'Available',
    notes VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- asset_assignments
-- --------------------------------------------------------------------------
CREATE TABLE asset_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    assigned_date DATE NOT NULL,
    returned_date DATE DEFAULT NULL,
    assigned_by INT UNSIGNED NOT NULL,
    CONSTRAINT fk_assignments_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- certifications
-- --------------------------------------------------------------------------
CREATE TABLE certifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    cert_name VARCHAR(150) NOT NULL,
    issuing_body VARCHAR(150) DEFAULT NULL,
    cert_code VARCHAR(50) DEFAULT NULL,
    date_earned DATE NOT NULL,
    expiry_date DATE DEFAULT NULL,
    credential_id VARCHAR(150) DEFAULT NULL,
    verification_url VARCHAR(255) DEFAULT NULL,
    status ENUM('Active', 'Expired', 'In Progress') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_certifications_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- cv_records
-- --------------------------------------------------------------------------
CREATE TABLE cv_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    version_label VARCHAR(100) DEFAULT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name_original VARCHAR(255) NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_cv_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_cv_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- notifications
-- --------------------------------------------------------------------------
-- recipient_employee_id scopes a notification to one employee (e.g. "your
-- leave was approved"). management_only marks a notification as visible
-- only to Admin/Managing Director/Technical Manager (e.g. "a new asset was
-- added"). A notification with neither set is a general announcement
-- visible to everyone.
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification TEXT NOT NULL,
    association VARCHAR(100) NOT NULL,
    recipient_employee_id INT UNSIGNED DEFAULT NULL,
    management_only TINYINT(1) NOT NULL DEFAULT 0,
    time_stamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_employee FOREIGN KEY (recipient_employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------------------------
-- Seed admin user
-- Username: admin
-- Password: ChangeMe123! (bcrypt hash below)
-- This account must have its password changed immediately after first login.
-- --------------------------------------------------------------------------
INSERT INTO users (username, password, role, employee_id)
VALUES ('admin', '$2y$12$gWGOzWTjQce5YpzaBOdRMePzgvUWty.myifJ3wCvJxDUDyAkVgOLi', 'Admin', NULL);
