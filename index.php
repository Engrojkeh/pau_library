<?php
// index.php - Animated Login
session_start();
include 'db_conn.php';

$error = "";

// SECURITY ADDITION: Brute-Force Rate Limiting
function checkRateLimit($ip) {
    $file = __DIR__ . '/rate_limit.json';
    $limits = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    
    $now = time();
    $window = 15 * 60; // 15 minutes
    $max_attempts = 5;
    
    // Clean up old entries
    foreach ($limits as $key => $data) {
        if ($now - $data['time'] > $window) unset($limits[$key]);
    }
    
    if (isset($limits[$ip]) && $limits[$ip]['attempts'] >= $max_attempts) {
        return false; // Blocked
    }
    return true; // Allowed
}

function recordAttempt($ip) {
    $file = __DIR__ . '/rate_limit.json';
    $limits = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    
    if (isset($limits[$ip])) {
        $limits[$ip]['attempts']++;
        $limits[$ip]['time'] = time();
    } else {
        $limits[$ip] = ['attempts' => 1, 'time' => time()];
    }
    
    file_put_contents($file, json_encode($limits));
}

function clearAttempts($ip) {
    $file = __DIR__ . '/rate_limit.json';
    if (file_exists($file)) {
        $limits = json_decode(file_get_contents($file), true);
        if (isset($limits[$ip])) {
            unset($limits[$ip]);
            file_put_contents($file, json_encode($limits));
        }
    }
}

$user_ip = $_SERVER['REMOTE_ADDR'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!checkRateLimit($user_ip)) {
        $error = "Too many login attempts! Your IP is temporarily blocked for 15 minutes.";
    } else {
        // SECURITY ADDITION: Trim spaces to prevent accidental login failures
        $user = trim($_POST['username']);
        $pass = $_POST['password'];
        $role = $_POST['role'];

        if ($role == 'admin') {
            $stmt = $conn->prepare("SELECT id, password FROM admins WHERE username = ?");
        } else {
            $stmt = $conn->prepare("SELECT id, password, full_name FROM students WHERE matric_no = ?");
        }

        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($pass, $row['password'])) {
                
                clearAttempts($user_ip); // Successful login resets the limit
                
                // SECURITY ADDITION: Prevent Session Hijacking
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['role'] = $role;
                
                if ($role == 'admin') header("Location: dashboard_admin.php");
                else header("Location: dashboard_student.php");
                exit();
            } else {
                recordAttempt($user_ip);
                $error = "Incorrect password.";
            }
        } else {
            recordAttempt($user_ip);
            $error = "Account not found.";
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PAU Smart Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        /* 1. ANIMATED BACKGROUND */
        body {
            background: linear-gradient(-45deg, #003366, #0055a5, #001f3f, #004080);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow: hidden;
            margin: 0;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* 2. GLASS LOGIN CARD */
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            /* Entrance Animation */
            animation: fadeInUp 1s ease-out;
            position: relative;
            z-index: 10;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* 3. LOGO PULSE */
        .school-logo {
            max-width: 100px;
            margin-bottom: 15px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* 4. INPUT FIELDS HOVER */
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

        /* 5. BUTTON HOVER */
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
            transform: translateY(-3px); /* Lifts up slightly */
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .btn:active {
            transform: scale(0.95) !important;
            transition: transform 0.1s !important;
        }

        .welcome-text { color: #003366; font-weight: 800; letter-spacing: 1px; }
        .footer-links a { text-decoration: none; transition: color 0.3s; }
        .footer-links a:hover { text-decoration: underline; }
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
            <img src="logo.png" onerror="this.src='https://ui-avatars.com/api/?name=PAU&background=003366&color=fff&rounded=true'" alt="PAU Logo" class="school-logo">
            <h4 class="welcome-text">SMART LIBRARY</h4>
            <p class="text-muted mb-4">Pinnacle African University</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger text-center p-2 animate__animated animate__shakeX">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-bold text-muted small">MATRIC NO / USERNAME</label>
                <input type="text" name="username" class="form-control" placeholder="e.g. JEREMIAH-01" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold text-muted small">PASSWORD</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold text-muted small">LOGIN AS</label>
                <select name="role" class="form-select" style="padding: 12px; border-radius: 10px;">
                    <option value="student">Student</option>
                    <option value="admin">Librarian (Admin)</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mt-2">SECURE LOGIN</button>
            
            <div class="d-flex justify-content-between mt-4 footer-links small">
                <a href="register.php" class="text-primary fw-bold">Create Account</a>
                <a href="reset_password.php" class="text-danger">Forgot Password?</a>
            </div>
        </form>
    </div>

</body>
</html>