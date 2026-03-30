<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Security: Only Admin can register new users
if ($_SESSION['level'] !== 'ADMINISTRATOR') {
    header("Location: dashboard.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fname      = mysqli_real_escape_string($conn, $_POST['firstname']);
    $mname      = mysqli_real_escape_string($conn, $_POST['middlename']); // New Field
    $lname      = mysqli_real_escape_string($conn, $_POST['lastname']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $faculty_id = mysqli_real_escape_string($conn, $_POST['faculty_id']);
    $dept       = mysqli_real_escape_string($conn, $_POST['department']);
    $level      = $_POST['level'];

    $temp_pass = password_hash("CDLStaff2026", PASSWORD_DEFAULT);

    // Check if Email OR Faculty ID already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR (faculty_id = ? AND faculty_id != '')");
    $check->bind_param("ss", $email, $faculty_id);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        $message = "<div class='error-msg'><i class='fas fa-exclamation-triangle'></i> Error: Email or Faculty ID already registered.</div>";
    } else {
        // Updated INSERT to include middlename
        $stmt = $conn->prepare("INSERT INTO users (firstname, middlename, lastname, email, faculty_id, department, password, level, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");

        // Now 8 's' parameters
        $stmt->bind_param("ssssssss", $fname, $mname, $lname, $email, $faculty_id, $dept, $temp_pass, $level);

        if ($stmt->execute()) {
            $message = "<div class='success-msg'><i class='fas fa-check-circle'></i> Success! Account created for <b> $fname.</b> Default Password: <b>CDLStaff2026</b></div>";
            $log_details = "Added a User with level: $level access and Username: $email";
            // CALL THE LOG FUNCTION
            log_system_activity($conn, 'UPDATE_GRADE', $log_details);
        } else {
            $message = "<div class='error-msg'><i class='fas fa-times-circle'></i> System Error: " . $conn->error . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Register User | Colegio de Laurel</title>
    <link rel="icon" type="image/png" href="../assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="../assets/img/CDL_seal.png">
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container" style="max-width: 800px;">
        <div class="profile-header">
            <div class="profile-title">
                <i class="fas fa-user-plus"></i>
                <div>
                    <h2>Register New Faculty/Staff</h2>
                    <small>Create a new official system account.</small>
                </div>
            </div>
            <div class="profile-actions">
                <a href="manage_users.php" class="btn-outline">
                    <i class="fas fa-chevron-left"></i> Back
                </a>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="form-card-modern">
            <form method="POST">
                <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr 1fr; margin-bottom: 20px;">
                    <div class="input-group">
                        <label>First Name</label>
                        <input type="text" name="firstname" required>
                    </div>
                    <div class="input-group">
                        <label>Middle Name</label>
                        <input type="text" name="middlename">
                    </div>
                    <div class="input-group">
                        <label>Last Name</label>
                        <input type="text" name="lastname" required>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="input-group">
                        <label>Official Email Address</label>
                        <input type="email" name="email" required placeholder="john.doe@cdl.edu.ph">
                    </div>
                    <div class="input-group">
                        <label><i class="fas fa-id-badge"></i> Access Role</label>
                        <div class="select-wrapper">
                            <select name="level" id="roleSelect" required onchange="toggleFacultyFields()">
                                <option value="" disabled selected>Select a role...</option>
                                <option value="REGISTRAR">Registrar</option>
                                <option value="FACULTY">Faculty Member</option>
                                <option value="ADMINISTRATOR">System Administrator</option>
                                <option value="STAFF">Staff</option>
                            </select>
                            <i class="fas fa-chevron-down select-icon"></i>
                        </div>
                    </div>
                </div>

                <div class="grid-2" id="facultyFields">
                    <div class="input-group">
                        <label>Faculty / Staff ID</label>
                        <input type="text" name="faculty_id" placeholder="e.g. FAC-2026-001">
                    </div>
                    <div class="input-group">
                        <label>Department</label>
                        <div class="select-wrapper">
                            <select name="department">
                                <option value="" selected>-- Select Department --</option>
                                <option value="Early Childhood Education">Early Childhood Education</option>
                                <option value="Criminology">Criminology</option>
                                <option value="General Education">General Education</option>
                                <option value="Agribusiness">Agribusiness</option>
                                <option value="Administrative Office">Administrative Office</option>
                            </select>
                            <i class="fas fa-chevron-down select-icon"></i>
                        </div>
                    </div>
                </div>

                <div class="info-alert" style="margin-top: 20px; margin-bottom: 30px;">
                    <i class="fas fa-shield-halved"></i>
                    <b>Security Protocol:</b> Default password is <u>CDLStaff2026</u>.
                </div>

                <div class="form-actions">
                    <button type="submit" class="login-btn" style="width: auto; padding: 12px 40px;">
                        <i class="fas fa-user-check"></i> Finalize Registration
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>

</html>