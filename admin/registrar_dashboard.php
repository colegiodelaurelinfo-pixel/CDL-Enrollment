<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';


if ($_SESSION['level'] !== 'ADMINISTRATOR' && $_SESSION['level'] !== 'REGISTRAR') {
    header("Location: dashboard.php");
    exit();
}

// 1. Fetch System Settings for the Header
$nav_settings = $conn->query("SELECT * FROM system_settings");
$sys = [];
while ($r = $nav_settings->fetch_assoc()) {
    $sys[$r['setting_key']] = $r['setting_value'];
}
$display_sy = $sys['active_sy'] ?? "None";
$display_sem = $sys['active_semester'] ?? "None";

// 2. Statistics for the Cards
$count_enrolled = $conn->query("SELECT COUNT(*) as total FROM students WHERE status = 'Enrolled' AND school_year = '$display_sy'")->fetch_assoc()['total'];
$count_dropped  = $conn->query("SELECT COUNT(*) as total FROM students WHERE status != 'Enrolled' AND school_year = '$display_sy'")->fetch_assoc()['total'];

// 3. Compliance: Count schedules that are NOT yet submitted for the current term
$count_pending_grades = $conn->query("SELECT COUNT(*) as total FROM grades WHERE final_grade IS NOT NULL AND academic_year = '$display_sy' AND semester = '$display_sem'")->fetch_assoc()['total'];

// 4. Recent Activity (Last 5 Students)
$recent_students = $conn->query("SELECT * FROM students ORDER BY date_enrolled DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Registrar Dashboard - CDL</title>
    <link rel="icon" type="image/png" href="../assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="../assets/img/CDL_seal.png">
    <style>
        :root {
            --primary-green: #2d5a27;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 5px solid #ccc;
            transition: 0.3s;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .card h3 {
            margin: 0;
            font-size: 2rem;
            color: #333;
        }

        .card p {
            margin: 5px 0 0;
            color: #777;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .card i {
            float: right;
            font-size: 2.5rem;
            opacity: 0.15;
        }

        .recent-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .view-link {
            display: inline-block;
            margin-top: 10px;
            font-size: 0.75rem;
            color: var(--primary-green);
            text-decoration: none;
            font-weight: bold;
        }

        .view-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body style="background: #f8faf9;">
    <?php include 'navbar.php'; ?>

    <div class="container" style="margin-top: 20px; max-width: 1100px;">
        <div style="margin-bottom: 25px;">
            <h2 style="margin: 0; color: var(--primary-green);">Academic Overview</h2>
            <small style="color: #666;">Welcome back, <strong><?= $_SESSION['firstname'] ?></strong>. Here is the status for <?= $display_sy ?> (<?= $display_sem ?>).</small>
        </div>

        <div class="stat-grid">
            <div class="card" style="border-left-color: #2d5a27;">
                <i class="fas fa-user-graduate"></i>
                <h3><?= $count_enrolled ?></h3>
                <p>Total Enrolled</p>
                <a href="manage_students.php?f_status=Enrolled" class="view-link">Manage Records →</a>
            </div>

            <div class="card" style="border-left-color: #d9534f;">
                <i class="fas fa-user-slash"></i>
                <h3><?= $count_dropped ?></h3>
                <p>Inactive / Dropped</p>
                <a href="manage_students.php?f_status=DO (Dropped Officially)" class="view-link">View Details →</a>
            </div>

            <div class="card" style="border-left-color: #f0ad4e;">
                <i class="fas fa-file-invoice"></i>
                <h3><?= $count_pending_grades ?></h3>
                <p>Submitted Grades</p>
                <a href="grading_status.php" class="view-link">Check Compliance →</a>
            </div>
        </div>

        <div class="recent-section">
            <h4 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <i class="fas fa-history"></i> Recent Enrollments
            </h4>
            <table class="admin-table" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Program</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_students->num_rows > 0): while ($s = $recent_students->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= $s['student_id'] ?></strong></td>
                                <td><?= strtoupper($s['lastname']) ?>, <?= $s['firstname'] ?></td>
                                <td><span class="prog-pill"><?= $s['program'] ?></span></td>
                                <td><span class="status-badge" style="background: #2d5a27; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem;">ENROLLED</span></td>
                            </tr>
                        <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #999; padding: 20px;">No recent activities found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div style="text-align: right;">
                <a href="register_student.php" class="view-link">+ Enroll New Student</a>
            </div>
        </div>
    </div>
</body>

</html>