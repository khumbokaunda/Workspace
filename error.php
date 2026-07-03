<?php
session_start();
$message = isset($_GET['message']) ? $_GET['message'] : 'Something went wrong. Please try again.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkDesk | Error</title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="src/css/style.css" rel="stylesheet">
</head>
<body class="comfortaa-regular bg-111 text-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="text-center">
        <i class="fa-solid fa-triangle-exclamation text-warning mb-3" style="font-size: 3rem;"></i>
        <h1 class="comfortaa-bold mb-2">Something went wrong</h1>
        <p class="text-white-50 mb-4"><?php echo htmlspecialchars($message); ?></p>
        <a href="./" class="btn btn-success comfortaa-bold px-4">
            <i class="fa-solid fa-house me-2"></i>Back to login
        </a>
    </div>
</body>
</html>
