<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';


if ($_SESSION['level'] !== 'STUDENT') {
    header("Location: ../dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. Get the Student's profile
$user_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$u_data = $user_stmt->get_result()->fetch_assoc();
$email = $u_data['email'];

$stmt = $conn->prepare("SELECT * FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    die("Student profile not found. Please contact the Registrar.");
}

$sid = $student['student_id'];

// 2. Fetch grades using: SELECT * FROM grades WHERE student_id = ?
$query = "SELECT g.final_grade, g.remarks, g.semester, g.academic_year, 
                 c.course_code, c.course_title 
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
    // USE DIRECT DB VALUE: "1st Semester" or "2nd Semester"
    $sem_label = trim($row['semester']);

    // Unique key to force separate tables per semester
    $table_key = $row['academic_year'] . " | " . $sem_label;
    $history[$table_key][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>My Academic Records</title>
    <style>
        .sem-block {
            background: white;
            padding: 20px;
            border: 1px solid #ddd;
            border-top: 4px solid #2d5a27;
            margin-bottom: 35px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .table-header-info {
            background: #f8f9fa;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            margin: -20px -20px 15px -20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .grade-table {
            width: 100%;
            border-collapse: collapse;
        }

        .grade-table th {
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #2d5a27;
            font-size: 0.85rem;
            color: #fafcfa;
            text-transform: uppercase;
        }

        .grade-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
        }

        .badge-passed {
            color: #2d5a27;
            font-weight: bold;
            background: #e8f5e9;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .badge-failed {
            color: #c0392b;
            font-weight: bold;
            background: #fdedec;
            padding: 4px 8px;
            border-radius: 4px;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            .sem-block {
                box-shadow: none;
                border: 1px solid #000;
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body class="bg-light">
   <?php include_once '../admin/navbar.php'; ?>
    <div class="container" style="max-width: 1000px; margin: 40px auto; padding: 0 20px;">
        <div class="no-print" style="margin-bottom: 30px;">
            <a href="student_dashboard.php" style="text-decoration: none; color: #2d5a27;"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <h2 style="color: #333; margin-top: 10px;">Academic Transcript</h2>
            <p>Official Records for Student ID: <strong><?= htmlspecialchars($sid) ?></strong></p>
        </div>

        <?php if (empty($history)): ?>
            <div style="background: white; padding: 40px; text-align: center; border-radius: 8px;">
                <p>No academic records found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($history as $table_title => $grades):
                list($year, $sem) = explode(" | ", $table_title);
            ?>
                <div class="sem-block">
                    <div class="table-header-info">
                        <span style="font-weight: bold; color: #2d5a27;"><i class="fas fa-calendar-alt"></i> AY: <?= htmlspecialchars($year) ?></span>
                        <span style="font-weight: bold; color: #555;"><?= htmlspecialchars($sem) ?></span>
                    </div>

                    <table class="grade-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Course Code</th>
                                <th style="width: 50%;">Course Title</th>
                                <th style="text-align: center;">Point Eq.</th>
                                <th style="text-align: center;">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grades as $g): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($g['course_code']) ?></strong></td>
                                    <td><?= htmlspecialchars($g['course_title']) ?></td>
                                    <td style="text-align: center; font-weight: bold; color: #1a5fb4;">
                                        <?= htmlspecialchars($g['final_grade']) ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php
                                        $rem = strtoupper($g['remarks']);
                                        $badge = ($rem == 'PASSED') ? 'badge-passed' : 'badge-failed';
                                        ?>
                                        <span class="<?= $badge ?>"><?= htmlspecialchars($rem) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="no-print" style="text-align: center; margin-top: 30px; margin-bottom: 50px;">
            <button onclick="window.print()" style="padding: 12px 30px; background: #2d5a27; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: bold;">
                <i class="fas fa-print"></i> PRINT TRANSCRIPT
            </button>
        </div>
    </div>
</body>

</html>