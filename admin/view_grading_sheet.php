<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

if (!in_array($_SESSION['level'], ['ADMINISTRATOR', 'REGISTRAR'])) {
    header("Location: ../login.php?msg=denied");
    exit();
}

// 1. Fetch Active Settings
$settings_res = $conn->query("SELECT * FROM system_settings");
$sys = [];
while ($r = $settings_res->fetch_assoc()) {
    $sys[$r['setting_key']] = $r['setting_value'];
}
$active_sy = $sys['active_sy'] ?? '';
$active_semester = $sys['active_semester'] ?? '';

// 2. Handle Filters
$selected_section = $_GET['section_id'] ?? '';
$selected_course = $_GET['course_id'] ?? '';

// 3. Fetch Data for Filters
$sections_res = $conn->query("SELECT id, section_name FROM sections ORDER BY section_name ASC");
$courses_res  = $conn->query("SELECT course_id, course_code, course_title FROM courses ORDER BY course_code ASC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <title>View Grading Sheet | CDL</title>
    <style>
        #gradeTable {
            font-size: 0.85rem;
            width: 100%;
            background: white;
            border-collapse: collapse !important;
        }

        .grade-pass {
            color: #2d5a27;
            font-weight: bold;
        }

        .grade-fail {
            color: #c53030;
            font-weight: bold;
        }

        .grade-special {
            color: #718096;
            font-style: italic;
        }

        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .stat-box {
            background: #f8fafc;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .stat-val {
            display: block;
            font-size: 1.2rem;
            font-weight: bold;
            color: #2d3748;
        }

        .stat-lbl {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #718096;
        }

        @media print {

            .no-print,
            .navbar,
            .dataTables_filter,
            .dataTables_length,
            .dataTables_paginate {
                display: none !important;
            }

            .container {
                width: 100%;
                max-width: 100%;
                margin: 0;
                padding: 0;
            }

            body {
                background: white;
                padding: 10px;
            }
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="dashboard-header-card no-print" style="padding: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin:0;"><i class="fas fa-file-invoice"></i> Official Grading Sheet</h3>
                <small>Term: <strong><?= $active_sy ?> | <?= $active_semester ?></strong></small>
            </div>
            <button onclick="window.print()" class="login-btn" style="width: auto; background: #2d3748;">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>

        <div class="filter-card no-print">
            <form method="GET" style="display: grid; grid-template-columns: 1fr 2fr auto; gap: 15px; align-items: center;">
                <div class="input-group">
                    <label class="small font-weight-bold">Section</label>
                    <select name="section_id" class="form-control form-control-sm" required>
                        <option value="">-- Select --</option>
                        <?php
                        $sections_res->data_seek(0);
                        while ($r = $sections_res->fetch_assoc()): ?>
                            <option value="<?= $r['id'] ?>" <?= $selected_section == $r['id'] ? 'selected' : '' ?>><?= $r['section_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label class="small font-weight-bold">Course</label>
                    <select name="course_id" class="form-control form-control-sm" required>
                        <option value="">-- Select --</option>
                        <?php
                        $courses_res->data_seek(0);
                        while ($r = $courses_res->fetch_assoc()): ?>
                            <option value="<?= $r['course_id'] ?>" <?= $selected_course == $r['course_id'] ? 'selected' : '' ?>><?= $r['course_code'] ?> - <?= $r['course_title'] ?></option>
                        <?php endwhile; ?>
                    </select>

                </div>
                    <button type="submit" class="login-btn" style="width: auto;">Load Sheet</button>
            </form>
        </div>

        <?php if ($selected_section && $selected_course):
            $sql = "SELECT g.*, u.lastname, u.firstname, u.id as student_no
                    FROM grades g
                    JOIN users u ON g.student_id = u.id
                    WHERE g.course_id = ? AND g.academic_year = ? AND g.semester = ?
                    ORDER BY u.lastname ASC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $selected_course, $active_sy, $active_semester);
            $stmt->execute();
            $res = $stmt->get_result();

            $total = 0;
            $passed = 0;
            $failed = 0;
            $inc = 0;
            $rows = [];
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
                $total++;
                if ($row['remarks'] == 'PASSED') $passed++;
                elseif ($row['remarks'] == 'FAILED') $failed++;
                elseif ($row['remarks'] == 'INC') $inc++;
            }
        ?>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                <div class="stat-box"><span class="stat-val"><?= $total ?></span><span class="stat-lbl">Total Students</span></div>
                <div class="stat-box" style="border-bottom: 3px solid #48bb78;"><span class="stat-val"><?= $passed ?></span><span class="stat-lbl">Passed</span></div>
                <div class="stat-box" style="border-bottom: 3px solid #f56565;"><span class="stat-val"><?= $failed ?></span><span class="stat-lbl">Failed</span></div>
                <div class="stat-box" style="border-bottom: 3px solid #a0aec0;"><span class="stat-val"><?= $inc ?></span><span class="stat-lbl">Incomplete</span></div>
            </div>

            <div class="table-responsive" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <table id="gradeTable" class="display">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student Name</th>
                            <th>Grade</th>
                            <th>Point Equiv.</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total > 0): foreach ($rows as $row):
                                $rem = $row['remarks'];
                                $r_class = ($rem == 'PASSED') ? 'grade-pass' : (($rem == 'FAILED') ? 'grade-fail' : 'grade-special');
                        ?>
                                <tr>
                                    <td><?= $row['student_no'] ?></td>
                                    <td><?= strtoupper($row['lastname']." ". $row['extension']. ", " . $row['firstname'] ." ". $row['middlename']) ?></td>
                                    <td><?= number_format($row['temp_final_grade']) ?></td>
                                    <td><strong><?= number_format($row['final_grade'], 2) ?></strong></td>
                                    <td class="<?= $r_class ?>"><?= $rem ?></td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            // Check if table exists before initializing to avoid errors
            if ($('#gradeTable').length > 0) {
                $('#gradeTable').DataTable({
                    "paging": false,
                    "info": true,
                    "searching": true,
                    "language": {
                        "emptyTable": "No grades recorded for this selection"
                    }
                });
            }
        });
    </script>
</body>

</html>