<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

$sid = $_GET['id'] ?? '';

// 1. Fetch Student Basic Info
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("s", $sid);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    die("Student not found.");
}

// 2. Fetch All Grades, joined with Course Titles
$query = "SELECT g.*, c.course_title, c.units 
          FROM grades g 
          JOIN courses c ON g.course_id = c.course_code 
          WHERE g.student_id = ? 
          ORDER BY g.academic_year ASC, g.semester ASC";
$g_stmt = $conn->prepare($query);
$g_stmt->bind_param("s", $sid);
$g_stmt->execute();
$grades_res = $g_stmt->get_result();

// 3. Group grades by Year and Semester in a PHP Array
$history = [];
while ($row = $grades_res->fetch_assoc()) {
    $term = $row['academic_year'] . " | " . $row['semester'];
    $history[$term][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Academic Record | <?= $sid ?></title>
    <link rel="icon" type="image/png" href="../assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="../assets/img/CDL_seal.png">
</head>

<body style="background: #f4f7f6;">
    <?php include 'navbar.php'; ?>

    <div class="container" style="margin-top: 30px;">
        <div class="form-card-modern" style="border-left: 10px solid #2d5a27;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h2 style="margin:0; color: #2d5a27;"><?= strtoupper($student['lastname'] . ", " . $student['firstname']) ?></h2>
                    <p style="margin: 5px 0; color: #666;">
                        <i class="fas fa-id-badge"></i> <?= $student['student_id'] ?> |
                        <i class="fas fa-graduation-cap"></i> <?= $student['program'] ?> - <?= $student['year_level'] ?> Year
                    </p>
                </div>
                <button onclick="window.print()" class="btn-outline"><i class="fas fa-print"></i> Print TOR</button>
            </div>
        </div>

        <?php if (empty($history)): ?>
            <div class="form-card-modern" style="text-align: center; padding: 50px;">
                <i class="fas fa-folder-open" style="font-size: 3rem; color: #ccc;"></i>
                <p>No grade records found for this student.</p>
            </div>
        <?php else: ?>
            <?php foreach ($history as $term => $rows): ?>
                <div class="form-card-modern" style="margin-top: 20px; padding: 0; overflow: hidden;">
                    <div style="background: #2d5a27; color: white; padding: 10px 20px; font-weight: bold;">
                        <i class="fas fa-calendar-check"></i> <?= $term ?>
                    </div>
                    <table class="admin-table" style="margin: 0;">
                        <thead>
                            <tr style="background: #f9f9f9;">
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th class="text-center">Units</th>
                                <th class="text-center">Final Grade</th>
                                <th class="text-center">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_grade = 0;
                            $count = 0;
                            foreach ($rows as $g):
                                $total_grade += $g['final_grade'];
                                $count++;
                                $remarks = ($g['final_grade'] <= 3.0) ? "PASSED" : "FAILED";
                                $remark_color = ($remarks == "PASSED") ? "#2d5a27" : "#c62828";
                            ?>
                                <tr>
                                    <td><?= $g['course_id'] ?></td>
                                    <td><?= $g['course_title'] ?></td>
                                    <td class="text-center"><?= $g['units'] ?></td>
                                    <td class="text-center" style="font-weight: bold;"><?= number_format($g['final_grade'], 2) ?></td>
                                    <td class="text-center" style="font-weight: bold; color: <?= $remark_color ?>;"><?= $remarks ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f0f7f0;">
                                <td colspan="3" style="text-align: right; font-weight: bold;">TERM GPA:</td>
                                <td class="text-center" style="font-weight: bold; font-size: 1.1rem;">
                                    <?= number_format($total_grade / $count, 2) ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>

</html>