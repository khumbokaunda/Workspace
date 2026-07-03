<?php
session_start();

if (isset($_POST['logout'])) {
    session_destroy();
    header("location: index.php");
    exit;
}

if (isset($_SESSION['logged_in'])) {
    header("location: dashboard");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkDesk | Login</title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="src/css/style.css" rel="stylesheet">
    <link href="src/css/parsely.css" rel="stylesheet">
</head>
<body class="comfortaa-regular bg-111 text-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">

    <div class="card bg-222 border-0 shadow-lg p-4" style="width: 100%; max-width: 400px;">
        <div class="text-center mb-4">
            <h1 class="comfortaa-bold brand-title mb-1">WorkDesk</h1>
            <p class="text-white-50 small mb-0">Workplace Management System</p>
        </div>

        <form id="login_form" data-parsley-validate novalidate>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                       required data-parsley-required-message="Please enter your username.">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" id="password" name="password" class="form-control bg-333 text-light border-0 focus-ring rounded-2 px-2 py-3"
                           required data-parsley-required-message="Please enter your password.">
                    <button class="btn btn-333 bg-333 border-0 text-light" type="button" onclick="view_password('password', 'password_icon')">
                        <i class="fa-solid fa-eye" id="password_icon"></i>
                    </button>
                </div>
            </div>
            <div id="login_error" class="text-danger small mb-3 d-none"></div>
            <button type="submit" class="btn btn-success w-100 py-2 comfortaa-bold">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Login
            </button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="src/parsely.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="src/script.js"></script>
    <script defer>
        $('#login_form').on('submit', function (event) {
            event.preventDefault();

            const username = DOMPurify.sanitize($('#username').val()).trim();
            const password = DOMPurify.sanitize($('#password').val()).trim();

            $('#login_error').addClass('d-none').text('');

            $.ajax({
                url: 'data_processors/verify_user.php',
                type: 'POST',
                data: { username: username, password: password },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        window.location.href = './dashboard';
                    } else {
                        $('#login_error').removeClass('d-none').text(response.error);
                    }
                },
                error: function () {
                    $('#login_error').removeClass('d-none').text('Something went wrong. Please try again.');
                }
            });
        });
    </script>
</body>
</html>
