<?php
// db_conn.php
require_once __DIR__ . '/security.php';

$servername = getenv('DB_HOST'); // Standard connection without persistent pooling
$username = getenv('DB_USER');
$password = getenv('DB_PASS');
$dbname = getenv('DB_NAME');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Database Connection failed: " . $conn->connect_error);
    die("<div style='font-family:sans-serif; text-align:center; background:#f4f7f6; padding:50px; color:#333;'><h3>Database Error</h3><p>Experiencing high traffic. Please try again later.</p></div>");
}
?>