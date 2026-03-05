<?php
// register.php - Secure User Registration
require_once 'db_conn.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form inputs
    $matric_no = trim($_POST['matric_no']);
    $full_name = trim($_POST['full_name']);
    $faculty = trim($_POST['faculty']);
    $raw_password = $_POST['password'];

    // 1. Hash the password securely
    $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

    // 2. Use Prepared Statements to prevent SQL Injection
    $stmt = $conn->prepare("INSERT INTO students (matric_no, full_name, faculty, password) VALUES (?, ?, ?, ?)");
    
    $stmt->bind_param("ssss", $matric_no, $full_name, $faculty, $hashed_password);

    if ($stmt->execute()) {
        echo "<script>alert('Registration Successful! You can now log in.'); window.location.href='index.php';</script>";
        exit();
    } else {
        $error = 'Error: Could not register user.';
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PAU Smart Library</title>
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
            <h4 class="welcome-text">CREATE ACCOUNT</h4>
            <p class="text-muted mb-4">Join PAU Smart Library</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger text-center p-2 animate__animated animate__shakeX">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-bold text-muted small">FULL NAME</label>
                <input type="text" name="full_name" class="form-control" placeholder="e.g. John Doe" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold text-muted small">MATRIC NO</label>
                <input type="text" name="matric_no" class="form-control" placeholder="e.g. JEREMIAH-01" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold text-muted small">FACULTY</label>
                <input type="text" name="faculty" class="form-control" placeholder="e.g. Science">
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold text-muted small">PASSWORD</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mt-2">REGISTER</button>
            
            <div class="text-center mt-4 small">
                <a href="index.php" class="text-primary fw-bold text-decoration-none">Already have an account? Log in</a>
            </div>
        </form>
    </div>
</body>
</html>