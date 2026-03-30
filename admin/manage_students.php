<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

if (!in_array($_SESSION['level'], ['ADMINISTRATOR', 'REGISTRAR'])) {
    header("Location: dashboard.php");
    exit();
}

// 1. Fetch Dynamic System Settings
$sys = [];
$settings_query = $conn->query("SELECT * FROM system_settings");
while ($row = $settings_query->fetch_assoc()) {
    $sys[$row['setting_key']] = $row['setting_value'];
}
$active_sy  = $sys['active_sy'];
$active_sem = $sys['active_semester'];

// 2. Fetch Filter Options
$programs = $conn->query("SELECT program_code FROM programs ORDER BY program_code ASC");
$sections_list = $conn->query("SELECT s.*, 
    (SELECT COUNT(*) FROM students st WHERE st.section = s.section_name AND st.school_year = '$active_sy' AND st.status = 'Enrolled') as current_count 
    FROM sections s ORDER BY s.section_name ASC");

$available_sections = [];
while ($sec = $sections_list->fetch_assoc()) {
    $available_sections[] = $sec;
}

// 3. Filters (Now strictly for the initial SQL load)
$f_prog   = $_GET['f_program'] ?? '';
$f_year   = $_GET['f_year'] ?? '';
$f_sec    = $_GET['f_section'] ?? '';
$f_status = $_GET['f_status'] ?? 'Enrolled';

$stats_options = ["Enrolled", "DO (Dropped Officially)", "DU (Dropped Unofficially)", "LOA (Leave of Absence)", "Transfer", "Graduated"];

// 4. Build Query
$conditions = ["school_year = ?", "semester = ?", "status = ?"];
$params = [$active_sy, $active_sem, $f_status];
$types = "sss";

if ($f_prog) {
    $conditions[] = "program = ?";
    $params[] = $f_prog;
    $types .= "s";
}
if ($f_year) {
    $conditions[] = "year_level = ?";
    $params[] = $f_year;
    $types .= "s";
}
if ($f_sec) {
    $conditions[] = "section = ?";
    $params[] = $f_sec;
    $types .= "s";
}

$sql = "SELECT * FROM students WHERE " . implode(" AND ", $conditions);
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$male_count = 0;
$female_count = 0;
$students = [];
while ($row = $result->fetch_assoc()) {
    if (strtoupper($row['sex'] ?? '') == 'MALE') $male_count++;
    if (strtoupper($row['sex'] ?? '') == 'FEMALE') $female_count++;
    $students[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <title>Manage Students - CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <style>
        :root {
            --primary-green: #2d5a27;
        }

        /* Compact DataTables Override */
        #studentTable {
            font-size: 0.82rem;
            width: 100% !important;
            border-collapse: collapse;
        }

        #studentTable thead th {
            background: var(--primary-green);
            color: white;
            padding: 10px;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        #studentTable tbody td {
            padding: 6px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #eee;
        }

        .filter-row {
            display: flex;
            gap: 10px;
            background: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .filter-group label {
            font-size: 0.65rem;
            font-weight: bold;
            color: #666;
        }

        .filter-group select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.75rem;
            min-width: 120px;
        }

        .prog-pill {
            background: #e8f5e9;
            color: var(--primary-green);
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.7rem;
            border: 1px solid #c8e6c9;
        }

        .status-badge {
            padding: 3px 8px;
            font-size: 0.65rem;
            border-radius: 4px;
            font-weight: bold;
            color: white;
        }

        .stat-badge {
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
            background: white;
            border: 1px solid #eee;
            font-weight: 600;
        }

        /* Modal Style */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(2px);
        }
    </style>

</head>

<body style="background: #f4f7f6;">
    <?php include 'navbar.php'; ?>

    <div class="container" style="max-width: 1250px; margin-top: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 15px;">
            <div>
                <?php if (isset($_GET['msg'])): ?>
                    <div id="alert-box" style="padding: 15px; margin-bottom: 20px; border-radius: 8px; display: flex; align-items: center; gap: 10px; font-weight: 500; font-size: 0.9rem;
        <?php
                    if ($_GET['msg'] == 'updated') echo 'background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9;';
                    elseif ($_GET['msg'] == 'error') echo 'background: #ffebee; color: #c62828; border: 1px solid #ffcdd2;';
                    else echo 'background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb;';
        ?>">

                        <i class="fas <?php
                                        if ($_GET['msg'] == 'updated') echo 'fa-check-circle';
                                        elseif ($_GET['msg'] == 'error') echo 'fa-exclamation-triangle';
                                        else echo 'fa-info-circle';
                                        ?>"></i>

                        <span>
                            <?php
                            if ($_GET['msg'] == 'updated') echo 'Success! Student profile has been updated.';
                            elseif ($_GET['msg'] == 'error') echo 'Error: Something went wrong while updating the record.';
                            else echo 'Notification: Action processed.';
                            ?>
                        </span>

                        <button onclick="this.parentElement.style.display='none'" style="margin-left: auto; background: none; border: none; cursor: pointer; color: inherit; font-size: 1.2rem;">&times;</button>
                    </div>

                    <script>
                        setTimeout(() => {
                            const alert = document.getElementById('alert-box');
                            if (alert) alert.style.transition = "opacity 0.5s ease";
                            if (alert) alert.style.opacity = "0";
                            setTimeout(() => alert ? alert.remove() : null, 500);
                        }, 4000);
                    </script>
                <?php endif; ?>
                <h3 style="margin:0; color: var(--primary-green);"><i class="fas fa-user-graduate mr-2"></i>Student Records</h3>
                <div style="display: flex; gap: 10px; margin-top: 8px;">
                    <span class="stat-badge" style="border-left: 4px solid var(--primary-green);">Total Enrolled: <?= count($students) ?></span>
                    <span class="stat-badge" style="color: #007bff;"><i class="fas fa-mars mr-1"></i> <?= $male_count ?></span>
                    <span class="stat-badge" style="color: #e83e8c;"><i class="fas fa-venus mr-1"></i> <?= $female_count ?></span>
                </div>
            </div>
            <div style="text-align: right;">
                <p style="font-size: 0.75rem; color: #666; margin-bottom: 5px;">Active Term: <strong><?= $active_sy ?> (<?= $active_sem ?>)</strong></p>
                <a href="register_student.php" class="login-btn" style="width: auto; padding: 7px 15px; font-size: 0.75rem; text-decoration: none;">+ Enroll New Student</a>
            </div>
        </div>

        <form method="GET" class="filter-row">
            <div class="filter-group">
                <label>Academic Status</label>
                <select name="f_status" onchange="this.form.submit()" style="background: #fff8e1; border-color: #ffe082; font-weight: bold;">
                    <?php foreach ($stats_options as $opt): ?>
                        <option value="<?= $opt ?>" <?= ($f_status == $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Program</label>
                <select name="f_program">
                    <option value="">All Programs</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?= $p['program_code'] ?>" <?= ($f_prog == $p['program_code']) ? 'selected' : '' ?>><?= $p['program_code'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Year Level</label>
                <select name="f_year">
                    <option value="">All Years</option>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <option value="<?= $i ?>" <?= ($f_year == $i) ? 'selected' : '' ?>>Year <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Section</label>
                <select name="f_section">
                    <option value="">All Sections</option>
                    <?php foreach ($available_sections as $s): ?>
                        <option value="<?= $s['section_name'] ?>" <?= ($f_sec == $s['section_name']) ? 'selected' : '' ?>><?= $s['section_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="login-btn" style="width: auto; padding: 6px 15px; background: #333; font-size: 0.75rem;">Apply Filters</button>
            <a href="manage_students.php" class="btn-mini" style="background:#eee; color:#333; padding: 6px 10px; border: 1px solid #ccc; text-decoration:none; font-size: 0.75rem; border-radius: 4px;">Reset</a>
        </form>

        <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
            <table id="studentTable" class="display compact">
                <thead>
                    <tr>
                        <th>ID No.</th>
                        <th>Full Name</th>
                        <th>Sex</th>
                        <th>Program</th>
                        <th>Year & Section</th>
                        <th>Status</th>
                        <th class="no-sort">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $row):
                        $st = $row['status'];
                        $bg = "#6c757d";
                        if ($st == 'Enrolled') $bg = "#2d5a27";
                        elseif ($st == 'Graduated') $bg = "#007bff";
                        elseif (strpos($st, 'DO') !== false) $bg = "#dc3545";
                        elseif ($st == 'LOA') $bg = "#6f42c1";
                    ?>
                        <tr>
                            <td><code style="font-weight: bold; color: #444;"><?= $row['student_id'] ?></code></td>
                            <td>
                                <strong><?= strtoupper($row['lastname']) ?> <?= $row['extension'] ?></strong> , <?= $row['firstname'] ?>
                                <small class="text-muted"><?= !empty($row['middlename']) ? substr($row['middlename'], 0, 1) . '.' : '' ?></small>
                            </td>
                            <td class="text-center"><?= substr($row['sex'] ?? '-', 0, 1) ?></td>
                            <td><span class="prog-pill"><?= $row['program'] ?></span></td>
                            <td>Yr <?= $row['year_level'] ?> - <small><?= $row['section'] ?: 'N/A' ?></small></td>
                            <td><span class="status-badge" style="background: <?= $bg ?>;"><?= $st ?></span></td>
                            <td class="text-center">
                                <a href="edit_student.php?id=<?= $row['student_id'] ?>" class="text-success mr-2" title="Edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>

                                <a href="view_transcript.php?student_id=<?= $row['student_id'] ?>" class="text-info mr-2" title="View Transcript">
                                    <i class="fas fa-file-invoice"></i> Transcript
                                </a>

                                <?php if ($st == 'Enrolled' && $row['year_level'] < 4): ?>
                                    <a href="javascript:void(0)" onclick="promoteStudent('<?= $row['student_id'] ?>', '<?= $row['year_level'] ?>')" class="text-primary" title="Promote">
                                        <i class="fas fa-arrow-circle-up"></i> Promote
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="promoteModal" class="modal">
        <div style="background:white; padding:25px; border-radius:10px; width:380px; box-shadow: 0 15px 35px rgba(0,0,0,0.2);">
            <h4 style="color: var(--primary-green); margin-top:0;"><i class="fas fa-level-up-alt mr-2"></i>Promotion</h4>
            <div id="promoDetails" style="margin-bottom: 20px; font-size:0.85rem; padding: 10px; background: #f9f9f9; border-radius: 5px;"></div>

            <label class="small font-weight-bold">New Section Assignment:</label>
            <select id="newSectionSelect" class="form-control mb-4">
                <option value="">-- Select Section --</option>
                <?php foreach ($available_sections as $sec): ?>
                    <option value="<?= $sec['section_name'] ?>"><?= $sec['section_name'] ?> (<?= $sec['current_count'] ?>/<?= $sec['max_capacity'] ?>)</option>
                <?php endforeach; ?>
            </select>

            <div style="display:flex; gap:10px;">
                <button onclick="confirmPromotion()" class="login-btn" style="flex:1;">Proceed</button>
                <button onclick="document.getElementById('promoteModal').style.display='none'" class="btn btn-light border" style="flex:1; font-size: 0.8rem;">Cancel</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#studentTable').DataTable({
                "pageLength": 25,
                "order": [
                    [1, "asc"]
                ],
                "columnDefs": [{
                    "targets": 'no-sort',
                    "orderable": false
                }]
            });
        });

        let currentPromoId = '';
        let targetYear = '';

        function promoteStudent(id, currentYear) {
            currentPromoId = id;
            targetYear = parseInt(currentYear) + 1;
            document.getElementById('promoDetails').innerHTML = `Student ID: <strong>${id}</strong><br>New Level: <span class="text-success">Year ${targetYear}</span>`;
            document.getElementById('promoteModal').style.display = 'flex';
        }

        function confirmPromotion() {
            const newSection = document.getElementById('newSectionSelect').value;
            if (!newSection) {
                alert("Please assign a section.");
                return;
            }

            fetch('ajax_promote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `student_id=${currentPromoId}&new_year_level=${targetYear}&new_section=${encodeURIComponent(newSection)}`
                })
                .then(res => res.text())
                .then(data => {
                    if (data.trim() === "success") {
                        window.location.reload();
                    } else {
                        alert("Promotion Error: " + data);
                    }
                });
        }
    </script>
</body>

</html>