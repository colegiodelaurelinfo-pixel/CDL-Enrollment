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

// 2. Fetch the Grand Total of all Enrolled Students
$grand_total_q = $conn->query("SELECT COUNT(*) as total FROM students WHERE UPPER(TRIM(status)) = 'ENROLLED'");
$grand_total = ($grand_total_q) ? $grand_total_q->fetch_assoc()['total'] : 0;

// 3. Optimized Query for Program Cards
$program_data_query = $conn->query("
    SELECT 
        program, 
        SUM(CASE WHEN UPPER(TRIM(sex)) IN ('MALE', 'M') THEN 1 ELSE 0 END) as male_count,
        SUM(CASE WHEN UPPER(TRIM(sex)) IN ('FEMALE', 'F') THEN 1 ELSE 0 END) as female_count,
        COUNT(*) as total_per_program
    FROM students 
    WHERE UPPER(TRIM(status)) = 'ENROLLED'
    GROUP BY program
    ORDER BY total_per_program DESC
");

$programs = ($program_data_query) ? $program_data_query->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Enrollment Report | CDL</title>
    <link rel="icon" type="image/png" href="../assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="../assets/img/CDL_seal.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f0f2f5;
                        color: #333;
            margin: 0;
            padding: 0;
        }

        /* The Hero Stat (Grand Total) */
        .hero-card {
            background: linear-gradient(135deg, #2d5a27 0%, #1e3c1a 100%);
            color: white;
            padding: 10px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(45, 90, 39, 0.2);
        }

        .hero-label {
            font-size: 11pt;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.8;
        }

        .hero-num {
            font-size: 48pt;
            font-weight: 600;
            margin: 10px 0;
            line-height: 1;
        }

        /* The Program Grid */
        .program-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04);
            border-top: 5px solid #2d5a27;
            display: flex;
            flex-direction: column;
            transition: 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .program-name {
            font-size: 13pt;
            font-weight: 800;
            color: #1a3c18;
            margin: 0 0 15px 0;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .stat-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .gender-split {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .g-box {
            flex: 1;
            padding: 10px;
            border-radius: 10px;
            text-align: center;
            font-weight: 700;
        }

        .m-box {
            background: #eff6ff;
            color: #1e40af;
        }

        .f-box {
            background: #fff1f2;
            color: #9f1239;
        }

        .bar-container {
            height: 10px;
            background: #eee;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            margin-top: 10px;
        }

        .bar-m {
            background: #3b82f6;
        }

        .bar-f {
            background: #f43f5e;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">

        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
            <div>
                <h1 style="margin:0; color:#2d5a27;">Enrollment Statistics</h1>
                <p style="margin:5px 0 0 0; color:#666;"><?= $active_sy ?> | <?= $active_sem ?> Semester</p>
            </div>
            <button onclick="window.print()" class="no-print" style="padding: 10px 20px; background: #fff; border: 1px solid #ddd; border-radius: 8px; cursor: pointer; font-weight: 600;">
                <i class="fas fa-print"></i> Print
            </button>
        </div>

        <div class="hero-card">
            <div class="hero-label">Total Officially Enrolled Students</div>
            <div class="hero-num"><?= number_format($grand_total) ?></div>
            <div style="font-size: 10pt; opacity: 0.7;">Verified students with "Enrolled" status</div>
        </div>

        <div class="program-container">
            <?php foreach ($programs as $row):
                $total = $row['total_per_program'];
                $m_perc = ($total > 0) ? ($row['male_count'] / $total) * 100 : 0;
                $f_perc = ($total > 0) ? ($row['female_count'] / $total) * 100 : 0;
            ?>
                <div class="card">
                    <h3 class="program-name"><?= $row['program'] ?></h3>

                    <div class="stat-line">
                        <span style="font-size: 9pt; font-weight: 700; color: #888;">TOTAL STUDENTS</span>
                        <span style="font-size: 16pt; font-weight: 800; color: #2d5a27;"><?= number_format($total) ?></span>
                    </div>

                    <div class="gender-split">
                        <div class="g-box m-box">
                            <span style="font-size: 7pt; display: block;">MALE</span>
                            <?= $row['male_count'] ?>
                        </div>
                        <div class="g-box f-box">
                            <span style="font-size: 7pt; display: block;">FEMALE</span>
                            <?= $row['female_count'] ?>
                        </div>
                    </div>

                    <div class="bar-container">
                        <div class="bar-m" style="width: <?= $m_perc ?>%"></div>
                        <div class="bar-f" style="width: <?= $f_perc ?>%"></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; margin-top: 6px; font-size: 7.5pt; color: #999; font-weight: 600;">
                        <span><?= round($m_perc) ?>% M</span>
                        <span><?= round($f_perc) ?>% F</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</body>

</html>