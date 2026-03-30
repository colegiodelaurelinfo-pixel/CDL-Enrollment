<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
include_once '../admin/navbar.php';
// Security: Only Faculty
if ($_SESSION['level'] !== 'FACULTY') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$selected_sched = $_GET['sched_id'] ?? null;

// 1. Get Active Term
$sys_res = $conn->query("SELECT * FROM system_settings");
$sys = [];
while ($r = $sys_res->fetch_assoc()) {
    $sys[$r['setting_key']] = $r['setting_value'];
}
$active_sy = $sys['active_sy'] ?? '';
$active_semester = $sys['active_semester'] ?? '';

// 2. Fetch all classes handled by this Faculty
$sched_query = $conn->prepare("
    SELECT s.id, c.course_code, c.course_title, sec.section_name, s.special 
    FROM schedules s
    JOIN courses c ON s.course_id = c.course_id
    JOIN sections sec ON s.section_id = sec.id
    WHERE s.faculty_id = ? AND s.school_year = ? AND s.semester = ?
");
$sched_query->bind_param("iss", $user_id, $active_sy, $active_semester);
$sched_query->execute();
$my_classes = $sched_query->get_result();

// 3. Fetch students if class is selected
$students = [];
$current_class = null;
if ($selected_sched) {
    $details = $conn->prepare("
        SELECT c.course_code, c.course_title, sec.section_name, s.special 
        FROM schedules s
        JOIN courses c ON s.course_id = c.course_id
        JOIN sections sec ON s.section_id = sec.id
        WHERE s.id = ? AND s.faculty_id = ?
    ");
    $details->bind_param("ii", $selected_sched, $user_id);
    $details->execute();
    $current_class = $details->get_result()->fetch_assoc();

    if ($current_class) {
        $special_type = $current_class['special'];
        $target_section = $current_class['section_name'];

        // --- THE LOGIC GATE ---

        // 1. MODULAR / LGU LOGIC
        if ($special_type === 'MODULAR') {
            $st_sql = "SELECT student_id, firstname, lastname, email, mobile_number 
                       FROM students 
                       WHERE lgu = 'YES' AND status = 'Enrolled'
                       ORDER BY lastname ASC";
            $st_query = $conn->prepare($st_sql);
        }
        // 2. SPECIAL ASSIGNMENT LOGIC (Swimming/Badminton)
        else if ($special_type !== 'N/A' && !empty($special_type)) {
            $st_sql = "SELECT student_id, firstname, lastname, email, mobile_number 
                       FROM students 
                       WHERE special = ? AND status = 'Enrolled'
                       ORDER BY lastname ASC";
            $st_query = $conn->prepare($st_sql);
            $st_query->bind_param("s", $special_type);
        }
        // 3. DEFAULT REGULAR LOGIC (Section-based)
        else {
            $st_sql = "SELECT student_id, firstname, lastname, email, mobile_number 
                       FROM students 
                       WHERE section = ? AND status = 'Enrolled'
                       ORDER BY lastname ASC";
            $st_query = $conn->prepare($st_sql);
            $st_query->bind_param("s", $target_section);
        }

        $st_query->execute();
        $students = $st_query->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Class List | CDL</title>
    <style>
        .student-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            font-size: 0.88rem;
        }

        .student-table th {
            background: var(--cdl-green);
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 0.75rem;
        }

        .student-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #edf2f7;
        }

        .special-badge {
            background: #007bff;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body style="background: var(--cdl-bg);">

    

    <div class="container" style="max-width: 1100px; margin: 30px auto; padding: 0 20px;">

        <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0; color: var(--cdl-green-dark);"><i class="fas fa-users"></i> Class Roster</h2>
            <a href="faculty_dashboard.php" class="btn-outline" style="width: auto; padding: 5px 15px; font-size: 0.85rem;"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <div class="class-selector no-print">
            <form method="GET" style="display: flex; gap: 12px; align-items: flex-end;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-size: 0.8rem; font-weight: bold; color: var(--cdl-text-muted); text-transform: uppercase;">Select Class:</label>
                    <select name="sched_id" class="form-control" required>
                        <option value="">-- Choose a Class --</option>
                        <?php
                        // Reset pointer for the second loop
                        $my_classes->data_seek(0);
                        while ($c = $my_classes->fetch_assoc()):
                        ?>
                            <option value="<?= $c['id'] ?>" <?= ($selected_sched == $c['id']) ? 'selected' : '' ?>>
                                <?= $c['course_code'] ?> - <?= $c['section_name'] ?>
                                <?= ($c['special'] !== 'N/A') ? "[{$c['special']}]" : "" ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="login-btn" style="width: auto; padding: 8px 20px; height: 38px;">View List</button>
            </form>
        </div>

        <?php if ($current_class): ?>
            <div class="table-container">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--cdl-green); padding-bottom: 10px; margin-bottom: 15px;">
                    <div>
                        <h3 style="margin: 0; color: var(--cdl-green);">
                            <?= $current_class['course_code'] ?>: <?= $current_class['course_title'] ?>
                            <?php if ($current_class['special'] !== 'N/A'): ?>
                                <span class="special-badge"><?= $current_class['special'] ?></span>
                            <?php endif; ?>
                        </h3>
                        <p style="margin: 3px 0 0; color: var(--cdl-text-muted); font-size: 0.85rem;">
                            Section: <strong><?= $current_class['section_name'] ?></strong> |
                            Total Students: <strong><?= $students->num_rows ?></strong>
                        </p>
                    </div>
                    <button onclick="window.print()" class="no-print btn-outline" style="padding: 5px 12px; font-size: 0.8rem;">
                        <i class="fas fa-print"></i> Print Roster
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">#</th>
                                <th style="width: 130px;">Student ID</th>
                                <th>Full Name</th>
                                <th>Email Address</th>
                                <th style="width: 140px;">Contact No.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 1;
                            if ($students && $students->num_rows > 0):
                                while ($s = $students->fetch_assoc()):
                            ?>
                                    <tr>
                                        <td style="text-align: center; color: var(--cdl-text-muted);"><?= $i++ ?></td>
                                        <td style="font-weight: 600; color: var(--cdl-green);"><?= $s['student_id'] ?></td>
                                        <td style="text-transform: uppercase; font-weight: 500;"><?= $s['lastname'] ?>, <?= $s['firstname'] ?></td>
                                        <td style="color: var(--cdl-text-muted); font-size: 0.85rem;"><?= strtolower($s['email']) ?></td>
                                        <td style="font-size: 0.85rem;"><?= $s['mobile_number'] ?></td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--cdl-text-muted); padding: 30px;">
                                        No students found for this <?= ($current_class['special'] !== 'N/A') ? 'Special Assignment' : 'Section' ?>.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; background: white; border-radius: 12px; border: 2px dashed #ccc;">
                <i class="fas fa-hand-pointer" style="font-size: 2.5rem; color: #ddd; margin-bottom: 10px;"></i>
                <h3 style="color: var(--cdl-text-muted); font-weight: 400;">Select a class above to load the roster.</h3>
            </div>
        <?php endif; ?>

    </div>
</body>

</html>