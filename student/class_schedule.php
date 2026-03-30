<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
checkAccess(['STUDENT']);

$user_id = $_SESSION['user_id'];
$view = $_GET['view'] ?? 'list';

// 1. Get Student Details
$st_query = $conn->prepare("SELECT student_id, section, program FROM students WHERE email = (SELECT email FROM users WHERE id = ?)");
$st_query->bind_param("i", $user_id);
$st_query->execute();
$st_data = $st_query->get_result()->fetch_assoc();

$student_id = $st_data['student_id'] ?? 'N/A';
$my_section = $st_data['section'] ?? '';

// 2. Get Active Term
$sys_res = $conn->query("SELECT * FROM system_settings");
$sys = [];
while ($r = $sys_res->fetch_assoc()) {
    $sys[$r['setting_key']] = $r['setting_value'];
}
$active_sy = $sys['active_sy'] ?? '';
$active_sem = $sys['active_semester'] ?? '';

// 3. Fetch Schedules
$sql = "SELECT s.*, c.course_code, c.course_title, u.firstname, u.lastname 
        FROM schedules s
        JOIN courses c ON s.course_id = c.course_id
        JOIN users u ON s.faculty_id = u.id
        JOIN sections sec ON s.section_id = sec.id
        WHERE sec.section_name = ? AND s.school_year = ? AND s.semester = ?
        ORDER BY FIELD(day_mon, 1) DESC, FIELD(day_tue, 1) DESC, s.time_start ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $my_section, $active_sy, $active_sem);
$stmt->execute();
$result = $stmt->get_result();

$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}

// Helper for Weekly View Positioning (7 AM start)
function getOffset($time)
{
    $hour = (int)date("H", strtotime($time));
    $minute = (int)date("i", strtotime($time));
    return (($hour - 7) * 60) + $minute;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>My Schedule | CDL</title>
    <style>
        :root {
            --cdl-green: #2d5a27;
            --hour-h: 60px;
        }

        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', sans-serif;
        }

        /* --- LIST VIEW CARDS --- */
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .sched-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border-left: 6px solid var(--cdl-green);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: 0.3s;
            position: relative;
        }

        .sched-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .course-code {
            font-weight: 800;
            font-size: 1.2rem;
            color: var(--cdl-green);
            margin-bottom: 5px;
            display: block;
        }

        .course-title {
            font-size: 0.95rem;
            color: #444;
            font-weight: 600;
            margin-bottom: 15px;
            line-height: 1.3;
        }

        .meta-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            color: #666;
            margin-top: 8px;
        }

        .meta-row i {
            color: var(--cdl-green);
            width: 16px;
            text-align: center;
        }

        .day-pill-container {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .day-pill {
            background: #e8f5e9;
            color: var(--cdl-green);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: bold;
            border: 1px solid #c8e6c9;
        }

        /* --- WEEKLY VIEW (PIXEL BASED) --- */
        .weekly-wrap {
            display: grid;
            grid-template-columns: 80px repeat(6, 1fr);
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }

        .day-col {
            position: relative;
            border-right: 1px solid #eee;
            height: calc(var(--hour-h) * 14);
            background-image: linear-gradient(#eee 1px, transparent 1px);
            background-size: 100% var(--hour-h);
        }

        .time-col-sidebar {
            background: #f8f9fa;
            border-right: 1px solid #ddd;
            text-align: center;
            color: #777;
            font-size: 0.75rem;
        }

        .hour-label {
            height: var(--hour-h);
            padding-top: 5px;
            border-bottom: 1px solid #eee;
        }

        .week-header {
            background: var(--cdl-green);
            color: white;
            text-align: center;
            padding: 12px 5px;
            font-weight: bold;
            font-size: 0.8rem;
            border-bottom: 1px solid #ddd;
        }

        .abs-block {
            position: absolute;
            left: 4px;
            right: 4px;
            background: #f1f8f1;
            border-left: 3px solid var(--cdl-green);
            padding: 5px;
            border-radius: 4px;
            font-size: 0.65rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* --- UTILS --- */
        .view-toggle {
            display: flex;
            background: #ddd;
            border-radius: 50px;
            padding: 4px;
            width: fit-content;
            margin-bottom: 20px;
        }

        .view-toggle a {
            padding: 8px 18px;
            text-decoration: none;
            color: #666;
            border-radius: 50px;
            font-weight: bold;
            font-size: 0.85rem;
            transition: 0.3s;
        }

        .view-toggle a.active {
            background: var(--cdl-green);
            color: white;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
            }

            .sched-card {
                break-inside: avoid;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>

<body>

    <div class="no-print"><?php include_once '../admin/navbar.php'; ?></div>

    <div class="container" style="max-width: 1200px; margin: 30px auto; padding: 0 20px;">

        <div style="background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; border-bottom: 4px solid var(--cdl-green); box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="margin:0; font-size: 1.5rem; color: #333;"><?= $_SESSION['firstname'] ?>'s Schedule</h1>
                    <p style="margin:5px 0 0; color: #777;">A.Y. <?= $active_sy ?> | <?= $active_sem ?></p>
                </div>
                <div style="text-align: right;">
                    <span style="display:block; font-weight: bold; font-size: 1.1rem;"><?= $my_section ?></span>
                    <span style="color: #999; font-size: 0.85rem;"><?= $st_data['program'] ?></span>
                </div>
            </div>
        </div>

        <div class="no-print" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="view-toggle">
                <a href="?view=list" class="<?= $view == 'list' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> List</a>
                <a href="?view=weekly" class="<?= $view == 'weekly' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> Weekly</a>
            </div>
            <button onclick="window.print()" class="btn-add" style="width:auto; padding: 10px 20px;"><i class="fas fa-print"></i> Print Schedule</button>
        </div>

        <?php if ($view == 'list'): ?>
            <div class="schedule-grid">
                <?php foreach ($schedules as $row): ?>
                    <div class="sched-card">
                        <span class="course-code"><?= $row['course_code'] ?></span>
                        <div class="course-title"><?= $row['course_title'] ?></div>

                        <div class="meta-row">
                            <i class="far fa-clock"></i>
                            <?= date("h:i A", strtotime($row['time_start'])) ?> — <?= date("h:i A", strtotime($row['time_end'])) ?>
                        </div>

                        <div class="meta-row">
                            <i class="fas fa-map-marker-alt"></i>
                            Room: <?= $row['room_name'] ?>
                        </div>

                        <div class="meta-row">
                            <i class="fas fa-user-tie"></i>
                            Prof. <?= $row['firstname'] ?> <?= $row['lastname'] ?>
                        </div>

                        <div class="day-pill-container">
                            <?php
                            if ($row['day_mon']) echo '<span class="day-pill">MON</span>';
                            if ($row['day_tue']) echo '<span class="day-pill">TUE</span>';
                            if ($row['day_wed']) echo '<span class="day-pill">WED</span>';
                            if ($row['day_thu']) echo '<span class="day-pill">THU</span>';
                            if ($row['day_fri']) echo '<span class="day-pill">FRI</span>';
                            if ($row['day_sat']) echo '<span class="day-pill">SAT</span>';
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="weekly-wrap">
                <div class="week-header" style="background:#f8f9fa;"></div>
                <div class="week-header">MON</div>
                <div class="week-header">TUE</div>
                <div class="week-header">WED</div>
                <div class="week-header">THU</div>
                <div class="week-header">FRI</div>
                <div class="week-header">SAT</div>

                <div class="time-col-sidebar">
                    <?php for ($h = 7; $h <= 20; $h++): ?>
                        <div class="hour-label"><?= date("g A", strtotime("$h:00")) ?></div>
                    <?php endfor; ?>
                </div>

                <?php
                $days = ['day_mon', 'day_tue', 'day_wed', 'day_thu', 'day_fri', 'day_sat'];
                foreach ($days as $d): ?>
                    <div class="day-col">
                        <?php foreach ($schedules as $s): if ($s[$d]):
                                $top = getOffset($s['time_start']);
                                $height = getOffset($s['time_end']) - $top;
                        ?>
                                <div class="abs-block" style="top:<?= $top ?>px; height:<?= $height ?>px; padding: 4px; line-height: 1.15; overflow: hidden; border-left: 3px solid var(--cdl-green);">

                                    <b style="color:var(--cdl-green); font-size: 0.9rem;"><?= $s['course_code'] ?></b><br>
                                    <b style="color: #000000; font-size: 0.9rem;"><?= $s['course_title'] ?></b><br>

                                    <div style="font-size: 0.7rem; color: #333; margin-top: 2px;">
                                        <i class="fas fa-user-tie" style="font-size: 0.9rem; color: var(--cdl-green);"></i>
                                        Prof. <?= $s['lastname'] ?>, <?= $s['firstname'] ?>
                                    </div>

                                    <span style="font-size: 0.8rem; color: #555;">
                                        <i class="fas fa-map-marker-alt" style="font-size: 0.9rem;"></i> <?= $s['room_name'] ?>
                                    </span><br>

                                    <span style="font-size: 0.65rem; font-weight: 500; color: #777; white-space: nowrap;">
                                        <i class="far fa-clock" style="font-size: 0.6rem;"></i>
                                        <?= date("g:i A", strtotime($s['time_start'])) ?> - <?= date("g:i A", strtotime($s['time_end'])) ?>
                                    </span>
                                </div>
                        <?php endif;
                        endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</body>

</html>