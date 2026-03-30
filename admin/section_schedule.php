<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';


// Fetch active settings
$settings = $conn->query("SELECT * FROM system_settings WHERE setting_key IN ('active_sy', 'active_semester')");
$active = [];
while ($row = $settings->fetch_assoc()) {
    $active[$row['setting_key']] = $row['setting_value'];
}

$active_sy = $_GET['sy'] ?? $active['active_sy'];
$active_sem = $_GET['sem'] ?? $active['active_semester'];

// Fetch all sections for the filter
$sections_query = $conn->query("SELECT id, section_name FROM sections ORDER BY section_name ASC");
$selected_section = $_GET['section_id'] ?? '';

// Main Data Fetching
$sched_data = [];
$section_info = null;

if ($selected_section) {
    // Get section name for the header
    $sec_stmt = $conn->prepare("SELECT section_name FROM sections WHERE id = ?");
    $sec_stmt->bind_param("i", $selected_section);
    $sec_stmt->execute();
    $section_info = $sec_stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("
        SELECT s.*, c.course_code, c.course_title, 
               CONCAT(u.firstname, ' ', u.lastname) as faculty_name
        FROM schedules s
        JOIN courses c ON s.course_id = c.course_id
        LEFT JOIN users u ON s.faculty_id = u.id
        WHERE s.section_id = ? AND s.school_year = ? AND s.semester = ?
        ORDER BY s.time_start ASC
    ");
    $stmt->bind_param("iss", $selected_section, $active_sy, $active_sem);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $sched_data[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Section Schedule | CDL</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="icon" type="image/png" href="../assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="../assets/img/CDL_seal.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filter-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .tab-container {
            display: flex;
            gap: 5px;
            margin-top: 20px;
        }

        .tab-btn {
            padding: 12px 25px;
            border: none;
            background: #e0e0e0;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            font-weight: bold;
            transition: 0.3s;
            color: #666;
        }

        .tab-btn.active {
            background: #2d5a27;
            color: white;
        }

        .view-content {
            background: white;
            padding: 25px;
            border-radius: 0 8px 8px 8px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            min-height: 400px;
        }

        /* Timetable Logic */
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
            min-width: 140px;
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
            font-size: 0.75rem;
            border-bottom: 1px solid #ddd;
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
            background: rgba(45, 90, 39, 0.92);
            color: white;
            border-radius: 4px;
            padding: 6px;
            font-size: 0.65rem;
            overflow: hidden;
            border-left: 4px solid #1a3617;
            line-height: 1.2;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
            z-index: 5;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            .view-content {
                box-shadow: none;
                border: none;
                padding: 0;
            }
        }
    </style>
</head>

<body style="background: #f4f7f6;">
    <?php include 'navbar.php'; ?>
    <div class="container" style="max-width: 1300px; margin: 20px auto; padding: 0 20px;">
        <h2 style="color: #1a3617; margin-bottom: 20px;"><i class="fas fa-users"></i> Section Schedule Viewer</h2>

        <div class="filter-card no-print">
            <form method="GET" style="display: flex; gap: 20px; align-items: flex-end;">
                <div style="flex: 1;">
                    <label style="font-size: 8pt; font-weight: 800; color: #555;">CHOOSE SECTION</label>
                    <select name="section_id" class="form-control" required onchange="this.form.submit()" style="height: 42px; border: 1.5px solid #2d5a27; font-weight: bold;">
                        <option value="">-- Select Section --</option>
                        <?php while ($sec = $sections_query->fetch_assoc()): ?>
                            <option value="<?= $sec['id'] ?>" <?= ($selected_section == $sec['id']) ? 'selected' : '' ?>><?= $sec['section_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div style="flex: 0 0 180px;">
                    <label style="font-size: 8pt; font-weight: 800; color: #555;">ACADEMIC YEAR</label>
                    <div style="height: 42px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 4px; line-height: 42px; padding: 0 15px; font-weight: bold;"><?= $active_sy ?></div>
                </div>
                <div style="flex: 0 0 180px;">
                    <label style="font-size: 8pt; font-weight: 800; color: #555;">SEMESTER</label>
                    <div style="height: 42px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 4px; line-height: 42px; padding: 0 15px; font-weight: bold;"><?= $active_sem ?></div>
                </div>
            </form>
        </div>

        <?php if ($selected_section && $section_info): ?>
            <div class="tab-container no-print">
                <button class="tab-btn active" onclick="switchView('weekly', this)"><i class="fas fa-calendar-alt"></i> Weekly View</button>
                <button class="tab-btn" onclick="switchView('list', this)"><i class="fas fa-list"></i> List View</button>
            </div>

            <div class="view-content">
                <button onclick="window.print()" class="no-print" style="padding: 8px 15px; background: #2d5a27; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-print"></i> Print Schedule
                </button>
                <div id="weekly-view">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin:0; color: #2d5a27;">Weekly Schedule: <?= $section_info['section_name'] ?></h3>

                    </div>

                    <div class="tt-wrapper">
                        <div class="tt-time-col">
                            <div class="tt-header" style="background: #f1f1f1; color: #333;">TIME</div>
                            <?php for ($i = 7; $i <= 20; $i++): ?>
                                <div class="tt-time-slot"><?= date("g A", strtotime("$i:00")) ?></div>
                            <?php endfor; ?>
                        </div>

                        <?php
                        $week_days = [
                            'day_mon' => 'MON',
                            'day_tue' => 'TUE',
                            'day_wed' => 'WED',
                            'day_thu' => 'THU',
                            'day_fri' => 'FRI',
                            'day_sat' => 'SAT',
                            'day_sun' => 'SUN'
                        ];

                        foreach ($week_days as $db_col => $label): ?>
                            <div class="tt-day-col">
                                <div class="tt-header"><?= $label ?></div>
                                <div class="tt-grid-container">
                                    <?php foreach ($sched_data as $s):
                                        if ($s[$db_col] == 1):
                                            $start_time = strtotime($s['time_start']);
                                            $end_time = strtotime($s['time_end']);
                                            $start_min = (date('G', $start_time) * 60) + date('i', $start_time);
                                            $cal_start_min = 7 * 60;
                                            $top_px = ($start_min - $cal_start_min) * (50 / 60);
                                            $duration_min = ($end_time - $start_time) / 60;
                                            $height_px = $duration_min * (50 / 60);
                                    ?>
                                            <div class="sched-block" style="top: <?= $top_px ?>px; height: <?= $height_px - 2 ?>px;">
                                                <div style="font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 3px; font-size: 1.05rem;">
                                                    <?= $s['course_code'] ?>
                                                </div>
                                                <div style="font-size: 0.9rem; line-height: 1.3;">
                                                    <strong>Room:</strong> <?= $s['room_name'] ?><br>
                                                    <strong>Inst:</strong> <?= $s['faculty_name'] ?: 'TBA' ?>
                                                </div>
                                            </div>
                                    <?php endif;
                                    endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="list-view" style="display:none;">
                    <h3 style="margin-bottom:20px; color: #2d5a27;">Subject List: <?= $section_info['section_name'] ?></h3>

                    <table class="admin-table">
                        <thead>
                            <tr style="background: #2d5a27; color: white;">
                                <th>Time Slot</th>
                                <th style="text-align:center;">Days</th>
                                <th>Course Info</th>
                                <th style="text-align:center;">Room</th>
                                <th>Instructor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sched_data as $s):
                                $d = [];
                                if ($s['day_mon']) $d[] = "M";
                                if ($s['day_tue']) $d[] = "T";
                                if ($s['day_wed']) $d[] = "W";
                                if ($s['day_thu']) $d[] = "TH";
                                if ($s['day_fri']) $d[] = "F";
                                if ($s['day_sat']) $d[] = "S";
                            ?>
                                <tr>
                                    <td style="font-weight:bold; color: #2d5a27;">
                                        <?= date("h:i A", strtotime($s['time_start'])) ?> - <?= date("h:i A", strtotime($s['time_end'])) ?>
                                    </td>
                                    <td style="text-align: center; font-weight: bold;"><?= implode("", $d) ?></td>
                                    <td><strong><?= $s['course_code'] ?></strong><br><small><?= $s['course_title'] ?></small></td>
                                    <td style="text-align: center; font-weight: bold;"><?= $s['room_name'] ?></td>
                                    <td><?= $s['faculty_name'] ?: '<span style="color:#999 italic">TBA</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sched_data)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding: 30px;">No subjects scheduled for this section yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 100px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <i class="fas fa-users-rectangle" style="font-size: 4rem; color: #eee; margin-bottom: 20px;"></i>
                <h3 style="color: #888;">Select a section to view the class schedule.</h3>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function switchView(view, btn) {
            document.getElementById('weekly-view').style.display = (view === 'weekly') ? 'block' : 'none';
            document.getElementById('list-view').style.display = (view === 'list') ? 'block' : 'none';

            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }
    </script>
</body>

</html>