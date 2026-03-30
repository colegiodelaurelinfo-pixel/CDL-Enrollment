<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';


// 1. Get Active Settings
$settings_query = $conn->query("SELECT * FROM system_settings WHERE setting_key IN ('active_sy', 'active_semester')");
$active = ['active_sy' => '', 'active_semester' => ''];
while ($row = $settings_query->fetch_assoc()) {
    $active[$row['setting_key']] = $row['setting_value'];
}

$active_sy = $_GET['sy'] ?? $active['active_sy'];
$active_sem = $_GET['sem'] ?? $active['active_semester'];

// 2. Fetch all instructors
$faculty_list = $conn->query("SELECT id, firstname, lastname FROM users WHERE level = 'FACULTY' ORDER BY lastname ASC");
$selected_faculty = $_GET['faculty_id'] ?? '';

$load_data = [];
$faculty_info = null;

if ($selected_faculty) {
    $f_stmt = $conn->prepare("SELECT firstname, lastname, middlename FROM users WHERE id = ?");
    $f_stmt->bind_param("i", $selected_faculty);
    $f_stmt->execute();
    $faculty_info = $f_stmt->get_result()->fetch_assoc();

    $query = "SELECT s.*, c.course_code, c.course_title, c.lec_units, c.lab_units,
               (s.day_mon + s.day_tue + s.day_wed + s.day_thu + s.day_fri + s.day_sat + s.day_sun) AS day_count,
               CASE WHEN s.section_id = 0 THEN (c.lec_units + c.lab_units) * (s.day_mon + s.day_tue + s.day_wed + s.day_thu + s.day_fri + s.day_sat + s.day_sun)
                    ELSE (c.lec_units + c.lab_units) END AS total_calculated_units,
               IF(s.section_id = 0, 'Common / PATHFit', sec.section_name) AS section_name
        FROM schedules s
        JOIN courses c ON s.course_id = c.course_id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.faculty_id = ? AND s.school_year = ? AND s.semester = ?
        ORDER BY s.time_start ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $selected_faculty, $active_sy, $active_sem);
    $stmt->execute();
    $load_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Faculty Loading | CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* General Layout */
        .load-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .summary-box {
            display: flex;
            justify-content: space-between;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 5px solid #2d5a27;
        }

        .total-units {
            font-size: 18pt;
            font-weight: bold;
            color: #2d5a27;
        }

        /* Tabs Styling */
        .tab-nav {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid #ddd;
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 12px 25px;
            cursor: pointer;
            border: none;
            background: #e9ecef;
            font-weight: bold;
            color: #666;
            border-radius: 8px 8px 0 0;
            transition: 0.3s;
        }

        .tab-btn.active {
            background: #2d5a27;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Timetable Grid */
        .tt-wrapper {
            display: flex;
            border: 1px solid #ddd;
            background: white;
            position: relative;
            overflow-x: auto;
        }

        .tt-time-col {
            width: 80px;
            flex-shrink: 0;
            background: #f9f9f9;
            border-right: 1px solid #ddd;
        }

        .tt-day-col {
            flex: 1;
            min-width: 130px;
            border-right: 1px solid #eee;
            position: relative;
        }

        .tt-header {
            height: 45px;
            background: #2d5a27;
            color: white;
            text-align: center;
            line-height: 45px;
            font-weight: bold;
            font-size: 0.85rem;
        }

        .tt-time-slot {
            height: 50px;
            border-bottom: 1px solid #eee;
            font-size: 0.7rem;
            text-align: center;
            color: #888;
            line-height: 50px;
        }

        .tt-grid-container {
            position: relative;
            height: 700px;
            background-image: linear-gradient(#eee 1px, transparent 1px);
            background-size: 100% 50px;
        }

        .sched-block {
            position: absolute;
            left: 4px;
            right: 4px;
            background: rgba(45, 90, 39, 0.9);
            color: white;
            border-radius: 4px;
            padding: 5px;
            font-size: 0.65rem;
            overflow: hidden;
            border-left: 4px solid #1a3617;
            line-height: 1.2;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            z-index: 5;
        }

        @media print {

            .no-print,
            .tab-nav {
                display: none !important;
            }

            .tab-content {
                display: block !important;
                margin-bottom: 40px;
                page-break-inside: avoid;
            }

            body {
                background: white;
            }

            .load-card {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>

<body style="background: #f4f7f6;">
    <?php include 'navbar.php'; ?>
    <div class="container" style="max-width: 1200px; margin: 20px auto;">
        <div class="no-print" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <form method="GET" style="display: flex; gap: 15px; align-items: end;">
                <div style="flex: 1;">
                    <label style="font-size: 8pt; font-weight: bold; color: #555;">SELECT INSTRUCTOR</label>
                    <select name="faculty_id" class="form-control" onchange="this.form.submit()" required>
                        <option value="">-- Choose Faculty --</option>
                        <?php $faculty_list->data_seek(0);
                        while ($f = $faculty_list->fetch_assoc()): ?>
                            <option value="<?= $f['id'] ?>" <?= ($selected_faculty == $f['id']) ? 'selected' : '' ?>>
                                <?= strtoupper($f['lastname'] . ", " . $f['firstname']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div style="flex: 0 0 160px;">
                    <label style="font-size: 7.5pt; font-weight: 800; color: #666;">ACADEMIC YEAR</label>
                    <div style="height: 38px; background: #eee; border: 1px solid #ccc; border-radius: 4px; line-height: 38px; padding: 0 10px; font-weight: bold;"><?= $active_sy ?></div>
                </div>
                <div style="flex: 0 0 160px;">
                    <label style="font-size: 7.5pt; font-weight: 800; color: #666;">SEMESTER</label>
                    <div style="height: 38px; background: #eee; border: 1px solid #ccc; border-radius: 4px; line-height: 38px; padding: 0 10px; font-weight: bold;"><?= $active_sem ?></div>
                </div>
            </form>
        </div>

        <?php if ($faculty_info): ?>
            <div class="load-card">
                <div style="text-align: center; margin-bottom: 30px;">
                    <h2 style="margin: 0; color: #2d5a27;">FACULTY SERVICE RECORD</h2>
                    <p style="color: #666;">Colegio de Laurel | A.Y. <?= $active_sy ?> | <?= $active_sem ?></p>
                </div>

                <div class="summary-box">
                    <div>
                        <span style="font-size: 8.5pt; color: #666; font-weight: bold;">INSTRUCTOR</span><br>
                        <strong style="font-size: 15pt; color: #1a3617;"><?= strtoupper($faculty_info['firstname'] . ' ' . $faculty_info['middlename'] . '. ' . $faculty_info['lastname']) ?></strong>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 8.5pt; color: #666; font-weight: bold;">TOTAL UNITS</span><br>
                        <span class="total-units">
                            <?php
                            $total = 0;
                            foreach ($load_data as $row) {
                                $total += ($row['total_calculated_units'] ?: 0);
                            }
                            echo round($total);
                            ?>
                        </span>
                    </div>
                </div>

                <div class="tab-nav no-print">
                    <button class="tab-btn active" onclick="openTab(event, 'weekly-view')"><i class="fas fa-calendar-alt"></i> Weekly Timetable</button>
                    <button class="tab-btn" onclick="openTab(event, 'summary-view')"><i class="fas fa-list-ul"></i> Class Summary</button>
                </div>

                <div id="weekly-view" class="tab-content active">
                    <div class="tt-wrapper">
                        <div class="tt-time-col">
                            <div class="tt-header" style="background: #f1f1f1; color: #333;">TIME</div>
                            <?php for ($i = 7; $i <= 20; $i++): ?>
                                <div class="tt-time-slot"><?= date("g A", strtotime("$i:00")) ?></div>
                            <?php endfor; ?>
                        </div>
                        <?php
                        $week_days = ['day_mon' => 'MON', 'day_tue' => 'TUE', 'day_wed' => 'WED', 'day_thu' => 'THU', 'day_fri' => 'FRI', 'day_sat' => 'SAT', 'day_sun' => 'SUN'];
                        foreach ($week_days as $db_col => $label): ?>
                            <div class="tt-day-col">
                                <div class="tt-header"><?= $label ?></div>
                                <div class="tt-grid-container">
                                    <?php foreach ($load_data as $s): if ($s[$db_col] == 1):
                                            $start_time = strtotime($s['time_start']);
                                            $end_time = strtotime($s['time_end']);
                                            $top_px = ((date('G', $start_time) * 60 + date('i', $start_time)) - (7 * 60)) * (50 / 60);
                                            $height_px = (($end_time - $start_time) / 60) * (50 / 60);
                                    ?>
                                            <div class="sched-block" style="top: <?= $top_px ?>px; height: <?= $height_px - 2 ?>px;">
                                                <div style="font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom:2px;"><?= $s['course_code'] ?></div>
                                                Sec: <?= $s['section_name'] ?><br>Room: <?= $s['room_name'] ?>
                                            </div>
                                    <?php endif;
                                    endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="summary-view" class="tab-content">
                    <table class="admin-table">
                        <thead>
                            <tr style="background: #2d5a27; color: white;">
                                <th>Code</th>
                                <th>Course Title</th>
                                <th style="text-align:center;">Section</th>
                                <th>Schedule</th>
                                <th style="text-align:center;">Room</th>
                                <th style="text-align:center;">Units</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($load_data as $s):
                                $days = [];
                                if ($s['day_mon']) $days[] = "M";
                                if ($s['day_tue']) $days[] = "T";
                                if ($s['day_wed']) $days[] = "W";
                                if ($s['day_thu']) $days[] = "TH";
                                if ($s['day_fri']) $days[] = "F";
                                if ($s['day_sat']) $days[] = "S";
                            ?>
                                <tr>
                                    <td><strong><?= $s['course_code'] ?></strong></td>
                                    <td><?= $s['course_title'] ?></td>
                                    <td style="text-align:center;"><?= $s['section_name'] ?></td>
                                    <td><span style="color:#2d5a27; font-weight:bold;"><?= implode("", $days) ?></span> | <?= date("g:i A", strtotime($s['time_start'])) ?>-<?= date("g:i A", strtotime($s['time_end'])) ?></td>
                                    <td style="text-align:center;"><?= $s['room_name'] ?: '---' ?></td>
                                    <td style="text-align:center;"><strong><?= (int)$s['total_calculated_units'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 30px;" class="no-print">
                    <button onclick="window.print()" style="padding: 12px 25px; background: #2d5a27; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: bold;"><i class="fas fa-print"></i> PRINT SERVICE RECORD</button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
                tabcontent[i].classList.remove("active");
            }
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            document.getElementById(tabName).style.display = "block";
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>

</html>