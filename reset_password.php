<?php
// reset_password.php
session_start();
include 'db_conn.php';
$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $matric_no = trim($_POST['matric_no']);
    $full_name = trim($_POST['full_name']);
    $new_pass = $_POST['new_password'];

    // 1. Verify User Exists by Matric No and Full Name (since email isn't in registration)
    $stmt = $conn->prepare("SELECT id FROM students WHERE matric_no = ? AND full_name = ?");
    $stmt->bind_param("ss", $matric_no, $full_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // 2. User found! Update Password
        $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);
        
        $update = $conn->prepare("UPDATE students SET password = ? WHERE matric_no = ?");
        $update->bind_param("ss", $hashed_password, $matric_no);
        
        if ($update->execute()) {
            $msg = "<div class='alert alert-success text-center animate__animated animate__fadeInDown'>Password Reset Successful! <a href='index.php' class='alert-link'>Login Now</a></div>";
        } else {
            $msg = "<div class='alert alert-danger text-center animate__animated animate__shakeX'>Error updating password.</div>";
        }
    } else {
        $msg = "<div class='alert alert-danger text-center animate__animated animate__shakeX'>Accont verification failed. Check Matric No and Full Name.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - PAU Smart Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        body {
            background: linear-gradient(-45deg, #003366, #0055a5, #001f3f, #004080);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
            padding: 20px;
            margin: 0;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
            width: 100%;
            max-width: 450px;
            padding: 40px;
            animation: fadeInUp 1s ease-out;
            position: relative;
            z-index: 10;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
            border: 2px solid #eee;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #003366;
            box-shadow: 0 0 10px rgba(0, 51, 102, 0.2);
            transform: scale(1.02);
        }
        .btn-primary {
            background-color: #003366;
            border: none;
            padding: 12px;
            font-weight: bold;
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .btn-primary:hover {
            background-color: #002244;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .btn:active {
            transform: scale(0.95) !important;
            transition: transform 0.1s !important;
        }
        .welcome-text { color: #003366; font-weight: 800; letter-spacing: 1px; }
    </style>
</head>
<body>
    <div style="position: absolute; top: 10%; left: 10%; width: 50px; height: 50px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 6s infinite ease-in-out;"></div>
    <div style="position: absolute; bottom: 20%; right: 10%; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 8s infinite ease-in-out;"></div>
    <style>
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
    </style>

    <div class="login-card">
        <div class="text-center">
            <h4 class="welcome-text mb-2">RESTORE ACCESS</h4>
            <p class="text-muted mb-4 small">Verify your identity to reset password</p>
        </div>
        
        <?php echo $msg; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-bold text-muted small">MATRIC NO / USERNAME</label>
                <input type="text" name="matric_no" class="form-control" placeholder="e.g. JEREMIAH-01" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold text-muted small">REGISTERED FULL NAME</label>
                <input type="text" name="full_name" class="form-control" placeholder="e.g. John Doe" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold text-muted small">NEW PASSWORD</label>
                <input type="password" name="new_password" class="form-control" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mt-2">RESET PASSWORD</button>
            
            <div class="text-center mt-4 small">
                <a href="index.php" class="text-primary fw-bold text-decoration-none">Remembered your password? Log in</a>
            </div>
        </form>
    </div>
</body>
</html>