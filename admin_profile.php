<?php
session_start();
include 'db_conn.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$id = $_SESSION['user_id'];
$msg = "";

// HANDLE FILE UPLOAD & UPDATE
if (isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    
    // 1. Handle Image Upload
    $profile_pic = $_POST['old_pic']; // Default to old pic
    if (!empty($_FILES["upload_pic"]["name"])) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES["upload_pic"]["name"]);
        // Move the file
        if (move_uploaded_file($_FILES["upload_pic"]["tmp_name"], $target_file)) {
            $profile_pic = $target_file;
        }
    }

    // 2. Update Database
    $stmt = $conn->prepare("UPDATE admins SET full_name=?, profile_pic=? WHERE id=?");
    $stmt->bind_param("ssi", $full_name, $profile_pic, $id);
    
    if ($stmt->execute()) {
        $msg = "<div class='alert alert-success'>Profile Updated! Refreshing sidebar...</div>";
        // Refresh page to show new image immediately
        header("Refresh: 1"); 
    } else {
        $msg = "<div class='alert alert-danger'>Error updating profile.</div>";
    }
}

// FETCH DATA
$query = $conn->query("SELECT * FROM admins WHERE id = $id");
$admin = $query->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Edit Admin Profile</h5>
                </div>
                <div class="card-body text-center">
                    <?php echo $msg; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <?php 
                                $pic = !empty($admin['profile_pic']) ? $admin['profile_pic'] : "https://via.placeholder.com/150"; 
                            ?>
                            <img src="<?php echo $pic; ?>" class="rounded-circle shadow" style="width: 150px; height: 150px; object-fit: cover;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Change Profile Picture</label>
                            <input type="file" name="upload_pic" class="form-control">
                            <input type="hidden" name="old_pic" value="<?php echo $admin['profile_pic']; ?>">
                        </div>

                        <div class="mb-3 text-start">
                            <label class="form-label">Display Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo $admin['full_name']; ?>" required>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary w-100">Save Changes</button>
                        <a href="dashboard_admin.php" class="btn btn-outline-secondary w-100 mt-2">Back to Dashboard</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>