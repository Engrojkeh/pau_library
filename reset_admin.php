<?php
// reset_admin.php
include 'db_conn.php';

// 1. Delete the old admin to avoid duplicates
$conn->query("DELETE FROM admins WHERE username='admin'");

// 2. Create a fresh password hash for "12345"
$new_password = password_hash("12345", PASSWORD_DEFAULT);

// 3. Insert the new admin user
$stmt = $conn->prepare("INSERT INTO admins (username, password, full_name) VALUES ('admin', ?, 'System Administrator')");
$stmt->bind_param("s", $new_password);

if ($stmt->execute()) {
    echo "<h1>SUCCESS! ✅</h1>";
    echo "<p>Admin account has been reset.</p>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> 12345</p>";
    echo "<br><a href='index.php'>Go to Login Page</a>";
} else {
    echo "<h1>ERROR ❌</h1>";
    echo "Could not reset admin: " . $conn->error;
}
?>