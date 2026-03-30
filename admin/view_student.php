<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// 1. Get the ID from the URL
$id = $_GET['id'] ?? '';

if (empty($id)) {
    die("<div class='container' style='margin-top:50px;'><h2>No Student ID provided.</h2><a href='manage_students.php'>Return to List</a></div>");
}

// 2. THE FIX: Only search 'student_id' since 'id' doesn't exist in your table
$query = "SELECT * FROM students WHERE student_id = ? LIMIT 1";
$stmt = $conn->prepare($query);

// 3. Now we only need ONE 's' and ONE variable because there is only one '?'
$stmt->bind_param("s", $id);

$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    die("<div class='container' style='margin-top:50px;'><h2>Student not found.</h2><a href='manage_students.php'>Return to List</a></div>");
}

$role = $_SESSION['level'] ?? 'STAFF';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Profile | <?php echo htmlspecialchars($student['lastname']); ?></title>
    <link rel="icon" type="image/png" href="../assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="../assets/img/CDL_seal.png">
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="profile-header">
            <div class="profile-title">
                <i class="fas fa-user-graduate"></i>
                <div>
                    <h2>Student Information Sheet</h2>
                    <small>Official Record: <b><?php echo htmlspecialchars($student['student_id']); ?></b></small>
                </div>
            </div>

            <div class="profile-actions">
                <a href="manage_students.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Back</a>

                <?php if ($role === 'ADMINISTRATOR'): ?>
                    <a href="edit_student.php?id=<?php echo $id; ?>" class="btn-edit">
                        <i class="fas fa-pen-to-square"></i> Edit Profile
                    </a>
                    <button class="btn-delete" onclick="confirmDelete('<?php echo $id; ?>')">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-grid">
            <div class="profile-section">
                <h3><i class="fas fa-id-card"></i> Personal Details</h3>
                <div class="info-group">
                    <label>Full Name:</label>
                    <span><?php echo htmlspecialchars($student['lastname'] . ", " . $student['firstname'] . " " . ($student['middlename'] ?? '')); ?></span>
                </div>
                <div class="info-group grid-2">
                    <div>
                        <label>Birth Date:</label>
                        <span><?php echo ($student['birthdate'] == '0000-00-00' || !$student['birthdate']) ? 'Not Set' : $student['birthdate']; ?></span>
                    </div>
                    <div>
                        <label>Gender:</label>
                        <span><?php echo htmlspecialchars($student['sex'] ?? $student['gender'] ?? 'Not Specified'); ?></span>
                    </div>
                </div>
                <div class="info-group">
                    <label>Birthplace:</label>
                    <span><?php echo htmlspecialchars($student['birthplace'] ?? 'Not Provided'); ?></span>
                </div>
            </div>

            <div class="profile-section">
                <h3><i class="fas fa-location-dot"></i> Address & Contact</h3>
                <div class="info-group"><label>Email:</label> <span><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></span></div>
                <div class="info-group">
                    <label>Region/Province:</label>
                    <span><?php echo htmlspecialchars(($student['region'] ?? 'N/A') . " / " . ($student['province'] ?? 'N/A')); ?></span>
                </div>
                <div class="info-group">
                    <label>City/Brgy:</label>
                    <span><?php echo htmlspecialchars(($student['city'] ?? 'N/A') . ", " . ($student['barangay'] ?? 'N/A')); ?></span>
                </div>
            </div>

            <div class="profile-section">
                <h3><i class="fas fa-graduation-cap"></i> Enrollment Status</h3>
                <div class="info-group"><label>Program/Course:</label> <span><?php echo htmlspecialchars($student['program']); ?></span></div>
                <div class="info-group"><label>Year Level:</label> <span><?php echo htmlspecialchars($student['year_level']); ?></span></div>
                <div class="info-group">
                    <label>S.Y. Enrolled:</label>
                    <span><?php echo htmlspecialchars($student['school_year'] ?? 'N/A'); ?></span>
                </div>
            </div>

            <div class="profile-section">
                <h3><i class="fas fa-users"></i> Emergency Contact</h3>
                <div class="info-group">
                    <label>Guardian Name:</label> <span><?php echo htmlspecialchars($student['guardian_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-group">
                    <label>Contact No:</label>
                    <span><?php echo htmlspecialchars($student['guardian_contact'] ?? $student['contact_no'] ?? 'N/A'); ?></span>

                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(id) {
            if (confirm("Are you sure you want to permanently delete this student record? This cannot be undone.")) {
                window.location.href = "delete_student.php?id=" + id;
            }
        }