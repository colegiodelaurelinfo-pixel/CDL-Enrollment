<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
include_once '../admin/navbar.php';
checkAccess(['FACULTY']);

$user_id = $_SESSION['user_id'];

// 1. Get Active Term
$sys_res = $conn->query("SELECT * FROM system_settings");
$sys = [];
while ($r = $sys_res->fetch_assoc()) {
    $sys[$r['setting_key']] = $r['setting_value'];
}
$active_sy = $sys['active_sy'] ?? '';
$active_sem = $sys['active_semester'] ?? '';

// 2. Get Total Classes
$stmt1 = $conn->prepare("SELECT COUNT(*) as total FROM schedules WHERE faculty_id = ? AND school_year = ? AND semester = ?");
$stmt1->bind_param("iss", $user_id, $active_sy, $active_sem);
$stmt1->execute();
$total_classes = $stmt1->get_result()->fetch_assoc()['total'];

/**
 * LOGIC FOR SPECIAL ASSIGNMENTS (Swimming/Badminton)
 * We check if the instructor has a schedule assigned with a 'special' tag.
 */
$check_special = $conn->prepare("SELECT DISTINCT special FROM schedules WHERE faculty_id = ? AND special IS NOT NULL AND special != 'N/A'");
$check_special->bind_param("i", $user_id);
$check_special->execute();
$special_res = $check_special->get_result();

$special_types = [];
while ($row = $special_res->fetch_assoc()) {
    $special_types[] = $row['special'];
}

if (!empty($special_types)) {
    // IF SPECIAL: Fetch students where st.special matches the instructor's assigned special course
    // and they are enrolled in the current SY/Semester.
    $placeholders = implode(',', array_fill(0, count($special_types), '?'));
    $sql_students = "SELECT COUNT(DISTINCT student_id) as total 
                     FROM students 
                     WHERE special IN ($placeholders) 
                     AND status = 'Enrolled' 
                     AND school_year = ? 
                     AND semester = ?";

    $stmt2 = $conn->prepare($sql_students);

    // Dynamically bind params for the IN clause + SY and SEM
    $types = str_repeat('s', count($special_types)) . "ss";
    $params = array_merge($special_types, [$active_sy, $active_sem]);
    $stmt2->bind_param($types, ...$params);
} else {
    // REGULAR LOGIC: Fetch students based on Section assignments in the schedule
    $sql_students = "SELECT COUNT(DISTINCT st.student_id) as total 
                     FROM students st 
                     WHERE st.section IN (
                        SELECT sec.section_name 
                        FROM schedules s 
                        JOIN sections sec ON s.section_id = sec.id 
                        WHERE s.faculty_id = ? 
                        AND s.school_year = ? 
                        AND s.semester = ?
                     ) AND st.status = 'Enrolled'";
    $stmt2 = $conn->prepare($sql_students);
    $stmt2->bind_param("iss", $user_id, $active_sy, $active_sem);
}

if ($stmt2) {
    $stmt2->execute();
    $total_students = $stmt2->get_result()->fetch_assoc()['total'] ?? 0;
} else {
    $total_students = 0;
}

// 3. Fetch Today's Classes
$today = date('l');
$day_col = "day_" . strtolower(substr($today, 0, 3));
$stmt3 = $conn->prepare("
    SELECT s.*, c.course_code, sec.section_name 
    FROM schedules s 
    JOIN courses c ON s.course_id = c.course_id
    JOIN sections sec ON s.section_id = sec.id
    WHERE s.faculty_id = ? AND s.$day_col = 1 AND s.school_year = ? AND s.semester = ?
    ORDER BY s.time_start ASC
");
$stmt3->bind_param("iss", $user_id, $active_sy, $active_sem);
$stmt3->execute();
$todays_classes = $stmt3->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Faculty Dashboard | CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <style>
        :root {
            --cdl-green: #2d5a27;
            --cdl-orange: #f39c12;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            border-bottom: 5px solid var(--cdl-green);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .menu-item {
            background: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: 0.3s;
            border: 1px solid #eee;
        }

        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            border-color: var(--cdl-green);
        }

        .menu-item i {
            font-size: 2.5rem;
            color: var(--cdl-green);
            margin-bottom: 15px;
        }
    </style>
</head>

<body style="background: #f4f7f6;">



    <div class="container" style="max-width: 1200px; margin: 30px auto; padding: 0 20px;">

        <div style="margin-bottom: 30px;">
            <h1 style="margin: 0;">Welcome, Prof. <?= $_SESSION['lastname'] ?>!</h1>
            <p style="color: #666;">Term: <strong><?= $active_sem ?></strong> | Academic Year: <strong><?= $active_sy ?></strong></p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--cdl-green);"><i class="fas fa-chalkboard-teacher"></i></div>
                <div>
                    <h3 style="margin:0; font-size: 1.8rem;"><?= $total_classes ?></h3>
                    <small style="color: #888; text-transform: uppercase;">Classes</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--cdl-orange);"><i class="fas fa-users"></i></div>
                <div>
                    <h3 style="margin:0; font-size: 1.8rem;"><?= $total_students ?></h3>
                    <small style="color: #888; text-transform: uppercase;">Total Students</small>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 30px;">

            <div>
                <h3 style="margin-bottom: 20px;"><i class="fas fa-rocket"></i> Faculty Tools</h3>
                <div class="menu-grid">
                    <a href="my_load.php" class="menu-item">
                        <i class="fas fa-calendar-check"></i>
                        <h4>My Teaching Load</h4>
                        <p style="font-size: 0.8rem; color: #777;">View weekly schedule</p>
                    </a>
                    <a href="add_grades.php" class="menu-item">
                        <i class="fas fa-edit"></i>
                        <h4>Encode Grades</h4>
                        <p style="font-size: 0.8rem; color: #777;">Input student marks</p>
                    </a>
                    <a href="class_list.php" class="menu-item">
                        <i class="fas fa-clipboard-list"></i>
                        <h4>Class Lists</h4>
                        <p style="font-size: 0.8rem; color: #777;">View student rosters</p>
                    </a>
                </div>
            </div>

            <div style="background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <h3 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                    <i class="far fa-clock"></i> Today's Classes
                </h3>
                <?php if ($todays_classes->num_rows > 0): ?>
                    <?php while ($c = $todays_classes->fetch_assoc()): ?>
                        <div style="padding: 15px 0; border-bottom: 1px solid #f9f9f9;">
                            <strong style="color: var(--cdl-green);"><?= $c['course_code'] ?></strong>
                            <span style="font-size: 0.8rem; background: #eee; padding: 2px 6px; border-radius: 4px; margin-left: 5px;"><?= $c['section_name'] ?></span>
                            <div style="font-size: 0.85rem; margin-top: 5px; color: #555;">
                                <?= date("h:i A", strtotime($c['time_start'])) ?> - <?= date("h:i A", strtotime($c['time_end'])) ?>
                            </div>
                            <div style="font-size: 0.8rem; color: #999;"><i class="fas fa-map-marker-alt"></i> <?= $c['room_name'] ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #999; text-align: center; margin-top: 20px;">No classes scheduled for today.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>
</body>

</html>