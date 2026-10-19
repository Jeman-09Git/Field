<?php
// Database connection - change user/password to match your XAMPP/WAMP setup
$conn = new mysqli('localhost', 'root', '', 'hotel_db');
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }