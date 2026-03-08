<?php
// dashboard_student.php
session_start();
require_once 'db_conn.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

// 2. XSS Protection Function
function sanitize($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

$student_id = $_SESSION['user_id'];

// 3. Get Student Details
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$profile_pic = !empty($student['profile_pic']) ? $student['profile_pic'] : "https://ui-avatars.com/api/?name=".urlencode($student['full_name'])."&background=random&color=fff&size=150";
$stmt->close();

// --- LOGIC: ALERTS ---
$alert_msg = "";
$today = date('Y-m-d');
$alert_sql = "SELECT b.title, t.due_date FROM transactions t JOIN books b ON t.book_id = b.id WHERE t.student_id = ? AND t.status = 'Active' AND t.due_date <= DATE_ADD(?, INTERVAL 2 DAY)";
$alert_stmt = $conn->prepare($alert_sql);
$alert_stmt->bind_param("is", $student_id, $today);
$alert_stmt->execute();
$alerts = $alert_stmt->get_result();
if ($alerts->num_rows > 0) {
    while($row = $alerts->fetch_assoc()) {
        $days_left = (strtotime($row['due_date']) - strtotime($today)) / (60 * 60 * 24);
        $safe_title = sanitize($row['title']);
        $safe_due = sanitize($row['due_date']);
        if ($days_left < 0) {
            $alert_msg .= "<div class='alert alert-danger custom-alert shadow-sm'><i class='fas fa-exclamation-triangle me-2'></i><strong>OVERDUE:</strong> '{$safe_title}' is late!</div>";
        } else {
            $alert_msg .= "<div class='alert alert-warning custom-alert shadow-sm'><i class='fas fa-clock me-2'></i><strong>DUE SOON:</strong> '{$safe_title}' is due on {$safe_due}.</div>";
        }
    }
}
$alert_stmt->close();

// --- LOGIC: CATALOG (HIERARCHY vs SEARCH) ---
$search = isset($_GET['search']) ? $_GET['search'] : '';
$books_flat = [];
$library = [];

if ($search) {
    // SEARCH MODE: Flat List
    $sql = "SELECT * FROM books WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ?";
    $stmt = $conn->prepare($sql);
    $term = "%$search%";
    $stmt->bind_param("sss", $term, $term, $term);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $books_flat[] = $row; }
    $stmt->close();
} else {
    // BROWSE MODE: Hierarchy
    $result = $conn->query("SELECT * FROM books ORDER BY faculty, department, title");
    while ($row = $result->fetch_assoc()) {
        $fac = empty($row['faculty']) ? "Uncategorized" : $row['faculty'];
        $dept = empty($row['department']) ? "General" : $row['department'];
        $library[$fac][$dept][] = $row;
    }
}

// Helper function for Cover Image
function getCover($isbn, $title) {
    $real = "https://covers.openlibrary.org/b/isbn/" . sanitize($isbn) . "-S.jpg?default=false";
    $fake = "https://ui-avatars.com/api/?name=".urlencode($title)."&background=random&color=fff&size=128&font-size=0.5";
    return "<img src='$real' onerror=\"this.src='$fake';\" class='book-cover'>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Smart Library</title>
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
            background: url('student_background.png') no-repeat center center fixed; 
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .custom-alert {
            border-radius: 12px;
            border: none;
            padding: 16px 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .search-form {
            position: relative;
            display: flex;
            width: 50%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }

        .search-form input {
            border: none;
            padding: 15px 20px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .search-form input:focus {
            box-shadow: none;
            outline: none;
        }

        .search-form .btn-primary {
            background: var(--primary-blue);
            border: none;
            padding: 0 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .search-form .btn-primary:hover {
            background: var(--secondary-blue);
        }

        /* Modern Table Card for History */
        .content-card {
            border-radius: 20px;
            border: none;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 15px 50px rgba(0,0,0,0.08);
            overflow: hidden;
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        .content-card .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 25px 30px;
            display: flex;
            align-items: center;
        }

        .content-card h5 {
            font-weight: 700;
            color: var(--primary-blue);
            margin: 0;
            font-size: 1.2rem;
            display: flex; align-items: center;
        }
        
        .content-card h5 i {
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

        .badge-status {
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
            font-size: 0.75rem;
        }
        
        .status-available { background-color: rgba(0, 176, 155, 0.15); color: #008f7a; }
        .status-borrowed { background-color: rgba(255, 193, 7, 0.15); color: #d39e00; }
        .status-active { background-color: rgba(255, 193, 7, 0.15); color: #d39e00; }
        .status-returned { background-color: rgba(40, 167, 69, 0.15); color: #28a745; }

        .book-cover { 
            width: 45px; height: 65px; 
            object-fit: cover; 
            border-radius: 6px; 
            margin-right: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            transition: transform 0.3s;
        }
        
        .book-cover:hover { transform: scale(1.1); }

        /* Accordion Enhancements */
        .accordion-item {
            border: none;
            border-radius: 15px !important;
            margin-bottom: 15px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .accordion-button {
            padding: 20px 25px;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary-blue);
            background: transparent;
        }
        
        .accordion-button:not(.collapsed) {
            background: rgba(0, 51, 102, 0.03);
            color: var(--primary-blue);
            box-shadow: none;
        }
        
        .accordion-button:focus {
            box-shadow: none;
            border-color: rgba(0,0,0,0.125);
        }

        .accordion-body {
            padding: 20px 25px;
            background: rgba(248, 249, 250, 0.5);
        }
        
        .dept-card {
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,0.05);
            background: #fff;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .dept-header {
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            transition: background 0.2s;
        }
        
        .dept-header:hover { background: rgba(248, 249, 250, 1); }
        .dept-header h6 { margin: 0; font-weight: 700; color: #495057; }
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .top-navbar { padding: 15px 20px; flex-direction: column; gap: 15px; text-align: center; }
            .nav-menu { flex-wrap: wrap; justify-content: center; }
            .nav-profile { margin-left: 0; padding-left: 0; border-left: none; width: 100%; justify-content: center; margin-top: 10px; }
            .main-content { padding: 20px 15px; }
            .content-card, .table-container, .stats-card { padding: 20px !important; }
            h3.page-title { font-size: 1.5rem; }
            .custom-alert { font-size: 0.9rem; }
        }
    </style>
</head>
<body>

<div class="page-overlay"></div>

<nav class="top-navbar">
    <a href="dashboard_student.php" class="navbar-brand">
        <i class="fas fa-book-reader text-warning me-2"></i> Smart Library
    </a>
    <div class="nav-menu">
        <a href="dashboard_student.php" class="nav-link active"><i class="fas fa-home"></i> Home</a>
        <a href="#catalog" class="nav-link"><i class="fas fa-book-reader"></i> Discover</a>
        <a href="#history" class="nav-link"><i class="fas fa-history"></i> My History</a>
        
        <div class="nav-profile">
            <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Student Profile">
            <div class="nav-profile-info">
                <span class="nav-profile-name"><?php echo sanitize($student['full_name']); ?></span>
                <span class="nav-profile-role"><?php echo sanitize($student['matric_no']); ?></span>
            </div>
            <i class="fas fa-chevron-down ms-2 text-white" style="font-size: 0.8rem; opacity: 0.7;"></i>
            
            <div class="profile-dropdown">
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <a href="logout.php" class="dropdown-item text-danger">
                    <i class="fas fa-sign-out-alt"></i> Secure Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="main-content">
    
    <?php if($alert_msg): ?>
        <div class="mb-4 animate__animated animate__pulse">
            <?php echo $alert_msg; ?>
        </div>
    <?php endif; ?>

    <div class="header-container">
        <h3 class="page-title">Library Collection</h3>
        
        <form class="search-form" method="GET">
            <input class="form-control" type="search" name="search" placeholder="Search by book title, author, or ISBN..." value="<?php echo sanitize($search); ?>">
            <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
            <?php if($search): ?>
                <a href="dashboard_student.php" class="btn btn-light border" style="border-radius: 0; display:flex; align-items:center; border-left: 1px solid #ddd!important;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div id="catalog" class="mb-5">
        <?php if ($search): ?>
            <div class="content-card">
                <div class="card-header bg-light">
                    <h5><i class="fas fa-search text-primary"></i> Search Results</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th class="ps-4">Book Details</th><th>ISBN</th><th>Location Info</th><th>Availability</th></tr></thead>
                        <tbody>
                            <?php if (count($books_flat) > 0): ?>
                                <?php foreach ($books_flat as $book): 
                                    $badge = ($book['status'] == 'Available') ? 'status-available' : 'status-borrowed';
                                ?>
                                <tr>
                                    <td class="d-flex align-items-center ps-4">
                                        <?php echo getCover($book['isbn'], $book['title']); ?>
                                        <div>
                                            <div class="fw-bold text-dark fs-6"><?php echo sanitize($book['title']); ?></div>
                                            <div class="text-muted small"><i class="fas fa-user-pen me-1 opacity-50"></i> <?php echo sanitize($book['author']); ?></div>
                                        </div>
                                    </td>
                                    <td><span class="text-muted"><i class="fas fa-barcode me-1 opacity-50"></i> <?php echo sanitize($book['isbn']); ?></span></td>
                                    <td><small class="badge bg-light text-dark border"><?php echo sanitize($book['faculty']) . ' › ' . sanitize($book['department']); ?></small></td>
                                    <td><span class="badge-status <?php echo $badge; ?>"><?php echo sanitize($book['status']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted"><i class="fas fa-inbox fs-3 mb-3 d-block opacity-25"></i>No books found matching your search.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <div class="accordion" id="facultyAccordion">
                <?php 
                $fac_idx = 0;
                foreach ($library as $facultyName => $departments): 
                    $fac_idx++;
                    $fac_total = 0;
                    foreach($departments as $d_books) $fac_total += count($d_books);
                ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php echo $fac_idx; ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $fac_idx; ?>">
                            <i class="fas fa-university me-3 text-secondary"></i>
                            <span class="me-auto"><?php echo sanitize($facultyName); ?></span>
                            <span class="badge bg-primary text-white ms-3 rounded-pill px-3"><?php echo $fac_total; ?></span>
                        </button>
                    </h2>
                    <div id="collapse<?php echo $fac_idx; ?>" class="accordion-collapse collapse" data-bs-parent="#facultyAccordion">
                        <div class="accordion-body">
                            <?php foreach ($departments as $deptName => $deptBooks): ?>
                                <div class="dept-card">
                                    <div class="dept-header" data-bs-toggle="collapse" href="#dept<?php echo $fac_idx . md5($deptName); ?>">
                                        <h6><i class="fas fa-folder-open me-2 text-primary"></i> <?php echo sanitize($deptName); ?></h6>
                                        <span class="badge bg-secondary text-white rounded-pill"><?php echo count($deptBooks); ?></span>
                                    </div>
                                    <div class="collapse show" id="dept<?php echo $fac_idx . md5($deptName); ?>">
                                        <div class="card-body p-0">
                                            <table class="table mb-0">
                                                <thead><tr><th class="ps-4" style="width:50%">Book Details</th><th>ISBN Number</th><th>Availability</th></tr></thead>
                                                <tbody>
                                                    <?php foreach ($deptBooks as $book): 
                                                        $badge = ($book['status'] == 'Available') ? 'status-available' : 'status-borrowed';
                                                    ?>
                                                    <tr>
                                                        <td class="d-flex align-items-center ps-4 py-3">
                                                            <?php echo getCover($book['isbn'], $book['title']); ?>
                                                            <div>
                                                                <span class="fw-bold d-block text-dark"><?php echo sanitize($book['title']); ?></span>
                                                                <span class="text-muted small"><i class="fas fa-user-pen me-1 opacity-50"></i> <?php echo sanitize($book['author']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="align-middle"><span class="text-muted small"><i class="fas fa-barcode me-1 opacity-50"></i> <?php echo sanitize($book['isbn']); ?></span></td>
                                                        <td class="align-middle"><span class="badge-status <?php echo $badge; ?>"><?php echo sanitize($book['status']); ?></span></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="history" class="content-card">
        <div class="card-header">
            <h5><i class="fas fa-history text-success"></i> My Personal History</h5>
        </div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead><tr><th class="ps-4">Book Title</th><th>Borrowed Date</th><th>Due Date</th><th>Return Status</th><th>Accrued Fine</th></tr></thead>
                <tbody>
                    <?php
                    $hist_sql = "SELECT b.title, t.borrow_date, t.due_date, t.status, t.fine_amount FROM transactions t JOIN books b ON t.book_id = b.id WHERE t.student_id = ? ORDER BY t.id DESC";
                    $hist = $conn->prepare($hist_sql); $hist->bind_param("i", $student_id); $hist->execute(); $res = $hist->get_result();
                    if ($res->num_rows > 0) {
                        while ($r = $res->fetch_assoc()) {
                            $fine = ($r['fine_amount'] > 0) ? "<span class='text-danger fw-bold shadow-sm px-2 py-1 rounded bg-white border border-danger'>₦" . sanitize($r['fine_amount']) . "</span>" : "<span class='text-success fw-bold'>₦0.00</span>";
                            $status_badge = ($r['status'] == 'Active') ? 'status-active' : 'status-returned';
                            echo "<tr>
                                    <td class='ps-4 fw-bold text-dark'>" . sanitize($r['title']) . "</td>
                                    <td><span class='text-muted small'><i class='fas fa-calendar-day me-1 opacity-50'></i> " . sanitize($r['borrow_date']) . "</span></td>
                                    <td><span class='text-muted small'><i class='fas fa-calendar-check me-1 opacity-50'></i> " . sanitize($r['due_date']) . "</span></td>
                                    <td><span class='badge-status {$status_badge}'>" . sanitize($r['status']) . "</span></td>
                                    <td>{$fine}</td>
                                  </tr>";
                        }
                    } else { echo "<tr><td colspan='5' class='text-center py-5 text-muted'><i class='fas fa-inbox fs-3 mb-3 d-block opacity-25'></i>No borrowing history found yet.</td></tr>"; }
                    $hist->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>