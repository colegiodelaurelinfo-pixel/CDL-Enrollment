<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// navbar.php handles session_start and $display_sy / $display_sem


$current_sy = $display_sy;
$current_sem = $display_sem;

// Data Fetching
$total_programs = $conn->query("SELECT COUNT(*) as total FROM programs")->fetch_assoc()['total'] ?? 0;
$total_courses = $conn->query("SELECT COUNT(*) as total FROM courses")->fetch_assoc()['total'] ?? 0;
$total_faculty = $conn->query("SELECT COUNT(*) as total FROM users WHERE level = 'FACULTY'")->fetch_assoc()['total'] ?? 0;

$stmt_enrolled = $conn->prepare("SELECT COUNT(*) as total FROM students WHERE school_year = ? AND semester = ?");
$stmt_enrolled->bind_param("ss", $current_sy, $current_sem);
$stmt_enrolled->execute();
$total_enrolled = $stmt_enrolled->get_result()->fetch_assoc()['total'] ?? 0;

$firstname = $_SESSION['firstname'] ?? 'User';
$level = $_SESSION['level'] ?? '';

// Role Protection
if ($level !== 'ADMINISTRATOR') {
    $redirects = [
        'FACULTY' => '../faculty/faculty_dashboard.php',
        'STUDENT' => '../student/student_dashboard.php'
    ];
    $target = $redirects[$level] ?? '../index.php';
    header("Location: $target");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Dashboard | Colegio de Laurel</title>
    <link rel="icon" type="image/png" href="../assets/img/CDL_seal.png">
    <link rel="apple-touch-icon" href="../assets/img/CDL_seal.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="dashboard-header-card">
            <div class="welcome-text">
                <h1>Welcome, <?php echo htmlspecialchars($firstname); ?>!</h1>
                <p>Access Level: <span class="status-badge status-active"><?php echo $level; ?></span></p>

                <div class="sy-indicator" style="background: var(--cdl-green); color: white; padding: 10px 15px; border-radius: 8px; display: inline-block; margin-top: 15px;">
                    <i class="fas fa-calendar-check"></i>
                    Academic Year: <strong><?php echo $current_sy; ?> | <?php echo $current_sem; ?></strong>
                </div>
            </div>
        </div>

        <div class="dashboard-grid" style="margin-bottom: 30px;">

            <div class="card" style="border-left: 5px solid #6c5ce7;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <small class="text-muted">PROGRAMS</small>
                        <h2 style="font-size: 2.2rem;"><?php echo number_format($total_programs); ?></h2>
                    </div>
                    <i class="fas fa-university" style="font-size: 1.5rem; background: #6c5ce7; padding: 15px; border-radius: 50%; color: white;"></i>
                </div>
                <a href="manage_programs.php" class="btn-link">View Details →</a>
            </div>

            <div class="card" style="border-left: 5px solid #f39c12;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <small class="text-muted">COURSES</small>
                        <h2 style="font-size: 2.2rem;"><?php echo number_format($total_courses); ?></h2>
                    </div>
                    <i class="fas fa-book" style="font-size: 1.5rem; background: #f39c12; padding: 15px; border-radius: 50%; color: white;"></i>
                </div>
                <a href="manage_courses.php" class="btn-link">Curriculum Management →</a>
            </div>

            <div class="card" style="border-left: 5px solid #007bff;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <small class="text-muted">FACULTY</small>
                        <h2 style="font-size: 2.2rem;"><?php echo number_format($total_faculty); ?></h2>
                    </div>
                    <i class="fas fa-chalkboard-teacher" style="font-size: 1.5rem; background: #007bff; padding: 15px; border-radius: 50%; color: white;"></i>
                </div>
                <a href="manage_users.php" class="btn-link">Faculty List →</a>
            </div>

            <div class="card" style="border-left: 5px solid var(--cdl-green);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <small class="text-muted">CURRENT ENROLLED</small>
                        <h2 style="font-size: 2.2rem;"><?php echo number_format($total_enrolled); ?></h2>
                    </div>
                    <i class="fas fa-user-check" style="font-size: 1.5rem; background: var(--cdl-green); padding: 15px; border-radius: 50%; color: white;"></i>
                </div>
                <a href="manage_students.php" class="btn-link">Registrar Tools →</a>
            </div>
            <div class="card">
                <h3><i class="fas fa-database"></i>System Maintenance</h3>
                <p>Review system activities and perform database optimization.</p>
                <a href="maintenance.php" class="btn-add" style="margin-top: 15px; background: var(--cdl-red);">
                    <i class="fas fa-eye"></i> View Audit Logs
                </a>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <h3><i class="fas fa-user-shield"></i> Administration</h3>
                <p>Manage system users, logs, and global academic settings.</p>
                <a href="settings.php" class="btn-add" style="margin-top: 15px; background: #333;">System Settings</a>
            </div>

            <div class="card">
                <h3><i class="fas fa-print"></i> Quick Reports</h3>
                <p>Generate faculty loads, enrollment statistics, and class lists.</p>
                <a href="enrollment_stats.php" class="btn-add" style="margin-top: 15px;">Enrollment Summary</a>
            </div>

            <div class="card">
                <h3><i class="fas fa-database"></i>Login Maintenance</h3>
                <p>Review system logins and perform database optimization.</p>
                <a href="view_logs.php" class="btn-add" style="margin-top: 15px; background: var(--cdl-red);">Who login?</a>
            </div>
        </div>
    </div>
</body>

</html>