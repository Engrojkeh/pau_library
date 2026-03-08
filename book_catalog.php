<?php
// book_catalog.php
session_start();
include 'db_conn.php';

// Security: Admin Only
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

// 1. HANDLE DELETE
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // a. Fetch QR Code path to delete the physical image
    $stmt = $conn->prepare("SELECT qr_code_path FROM books WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row && !empty($row['qr_code_path'])) {
        $qr_path = __DIR__ . '/' . ltrim($row['qr_code_path'], '/');
        if (file_exists($qr_path) && !is_dir($qr_path)) unlink($qr_path);
    }
    $stmt->close();

    // b. Delete related transactions first (Foreign Key Constraint fix)
    $stmt_trans = $conn->prepare("DELETE FROM transactions WHERE book_id=?");
    $stmt_trans->bind_param("i", $id);
    $stmt_trans->execute();
    $stmt_trans->close();

    // c. Delete the book
    $stmt_book = $conn->prepare("DELETE FROM books WHERE id=?");
    $stmt_book->bind_param("i", $id);
    $stmt_book->execute();
    $stmt_book->close();

    header("Location: book_catalog.php?msg=deleted");
    exit();
}

// 2. HANDLE SEARCH & DATA FETCHING
$search = isset($_GET['search']) ? $_GET['search'] : '';
$books = [];

if ($search) {
    // SEARCH MODE: Fetch flat list matching query
    $sql = "SELECT * FROM books WHERE title LIKE ? OR isbn LIKE ? OR author LIKE ?";
    $stmt = $conn->prepare($sql);
    $term = "%$search%";
    $stmt->bind_param("sss", $term, $term, $term);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
} else {
    // BROWSE MODE: Fetch ALL books to group them
    $result = $conn->query("SELECT * FROM books ORDER BY faculty, department, title");
    
    // 3. GROUPING LOGIC (The "Brain" of the Hierarchy)
    // Structure: $library['FacultyName']['DeptName'] = [Book1, Book2...]
    $library = [];
    while ($row = $result->fetch_assoc()) {
        $fac = empty($row['faculty']) ? "Uncategorized" : $row['faculty'];
        $dept = empty($row['department']) ? "General" : $row['department'];
        
        $library[$fac][$dept][] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Master Book Catalog</title>
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

        .main-content { padding: 40px 50px; min-height: calc(100vh - 80px); animation: fadeIn 0.8s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Accordion Styling */
        .accordion-button:not(.collapsed) {
            background-color: #e7f1ff;
            color: #003366;
            font-weight: bold;
        }
        .dept-header {
            background-color: #f8f9fa;
            border-left: 4px solid #003366;
            padding: 10px 15px;
            margin-bottom: 10px;
            cursor: pointer;
        }
        .dept-header:hover { background-color: #e2e6ea; }
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .top-navbar { padding: 15px 20px; flex-direction: column; gap: 15px; text-align: center; }
            .nav-menu { flex-wrap: wrap; justify-content: center; }
            .nav-profile { margin-left: 0; padding-left: 0; border-left: none; width: 100%; justify-content: center; margin-top: 10px; }
            .main-content { padding: 20px 15px; }
            .content-card, .table-container { padding: 20px !important; }
            h3.page-title { font-size: 1.5rem; }
            .filter-section { flex-direction: column; align-items: stretch !important; gap: 15px; }
            .filter-section .d-flex { flex-direction: column; width: 100%; }
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

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary"><i class="fas fa-layer-group me-2"></i>Library Collections</h2>
        
        <form class="d-flex w-50" method="GET">
            <input class="form-control me-2" type="search" name="search" placeholder="Search by Title, Author, or ISBN..." value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-outline-primary" type="submit">Search</button>
            <?php if($search): ?>
                <a href="book_catalog.php" class="btn btn-outline-secondary ms-2">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-success">Book deleted successfully.</div>
    <?php endif; ?>

    <?php if ($search): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark">Search Results</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Title</th><th>Author</th><th>ISBN</th><th>Location</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($books) > 0): ?>
                            <?php foreach ($books as $book): ?>
                            <tr>
                                <td><?php echo $book['title']; ?></td>
                                <td><?php echo $book['author']; ?></td>
                                <td><?php echo $book['isbn']; ?></td>
                                <td><small><?php echo $book['faculty'] . ' > ' . $book['department']; ?></small></td>
                                <td>
                                    <a href="edit_book.php?id=<?php echo $book['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                    <a href="book_catalog.php?delete=<?php echo $book['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center p-3">No books found matching "<?php echo $search; ?>"</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <div class="accordion" id="facultyAccordion">
            <?php 
            $fac_index = 0;
            if (empty($library)) {
                echo "<div class='alert alert-info'>No books in the library yet. <a href='add_book.php'>Add one now</a>.</div>";
            }
            
            foreach ($library as $facultyName => $departments): 
                $fac_index++;
                // Calculate total books in this faculty
                $fac_total = 0;
                foreach($departments as $d_books) $fac_total += count($d_books);
            ?>
            
            <div class="accordion-item shadow-sm mb-3 border-0">
                <h2 class="accordion-header" id="heading<?php echo $fac_index; ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $fac_index; ?>">
                        <span class="fw-bold fs-5 me-2"><?php echo $facultyName; ?></span>
                        <span class="badge bg-primary rounded-pill"><?php echo $fac_total; ?> Books</span>
                    </button>
                </h2>
                <div id="collapse<?php echo $fac_index; ?>" class="accordion-collapse collapse" data-bs-parent="#facultyAccordion">
                    <div class="accordion-body bg-light">
                        
                        <?php foreach ($departments as $deptName => $deptBooks): ?>
                            <div class="card mb-3 border-0 shadow-sm">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center" 
                                     data-bs-toggle="collapse" href="#dept<?php echo $fac_index . '_' . md5($deptName); ?>" 
                                     style="cursor: pointer;">
                                     
                                    <h6 class="mb-0 text-primary"><i class="fas fa-folder me-2"></i><?php echo $deptName; ?></h6>
                                    <span class="badge bg-secondary"><?php echo count($deptBooks); ?></span>
                                </div>
                                
                                <div class="collapse show" id="dept<?php echo $fac_index . '_' . md5($deptName); ?>">
                                    <div class="card-body p-0">
                                        <table class="table table-striped mb-0 table-sm">
                                            <thead>
                                                <tr>
                                                    <th style="width: 40%;">Title</th>
                                                    <th style="width: 30%;">Author</th>
                                                    <th style="width: 15%;">ISBN</th>
                                                    <th style="width: 15%;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($deptBooks as $book): ?>
                                                <tr>
                                                    <td class="ps-3"><?php echo $book['title']; ?></td>
                                                    <td><?php echo $book['author']; ?></td>
                                                    <td><?php echo $book['isbn']; ?></td>
                                                   <td>
    <button type="button" class="btn btn-sm btn-outline-dark me-1" 
            onclick="showQrModal('<?php echo $book['title']; ?>', '<?php echo $book['qr_code_path']; ?>')" 
            title="Print QR Code">
        <i class="fas fa-qrcode"></i>
    </button>

    <a href="edit_book.php?id=<?php echo $book['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
        <i class="fas fa-edit"></i>
    </a>

    <a href="book_catalog.php?delete=<?php echo $book['id']; ?>" class="btn btn-sm btn-outline-danger" 
       onclick="return confirm('Delete this book?')" title="Delete">
        <i class="fas fa-trash"></i>
    </a>
</td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<div class="modal fade" id="reprintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Print QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center" id="reprintArea">
                <h5 id="modalBookTitle" class="fw-bold text-primary"></h5>
                <img id="modalQrImage" src="" style="width: 200px; height: 200px; margin: 15px 0;">
                <p class="text-muted">Use CTRL+P to print this code.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printQr()">Print Now</button>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
        body * { visibility: hidden; }
        #reprintArea, #reprintArea * { visibility: visible; }
        #reprintArea { position: absolute; left: 0; top: 0; width: 100%; }
        /* Hide the close buttons during print */
        .modal-footer, .btn-close { display: none; } 
    }
</style>

<script>
    // Function to open the modal with correct data
    function showQrModal(title, path) {
        document.getElementById('modalBookTitle').innerText = title;
        document.getElementById('modalQrImage').src = path;
        var myModal = new bootstrap.Modal(document.getElementById('reprintModal'));
        myModal.show();
    }

    // Function to trigger print
    function printQr() {
        window.print();
    }
</script>
</body>
</html>