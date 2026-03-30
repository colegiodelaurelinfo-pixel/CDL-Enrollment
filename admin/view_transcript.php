<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Access Control
$allowed_roles = ['ADMINISTRATOR', 'REGISTRAR'];
if (!isset($_SESSION['level']) || !in_array($_SESSION['level'], $allowed_roles)) {
    header("Location: ../dashboard.php?error=unauthorized");
    exit();
}

if (!isset($_GET['student_id'])) {
    die("Error: Student ID is required.");
}
$sid = $_GET['student_id'];

// 1. Fetch Student Info (JOINed with programs table to get program_name)
$s_stmt = $conn->prepare("SELECT s.*, p.program_name 
                          FROM students s 
                          LEFT JOIN programs p ON s.program = p.program_code 
                          WHERE s.student_id = ?");
$s_stmt->bind_param("s", $sid);
$s_stmt->execute();
$student = $s_stmt->get_result()->fetch_assoc();

if (!$student) {
    die("Student not found.");
}

// 2. Fetch Grades with Units Calculation (Lec + Lab)
$query = "SELECT g.final_grade, g.remarks, g.semester, g.academic_year, 
                 c.course_code, c.course_title, 
                 (c.lec_units + c.lab_units) AS total_units 
          FROM grades g 
          LEFT JOIN courses c ON g.course_id = c.course_id 
          WHERE g.student_id = ? 
          ORDER BY g.academic_year ASC, g.semester ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $sid);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $sem = trim($row['semester']);
    $year = trim($row['academic_year']);

    // Remove the comma and use <br> for a new line
    $label = strtoupper($year . ", " . str_replace(" Semester", "", $sem));
    $history[$label][] = $row;
}

// Get generation time
$generated_at = date("F d, Y h:i A");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Transcript - <?= htmlspecialchars($sid) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 11px;
            margin: 0;
            background: #525659;
            color: #000;
        }

        .page {
            width: 8.5in;
            min-height: 11in;
            padding: 0.5in;
            margin: 20px auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            box-sizing: border-box;
            position: relative;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #000;
            margin-bottom: 20px;
        }

        .section-title {
            background: #000;
            color: #fff;
            padding: 4px 10px;
            font-weight: bold;
            margin: 15px 0 5px 0;
            font-size: 12px;
            text-transform: uppercase;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .data-table td {
            border: 1px solid #ccc;
            padding: 6px;
            vertical-align: top;
        }

        .record-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }

        .record-table th {
            border: 1px solid #000;
            background: #f2f2f2;
            padding: 8px;
            font-size: 10px;
            text-transform: uppercase;
        }

        .record-table td {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            padding: 3px 10px;
            line-height: .8;
            vertical-align: top;
        }

        /* SEAL IMAGE CSS */
        .seal-img {
            max-height: 70px;
            width: auto;
            object-fit: contain;
        }

        /* ACTION BUTTONS */
        .action-buttons {
            position: fixed;
            top: 20px;
            left: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }

        .btn-print {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 15px 25px;
            background: #2d5a27;
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: bold;
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back {
            padding: 10px 20px;
            background: #444;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .btn-back:hover {
            background: #222;
        }

        /* Footer styling for page numbers */
        .footer-info {
            position: absolute;
            bottom: 0.4in;
            left: 0.5in;
            right: 0.5in;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #666;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }

        @media print {

            .btn-print,
            .action-buttons,
            .noprint {
                display: none !important;
            }

            body {
                background: none;
            }

            .page {
                margin: 0;
                box-shadow: none;
                width: 100%;
                height: 11in;
            }
        }
    </style>
</head>

<body>
    <div class="action-buttons noprint">
        <a href="manage_students.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> BACK
        </a>
    </div>

    <button class="btn-print noprint" onclick="window.print()">
        <i class="fas fa-print"></i> PRINT OFFICIAL TRANSCRIPT
    </button>

    <div class="page">
        <table class="header-table">
            <tr>
                <td style="vertical-align: bottom;">
                    <div style="font-size: 20px; font-weight: bold;">Official Transcript of Records</div>
                    <div style="font-size: 12px;">Office of the Registrar</div>
                    <div style="font-weight: bold; font-size: 14px; margin-top: 5px; text-transform: uppercase;">COLEGIO DE LAUREL</div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <img src="../assets/img/CDL_seal.png" alt="School Seal" class="seal-img">
                </td>
            </tr>
        </table>

        <div class="section-title">PERSONAL DATA</div>
        <table class="data-table">
            <tr>
                <td style="width: 15%; font-weight: bold;">NAME:<br>DATE OF BIRTH:<br>ADDRESS:</td>
                <td style="width: 40%;"><?= htmlspecialchars(strtoupper($student['lastname'] . ' ' . $student['extension'] . ', ' . $student['firstname'] . ' ' . $student['middlename'])) ?><br>
                    <?= htmlspecialchars(strtoupper($student['birthdate'])) ?><br>
                    <?= htmlspecialchars(strtoupper($student['house_no_street'] . ' ' . $student['barangay'] . ', ' . $student['city'] . ', ' . $student['province'])) ?><br>
                </td>
                <td style="width: 15%; font-weight: bold;">STUDENT NO:<br>BIRTHPLACE:</td>
                <td style="width: 30%;"><?= htmlspecialchars($sid) ?><br>
                    <?= htmlspecialchars($student['birthplace']) ?>
                </td>
            </tr>
        </table>

        <div class="section-title">SCHOLASTIC DATA</div>
        <table class="data-table">
            <tr>
                <td style="width: 15%; font-weight: bold;">TERM ADMITTED:<br>PROGRAM:</td>
                <td style="width: 40%;"><?= htmlspecialchars(strtoupper("SY: 2024-2025, 1st Semester")) ?><br>
                    <?= htmlspecialchars(strtoupper($student['program_name'])) ?>
                </td>
                <td style="width: 15%; font-weight: bold;">SCHOOL LAST ATTENDED:<br>PROGRAM:</td>
                <td style="width: 30%;"><?= htmlspecialchars($student['last_school_attended']) ?><br><br>
                    SENIOR HIGH GRADUATE
                </td>
            </tr>
        </table>

        <div class="section-title">ACADEMIC RECORD</div>
        <table class="record-table">
            <thead>
                <tr>
                    <th style="width: 15%;">SCHOOL YEAR & TERM</th>
                    <th style="width: 15%;">SUBJECT CODE</th>
                    <th style="width: 40%;">DESCRIPTIVE TITLE</th>
                    <th style="width: 15%;">FINAL GRADE</th>
                    <th style="width: 15%;">CREDITS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $label => $grades): ?>
                    <?php $first = true; ?>
                    <?php foreach ($grades as $g): ?>
                        <tr>
                            <td style="text-align: center; font-weight: bold; vertical-align: top; padding-top: 10px; width: 100px;">
                                <?= $first ? $label : '' ?>
                            </td>
                            <td><?= htmlspecialchars($g['course_code']) ?></td>
                            <td><?= htmlspecialchars($g['course_title']) ?></td>
                            <td style="text-align: center; font-weight: bold;"><?= number_format($g['final_grade'], 2) ?></td>
                            <td style="text-align: center;"><?= number_format($g['total_units']) ?> UNITS</td>
                        </tr>
                        <?php $first = false; ?>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="5" style="border-bottom: 1px solid #000; height: 1px; padding: 0;"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 60px;">
            <table style="width: 100%;">
                <tr>
                    <td style="font-style: italic; vertical-align: bottom;">Transcript is NOT valid without school seal and Registrar's signature.</td>
                    <td style="text-align: center; width: 250px;">
                        <div style="border-bottom: 1px solid #000; font-weight: bold; padding-bottom: 5px;">
                            STEPHEN ANDREW DE CASTRO
                        </div>
                        <div style="margin-top: 5px;">School Registrar</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer-info">
            <div>Date Generated: <?= $generated_at ?></div>
            <div>Page 1 of 1</div>
        </div>
    </div>

</body>

</html>