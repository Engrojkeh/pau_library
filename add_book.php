<?php
// add_book.php
session_start();
include 'db_conn.php';
require_once 'libs/phpqrcode/qrlib.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php"); exit();
}

$new_qr_url = "";
$new_book_title = "";
$error_msg = "";

// Fetch Admin Profile for Sidebar
$stmt = $conn->prepare("SELECT full_name, profile_pic FROM admins WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$profile_pic = !empty($admin['profile_pic']) ? $admin['profile_pic'] : "https://ui-avatars.com/api/?name=".urlencode($admin['full_name'] ?? 'Admin')."&background=003366&color=fff&rounded=true";
$stmt->close();

if (isset($_POST['submit'])) {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $isbn = $_POST['isbn'];
    $faculty = $_POST['faculty'];
    $dept = $_POST['department'];

    // Ensure uploads directory exists
    if (!file_exists('uploads')) {
        mkdir('uploads', 0777, true);
    }

    // Check if duplicate ISBN exists
    $check = $conn->prepare("SELECT id FROM books WHERE isbn = ?");
    $check->bind_param("s", $isbn);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error_msg = "A book with ISBN {$isbn} already exists!";
    } else {
        // Generate QR
        $qr_data = "BOOK-" . $isbn; 
        $file_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $isbn) . ".png";
        $file_path = "uploads/" . $file_name;
        QRcode::png($qr_data, $file_path, QR_ECLEVEL_L, 5);

        // Insert
        $stmt = $conn->prepare("INSERT INTO books (title, author, isbn, qr_code_path, faculty, department, status) VALUES (?, ?, ?, ?, ?, ?, 'Available')");
        $stmt->bind_param("ssssss", $title, $author, $isbn, $file_path, $faculty, $dept);

        if ($stmt->execute()) {
            $new_qr_url = $file_path;
            $new_book_title = $title;
        } else {
            $error_msg = "Database Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book - Smart Library</title>
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
            border-radius: 20px; border: none; background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 15px 50px rgba(0,0,0,0.08); padding: 40px;
            max-width: 800px; margin: 0 auto;
        }

        .form-control, .form-select { 
            border-radius: 12px; padding: 14px 18px; border: 1.5px solid #e2e8f0;
            font-size: 1rem; color: #4a5568; transition: all 0.3s; background: #f8fafc;
        }
        .form-control:focus, .form-select:focus { 
            box-shadow: 0 0 0 4px rgba(0, 51, 102, 0.1); border-color: var(--primary-blue); background: #fff;
        }
        .form-label { font-weight: 600; color: #4a5568; margin-bottom: 8px; font-size: 0.9rem; letter-spacing: 0.5px; }
        
        .btn-primary { background: var(--primary-blue); border: none; padding: 14px; font-weight: 600; border-radius: 12px; transition: all 0.3s; letter-spacing: 0.5px; }
        .btn-primary:hover { background: var(--secondary-blue); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }

        /* Print Styles */
        @media print {
            body * { visibility: hidden; }
            #qrModal, #qrModal * { visibility: visible; }
            #qrModal { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 100%; text-align: center; }
            .modal-footer { display: none; }
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
        <a href="reports.php" class="nav-link"><i class="fas fa-chart-pie"></i> Reports</a> 
        <a href="book_catalog.php" class="nav-link"><i class="fas fa-book-open"></i> Catalog</a>
        <a href="add_book.php" class="nav-link active"><i class="fas fa-plus-circle"></i> Add Book</a>
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
    
    <div class="text-center mb-4">
        <h3 class="page-title">Add New Collection Asset</h3>
    </div>

    <div class="content-card position-relative">
        <div class="position-absolute end-0 top-0 p-4 opacity-10"><i class="fas fa-book-medical" style="font-size: 6rem; color: var(--primary-blue);"></i></div>
        
        <?php if($error_msg): ?>
            <div class="alert alert-danger rounded-3 fw-bold mb-4 shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <form method="POST" class="position-relative z-1">
            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label">BOOK TITLE OR NAME</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Introduction to Algorithms" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">AUTHOR NAME</label>
                    <input type="text" name="author" class="form-control" placeholder="e.g. Thomas H. Cormen" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">ISBN / UNIQUE IDENTIFIER</label>
                    <input type="text" name="isbn" class="form-control" placeholder="e.g. 9780262033848" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">ASSIGN FACULTY</label>
                    <select id="faculty" name="faculty" class="form-select" onchange="updateDepts()" required>
                        <option value="">Select Faculty...</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">ASSIGN DEPARTMENT</label>
                    <select id="department" name="department" class="form-select" required>
                        <option value="">Select Department...</option>
                    </select>
                </div>
                
                <div class="col-12 mt-5">
                    <button type="submit" name="submit" class="btn btn-primary w-100 fs-5"><i class="fas fa-qrcode me-2"></i> Register Book & Generate QR Code</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Success Modal -->
<?php if ($new_qr_url): ?>
<div class="modal fade" id="qrModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
            <div class="modal-header bg-success text-white border-0 p-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-check-circle me-2"></i> Book Registered</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-5">
                <h5 class="fw-bold mb-4 text-dark"><?php echo htmlspecialchars($new_book_title); ?></h5>
                <div class="p-3 d-inline-block rounded-3 shadow-sm border" style="background: white;">
                    <img src="<?php echo htmlspecialchars($new_qr_url); ?>" style="width: 220px; height: 220px; object-fit: contain;">
                </div>
                <p class="text-muted mt-4 small fw-semibold">This QR Code has been saved to your server.<br>Print it and stick it to the physical book to enable smart scanning.</p>
            </div>
            <div class="modal-footer border-0 p-3 bg-light d-flex justify-content-between">
                <a href="book_catalog.php" class="btn btn-outline-secondary px-4 fw-bold rounded-3">View Catalog</a>
                <button type="button" class="btn btn-primary px-4 fw-bold rounded-3" onclick="window.print()"><i class="fas fa-print me-2"></i> Print Tag</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Master List
const universityData = {
    "School of Science & Technology": ["Computer Science", "Information Technology", "Mathematics & Statistics"],
    "College of Engineering": ["Civil Engineering", "Mechanical Engineering", "Electrical Engineering"],
    "School of Management & Social Sciences": ["Accounting", "Business Administration", "Economics"]
};

function loadFaculties() {
    let facultySelect = document.getElementById("faculty");
    let firstOption = facultySelect.options[0];
    facultySelect.innerHTML = ""; facultySelect.add(firstOption);
    for (let faculty in universityData) {
        let option = document.createElement("option");
        option.text = faculty; option.value = faculty;
        facultySelect.add(option);
    }
}

function updateDepts() {
    let facultySelect = document.getElementById("faculty");
    let deptSelect = document.getElementById("department");
    let selectedFaculty = facultySelect.value;
    deptSelect.innerHTML = '<option value="">Select Department...</option>';
    if (selectedFaculty && universityData[selectedFaculty]) {
        universityData[selectedFaculty].sort().forEach(function(dept) {
            let option = document.createElement("option");
            option.text = dept; option.value = dept; deptSelect.add(option);
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadFaculties();
    <?php if ($new_qr_url): ?>
        var myModal = new bootstrap.Modal(document.getElementById('qrModal'));
        myModal.show();
    <?php endif; ?>
});
</script>
</body>
</html>