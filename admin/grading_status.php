<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';


// --- 1. SETTINGS & DATA FETCHING ---
$settings_query = $conn->query("SELECT * FROM system_settings WHERE setting_key IN ('active_sy', 'active_semester')");
$active = [];
while ($row = $settings_query->fetch_assoc()) {
    $active[$row['setting_key']] = $row['setting_value'];
}

$sy = $active['active_sy'] ?? '';
$sem = $active['active_semester'] ?? '';

// --- 2. FETCH COMPLIANCE DATA ---
$query = "
    SELECT 
        s.course_id, c.course_code, c.course_title, sec.section_name, 
        CONCAT(u.firstname, ' ', u.lastname) as instructor, u.email as instructor_email,
        (SELECT MAX(sent_at) FROM faculty_reminders fr WHERE fr.instructor_email = u.email) as last_reminded,
        (SELECT COUNT(*) FROM grades g WHERE g.course_id = s.course_id AND g.academic_year = s.school_year AND g.semester = s.semester AND g.final_grade IS NOT NULL) as grade_count
    FROM schedules s
    JOIN courses c ON s.course_id = c.course_id
    JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN users u ON s.faculty_id = u.id
    WHERE s.school_year = ? AND s.semester = ?
    GROUP BY s.course_id, s.section_id
    ORDER BY grade_count ASC, instructor ASC
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("SQL Error: " . $conn->error);
}
$stmt->bind_param("ss", $sy, $sem);
$stmt->execute();
$report = $stmt->get_result();

$total_classes = $report->num_rows;
$submitted_classes = 0;
$data_rows = [];
$pending_emails = [];

while ($row = $report->fetch_assoc()) {
    if ($row['grade_count'] > 0) {
        $submitted_classes++;
    } else {
        if (!empty($row['instructor_email'])) {
            $pending_emails[] = $row['instructor_email'];
        }
    }
    $data_rows[] = $row;
}

$unique_pending_emails = array_unique($pending_emails);
$progress_percent = ($total_classes > 0) ? round(($submitted_classes / $total_classes) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Grading Compliance | CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f4f7f6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .progress-container {
            background: #e9ecef;
            border-radius: 10px;
            height: 25px;
            margin: 20px 0;
            overflow: hidden;
            border: 1px solid #dee2e6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 8pt;
            font-weight: bold;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-submitted {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 style="color: #2d5a27; margin:0;"><i class="fas fa-mail-bulk"></i> Grading Compliance</h2>
                <p class="text-muted m-0"><?= $sem ?>, SY <?= $sy ?></p>
            </div>
            <div class="text-right">
                <button id="btnBulkRemind" class="btn btn-danger font-weight-bold">
                    <i class="fas fa-paper-plane"></i> SEND REMINDERS (<?= count($unique_pending_emails) ?>)
                </button>
                <div id="emailStatus" class="mt-2 font-weight-bold"></div>
            </div>
        </div>

        <div class="progress-container">
            <div class="progress-bar"><?= $progress_percent ?>%</div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><strong><?= $total_classes ?></strong><br><small>TOTAL CLASSES</small></div>
            <div class="stat-card text-success"><strong><?= $submitted_classes ?></strong><br><small>SUBMITTED</small></div>
            <div class="stat-card text-danger"><strong><?= $total_classes - $submitted_classes ?></strong><br><small>PENDING</small></div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover m-0">
                    <thead class="thead-dark">
                        <tr>
                            <th>Instructor</th>
                            <th>Course</th>
                            <th>Section</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data_rows as $row): $is_submitted = ($row['grade_count'] > 0); ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($row['instructor'] ?? 'Unassigned') ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['instructor_email'] ?? '') ?></small>
                                    <?php if ($row['last_reminded']): ?>
                                        <div style="font-size: 7.5pt; color: #856404;" class="mt-1">
                                            <i class="fas fa-history"></i> Last Reminded: <?= date('M d, h:i A', strtotime($row['last_reminded'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $row['course_code'] ?></td>
                                <td><?= $row['section_name'] ?></td>
                                <td>
                                    <span class="status-badge <?= $is_submitted ? 'status-submitted' : 'status-pending' ?>">
                                        <?= $is_submitted ? 'SUBMITTED' : 'PENDING' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#btnBulkRemind').on('click', function() {
                const emails = <?= json_encode(array_values($unique_pending_emails)) ?>;

                if (emails.length === 0) {
                    alert("No pending instructors to remind.");
                    return;
                }

                if (!confirm("Send reminders to " + emails.length + " instructors?")) return;

                const btn = $(this);
                const status = $('#emailStatus');

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> SENDING...');
                status.show().text("Sending emails...").css("color", "blue");

                $.ajax({
                    url: 'ajax_send_reminders.php',
                    method: 'POST',
                    data: {
                        emails: emails,
                        sy: '<?= $sy ?>',
                        sem: '<?= $sem ?>'
                    },
                    dataType: 'json', // Expect JSON response
                    success: function(response) {
                        if (response.status === 'success') {
                            status.text("✅ " + response.message).css("color", "green");
                            btn.html('<i class="fas fa-check"></i> SENT');
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            status.text("❌ Error: " + response.message).css("color", "red");
                            btn.prop('disabled', false).text("TRY AGAIN");
                        }
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                        status.text("❌ Server Error. Check console.").css("color", "red");
                        btn.prop('disabled', false).text("TRY AGAIN");
                    }
                });
            });
        });
    </script>
</body>

</html>