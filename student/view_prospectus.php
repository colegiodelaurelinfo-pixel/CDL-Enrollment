<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';


// Force redirect if NOT logged in
if (!isset($_SESSION['level'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_level = $_SESSION['level'];

// LOGIC: Automatically get program if student, allow GET if admin
if ($user_level === 'STUDENT') {
    $stmt = $conn->prepare("SELECT program FROM students WHERE email = (SELECT email FROM users WHERE id = ?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $program = $res['program'] ?? '';
} else {
    $program = isset($_GET['program']) ? mysqli_real_escape_string($conn, $_GET['program']) : '';
}

if (empty($program)) {
    die("<div class='container mt-5 alert alert-danger'>Program not found. Please contact the Registrar.</div>");
}

// Fetch grouped courses
$sql = "SELECT c.*, pc.year_level, pc.semester 
        FROM courses c
        JOIN program_curriculum pc ON c.course_id = pc.course_id
        WHERE pc.program_code = ?
        ORDER BY pc.year_level ASC, 
                 FIELD(pc.semester, '1st Semester', '2nd Semester', 'Summer') ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $program);
$stmt->execute();
$result = $stmt->get_result();

$curriculum = [];
while ($row = $result->fetch_assoc()) {
    $curriculum[$row['year_level']][$row['semester']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prospectus | <?= htmlspecialchars($program) ?></title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
<link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* STRENGTHENED LOGO FIX */
        .navbar-brand img, 
        .logo-img, 
        nav img, 
        .navbar img {
            height: 45px !important; /* Force the height */
            width: auto !important;
            display: inline-block;
        }

        /* Prevent content from hiding behind fixed navbars if applicable */
        body { padding-top: 0px; } 

        @media print { .no-print { display: none; } .container { width: 100%; max-width: 100%; border:none; shadow:none; } }
        .year-header { background-color: #2d5a27; color: white; padding: 10px; margin-top: 30px; border-radius: 5px; font-weight: bold; }
        .sem-header { background-color: #f8f9fa; font-weight: bold; border-left: 5px solid #2d5a27; padding: 10px; margin: 15px 0 5px 0; }
        .table thead th { background: #38961d; font-size: 0.85rem; text-transform: uppercase; }
    </style>
</head>
<body class="bg-light">

    <?php include_once '../admin/navbar.php'; ?>

    <div class="container bg-white shadow-sm p-5 my-4">
        <div class="no-print mb-4">
            <a href="student_dashboard.php" class="text-success text-decoration-none">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <div>
                <h2 class="mb-0 text-dark">Course Prospectus</h2>
                <h4 class="text-success"><?= htmlspecialchars($program) ?></h4>
            </div>
            <button onclick="window.print()" class="btn btn-outline-dark no-print">
                <i class="fas fa-print"></i> Print PDF
            </button>
        </div>

        <?php if (empty($curriculum)): ?>
            <div class="alert alert-warning">No curriculum data found for this program.</div>
        <?php else: ?>
            <?php foreach ($curriculum as $year => $semesters): ?>
                <div class="year-header"><i class="fas fa-graduation-cap mr-2"></i> YEAR LEVEL: <?= htmlspecialchars($year) ?></div>
                <?php foreach ($semesters as $sem => $courses): 
                    $total_units = 0; 
                ?>
                    <div class="sem-header"><?= strtoupper($sem) ?></div>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th width="15%">Code</th>
                                <th width="45%">Description</th>
                                <th width="10%" class="text-center">Lec</th>
                                <th width="10%" class="text-center">Lab</th>
                                <th width="20%">Pre-requisite</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $c): 
                                $total_units += ($c['lec_units'] + $c['lab_units']);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($c['course_code']) ?></td>
                                <td><?= htmlspecialchars($c['course_title']) ?></td>
                                <td class="text-center"><?= $c['lec_units'] ?></td>
                                <td class="text-center"><?= $c['lab_units'] ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($c['pre_requisite'] ?: 'None') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="bg-light font-weight-bold">
                                <td colspan="4" class="text-right">Total Units for Semester:</td>
                                <td class="text-success"><?= $total_units ?> Units</td>
                            </tr>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>