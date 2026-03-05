<?php
// scan_book.php
session_start();
include 'db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Sidebar Admin Details
$stmt = $conn->prepare("SELECT full_name, profile_pic FROM admins WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$profile_pic = !empty($admin['profile_pic']) ? $admin['profile_pic'] : "https://ui-avatars.com/api/?name=".urlencode($admin['full_name'] ?? 'Admin')."&background=003366&color=fff&rounded=true";
$stmt->close();

$msg = "";
$msg_type = "";

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'borrow_success') {
        $msg = "Book Borrowed Successfully!";
        $msg_type = "success";
    } elseif ($_GET['msg'] === 'return_success') {
        $msg = "Book Returned Successfully!";
        if (isset($_GET['fine'])) {
            $msg .= " Fine Due: ₦" . htmlspecialchars($_GET['fine']);
        }
        $msg_type = "success";
    } elseif ($_GET['msg'] === 'error') {
        $msg = "Error: " . htmlspecialchars($_GET['details'] ?? 'Unknown Error');
        $msg_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Book - Smart Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="js/html5-qrcode.min.js"></script>
    
    <style>
        :root {
            --primary-blue: #003366;
            --secondary-blue: #001f3f;
            --accent-gold: #ffc107;
            --bg-light: #f4f7f6;
            --text-dark: #2c3e50;
        }
        body { 
            font-family: 'Inter', sans-serif;
            background: url('admin_background.png') no-repeat center center fixed; 
            background-size: cover;
            background-color: var(--bg-light); 
            color: var(--text-dark);
            overflow-x: hidden;
            margin: 0;
        }
        .page-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(244, 247, 246, 0.4); backdrop-filter: blur(6px); z-index: -1;
        }
        
        /* Button Click Animation */
        .btn:active {
            transform: scale(0.95) !important;
            transition: transform 0.1s !important;
        }
        /* Top Navbar */
        .top-navbar {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            box-shadow: 0 4px 25px rgba(0,0,0,0.2);
            padding: 12px 50px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar-brand { display: flex; align-items: center; color: white !important; font-weight: 800; font-size: 1.5rem; letter-spacing: 1px; text-decoration: none; }
        .nav-menu { display: flex; align-items: center; gap: 8px; }
        .nav-link { color: rgba(255,255,255,0.7) !important; padding: 10px 18px; font-size: 0.95rem; font-weight: 600; transition: all 0.3s ease; text-decoration: none; display: flex; align-items: center; border-radius: 8px; }
        .nav-link i { margin-right: 8px; font-size: 1.1rem; transition: transform 0.3s; }
        .nav-link:hover, .nav-link.active { color: white !important; background: rgba(255,255,255,0.1); transform: translateY(-2px); }
        .nav-link:hover i, .nav-link.active i { color: var(--accent-gold); }
        .nav-profile { display: flex; align-items: center; gap: 15px; margin-left: 20px; padding-left: 20px; border-left: 1px solid rgba(255,255,255,0.2); }
        .nav-profile img { width: 45px; height: 45px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.2); background: #fff; object-fit: cover; }
        .nav-profile-info { display: flex; flex-direction: column; color: white; justify-content: center; }
        .nav-profile-name { font-weight: 700; font-size: 0.95rem; line-height: 1.2; }
        .nav-profile-role { font-size: 0.75rem; color: var(--accent-gold); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }

        .main-content { padding: 40px 50px; min-height: calc(100vh - 80px); animation: fadeIn 0.8s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        h3.page-title { font-weight: 800; color: var(--primary-blue); position: relative; display: inline-block; margin-bottom: 40px; font-size: 2rem; letter-spacing: -0.5px; }
        h3.page-title::after { content: ''; position: absolute; left: 0; bottom: -10px; width: 45px; height: 5px; background: var(--accent-gold); border-radius: 3px; }

        .content-card {
            border-radius: 20px; border: none; background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 15px 50px rgba(0,0,0,0.08); padding: 40px;
            max-width: 700px; margin: 0 auto; text-align: center;
        }

        #reader { 
            width: 100%; 
            margin: auto; 
            border: 3px dashed var(--primary-blue) !important; 
            border-radius: 15px;
            overflow: hidden;
            background: #f8fafc;
            min-height: 350px;
            box-shadow: inset 0 4px 15px rgba(0,0,0,0.03);
        }
        
        .form-control { 
            border-radius: 12px; padding: 14px 18px; border: 1.5px solid #e2e8f0;
            font-size: 1rem; color: #4a5568; transition: all 0.3s; background: #f8fafc;
            text-align: center; font-weight: 600; letter-spacing: 1px;
        }
        .form-control:focus { 
            box-shadow: 0 0 0 4px rgba(0, 51, 102, 0.1); border-color: var(--primary-blue); background: #fff;
        }

        .btn-custom { padding: 14px 25px; font-weight: 600; border-radius: 12px; transition: all 0.3s; border: none; }
        .btn-success-custom { background: #00b09b; color: white; }
        .btn-success-custom:hover { background: #008f7a; transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0,176,155,0.3); }
        .btn-warning-custom { background: var(--accent-gold); color: #000; }
        .btn-warning-custom:hover { background: #e0a800; transform: translateY(-2px); box-shadow: 0 8px 15px rgba(255,193,7,0.3); }

        .scan-result-box {
            background: rgba(0, 51, 102, 0.03);
            border-radius: 15px;
            border: 1px solid rgba(0, 51, 102, 0.1);
            padding: 25px;
            margin-top: 25px;
            display: none;
            animation: fadeIn 0.5s ease-out;
        }

        .alert-custom { border-radius: 12px; border: none; font-weight: 600; padding: 15px; }
    </style>
</head>
<body>

<div class="page-overlay"></div>

<nav class="top-navbar">
    <a href="dashboard_admin.php" class="navbar-brand">
        <i class="fas fa-book-reader text-warning me-2"></i> Smart Library
    </a>
    <div class="nav-menu">
        <a href="dashboard_admin.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="reports.php" class="nav-link"><i class="fas fa-chart-pie"></i> Reports</a> 
        <a href="book_catalog.php" class="nav-link"><i class="fas fa-book-open"></i> Catalog</a>
        <a href="add_book.php" class="nav-link"><i class="fas fa-plus-circle"></i> Add Book</a>
        <a href="scan_book.php" class="nav-link active"><i class="fas fa-qrcode"></i> Scan QR</a>
        
        <div class="nav-profile">
            <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Admin Profile">
            <div class="nav-profile-info">
                <span class="nav-profile-name"><?php echo htmlspecialchars($admin['full_name'] ?? 'Admin'); ?></span>
                <span class="nav-profile-role">Head Librarian</span>
            </div>
            <a href="logout.php" class="btn btn-sm btn-outline-danger ms-3 text-white border-white" style="border-radius:8px;" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</nav>

<div class="main-content">
    <div class="text-center mb-4">
        <h3 class="page-title">Smart QR Scanner</h3>
        <p class="text-muted fw-semibold">Scan library assigned QR tags to log borrows or returns instantly.</p>
    </div>

    <div class="content-card position-relative">
        <?php if($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-custom shadow-sm mb-4">
                <i class="fas fa-<?php echo ($msg_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?> me-2"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div id="debug-message" class="alert alert-info alert-custom mb-4">
            <i class="fas fa-camera text-info me-2"></i> Initializing Camera System...
        </div>

        <div id="reader"></div>

        <div id="result_section" class="scan-result-box">
            <h5 class="text-muted fw-bold mb-3">QR Data: <span id="scanned_text" class="text-primary"></span></h5>
            
            <form action="process_borrow.php" method="POST">
                <input type="hidden" name="qr_data" id="hidden_qr_data">
                
                <div class="mb-4">
                    <label class="form-label text-muted fw-bold small">STUDENT MATRIC NUMBER</label>
                    <input type="text" name="matric_no" class="form-control w-75 mx-auto" placeholder="e.g. JEREMIAH-01 (Required for Borrowing)">
                </div>
                
                <div class="d-flex justify-content-center gap-3">
                    <button type="submit" name="action" value="borrow" class="btn btn-custom btn-success-custom w-50">
                        <i class="fas fa-book-reader me-2"></i> Borrow Book
                    </button>
                    <button type="submit" name="action" value="return" class="btn btn-custom btn-warning-custom w-50">
                        <i class="fas fa-undo me-2"></i> Return Book
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function onScanSuccess(decodedText, decodedResult) {
        document.getElementById('debug-message').className = "alert alert-success alert-custom mb-4";
        document.getElementById('debug-message').innerHTML = "<i class='fas fa-check-circle me-2'></i> Scan Successful! Awaiting student matric reference.";
        
        document.getElementById('result_section').style.display = 'block';
        document.getElementById('scanned_text').innerText = decodedText;
        document.getElementById('hidden_qr_data').value = decodedText;
        
        html5QrcodeScanner.clear();
    }

    function onScanFailure(error) {
        // Ignored to avoid log spam
    }

    if (typeof Html5QrcodeScanner === 'undefined') {
        document.getElementById('debug-message').className = "alert alert-danger alert-custom mb-4";
        document.getElementById('debug-message').innerHTML = "<i class='fas fa-exclamation-triangle me-2'></i> <b>System Error:</b> The HTML5 QR Code library is missing or blocked.";
    } else {
        let html5QrcodeScanner = new Html5QrcodeScanner(
            "reader",
            { fps: 10, qrbox: {width: 250, height: 250} },
            false);
        
        html5QrcodeScanner.render(onScanSuccess, onScanFailure)
        .then(() => {
            document.getElementById('debug-message').innerHTML = "<i class='fas fa-camera me-2'></i> Camera securely online. Align QR code within the frame.";
        })
        .catch(err => {
            document.getElementById('debug-message').className = "alert alert-danger alert-custom mb-4";
            document.getElementById('debug-message').innerHTML = "<i class='fas fa-video-slash me-2'></i> Camera Error: " + err;
        });
    }
</script>
</body>
</html>