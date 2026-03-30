<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// 1. Get Filter Values
$filter_program  = isset($_GET['program']) ? $_GET['program'] : 'ALL';
$filter_semester = isset($_GET['semester']) ? $_GET['semester'] : 'ALL';
$filter_year     = isset($_GET['year_level']) ? $_GET['year_level'] : 'ALL'; // New Filter

// 2. Query Logic
$where_clauses = [];
if ($filter_program !== 'ALL') {
    $where_clauses[] = "pc.program_code = '" . mysqli_real_escape_string($conn, $filter_program) . "'";
}
if ($filter_semester !== 'ALL') {
    $where_clauses[] = "pc.semester = '" . mysqli_real_escape_string($conn, $filter_semester) . "'";
}
if ($filter_year !== 'ALL') {
    $where_clauses[] = "pc.year_level = '" . mysqli_real_escape_string($conn, $filter_year) . "'";
}

$where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

$sql = "SELECT c.*, pc.id AS curriculum_id, pc.year_level, pc.semester, pc.program_code, p.program_name,
        (SELECT GROUP_CONCAT(pre_c.course_code SEPARATOR ', ') FROM course_prerequisites cp 
         JOIN courses pre_c ON cp.prerequisite_course_id = pre_c.course_id WHERE cp.course_id = c.course_id) AS pre_requisite_list
        FROM courses c
        JOIN program_curriculum pc ON c.course_id = pc.course_id
        LEFT JOIN programs p ON pc.program_code = p.program_code
        $where_sql
        ORDER BY pc.program_code ASC, pc.year_level ASC, FIELD(pc.semester, '1st Semester', '2nd Semester', 'Summer') ASC, c.course_code ASC";

$result = $conn->query($sql);
$curriculum = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $curriculum[$row['program_code']]['name'] = $row['program_name'];
        $curriculum[$row['program_code']]['years'][$row['year_level']][$row['semester']][] = $row;
    }
}

// Fetch programs for dropdown
$programs_res = $conn->query("SELECT program_code FROM programs ORDER BY program_code ASC");
$all_programs = [];
if ($programs_res) {
    while ($p = $programs_res->fetch_assoc()) {
        $all_programs[] = $p;
    }
}

// Fetch distinct Year Levels for the dropdown
$years_res = $conn->query("SELECT DISTINCT year_level FROM program_curriculum ORDER BY year_level ASC");
$all_years = [];
if ($years_res) {
    while ($y = $years_res->fetch_assoc()) {
        $all_years[] = $y['year_level'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Curriculum | CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        :root {
            --cdl-green: #2d5a27;
            --cdl-dark: #1e3d1a;
        }

        body {
            background-color: #f0f2f5;
            font-size: 0.82rem;
            color: #333;
        }

        .main-content {
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Styles preserved from your previous version */
        .table-tight {
            margin-bottom: 20px;
            background: #fff;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .table-tight thead th {
            background: #f8f9fa;
            padding: 5px 10px;
            font-size: 0.72rem;
            color: #666;
            border-bottom: 1px solid #dee2e6;
        }

        .table-tight tbody td {
            padding: 5px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f1f1;
        }

        .prog-title {
            background: var(--cdl-dark);
            color: #fff;
            padding: 6px 12px;
            font-weight: bold;
            border-radius: 4px 4px 0 0;
        }

        .combined-header {
            background: var(--cdl-green);
            color: white;
            padding: 4px 12px;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        .unit-badge {
            background: #eef5ee;
            color: var(--cdl-green);
            padding: 1px 5px;
            border-radius: 3px;
            font-weight: bold;
            border: 1px solid #d4e2d4;
        }

        .course-code {
            font-weight: 700;
            color: var(--cdl-green);
            width: 85px;
        }

        .filter-section {
            background: #fff;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
            margin-bottom: 15px;
        }

        /* Select2 Fixes */
        .select2-container--default .select2-selection--single {
            height: 31px !important;
            border: 1px solid #ced4da !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #333 !important;
            line-height: 31px !important;
            padding-left: 10px !important;
            font-size: 0.85rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 30px !important;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="main-content">
        <!--   <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="font-weight-bold mb-0 text-dark">Curriculum Management</h5>
            <a href="add_course.php" class="btn btn-success btn-sm shadow-sm"><i class="fas fa-plus mr-1"></i> Add Course</a>
        </div>
        -->
        <div class="filter-section shadow-sm">
            <form method="GET" class="form-row align-items-end">
                <div class="col-md-3">
                    <label class="small font-weight-bold text-muted mb-1">PROGRAM</label>
                    <select name="program" class="form-control form-control-sm select2">
                        <option value="ALL">All Programs</option>
                        <?php foreach ($all_programs as $p): ?>
                            <option value="<?= $p['program_code'] ?>" <?= $filter_program == $p['program_code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['program_code']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="small font-weight-bold text-muted mb-1">YEAR LEVEL</label>
                    <select name="year_level" class="form-control form-control-sm select2">
                        <option value="ALL">All Years</option>
                        <?php foreach ($all_years as $year_val): ?>
                            <option value="<?= $year_val ?>" <?= $filter_year == $year_val ? 'selected' : '' ?>>
                                Year <?= $year_val ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="small font-weight-bold text-muted mb-1">SEMESTER</label>
                    <select name="semester" class="form-control form-control-sm select2">
                        <option value="ALL">All Semesters</option>
                        <option value="1st Semester" <?= $filter_semester == '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
                        <option value="2nd Semester" <?= $filter_semester == '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-dark btn-sm btn-block" style="height: 31px;">Apply Filters</button>
                </div>
            </form>
        </div>

        <?php if (empty($curriculum)): ?>
            <div class="alert alert-light text-center border small">No records found.</div>
        <?php endif; ?>

        <?php foreach ($curriculum as $prog_code => $prog_data): ?>
            <div class="prog-title small shadow-sm"><?= $prog_code ?> — <?= htmlspecialchars($prog_data['name']) ?></div>
            <?php foreach ($prog_data['years'] as $year => $semesters): ?>
                <?php foreach ($semesters as $sem => $courses): ?>
                    <div class="combined-header">YEAR <?= $year ?> • <?= strtoupper($sem) ?></div>
                    <div class="table-responsive table-tight shadow-sm">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 15%">Code</th>
                                    <th style="width: 45%">Course Title</th>
                                    <th class="text-center">Lec</th>
                                    <th class="text-center">Lab</th>
                                    <th style="width: 20%">Pre-requisites</th>
                                    <th class="text-center">Edit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $s_lec = 0;
                                $s_lab = 0;
                                foreach ($courses as $row):
                                    $s_lec += (int)$row['lec_units'];
                                    $s_lab += (int)$row['lab_units']; ?>
                                    <tr>
                                        <td class="course-code"><?= $row['course_code'] ?></td>
                                        <td class="text-dark font-weight-500"><?= $row['course_title'] ?></td>
                                        <td class="text-center"><?= $row['lec_units'] ?></td>
                                        <td class="text-center"><?= $row['lab_units'] ?></td>
                                        <td class="text-muted" style="font-size: 0.75rem;"><?= $row['pre_requisite_list'] ?: '—' ?></td>
                                        <td class="text-center">
                                            <a href="edit_course.php?id=<?= $row['course_id'] ?>" class="btn btn-link btn-sm p-0 text-success">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-light">
                                <tr class="small font-weight-bold">
                                    <td colspan="2" class="text-right text-muted">Sub-total Units:</td>
                                    <td class="text-center"><?= $s_lec ?></td>
                                    <td class="text-center"><?= $s_lab ?></td>
                                    <td colspan="2" class="text-center"><span class="unit-badge"><?= ($s_lec + $s_lab) ?> Total</span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.select2').select2({
                width: '100%'
            });
        });
    </script>
</body>

</html>