<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Force redirect if NOT a student
if ($_SESSION['level'] !== 'STUDENT') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. Fetch User and Student Data
$user_query = $conn->prepare("SELECT email FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_data = $user_query->get_result()->fetch_assoc();
$email = $user_data['email'];

$student_query = $conn->prepare("SELECT * FROM students WHERE email = ?");
$student_query->bind_param("s", $email);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();

if (!$student) {
    die("Student record not found. Please contact the Registrar.");
}

// 2. Formatting Displays
$year_num = $student['year_level'];
$year_display = match($year_num) {
    '1', 1 => "1st Year",
    '2', 2 => "2nd Year",
    '3', 3 => "3rd Year",
    '4', 4 => "4th Year",
    default => $year_num . "th Year",
};

// Handle Semester display logic
$sem_display = ($student['semester'] == '1' || $student['semester'] == '1st Semester') ? "1st Semester" : "2nd Semester";

// 3. Curriculum Stats (Units & Course Count)
$p_code = $student['program'];
$stats_query = $conn->prepare("
    SELECT COUNT(c.course_id) as total_courses, SUM(c.lec_units + c.lab_units) as total_units 
    FROM courses c 
    JOIN program_curriculum pc ON c.course_id = pc.course_id 
    WHERE pc.program_code = ?
");
$stats_query->bind_param("s", $p_code);
$stats_query->execute();
$curriculum_stats = $stats_query->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Student Portal | Colegio de Laurel</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
<link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <style>
        :root { --cdl-green: #2d5a27; --cdl-dark: #1e3d1a; }
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        .profile-header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            border-left: 8px solid var(--cdl-green);
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: 0.3s;
            border-bottom: 4px solid transparent;
        }
        .stat-card:hover { transform: translateY(-5px); border-bottom: 4px solid var(--cdl-green); }
        .stat-card i { font-size: 1.8rem; color: var(--cdl-green); margin-bottom: 10px; }
        .stat-card h4 { font-size: 0.9rem; color: #888; text-transform: uppercase; letter-spacing: 1px; }
        
        .info-table td { padding: 12px 8px; border-bottom: 1px solid #f1f1f1; }
        .info-label { color: #888; font-weight: 500; width: 30%; }

        .btn-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 15px;
            border-radius: 10px;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none !important;
            margin-bottom: 10px;
        }
        .btn-schedule { background: var(--cdl-green); color: white; }
        .btn-schedule:hover { background: var(--cdl-dark); color: white; box-shadow: 0 4px 12px rgba(45, 90, 39, 0.3); }
        
        .btn-grades { background: #343a40; color: white; }
        .btn-grades:hover { background: #000; color: white; }

        .alert-custom {
            background: #f0f9f0;
            border-left: 5px solid var(--cdl-green);
            padding: 15px;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .profile-header { flex-direction: column; text-align: center; gap: 20px; }
            .header-right { text-align: center !important; }
        }
        
    </style>
</head>
<body>

   <?php include_once '../admin/navbar.php'; ?>

    <div class="container mt-4 mb-5">
        
        <div class="profile-header">
            <div class="d-flex align-items-center">
                <div class="mr-4 d-none d-md-block" style="background: #eef5ee; padding: 20px; border-radius: 50%;">
                    <i class="fas fa-user-graduate fa-2x text-success"></i>
                </div>
                <div>
                    <h2 class="mb-1">Welcome, <?php echo htmlspecialchars($_SESSION['firstname']); ?>!</h2>
                    <p class="text-muted mb-0">Student ID: <span class="text-dark font-weight-bold"><?php echo htmlspecialchars($student['student_id']); ?></span></p>
                </div>
            </div>
            <div class="header-right text-md-right">
                <span class="badge badge-success px-3 py-2 mb-2" style="border-radius: 20px;">
                    <i class="fas fa-check-circle mr-1"></i> Officially Enrolled
                </span>
                <p class="small text-muted mb-0">
                    S.Y. <?php echo htmlspecialchars($student['school_year']); ?> | <?php echo $sem_display; ?>
                </p>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <i class="fas fa-university"></i>
                    <h4>Program</h4>
                    <p class="h5 font-weight-bold mb-0"><?php echo htmlspecialchars($student['program']); ?></p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <i class="fas fa-layer-group"></i>
                    <h4>Year Level</h4>
                    <p class="h5 font-weight-bold mb-0"><?php echo $year_display; ?></p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h4>Section</h4>
                    <p class="h5 font-weight-bold mb-0"><?php echo htmlspecialchars($student['section']); ?></p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7 mb-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                    <div class="card-body p-4">
                        <h4 class="text-success mb-4"><i class="fas fa-address-card mr-2"></i>Personal Details</h4>
                        <table class="info-table w-100">
                            <tr>
                                <td class="info-label">Full Name</td>
                                <td class="font-weight-bold"><?php echo htmlspecialchars($_SESSION['firstname'] . " " . $_SESSION['lastname']); ?></td>
                            </tr>
                            <tr>
                                <td class="info-label">Email</td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                            </tr>
                            <tr>
                                <td class="info-label">Mobile</td>
                                <td><?php echo htmlspecialchars($student['mobile_number']); ?></td>
                            </tr>
                            <tr>
                                <td class="info-label">Address</td>
                                <td><?php echo htmlspecialchars($student['house_no_street'] . " " . $student['barangay'] . ", " . $student['city'] . ", " . $student['province']); ?></td>
                            </tr>
                            <tr>
                                <td class="info-label">Curriculum</td>
                                <td><?php echo $curriculum_stats['total_courses']; ?> Subjects (<?php echo $curriculum_stats['total_units']; ?> Total Units)</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 mb-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                    <div class="card-body p-4">
                        <h4 class="text-success mb-4"><i class="fas fa-th-large mr-2"></i>Quick Actions</h4>
                        
                        <a href="class_schedule.php" class="btn btn-outline-success btn-block py-3" style="border-radius: 10px; border-width: 2px; font-weight: 600;">
                            <i class="fas fa-scroll mr-2"></i> View My Class Schedule
                        </a>

                        <a href="view_grades.php" class="btn btn-outline-success btn-block py-3" style="border-radius: 10px; border-width: 2px; font-weight: 600;">
                            <i class="fas fa-scroll mr-2"></i> View My Grades
                        </a>

                       <a href="view_prospectus.php?program=<?php echo urlencode($student['program']); ?>" 
   class="btn btn-outline-success btn-block py-3" style="border-radius: 10px; border-width: 2px; font-weight: 600;">
    <i class="fas fa-scroll mr-2"></i> View My Course Prospectus
</a>

                        <div class="alert-custom mt-4 d-flex align-items-start">
                            <i class="fas fa-info-circle mt-1 mr-3 text-success"></i>
                            <p class="small mb-0 text-dark">
                                Your current enrollment data is synced for <strong>S.Y. <?php echo htmlspecialchars($student['school_year']); ?></strong>. 
                                Please visit the Registrar's Office for any profile updates.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>