<?php
require_once __DIR__ . '/config.php';

$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    echo "<script>alert('Unable to connect to the database. Please try again later.');</script>";
    exit;
}

$conn->set_charset('utf8mb4');
