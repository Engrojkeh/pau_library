<?php
// edit_book.php
session_start();
include 'db_conn.php';
require_once 'libs/phpqrcode/qrlib.php'; // Needed in case ISBN changes

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php"); exit();
}

// Fetch Admin Profile for Navbar
$stmt = $conn->prepare("SELECT full_name, profile_pic FROM admins WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$profile_pic = !empty($admin['profile_pic']) ? $admin['profile_pic'] : "https://ui-avatars.com/api/?name=".urlencode($admin['full_name'] ?? 'Admin')."&background=003366&color=fff&rounded=true";
$stmt->close();

if (!isset($_GET['id'])) {
    header("Location: book_catalog.php"); exit();
}

$id = $_GET['id'];
$msg = "";

// 1. FETCH CURRENT DATA
$stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();

// 2. HANDLE UPDATE
if (isset($_POST['update_book'])) {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $isbn = $_POST['isbn'];
    $faculty = $_POST['faculty'];
    $dept = $_POST['department'];
    
    // Check if ISBN changed (Crucial for QR Code)
    if ($isbn !== $book['isbn']) {
        // ISBN changed! We must regenerate the QR Code
        // 1. Delete old image
        if (file_exists($book['qr_code_path'])) {
            unlink($book['qr_code_path']); 
        }
        
        // 2. Generate new
        $qr_data = "BOOK-" . $isbn;
        $file_name = $isbn . ".png";
        $file_path = "uploads/" . $file_name;
        QRcode::png($qr_data, $file_path, QR_ECLEVEL_L, 5);
        
        // 3. Update with new QR path
        $sql = "UPDATE books SET title=?, author=?, isbn=?, qr_code_path=?, faculty=?, department=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $title, $author, $isbn, $file_path, $faculty, $dept, $id);
        
    } else {
        // ISBN stayed same, just update text details
        $sql = "UPDATE books SET title=?, author=?, faculty=?, department=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $title, $author, $faculty, $dept, $id);
    }

    if ($stmt->execute()) {
        $msg = "<div class='alert alert-success'>Book Updated Successfully! <a href='book_catalog.php'>Back to Catalog</a></div>";
        // Refresh data
        $book['title'] = $title; $book['author'] = $author; $book['isbn'] = $isbn;
    } else {
        $msg = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Book - PAU Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            background: url('admin_background.png') no-repeat center center fixed; 
            background-size: cover;
            background-color: #f4f7f6; 
            color: #2c3e50;
            overflow-x: hidden;
            margin: 0;
        }
        .page-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(244, 247, 246, 0.4); backdrop-filter: blur(6px); z-index: -1;
        }
        .btn:active {
            transform: scale(0.95) !important;
            transition: transform 0.1s !important;
        }
        /* Top Navbar */
        .top-navbar {
            background: linear-gradient(135deg, #003366 0%, #001f3f 100%);
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
        .nav-link:hover i, .nav-link.active i { color: #ffc107; }
        .nav-profile { display: flex; align-items: center; gap: 15px; margin-left: 20px; padding-left: 20px; border-left: 1px solid rgba(255,255,255,0.2); }
        .nav-profile img { width: 45px; height: 45px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.2); background: #fff; object-fit: cover; }
        .nav-profile-info { display: flex; flex-direction: column; color: white; justify-content: center; }
        .nav-profile-name { font-weight: 700; font-size: 0.95rem; line-height: 1.2; }
        .nav-profile-role { font-size: 0.75rem; color: #ffc107; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
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
        <a href="book_catalog.php" class="nav-link active"><i class="fas fa-book-open"></i> Catalog</a>
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

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Edit Book Details</h5>
                </div>
                <div class="card-body">
                    <?php echo $msg; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label>Book Title</label>
                            <input type="text" name="title" class="form-control" value="<?php echo $book['title']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Author</label>
                            <input type="text" name="author" class="form-control" value="<?php echo $book['author']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>ISBN (Unique ID)</label>
                            <input type="text" name="isbn" class="form-control" value="<?php echo $book['isbn']; ?>" required>
                            <small class="text-danger">* Changing ISBN will regenerate the QR Code.</small>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label>Faculty</label>
                                <select id="faculty" name="faculty" class="form-select" onchange="updateDepts()" required>
                                    <option value="">Select Faculty...</option>
                                    <option value="SST" <?php echo ($book['faculty'] === 'SST') ? 'selected' : ''; ?>>School of Science & Tech</option>
                                    <option value="SMC" <?php echo ($book['faculty'] === 'SMC') ? 'selected' : ''; ?>>School of Media & Comm</option>
                                    <option value="SIMS" <?php echo ($book['faculty'] === 'SIMS') ? 'selected' : ''; ?>>School of Management</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label>Department</label>
                                <select id="department" name="department" class="form-select" required>
                                    <option value="">Select Department...</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" name="update_book" class="btn btn-warning w-100">Update Book</button>
                        <a href="book_catalog.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Keep the cascading logic
const depts = {
    "SST": ["Computer Science", "Electrical Engineering", "Mechanical Engineering"],
    "SMC": ["Mass Communication", "Information Science"],
    "SIMS": ["Accounting", "Business Administration", "Economics"]
};

function updateDepts() {
    let fac = document.getElementById("faculty").value;
    let deptSelect = document.getElementById("department");
    let initialDept = "<?php echo addslashes($book['department']); ?>";
    
    // Clear old options
    deptSelect.innerHTML = '<option value="">Select Department...</option>';
    
    if (fac && depts[fac]) {
        depts[fac].forEach(function(d) {
            let option = document.createElement("option");
            option.text = d;
            option.value = d;
            // Pre-select if initial
            if (fac === "<?php echo addslashes($book['faculty']); ?>" && d === initialDept) {
                option.selected = true;
            }
            deptSelect.add(option);
        });
    }
}

// Pre-fill department options on page load
window.onload = function() {
    updateDepts();
};
</script>
</body>
</html>