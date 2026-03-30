<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Security: Only Admin or Registrar
if (!in_array($_SESSION['level'], ['ADMINISTRATOR', 'REGISTRAR'])) {
    header("Location: dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : null;

if (!$id) {
    header("Location: manage_courses.php");
    exit();
}

// --- HANDLE UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = mysqli_real_escape_string($conn, $_POST['course_code']);
    $title = mysqli_real_escape_string($conn, $_POST['course_title']);
    $lec = (int)$_POST['lec_units'];
    $lab = (int)$_POST['lab_units'];

    $update = $conn->query("UPDATE courses SET 
        course_code = '$code', 
        course_title = '$title', 
        lec_units = $lec, 
        lab_units = $lab 
        WHERE course_id = '$id'");

    if ($update) {
        header("Location: manage_courses.php?msg=updated");
        $log_details = "Updated course $code | $title";
        // CALL THE LOG FUNCTION
        log_system_activity($conn, 'UPDATE_COURSE', $log_details);
        exit();
    } else {
        $error = "Update failed: " . $conn->error;
    }
}

// --- FETCH CURRENT DATA ---
$res = $conn->query("SELECT * FROM courses WHERE course_id = '$id'");
$course = $res->fetch_assoc();

if (!$course) {
    die("Course not found.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Course | CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-card {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-cdl {
            background: #2d5a27;
            color: white;
            border: none;
        }

        .btn-cdl:hover {
            background: #1e3d1a;
            color: white;
        }
    </style>
</head>

<body style="background: #f4f7f6;">
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="form-card">
            <h5 class="mb-4 font-weight-bold text-dark"><i class="fas fa-edit mr-2"></i>Edit Course</h5>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger small"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="small font-weight-bold">COURSE CODE</label>
                    <input type="text" name="course_code" class="form-control" value="<?= htmlspecialchars($course['course_code']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold">COURSE TITLE</label>
                    <input type="text" name="course_title" class="form-control" value="<?= htmlspecialchars($course['course_title']) ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold">LEC UNITS</label>
                        <input type="number" name="lec_units" class="form-control" value="<?= $course['lec_units'] ?>" min="0" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold">LAB UNITS</label>
                        <input type="number" name="lab_units" class="form-control" value="<?= $course['lab_units'] ?>" min="0" required>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-cdl btn-block">Update Course Information</button>
                    <a href="manage_courses.php" class="btn btn-light btn-block btn-sm mt-2 text-muted">Cancel and Go Back</a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>