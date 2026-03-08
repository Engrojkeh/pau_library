<?php
// dashboard_admin.php - Secure Admin Dashboard
session_start();

// 1. Secure Session Management
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 2. Database Connection
require_once 'db_conn.php';

// 3. XSS Protection Function
function sanitize($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

$admin_id = $_SESSION['user_id'];
$today = date("Y-m-d");

// --- FETCH ADMIN DETAILS ---
$stmt = $conn->prepare("SELECT full_name, profile_pic FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_result = $stmt->get_result();
$admin = $admin_result->fetch_assoc();
$profile_pic = !empty($admin['profile_pic']) ? $admin['profile_pic'] : "https://ui-avatars.com/api/?name=".urlencode($admin['full_name'] ?? 'Admin')."&background=003366&color=fff&rounded=true";
$stmt->close();

// --- FETCH DASHBOARD STATISTICS ---
$book_query = $conn->query("SELECT COUNT(*) AS total FROM books");
$total_books = $book_query->fetch_assoc()['total'];

$borrow_query = $conn->query("SELECT COUNT(*) AS borrowed FROM transactions WHERE status = 'Active'");
$borrowed = $borrow_query->fetch_assoc()['borrowed'];

$available = $total_books - $borrowed;

// --- FETCH TODAY'S DAILY REPORT ---
$report_sql = "SELECT t.borrow_date, t.return_date, t.status, s.full_name, s.department, b.title, t.id as created_at 
               FROM transactions t 
               JOIN students s ON t.student_id = s.id 
               JOIN books b ON t.book_id = b.id 
               WHERE t.borrow_date = '$today' OR t.return_date = '$today' 
               ORDER BY t.id DESC LIMIT 10";
$daily_report = $conn->query($report_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smart Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
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
        
        /* Glassmorphism Overlay */
        .page-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(244, 247, 246, 0.4);
            backdrop-filter: blur(6px);
            z-index: -1;
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

        .navbar-brand {
            display: flex; align-items: center; color: white !important; font-weight: 800; font-size: 1.5rem; letter-spacing: 1px; text-decoration: none;
        }
        
        .nav-menu { display: flex; align-items: center; gap: 8px; }
        
        .nav-link {
            color: rgba(255,255,255,0.7) !important;
            padding: 10px 18px;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex; align-items: center; border-radius: 8px;
        }

        .nav-link i { margin-right: 8px; font-size: 1.1rem; transition: transform 0.3s; }
        
        .nav-link:hover, .nav-link.active {
            color: white !important;
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }
        
        .nav-link:hover i, .nav-link.active i { color: var(--accent-gold); }

        .nav-profile { position: relative; display: flex; align-items: center; gap: 15px; margin-left: 20px; padding-left: 20px; border-left: 1px solid rgba(255,255,255,0.2); cursor: pointer; }
        .nav-profile img { width: 45px; height: 45px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.2); background: #fff; object-fit: cover; transition: transform 0.3s ease; }
        .nav-profile-info { display: flex; flex-direction: column; color: white; justify-content: center; }
        .nav-profile-name { font-weight: 700; font-size: 0.95rem; line-height: 1.2; }
        .nav-profile-role { font-size: 0.75rem; color: var(--accent-gold); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }

        /* Animated Dropdown */
        .profile-dropdown {
            position: absolute;
            top: 60px;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            min-width: 200px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1001;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .nav-profile:hover .profile-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--primary-blue);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .dropdown-item i { font-size: 1.1rem; color: var(--accent-gold); }
        .dropdown-item:hover { background: rgba(0, 51, 102, 0.05); color: var(--accent-gold); padding-left: 25px; }
        
        /* Main Content Area */
        .main-content { 
            padding: 40px 50px; 
            min-height: calc(100vh - 80px);
            animation: fadeIn 0.8s ease-out;
            position: relative;
            z-index: 10;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .header-container {
            margin-bottom: 40px;
        }
        
        h3.page-title {
            font-weight: 800;
            color: var(--primary-blue);
            margin: 0;
            position: relative;
            display: inline-block;
            font-size: 2rem;
            letter-spacing: -0.5px;
        }
        
        h3.page-title::after {
            content: '';
            position: absolute;
            left: 0; bottom: -10px;
            width: 45px; height: 5px;
            background: var(--accent-gold);
            border-radius: 3px;
        }

        /* Stat Cards with Modern Design */
        .stat-card {
            border-radius: 20px;
            border: none;
            padding: 30px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.06);
            position: relative;
            overflow: hidden;
            z-index: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 100%);
            z-index: -1;
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 51, 102, 0.15);
        }

        .stat-card.bg-primary-custom { background: linear-gradient(135deg, #005ce6 0%, var(--primary-blue) 100%); color: white; }
        .stat-card.bg-warning-custom { background: linear-gradient(135deg, #ffcf33 0%, #d39e00 100%); color: #2c3e50; }
        .stat-card.bg-success-custom { background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%); color: white; }

        .stat-card h5 {
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .stat-card h2 {
            font-size: 3.5rem;
            font-weight: 800;
            margin: 0;
            line-height: 1;
        }

        .card-icon { 
            position: absolute; 
            right: -15px; 
            bottom: -20px; 
            font-size: 7rem; 
            opacity: 0.15;
            transform: rotate(-10deg);
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .stat-card:hover .card-icon {
            transform: rotate(0) scale(1.15);
            opacity: 0.25;
        }

        /* Modern Table Card */
        .table-card {
            border-radius: 20px;
            border: none;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 15px 50px rgba(0,0,0,0.08);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .table-card .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 25px 30px;
            display: flex;
            align-items: center;
        }

        .table-card h5 {
            font-weight: 700;
            color: var(--primary-blue);
            margin: 0;
            font-size: 1.2rem;
            display: flex; align-items: center;
        }
        
        .table-card h5 i {
            color: var(--accent-gold);
            margin-right: 12px;
            font-size: 1.3rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 2px solid rgba(0,0,0,0.04);
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
            padding: 18px 25px;
            background: rgba(248, 249, 250, 0.5);
        }

        .table tbody td {
            vertical-align: middle;
            padding: 18px 25px;
            color: #495057;
            border-bottom: 1px solid rgba(0,0,0,0.03);
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .table tbody tr {
            transition: background 0.3s ease;
        }

        .table tbody tr:hover td {
            background-color: rgba(0, 51, 102, 0.02);
            color: var(--primary-blue);
        }

        .badge {
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
            font-size: 0.75rem;
        }
        
        .badge-returned { background-color: rgba(0, 176, 155, 0.15); color: #008f7a; }
        .badge-borrowed { background-color: rgba(255, 193, 7, 0.15); color: #d39e00; }

        .table-card .card-footer {
            background: rgba(248, 249, 250, 0.5);
            padding: 20px 30px;
            border-top: 1px solid rgba(0,0,0,0.03);
        }

        .btn-custom-outline {
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
            font-weight: 600;
            padding: 10px 24px;
            border-radius: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-custom-outline:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 51, 102, 0.2);
        }
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .top-navbar { padding: 15px 20px; flex-direction: column; gap: 15px; text-align: center; }
            .nav-menu { flex-wrap: wrap; justify-content: center; }
            .nav-profile { margin-left: 0; padding-left: 0; border-left: none; width: 100%; justify-content: center; margin-top: 10px; }
            .main-content { padding: 20px 15px; }
            .content-card, .table-container, .stats-card { padding: 20px !important; }
            h3.page-title { font-size: 1.5rem; }
            .stats-card i { font-size: 2rem; }
            .stats-card h2 { font-size: 2rem; }
        }
    </style>
</head>
<body>

<div class="page-overlay"></div>

<nav class="top-navbar">
    <a href="dashboard_admin.php" class="navbar-brand">
        <i class="fas fa-book-reader text-warning me-2"></i> Smart Library
    </a>
    <div class="nav-menu">
        <a href="dashboard_admin.php" class="nav-link active"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="reports.php" class="nav-link"><i class="fas fa-chart-pie"></i> Reports</a> 
        <a href="book_catalog.php" class="nav-link"><i class="fas fa-book-open"></i> Catalog</a>
        <a href="add_book.php" class="nav-link"><i class="fas fa-plus-circle"></i> Add Book</a>
        <a href="scan_book.php" class="nav-link"><i class="fas fa-qrcode"></i> Scan QR</a>
        
        <div class="nav-profile">
            <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Admin Profile">
            <div class="nav-profile-info">
                <span class="nav-profile-name"><?php echo sanitize($admin['full_name']); ?></span>
                <span class="nav-profile-role">Head Librarian</span>
            </div>
            <i class="fas fa-chevron-down ms-2 text-white" style="font-size: 0.8rem; opacity: 0.7;"></i>
            
            <div class="profile-dropdown">
                <a href="admin_profile.php" class="dropdown-item">
                    <i class="fas fa-user-shield"></i> Admin Settings
                </a>
                <a href="logout.php" class="dropdown-item text-danger">
                    <i class="fas fa-sign-out-alt"></i> Secure Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="main-content">
    <div class="header-container">
        <h3 class="page-title">Library Overview</h3>
    </div>
    
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card bg-primary-custom">
                <h5>Total Books</h5>
                <h2><?php echo number_format($total_books); ?></h2>
                <i class="fas fa-book-reader card-icon"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card bg-warning-custom">
                <h5>Currently Borrowed</h5>
                <h2><?php echo number_format($borrowed); ?></h2>
                <i class="fas fa-hand-holding-heart card-icon"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card bg-success-custom">
                <h5>Available Now</h5>
                <h2><?php echo number_format($available); ?></h2>
                <i class="fas fa-check-circle card-icon"></i>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="card-header">
            <h5><i class="fas fa-calendar-check text-warning"></i> Today's Activity Log (<?php echo date("F j, Y"); ?>)</h5>
        </div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Action</th>
                        <th>Student Name</th>
                        <th>Department</th>
                        <th>Book Title</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($daily_report && $daily_report->num_rows > 0): ?>
                        <?php while($row = $daily_report->fetch_assoc()): 
                            $is_return = ($row['return_date'] == $today);
                            $badge_class = $is_return ? 'badge-returned' : 'badge-borrowed';
                            $badge_text = $is_return ? 'Returned' : 'Borrowed';
                        ?>
                        <tr>
                            <td class="ps-4"><span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span></td>
                            <td class="fw-bold text-dark"><?php echo sanitize($row['full_name']); ?></td>
                            <td class="text-muted"><small><i class="fas fa-building ms-1 me-1 opacity-50"></i> <?php echo sanitize($row['department']); ?></small></td>
                            <td><i class="fas fa-book ms-1 me-1 opacity-50 text-secondary"></i> <?php echo sanitize($row['title']); ?></td>
                            <td><span class="text-muted fw-bold"><?php echo sanitize($row['status']); ?></span></td> 
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="fas fa-inbox fs-3 mb-3 d-block opacity-25"></i>No activity recorded yet today.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer text-end">
            <a href="reports.php" class="btn-custom-outline"><i class="fas fa-stream me-2"></i> View Full History</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>