<?php
// db_conn.php
require_once __DIR__ . '/security.php';

$servername = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST');
$username = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER');
$password = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS');
$dbname = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Database Connection failed: " . $conn->connect_error);
    die("<div style='font-family:sans-serif; text-align:center; background:#f4f7f6; padding:50px; color:#333;'><h3>Database Error</h3><p>Experiencing high traffic. Please try again later.</p></div>");
}
?>