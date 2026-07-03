// Shared helpers used across multiple pages.

function escape_html(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}

function view_password(input_id, icon_id) {
    const input = document.getElementById(input_id);
    const icon = document.getElementById(icon_id);

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function show_success_toast(message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: message,
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true
    });
}

function show_error_toast(message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'error',
        title: message,
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true
    });
}

function toggle_theme() {
    const current = document.documentElement.getAttribute('data-bs-theme');
    const next = current === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-bs-theme', next);
    localStorage.setItem('workdesk_theme', next);

    const icon = document.getElementById('theme_toggle_icon');
    if (icon) {
        icon.classList.remove('fa-sun', 'fa-moon');
        icon.classList.add(next === 'light' ? 'fa-sun' : 'fa-moon');
    }
}

// Registered here (rather than deferred) so it fires after each page's own
// deferred script has already built its DataTable, catching every
// data-tooltip element including ones inside DataTable rows. A dedicated
// attribute is used instead of data-bs-toggle="tooltip" so it doesn't
// collide with buttons that also trigger a modal or dropdown.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-tooltip]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});
