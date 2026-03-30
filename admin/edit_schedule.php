<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
checkAccess(['ADMINISTRATOR', 'REGISTRAR']);

// 1. Get Schedule ID
if (!isset($_GET['id'])) {
    header("Location: manage_schedules.php");
    exit();
}
$id = intval($_GET['id']);

// 2. Fetch Existing Schedule Data
$stmt = $conn->prepare("SELECT * FROM schedules WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();

if (!$current) {
    header("Location: manage_schedules.php");
    exit();
}

$error_msg = "";

// 3. Handle Update Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_schedule'])) {
    $section_id = $_POST['section_id'];
    $course_id  = $_POST['course_id'];
    $faculty_id = $_POST['faculty_id'];
    $room       = $conn->real_escape_string($_POST['room_name']);
    $t_start    = $_POST['time_start'];
    $t_end      = $_POST['time_end'];
    $special    = $conn->real_escape_string($_POST['special'] ?? 'N/A');

    // Days logic (Including Sunday)
    $mon = isset($_POST['days']['mon']) ? 1 : 0;
    $tue = isset($_POST['days']['tue']) ? 1 : 0;
    $wed = isset($_POST['days']['wed']) ? 1 : 0;
    $thu = isset($_POST['days']['thu']) ? 1 : 0;
    $fri = isset($_POST['days']['fri']) ? 1 : 0;
    $sat = isset($_POST['days']['sat']) ? 1 : 0;
    $sun = isset($_POST['days']['sun']) ? 1 : 0;

    // Time Validation
    if (strtotime($t_start) >= strtotime($t_end)) {
        $error_msg = "Error: End time must be later than start time.";
    } elseif (empty($_POST['days'])) {
        $error_msg = "Error: Please select at least one day.";
    } else {
        // 4. Conflict Check
        $day_map = ['mon' => 'day_mon', 'tue' => 'day_tue', 'wed' => 'day_wed', 'thu' => 'day_thu', 'fri' => 'day_fri', 'sat' => 'day_sat', 'sun' => 'day_sun'];
        $conflict_found = false;

        foreach ($_POST['days'] as $day_key => $value) {
            $column = $day_map[$day_key];
            $check_sql = "SELECT s.* FROM schedules s 
                          WHERE s.school_year = ? AND s.semester = ? AND s.$column = 1 
                          AND (s.time_start < ? AND s.time_end > ?) 
                          AND (s.room_name = ? OR s.faculty_id = ? OR s.section_id = ?)
                          AND s.id != ?";

            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("sssssiii", $current['school_year'], $current['semester'], $t_end, $t_start, $room, $faculty_id, $section_id, $id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $error_msg = "<strong>CONFLICT:</strong> The new timing or location overlaps with another schedule.";
                $conflict_found = true;
                break;
            }
        }

        if (!$conflict_found) {
            // Added 'special=?' to the UPDATE statement
            $update = $conn->prepare("UPDATE schedules SET section_id=?, course_id=?, faculty_id=?, room_name=?, day_mon=?, day_tue=?, day_wed=?, day_thu=?, day_fri=?, day_sat=?, day_sun=?, time_start=?, time_end=?, special=? WHERE id=?");
            $update->bind_param("iiisiiiiiiisssi", $section_id, $course_id, $faculty_id, $room, $mon, $tue, $wed, $thu, $fri, $sat, $sun, $t_start, $t_end, $special, $id);

            if ($update->execute()) {
                $log_details = "Updated schedule ID $id (Special: $special) for Section $section_id";
                if (function_exists('log_system_activity')) {
                    log_system_activity($conn, 'UPDATE_SCHEDULE', $log_details);
                }
                header("Location: manage_schedules.php?msg=updated");
                exit();
            } else {
                $error_msg = "Update failed: " . $conn->error;
            }
        }
    }
}

// 5. Fetch Dropdown Data
$sections_res = $conn->query("SELECT id, section_name FROM sections ORDER BY section_name ASC");
$courses_res  = $conn->query("SELECT course_id, course_code, course_title FROM courses ORDER BY course_code ASC");
$faculty_res  = $conn->query("SELECT id, firstname, lastname FROM users WHERE level = 'FACULTY' ORDER BY lastname ASC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Edit Schedule | CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <style>
        :root {
            --cdl-green: #2d5a27;
        }

        .edit-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border-top: 5px solid var(--cdl-green);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
        }

        .input-group label {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 0.85rem;
        }

        .input-group select,
        .input-group input {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: inherit;
        }

        .day-selector {
            display: flex;
            gap: 8px;
            margin-top: 5px;
            flex-wrap: wrap;
        }

        .day-btn {
            cursor: pointer;
            border: 1px solid #ddd;
            padding: 10px 15px;
            border-radius: 6px;
            background: #f9f9f9;
            transition: 0.2s;
            font-size: 0.8rem;
        }

        .day-btn input {
            display: none;
        }

        .day-btn:has(input:checked) {
            background: var(--cdl-green);
            color: white;
            border-color: var(--cdl-green);
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-save {
            flex: 2;
            background: var(--cdl-green);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-cancel {
            flex: 1;
            background: #666;
            color: white;
            text-decoration: none;
            text-align: center;
            padding: 12px;
            border-radius: 6px;
            font-weight: bold;
        }

        .error-banner {
            background: #fee2e2;
            color: #dc2626;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #dc2626;
        }
    </style>
</head>

<body style="background: #f4f7f6; font-family: 'Segoe UI', sans-serif;">
    <?php include 'navbar.php'; ?>

    <div class="container" style="max-width: 850px; margin: 40px auto; padding: 0 20px;">
        <div class="edit-card">
            <h2 style="margin-top:0;"><i class="fas fa-edit"></i> Modify Schedule</h2>
            <p style="color: #666; margin-bottom: 25px;">Update class details for the current term.</p>

            <?php if ($error_msg): ?>
                <div class="error-banner"><i class="fas fa-exclamation-circle"></i> <?= $error_msg ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Section</label>
                        <select name="section_id" required>
                            <?php while ($r = $sections_res->fetch_assoc()): ?>
                                <option value="<?= $r['id'] ?>" <?= ($r['id'] == $current['section_id']) ? 'selected' : '' ?>>
                                    <?= $r['section_name'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Course (Code - Title)</label>
                        <select name="course_id" required>
                            <?php while ($r = $courses_res->fetch_assoc()): ?>
                                <option value="<?= $r['course_id'] ?>" <?= ($r['course_id'] == $current['course_id']) ? 'selected' : '' ?>>
                                    <?= $r['course_code'] ?> - <?= $r['course_title'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="input-group">
                        <label>Faculty</label>
                        <select name="faculty_id" required>
                            <?php while ($r = $faculty_res->fetch_assoc()): ?>
                                <option value="<?= $r['id'] ?>" <?= ($r['id'] == $current['faculty_id']) ? 'selected' : '' ?>>
                                    <?= $r['lastname'] ?>, <?= $r['firstname'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Special Assignment</label>
                        <select name="special">
                            <option value="N/A" <?= ($current['special'] == 'N/A') ? 'selected' : '' ?>>None (Regular)</option>
                            <option value="SWIMMING" <?= ($current['special'] == 'SWIMMING') ? 'selected' : '' ?>>Swimming</option>
                            <option value="BADMINTON" <?= ($current['special'] == 'BADMINTON') ? 'selected' : '' ?>>Badminton</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="input-group">
                        <label>Room</label>
                        <input type="text" name="room_name" value="<?= htmlspecialchars($current['room_name']) ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Start Time</label>
                        <input type="time" name="time_start" value="<?= date('H:i', strtotime($current['time_start'])) ?>" required>
                    </div>
                    <div class="input-group">
                        <label>End Time</label>
                        <input type="time" name="time_end" value="<?= date('H:i', strtotime($current['time_end'])) ?>" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Days of the Week</label>
                    <div class="day-selector">
                        <?php
                        $days_list = [
                            'mon' => 'MON',
                            'tue' => 'TUE',
                            'wed' => 'WED',
                            'thu' => 'THU',
                            'fri' => 'FRI',
                            'sat' => 'SAT',
                            'sun' => 'SUN'
                        ];
                        foreach ($days_list as $key => $label):
                            $col = "day_" . $key;
                        ?>
                            <label class="day-btn">
                                <input type="checkbox" name="days[<?= $key ?>]" <?= ($current[$col]) ? 'checked' : '' ?>> <?= $label ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="btn-group">
                    <a href="manage_schedules.php" class="btn-cancel">Cancel</a>
                    <button type="submit" name="update_schedule" class="btn-save">Update Schedule Entry</button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>