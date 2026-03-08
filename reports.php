<?php
// reports.php
session_start();
include 'db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php"); exit();
}

// DEFAULT: Show "This Month"
$start_date = date('Y-m-01'); // 1st of this month
$end_date = date('Y-m-t');   // Last day of this month

// OVERRIDE if user filters
if (isset($_GET['filter']) && !empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
}

// 1. CALCULATE TOTALS (Securely using Prepared Statements)
function getSingleStat($conn, $query, $start, $end) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res['total'] ?? 0;
}

// Fines Collected
$total_fines = getSingleStat($conn, "SELECT SUM(fine_amount) as total FROM transactions WHERE return_date BETWEEN ? AND ?", $start_date, $end_date);

// Books Borrowed
$total_borrowed = getSingleStat($conn, "SELECT COUNT(*) as total FROM transactions WHERE borrow_date BETWEEN ? AND ?", $start_date, $end_date);

// Books Returned
$total_returned = getSingleStat($conn, "SELECT COUNT(*) as total FROM transactions WHERE return_date BETWEEN ? AND ? AND status='Returned'", $start_date, $end_date);

// Overdue (Currently active and late) doesn't use dates in this query
$overdue_sql = "SELECT COUNT(*) as total FROM transactions WHERE status='Active' AND due_date < CURDATE()";
$total_overdue = $conn->query($overdue_sql)->fetch_assoc()['total'];

// Fetch Admin Profile for Sidebar
$stmt = $conn->prepare("SELECT full_name, profile_pic FROM admins WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$profile_pic = !empty($admin['profile_pic']) ? $admin['profile_pic'] : "https://ui-avatars.com/api/?name=".urlencode($admin['full_name'] ?? 'Admin')."&background=003366&color=fff&rounded=true";
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Reports - Smart Library</title>
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
            border-radius: 20px; border: none; background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 15px 50px rgba(0,0,0,0.08); overflow: hidden; backdrop-filter: blur(10px);
            margin-bottom: 30px; padding: 30px;
        }

        .stat-card {
            border-radius: 15px; border: none; padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: all 0.3s ease; position: relative; overflow: hidden; height: 100%;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.1); }
        .stat-card.border-primary { border-left: 5px solid #005ce6; }
        .stat-card.border-success { border-left: 5px solid #00b09b; }
        .stat-card.border-danger { border-left: 5px solid #ff4b4b; }
        .stat-card.border-warning { border-left: 5px solid #ffc107; }

        .stat-card h3 { font-size: 2.5rem; font-weight: 800; margin: 0; }
        .stat-card h6 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 10px; }
        .stat-icon { position: absolute; right: 20px; bottom: 20px; font-size: 3rem; opacity: 0.1; }

        .table-card .card-header { background: transparent; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 25px 30px; }
        .table-card h5 { font-weight: 700; color: var(--primary-blue); margin: 0; font-size: 1.2rem; display: flex; align-items: center; }
        .table-card h5 i { color: var(--accent-gold); margin-right: 12px; font-size: 1.3rem; }
        .table { margin-bottom: 0; }
        .table thead th { border-bottom: 2px solid rgba(0,0,0,0.04); color: #6c757d; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; padding: 18px 25px; background: rgba(248, 249, 250, 0.5); }
        .table tbody td { vertical-align: middle; padding: 18px 25px; color: #495057; border-bottom: 1px solid rgba(0,0,0,0.03); font-weight: 500; }
        .table tbody tr:hover td { background-color: rgba(0, 51, 102, 0.02); color: var(--primary-blue); }
        
        .badge-status { padding: 8px 14px; border-radius: 8px; font-weight: 600; font-size: 0.75rem; }
        .status-returned { background-color: rgba(40, 167, 69, 0.15); color: #28a745; }
        .status-borrowed { background-color: rgba(255, 193, 7, 0.15); color: #d39e00; }

        .form-control { border-radius: 10px; padding: 12px; border: 1px solid #ddd; }
        .form-control:focus { box-shadow: none; border-color: var(--primary-blue); }
        .btn-primary { background: var(--primary-blue); border: none; padding: 12px; font-weight: 600; border-radius: 10px; transition: all 0.3s; }
        .btn-primary:hover { background: var(--secondary-blue); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .top-navbar { padding: 15px 20px; flex-direction: column; gap: 15px; text-align: center; }
            .nav-menu { flex-wrap: wrap; justify-content: center; }
            .nav-profile { margin-left: 0; padding-left: 0; border-left: none; width: 100%; justify-content: center; margin-top: 10px; }
            .main-content { padding: 20px 15px; }
            .content-card, .table-container, .stats-card { padding: 20px !important; }
            h3.page-title { font-size: 1.5rem; }
            .filter-section { flex-direction: column; align-items: stretch !important; gap: 15px; }
            .stats-card h2 { font-size: 1.8rem; }
            .stats-card i { font-size: 1.8rem; }
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
        <a href="dashboard_admin.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="reports.php" class="nav-link active"><i class="fas fa-chart-pie"></i> Reports</a> 
        <a href="book_catalog.php" class="nav-link"><i class="fas fa-book-open"></i> Catalog</a>
        <a href="add_book.php" class="nav-link"><i class="fas fa-plus-circle"></i> Add Book</a>
        <a href="scan_book.php" class="nav-link"><i class="fas fa-qrcode"></i> Scan QR</a>
        
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
    <h3 class="page-title">Library Performance Report</h3>

    <div class="content-card mb-4" style="padding: 25px;">
        <form method="GET" class="row align-items-end g-3">
            <div class="col-md-4">
                <label class="form-label fw-bold text-muted small">START DATE</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-muted small">END DATE</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" required>
            </div>
            <div class="col-md-4">
                <button type="submit" name="filter" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i> Generate Custom Report</button>
            </div>
        </form>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="stat-card border-primary bg-white">
                <h6 class="text-muted">Books Borrowed</h6>
                <h3 class="text-primary"><?php echo number_format($total_borrowed); ?></h3>
                <i class="fas fa-book-reader stat-icon"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card border-success bg-white">
                <h6 class="text-muted">Books Returned</h6>
                <h3 class="text-success"><?php echo number_format($total_returned); ?></h3>
                <i class="fas fa-undo stat-icon"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card border-danger bg-white">
                <h6 class="text-muted">Currently Overdue</h6>
                <h3 class="text-danger"><?php echo number_format($total_overdue); ?></h3>
                <i class="fas fa-exclamation-triangle stat-icon text-danger"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card border-warning bg-white">
                <h6 class="text-muted">Fines Collected</h6>
                <h3 class="text-warning" style="color:#d39e00!important;">₦<?php echo number_format($total_fines, 2); ?></h3>
                <i class="fas fa-money-bill-wave stat-icon"></i>
            </div>
        </div>
    </div>

    <div class="content-card table-card p-0">
        <div class="card-header">
            <h5><i class="fas fa-list-alt text-primary"></i> Detailed Transaction Log</h5>
        </div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Date Event</th>
                        <th>Student Name</th>
                        <th>Book Title</th>
                        <th>Action Tracked</th>
                        <th>Fine Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $log_sql = "SELECT t.*, s.full_name, b.title 
                                FROM transactions t 
                                JOIN students s ON t.student_id = s.id 
                                JOIN books b ON t.book_id = b.id 
                                WHERE (borrow_date BETWEEN ? AND ?) 
                                OR (return_date BETWEEN ? AND ?)
                                ORDER BY t.id DESC";
                    $stmt = $conn->prepare($log_sql);
                    $stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
                    $stmt->execute();
                    $logs = $stmt->get_result();

                    if ($logs->num_rows > 0) {
                        while($row = $logs->fetch_assoc()) {
                            $is_return = ($row['status'] == 'Returned');
                            $action = $is_return ? 'Returned' : 'Borrowed';
                            $date = $is_return ? $row['return_date'] : $row['borrow_date'];
                            
                            $badge = $is_return ? 'status-returned' : 'status-borrowed';
                            $fine = ($row['fine_amount'] > 0) ? "<span class='text-danger fw-bold'>₦{$row['fine_amount']}</span>" : "₦0";
                            
                            echo "<tr>
                                    <td class=\"ps-4\"><span class=\"text-muted small\"><i class=\"fas fa-calendar me-1 opacity-50\"></i> ".htmlspecialchars($date)."</span></td>
                                    <td class=\"fw-bold text-dark\">".htmlspecialchars($row['full_name'])."</td>
                                    <td><i class=\"fas fa-book me-1 opacity-50 text-secondary\"></i> ".htmlspecialchars($row['title'])."</td>
                                    <td><span class=\"badge-status {$badge}\">$action</span></td>
                                    <td>$fine</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-5 text-muted'><i class='fas fa-inbox fs-3 mb-3 d-block opacity-25'></i>No records found for this period.</td></tr>";
                    }
                    $stmt->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>