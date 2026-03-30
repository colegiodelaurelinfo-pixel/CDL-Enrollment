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

// 2. Master Query: Get all Faculty and SUM their units
$summary_query = "
    SELECT 
        u.id, 
        u.firstname, 
        u.lastname,
        COUNT(s.id) as subject_count,
        IFNULL(SUM(c.lec_units + c.lab_units), 0) as total_units
    FROM users u
    LEFT JOIN schedules s ON u.id = s.faculty_id 
        AND s.school_year = '$active_sy' 
        AND s.semester = '$active_sem'
    LEFT JOIN courses c ON s.course_id = c.course_id
    WHERE u.level = 'FACULTY'
    GROUP BY u.id
    ORDER BY u.lastname ASC
";

$results = $conn->query($summary_query);

// Initialize Counters for the Footer
$grand_total_units = 0;
$grand_total_subjects = 0;
$faculty_count = 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Faculty Load Summary | CDL</title>
    <link rel="icon" href="../assets/img/CDL_seal.ico">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        .report-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-top: 20px;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 8pt;
            font-weight: bold;
        }

        .overload {
            background: #fff1f2;
            color: #e11d48;
        }

        .normal {
            background: #f0fdf4;
            color: #166534;
        }

        .footer-totals {
            background: #f8fafc;
            font-weight: 800;
            border-top: 2px solid #2d5a27 !important;
        }

        @media print {
            .no-print {
                display: none;
            }

            .report-card {
                box-shadow: none;
                border: 1px solid #eee;
            }
        }
    </style>
</head>

<body style="background: #f8fafc;">
    <?php include 'navbar.php'; ?>
    <div class="container" style="max-width: 1000px; margin: 30px auto; padding: 0 20px;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div>
                <h2 style="margin:0; color: #1e293b;"><i class="fas fa-users-rectangle"></i> Faculty Load Summary</h2>
                <p style="margin:5px 0; color: #64748b;">A.Y. <?= $active_sy ?> | <?= $active_sem ?> Semester</p>
            </div>
            <button onclick="window.print()" class="no-print" style="background: #2d5a27; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold;">
                <i class="fas fa-print"></i> Export Report
            </button>
        </div>

        <div class="report-card">
            <table class="admin-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #2d5a27; color: white; text-align: left;">
                        <th style="padding: 15px;">Instructor Name</th>
                        <th style="text-align: center;">No. of Subjects</th>
                        <th style="text-align: center;">Total Units</th>
                        <th style="text-align: center;">Load Status</th>
                        <th class="no-print" style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($results->num_rows > 0): ?>
                        <?php while ($row = $results->fetch_assoc()):
                            $units = $row['total_units'];
                            $is_overload = ($units > 21);

                            // Increment Grand Totals
                            $grand_total_units += $units;
                            $grand_total_subjects += $row['subject_count'];
                            $faculty_count++;
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 15px; font-weight: 600;">
                                    <?= strtoupper($row['lastname'] . ", " . $row['firstname']) ?>
                                </td>
                                <td style="text-align: center; color: #64748b;"><?= $row['subject_count'] ?></td>
                                <td style="text-align: center; font-weight: 800; color: #1e293b;"><?= $units ?></td>
                                <td style="text-align: center;">
                                    <span class="status-badge <?= $is_overload ? 'overload' : 'normal' ?>">
                                        <?= $is_overload ? 'OVERLOAD' : 'NORMAL' ?>
                                    </span>
                                </td>
                                <td class="no-print" style="text-align: center;">
                                    <a href="faculty_loading.php?faculty_id=<?= $row['id'] ?>" style="color: #2d5a27; text-decoration: none;" title="View Detailed Load">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                        <tr class="footer-totals">
                            <td style="padding: 15px; text-align: right;">TOTAL FOR <?= $faculty_count ?> INSTRUCTORS:</td>
                            <td style="text-align: center;"><?= $grand_total_subjects ?></td>
                            <td style="text-align: center; color: #2d5a27; font-size: 13pt;"><?= $grand_total_units ?></td>
                            <td colspan="2" class="no-print"></td>
                        </tr>

                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;">No faculty members found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>

</html>