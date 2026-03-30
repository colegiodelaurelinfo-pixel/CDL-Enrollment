<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
include_once '../admin/navbar.php';
$faculty_id = $_SESSION['user_id'];

// --- 1. DYNAMIC SETTINGS FETCHING ---
$settings_query = $conn->query("SELECT * FROM system_settings WHERE setting_key IN ('active_sy', 'active_semester')");
$display_sy = '';
$display_sem = '';
if ($settings_query) {
    while ($row = $settings_query->fetch_assoc()) {
        if ($row['setting_key'] == 'active_sy') $display_sy = $row['setting_value'];
        if ($row['setting_key'] == 'active_semester') $display_sem = $row['setting_value'];
    }
}

// --- 2. DROPDOWN DATA PREPARATION ---
// Updated to include LGU/Modular logic in dropdown visibility
$p_query = $conn->prepare("
    SELECT DISTINCT s.program 
    FROM schedules sch
    JOIN sections sec ON sch.section_id = sec.id
    JOIN students s ON (
        (sch.special = 'MODULAR' AND s.lgu = 'YES') OR
        (sch.special != 'N/A' AND sch.special != 'MODULAR' AND s.special = sch.special) OR
        ((sch.special = 'N/A' OR sch.special IS NULL) AND sec.section_name = s.section)
    )
    WHERE sch.faculty_id = ? AND sch.school_year = ? AND sch.semester = ?
    ORDER BY s.program ASC");
$p_query->bind_param("iss", $faculty_id, $display_sy, $display_sem);
$p_query->execute();
$programs_list = $p_query->get_result()->fetch_all(MYSQLI_ASSOC);

$sec_query = $conn->prepare("
    SELECT DISTINCT sec.section_name as section, s.program 
    FROM schedules sch
    JOIN sections sec ON sch.section_id = sec.id
    JOIN students s ON (
        (sch.special = 'MODULAR' AND s.lgu = 'YES') OR
        (sch.special != 'N/A' AND sch.special != 'MODULAR' AND s.special = sch.special) OR
        ((sch.special = 'N/A' OR sch.special IS NULL) AND sec.section_name = s.section)
    )
    WHERE sch.faculty_id = ? AND sch.school_year = ? AND sch.semester = ?
    ORDER BY sec.section_name ASC");
$sec_query->bind_param("iss", $faculty_id, $display_sy, $display_sem);
$sec_query->execute();
$sections_master = $sec_query->get_result()->fetch_all(MYSQLI_ASSOC);

$course_query = $conn->prepare("
    SELECT DISTINCT s.program as program_code, c.course_id, c.course_code, c.course_title 
    FROM schedules sch
    JOIN courses c ON sch.course_id = c.course_id
    JOIN sections sec ON sch.section_id = sec.id
    JOIN students s ON (
        (sch.special = 'MODULAR' AND s.lgu = 'YES') OR
        (sch.special != 'N/A' AND sch.special != 'MODULAR' AND s.special = sch.special) OR
        ((sch.special = 'N/A' OR sch.special IS NULL) AND sec.section_name = s.section)
    )
    WHERE sch.faculty_id = ? AND sch.school_year = ? AND sch.semester = ?
    ORDER BY c.course_code ASC");
$course_query->bind_param("iss", $faculty_id, $display_sy, $display_sem);
$course_query->execute();
$courses_master = $course_query->get_result()->fetch_all(MYSQLI_ASSOC);

$report_data = null;
$course_info = null;
$sched_info = null;
$display_days = "---";
$display_time = "---";

// --- 3. GENERATE REPORT LOGIC ---
if (isset($_GET['btn_generate'])) {
    $prog = $_GET['program'];
    $sect = $_GET['section'];
    $subj_id = (int)$_GET['subject'];

    $sched_stmt = $conn->prepare("
        SELECT s.*, sec.section_name, c.course_code, c.course_title,
               CONCAT(u.firstname, ' ', IF(u.middlename!='', CONCAT(SUBSTRING(u.middlename,1,1),'. '),''), u.lastname) AS instructor_full_name
        FROM schedules s
        INNER JOIN sections sec ON s.section_id = sec.id
        INNER JOIN courses c ON s.course_id = c.course_id
        LEFT JOIN users u ON s.faculty_id = u.id
        WHERE s.course_id = ? AND sec.section_name = ? AND s.semester = ? AND s.school_year = ? AND s.faculty_id = ?
        LIMIT 1
    ");
    $sched_stmt->bind_param("isssi", $subj_id, $sect, $display_sem, $display_sy, $faculty_id);
    $sched_stmt->execute();
    $sched_info = $sched_stmt->get_result()->fetch_assoc();

    if ($sched_info) {
        $course_info = ['course_code' => $sched_info['course_code'], 'course_title' => $sched_info['course_title']];
        $special_type = $sched_info['special'];

        // Format Schedule Strings
        $days = [];
        if ($sched_info['day_mon']) $days[] = "M";
        if ($sched_info['day_tue']) $days[] = "T";
        if ($sched_info['day_wed']) $days[] = "W";
        if ($sched_info['day_thu']) $days[] = "TH";
        if ($sched_info['day_fri']) $days[] = "F";
        if ($sched_info['day_sat']) $days[] = "S";
        if ($sched_info['day_sun']) $days[] = "SU";
        $display_days = !empty($days) ? implode("", $days) : "TBA";
        if (!empty($sched_info['time_start'])) {
            $display_time = date("g:i A", strtotime($sched_info['time_start'])) . " - " . date("g:i A", strtotime($sched_info['time_end']));
        }

        // --- FETCH STUDENTS WITH MODULAR LOGIC ---
        $base_q = "SELECT s.student_id, s.lastname, s.firstname, s.middlename, 
                          g.temp_final_grade, g.final_grade, g.remarks
                   FROM students s
                   LEFT JOIN grades g ON s.student_id = g.student_id 
                       AND g.course_id = ? AND g.academic_year = ? AND g.semester = ?
                   WHERE s.status = 'Enrolled' ";

        if ($special_type === 'MODULAR') {
            $q = $base_q . " AND s.lgu = 'YES' ORDER BY s.lastname ASC";
            $stmt = $conn->prepare($q);
            $stmt->bind_param("iss", $subj_id, $display_sy, $display_sem);
        } elseif ($special_type !== 'N/A' && !empty($special_type)) {
            $q = $base_q . " AND s.special = ? ORDER BY s.lastname ASC";
            $stmt = $conn->prepare($q);
            $stmt->bind_param("isss", $subj_id, $display_sy, $display_sem, $special_type);
        } else {
            $q = $base_q . " AND s.section = ? ORDER BY s.lastname ASC";
            $stmt = $conn->prepare($q);
            $stmt->bind_param("isss", $subj_id, $display_sy, $display_sem, $sect);
        }
        $stmt->execute();
        $report_data = $stmt->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Official Grade Sheet | CDL</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-size: 9pt;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f7f6;
            color: #000;
        }

        .print-logo {
            width: 65px;
            height: 65px;
            object-fit: contain;
        }

        .report-header {
            display: flex;
            justify-content: center;
            align-items: center;
            border-bottom: 3px double #2d5a27;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .header-logo-container {
            display: flex;
            gap: 5px;
            margin-right: 1px;
        }

        .header-text {
            text-align: LEFT;
        }

        .registrar-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            border: 1px solid black;
            table-layout: fixed;
        }

        .registrar-table td {
            border: 1px solid black;
            padding: 2px 5px;
            vertical-align: top;
        }

        .label-cell {
            color: #555;
            font-size: 6pt;
            text-transform: uppercase;
            font-weight: bold;
        }

        .value-cell {
            font-weight: bold;
            text-transform: uppercase;
            display: block;
            font-size: 8pt;
        }

        .grading-header {
            background-color: #1a5fb4 !important;
            color: white !important;
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }

        .admin-table th {
            padding: 8px;
            background-color: #2d5a27 !important;
            color: white !important;
            border: 1px solid #000;
            font-size: 8pt;
            text-transform: uppercase;
        }

        .admin-table td {
            padding: 3px 5px;
            border: 1px solid #000;
            font-size: 8.5pt;
        }

        @media print {

            .no-print,
            nav,
            .navbar {
                display: none !important;
            }

            body {
                background: white;
                padding: 0;
            }

            .container {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
            }

            .sheet-print {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
            }
        }
    </style>
</head>

<body>

    <div class="container" style="max-width: 1050px; margin: 20px auto;">
        <div class="form-card-modern no-print" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd;">
            <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) auto; gap: 15px; align-items: end;">
                <div>
                    <label style="font-size: 8pt; font-weight: bold;">PROGRAM</label>
                    <select name="program" id="program_select" class="form-control" required onchange="updateDropdowns()">
                        <option value="">-- Select Program --</option>
                        <?php foreach ($programs_list as $p): ?>
                            <option value="<?= $p['program'] ?>" <?= (isset($_GET['program']) && $_GET['program'] == $p['program']) ? 'selected' : '' ?>>
                                <?= $p['program'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size: 8pt; font-weight: bold;">SECTION</label>
                    <select name="section" id="section_select" class="form-control" required></select>
                </div>
                <div>
                    <label style="font-size: 8pt; font-weight: bold;">SUBJECT</label>
                    <select name="subject" id="subject_select" class="form-control" required></select>
                </div>
                <button type="submit" name="btn_generate" class="login-btn" style="background:#2d5a27; height: 40px; color:white; border:none; border-radius:4px; padding: 0 20px;">
                    <i class="fas fa-file-invoice"></i> GENERATE
                </button>
            </form>
        </div>

        <?php if ($report_data): ?>
            <div class="form-card-modern sheet-print" style="background: white; padding: 20px; border-radius: 2px; box-shadow: 0 0 20px rgba(0,0,0,0.1); position: relative;">
                <div style="position: absolute; top: 5px; right: 20px; font-size: 7pt; font-weight: bold; text-align: right;">
                    CDL Registrar Form No. ______ <br>
                    Date Generated: <?= date("F d, Y g:i A") ?>
                </div>
                <div class="report-header">
                    <div class="header-logo-container">
                        <img src="../assets/img/Laurel_seal.png" class="print-logo" alt="Seal">
                        <img src="../assets/img/CDL_seal.png" class="print-logo" alt="Seal">
                    </div>
                    <div class="header-text">
                        <p style="margin:0; font-size: 10pt;">Municipality of Laurel</p>
                        <h1 style="margin:0; color: #2d5a27; font-size: 16pt;">COLEGIO DE LAUREL</h1>
                        <p style="margin:0; font-size: 8pt;">Laurel Academic and Sports Complex, Barangay As-Is, Laurel, Batangas</p>
                        <h2 style="margin:0; font-size: 12pt;">OFFICE OF THE REGISTRAR</h2>
                        <h3 style="margin:0; font-size: 10pt;">REPORT OF GRADES</h3>
                    </div>
                </div>

                <table class="registrar-table">
                    <tr>
                        <td style="width: 20%;">
                            <div class="label-cell">Semester</div>
                            <div class="value-cell"><?= $display_sem ?></div>
                        </td>
                        <td style="width: 20%;">
                            <div class="label-cell">School Year</div>
                            <div class="value-cell"><?= $display_sy ?></div>
                        </td>
                        <td>
                            <div class="label-cell">College/Program</div>
                            <div class="value-cell"><?= $_GET['program'] ?></div>
                        </td>
                        <td rowspan="4" style="width: 35%; padding: 0;">
                            <div class="grading-header">GRADING SYSTEM</div>
                            <div style="display: flex; font-size: 8pt; padding: 5px;">
                                <div style="flex: 1;"><strong>1.00</strong>: 98-100<br><strong>1.25</strong>: 95-97<br><strong>1.50</strong>: 92-94<br><strong>1.75</strong>: 89-91<br><strong>2.00</strong>: 86-88<br><strong>* DO/DU</strong>: Dropped</div>
                                <div style="flex: 1;"><strong>2.25</strong>: 83-85<br><strong>2.50</strong>: 79-82<br><strong>2.75</strong>: 76-78<br><strong>3.00</strong>: 75<br><strong>5.00</strong>: Below 75<br><strong>* INC</strong>: Incomplete</div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label-cell">Course Code</div>
                            <div class="value-cell"><?= $course_info['course_code'] ?></div>
                        </td>
                        <td colspan="2">
                            <div class="label-cell">Course Title</div>
                            <div class="value-cell"><?= $course_info['course_title'] ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label-cell">Section</div>
                            <div class="value-cell"><?= $_GET['section'] ?></div>
                        </td>
                        <td>
                            <div class="label-cell">Schedule</div>
                            <div class="value-cell"><?= $display_days ?></div>
                        </td>
                        <td>
                            <div class="label-cell">Time</div>
                            <div class="value-cell"><?= $display_time ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label-cell">Room</div>
                            <div class="value-cell"><?= $sched_info['room_name'] ?? 'TBA' ?></div>
                        </td>
                        <td colspan="2">
                            <div class="label-cell">Instructor</div>
                            <div class="value-cell"><?= $sched_info['instructor_full_name'] ?? 'UNASSIGNED' ?></div>
                        </td>
                    </tr>
                </table>

                <table class="admin-table" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="12%">Student ID</th>
                            <th width="18%">Last Name</th>
                            <th width="18%">First Name</th>
                            <th width="12%">Middle Name</th>
                            <th width="10%">Grade</th>
                            <th width="10%">Point Eq.</th>
                            <th width="15%">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num = 1;
                        while ($row = $report_data->fetch_assoc()): ?>
                            <tr>
                                <td style="text-align:center;"><?= $row_num++ ?></td>
                                <td style="text-align:center;"><?= $row['student_id'] ?></td>
                                <td><?= strtoupper($row['lastname']) ?></td>
                                <td><?= strtoupper($row['firstname']) ?></td>
                                <td><?= strtoupper($row['middlename'] ?? '') ?></td>
                                <td style="text-align:center; font-weight:bold;"><?= ($row['temp_final_grade'] !== null) ? number_format((float)$row['temp_final_grade'], 2) : '---' ?></td>
                                <td style="text-align:center; font-weight:bold;"><?= $row['final_grade'] ?? '---' ?></td>
                                <td style="text-align:center; font-weight:bold;"><?= strtoupper($row['remarks'] ?? '--') ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <p style="text-align:center; font-size:8pt;">*** NOTHING FOLLOWS ***</p>

                <table style="width: 100%; border-collapse: collapse; table-layout: fixed; border: none;">
                    <tr>
                        <td style="padding: 20px 10px; vertical-align: top;">
                            <div style="font-weight: bold; font-size: 9pt;">SUBMITTED BY:</div>
                            <div style="text-align: center; margin-top: 25px;">
                                <span style="font-weight: bold; font-size: 10pt;">
                                    <?= strtoupper($sched_info['instructor_full_name'] ?? 'UNASSIGNED') ?>
                                </span><br>
                                <span style="font-size: 9pt;">Instructor / Date: ____________________</span>
                            </div>
                        </td>
                        <td style="padding: 20px 10px; vertical-align: top;">
                            <div style="font-weight: bold; font-size: 9pt;">NOTED :</div>
                            <div style="text-align: center; margin-top: 25px;">
                                <span style="font-weight: bold; font-size: 10pt;">ATTY. FERMO G. RAMOS, PhD</span><br>
                                <span style="font-size: 9pt;">Vice President for Academic Affairs / Date: ____________________</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 10px; vertical-align: top;">
                            <div style="font-weight: bold; font-size: 9pt;">VERIFIED BY:</div>
                            <div style="text-align: center; margin-top: 25px;">
                                <span style="font-weight: bold; font-size: 10pt;">ATTY. FERMO G. RAMOS, PhD</span><br>
                                <span style="font-size: 9pt;">Chairperson / Date: ____________________</span>
                            </div>
                        </td>
                        <td style="padding: 20px 10px; vertical-align: top;">
                            <div style="font-weight: bold; font-size: 9pt;">SUBMITTED TO:</div>
                            <div style="text-align: center; margin-top: 25px;">
                                <span style="font-weight: bold; font-size: 10pt;">STEPHEN ANDREW DE CASTRO</span><br>
                                <span style="font-size: 9pt;">Acting Assistant Registrar</span>
                            </div>
                        </td>
                    </tr>
                </table>

                <button onclick="window.print()" class="no-print" style="display:block; margin:20px auto; padding:12px 30px; background:#2d5a27; color:white; border:none; border-radius:5px; cursor:pointer;">
                    <i class="fas fa-print"></i> PRINT REPORT
                </button>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const masterSections = <?= json_encode($sections_master); ?>;
        const masterCourses = <?= json_encode($courses_master); ?>;

        function updateDropdowns() {
            const prog = document.getElementById('program_select').value;
            const sectD = document.getElementById('section_select');
            const subjD = document.getElementById('subject_select');
            const curSect = "<?= $_GET['section'] ?? '' ?>";
            const curSubj = "<?= $_GET['subject'] ?? '' ?>";

            sectD.innerHTML = '<option value="">-- Select Section --</option>';
            subjD.innerHTML = '<option value="">-- Select Subject --</option>';

            if (!prog) return;

            masterSections.filter(s => s.program === prog).forEach(s => {
                let opt = new Option(s.section, s.section);
                if (s.section === curSect) opt.selected = true;
                sectD.add(opt);
            });

            masterCourses.filter(c => c.program_code === prog).forEach(c => {
                let opt = new Option(c.course_code + " - " + c.course_title, c.course_id);
                if (c.course_id == curSubj) opt.selected = true;
                subjD.add(opt);
            });
        }
        window.onload = updateDropdowns;
    </script>
</body>

</html>