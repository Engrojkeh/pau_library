<?php
// profile.php (Student)
session_start();
include 'db_conn.php';

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
$id = $_SESSION['user_id'];
$msg = "";

// UPDATE LOGIC
if (isset($_POST['update_profile'])) {
    $fname = $_POST['first_name'];
    $mname = $_POST['middle_name'];
    $sname = $_POST['surname'];
    $phone = $_POST['phone'];
    $faculty = $_POST['faculty'];
    $dept = $_POST['department'];
    $full_name = "$fname $mname $sname";

    // IMAGE UPLOAD
    $profile_pic = $_POST['old_pic']; 
    if (!empty($_FILES["upload_pic"]["name"])) {
        $target_dir = "uploads/";
        // Unique name to prevent conflicts
        $target_file = $target_dir . "stu_" . $id . "_" . basename($_FILES["upload_pic"]["name"]);
        if (move_uploaded_file($_FILES["upload_pic"]["tmp_name"], $target_file)) {
            $profile_pic = $target_file;
        }
    }
    
    $stmt = $conn->prepare("UPDATE students SET full_name=?, phone=?, faculty=?, department=?, profile_pic=? WHERE id=?");
    $stmt->bind_param("sssssi", $full_name, $phone, $faculty, $dept, $profile_pic, $id);
    
    if ($stmt->execute()) {
        $msg = "<div class='alert alert-success'>Profile Updated! Refreshing...</div>";
        header("Refresh: 1");
    } else {
        $msg = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}

// FETCH CURRENT DATA
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Split name
$names = explode(" ", $user['full_name']);
$f_val = isset($names[0]) ? $names[0] : "";
$m_val = isset($names[1]) ? $names[1] : "";
$s_val = isset($names[2]) ? $names[2] : "";
if (count($names) == 2) { $s_val = $names[1]; $m_val = ""; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white"><h4>Edit My Profile</h4></div>
                <div class="card-body">
                    <?php echo $msg; ?>
                    <form method="POST" enctype="multipart/form-data">
                        
                        <div class="mb-4 text-center">
                            <?php $pic = !empty($user['profile_pic']) ? $user['profile_pic'] : "https://via.placeholder.com/150"; ?>
                            <img src="<?php echo $pic; ?>" class="rounded-circle shadow mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                            <input type="file" name="upload_pic" class="form-control w-50 mx-auto">
                            <input type="hidden" name="old_pic" value="<?php echo $user['profile_pic']; ?>">
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4"><label>First Name</label><input type="text" name="first_name" class="form-control" value="<?php echo $f_val; ?>" required></div>
                            <div class="col-md-4"><label>Middle Name</label><input type="text" name="middle_name" class="form-control" value="<?php echo $m_val; ?>"></div>
                            <div class="col-md-4"><label>Surname</label><input type="text" name="surname" class="form-control" value="<?php echo $s_val; ?>" required></div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6"><label>Matric No</label><input type="text" class="form-control bg-light" value="<?php echo $user['matric_no']; ?>" readonly></div>
                            <div class="col-md-6"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?php echo $user['phone']; ?>" required></div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label>Faculty</label>
                                <select id="faculty" name="faculty" class="form-select" onchange="updateDepts()" required>
                                    <option value="<?php echo $user['faculty']; ?>"><?php echo $user['faculty']; ?></option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label>Department</label>
                                <select id="department" name="department" class="form-select" required>
                                    <option value="<?php echo $user['department']; ?>"><?php echo $user['department']; ?></option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary w-100">Save Changes</button>
                        <a href="dashboard_student.php" class="btn btn-secondary w-100 mt-2">Back to Dashboard</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// MASTER LIST SCRIPT (Required for dropdowns)
const universityData = {
    "School of Science & Technology": ["Computer Science", "Software Engineering", "Information Technology", "Microbiology", "Biochemistry", "Industrial Chemistry", "Physics with Electronics", "Mathematics & Statistics", "Geology"],
    "College of Engineering": ["Civil Engineering", "Mechanical Engineering", "Electrical & Electronics Engineering", "Petroleum Engineering", "Chemical Engineering", "Mechatronics Engineering", "Computer Engineering"],
    "School of Management & Social Sciences": ["Accounting", "Business Administration", "Economics", "Mass Communication", "Political Science", "International Relations", "Banking & Finance", "Marketing", "Public Administration"],
    "College of Law": ["Public Law", "Private Law", "International Law", "Commercial Law"],
    "College of Medicine & Health Sciences": ["Medicine & Surgery", "Nursing Science", "Public Health", "Medical Laboratory Science", "Anatomy", "Physiology"],
    "School of Arts & Humanities": ["English & Literary Studies", "History & International Studies", "Performing Arts", "Philosophy", "Religious Studies"],
    "School of Environmental Design": ["Architecture", "Building Technology", "Estate Management", "Urban & Regional Planning"]
};
function loadFaculties() {
    let facultySelect = document.getElementById("faculty");
    let firstOption = facultySelect.options[0];
    facultySelect.innerHTML = ""; facultySelect.add(firstOption);
    for (let faculty in universityData) {
        let option = document.createElement("option"); option.text = faculty; option.value = faculty; facultySelect.add(option);
    }
}
function updateDepts() {
    let facultySelect = document.getElementById("faculty");
    let deptSelect = document.getElementById("department");
    let selectedFaculty = facultySelect.value;
    deptSelect.innerHTML = '<option value="">Select Department...</option>';
    if (selectedFaculty && universityData[selectedFaculty]) {
        universityData[selectedFaculty].sort().forEach(function(dept) {
            let option = document.createElement("option"); option.text = dept; option.value = dept; deptSelect.add(option);
        });
    }
}
document.addEventListener('DOMContentLoaded', function() {
    loadFaculties();
    var userFaculty = "<?php echo $user['faculty']; ?>";
    var userDept = "<?php echo $user['department']; ?>";
    if(userFaculty) {
        document.getElementById('faculty').value = userFaculty;
        updateDepts();
        document.getElementById('department').value = userDept;
    }
});
</script>
</body>
</html>