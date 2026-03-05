<?php
// fix_admin.php - Fixes the database and resets the password
require_once 'db_conn.php';

// 1. Expand the password column so it doesn't chop off the secure hash
$conn->query("ALTER TABLE admins MODIFY password VARCHAR(255)");

$username = "admin";
$plain_password = "admin123";
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

// 2. Check if the admin actually exists
$check = $conn->query("SELECT * FROM admins WHERE username = '$username'");

if ($check->num_rows > 0) {
    // Update existing admin
    $conn->query("UPDATE admins SET password = '$hashed_password' WHERE username = '$username'");
    echo "<h2 style='color: green;'>Admin Updated!</h2>";
} else {
    // Insert a brand new admin if the table was empty
    $conn->query("INSERT INTO admins (username, password, full_name) VALUES ('$username', '$hashed_password', 'Head Librarian')");
    echo "<h2 style='color: blue;'>Brand New Admin Created!</h2>";
}

echo "<h3>Try logging in now with:</h3>";
echo "<b>Role:</b> Librarian (Admin)<br>";
echo "<b>Username:</b> admin<br>";
echo "<b>Password:</b> admin123<br>";
echo "<br><a href='index.php'>Click here to go to Login</a>";
?>