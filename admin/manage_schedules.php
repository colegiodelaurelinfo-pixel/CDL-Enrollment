<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

if (!in_array($_SESSION['level'], ['ADMINISTRATOR', 'REGISTRAR'])) {
    header("Location: ../login.php?msg=denied");
    exit();
}

// 1. Fetch Active Settings
$settings_res = $conn->query("SELECT * FROM system_settings");
$sys = [];
while ($r = $settings_res->fetch_assoc()) {
    $sys[$r['setting_key']] = $r['setting_value'];
}
$active_sy = $sys['active_sy'] ?? '';
$active_semester = $sys['active_semester'] ?? '';

$conflict_msg = "";

// 2. Handle Saving Schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_schedule'])) {
    $section_id = $_POST['section_id'];
    $course_id  = $_POST['course_id'];
    $faculty_id = $_POST['faculty_id'];
    $room       = $conn->real_escape_string($_POST['room_name']);
    $t_start    = $_POST['time_start'];
    $t_end      = $_POST['time_end'];
    $days_input = $_POST['days'] ?? [];
    $special    = $conn->real_escape_string($_POST['special'] ?? 'N/A');

    // Restoration of all 7 days for DB insert
    $mon = isset($days_input['mon']) ? 1 : 0;
    $tue = isset($days_input['tue']) ? 1 : 0;
    $wed = isset($days_input['wed']) ? 1 : 0;
    $thu = isset($days_input['thu']) ? 1 : 0;
    $fri = isset($days_input['fri']) ? 1 : 0;
    $sat = isset($days_input['sat']) ? 1 : 0;
    $sun = isset($days_input['sun']) ? 1 : 0;

    if (strtotime($t_start) >= strtotime($t_end)) {
        $conflict_msg = "Error: End time must be later than start time.";
    } elseif (empty($days_input)) {
        $conflict_msg = "Error: Please select at least one day.";
    } else {
        $day_map = ['mon' => 'day_mon', 'tue' => 'day_tue', 'wed' => 'day_wed', 'thu' => 'day_thu', 'fri' => 'day_fri', 'sat' => 'day_sat', 'sun' => 'day_sun'];
        $conflict_found = false;

        foreach ($days_input as $day_key => $value) {
            $column = $day_map[$day_key];
            $check_sql = "SELECT s.*, c.course_code, u.lastname as faculty_name, sec.section_name 
                          FROM schedules s
                          JOIN courses c ON s.course_id = c.course_id
                          JOIN users u ON s.faculty_id = u.id
                          JOIN sections sec ON s.section_id = sec.id
                          WHERE s.school_year = ? AND s.semester = ? AND s.$column = 1
                          AND (s.time_start < ? AND s.time_end > ?)
                          AND (s.room_name = ? OR s.faculty_id = ? OR s.section_id = ?)";

            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("sssssii", $active_sy, $active_semester, $t_end, $t_start, $room, $faculty_id, $section_id);
            $check_stmt->execute();
            $res = $check_stmt->get_result();

            if ($res->num_rows > 0) {
                $c = $res->fetch_assoc();
                $day_name = strtoupper($day_key);
                $time_range = date("g:iA", strtotime($c['time_start'])) . "-" . date("g:iA", strtotime($c['time_end']));
                $conflict_msg = "<strong>CONFLICT ON {$day_name}:</strong> Data already exists for <u>{$c['course_code']}</u> at {$time_range}.";
                $conflict_found = true;
                break;
            }
        }

        if (!$conflict_found) {
            $stmt = $conn->prepare("INSERT INTO schedules (section_id, course_id, faculty_id, room_name, day_mon, day_tue, day_wed, day_thu, day_fri, day_sat, day_sun, time_start, time_end, semester, school_year, special) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisiiiiiiisssss", $section_id, $course_id, $faculty_id, $room, $mon, $tue, $wed, $thu, $fri, $sat, $sun, $t_start, $t_end, $active_semester, $active_sy, $special);

            if ($stmt->execute()) {
                header("Location: manage_schedules.php?msg=added");
                exit();
            }
        }
    }
}

// 3. Fetch Data for Dropdowns
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <title>Class Scheduling | CDL</title>
    <style>
        #schedTable {
            font-size: 0.82rem;
            width: 100%;
            background: white;
        }

        .prog-pill {
            background: #eef5ee;
            color: #2d5a27;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
        }

        .special-badge {
            background: #007bff;
            color: white;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 0.65rem;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="dashboard-header-card" style="padding: 15px; margin-bottom: 20px;">
            <h3 style="margin:0;"><i class="fas fa-calendar-alt"></i> Class Scheduling</h3>
            <small>Term: <strong><?= $active_sy ?> | <?= $active_semester ?></strong></small>
        </div>

        <?php if ($conflict_msg): ?>
            <div class="error-msg" style="margin-bottom: 15px; padding: 10px; background: #fff5f5; border: 1px solid #feb2b2; border-radius: 5px; color: #c53030;"><?= $conflict_msg ?></div>
        <?php endif; ?>

        <div class="form-card-modern" style="padding: 20px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px;">
            <form method="POST">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">

                    <div class="input-group">
                        <label class="small font-weight-bold">Section</label>
                        <select name="section_id" class="form-control form-control-sm" required>
                            <option value="">-- Select --</option>
                            <?php
                            $sections_res->data_seek(0);
                            while ($r = $sections_res->fetch_assoc()):
                                $selected = (isset($_POST['section_id']) && $_POST['section_id'] == $r['id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $r['id'] ?>" <?= $selected ?>><?= $r['section_name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label class="small font-weight-bold">Course (Code - Title)</label>
                        <select name="course_id" class="form-control form-control-sm" required>
                            <option value="">-- Select --</option>
                            <?php
                            $courses_res->data_seek(0);
                            while ($r = $courses_res->fetch_assoc()):
                                $selected = (isset($_POST['course_id']) && $_POST['course_id'] == $r['course_id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $r['course_id'] ?>" <?= $selected ?>><?= $r['course_code'] ?> - <?= $r['course_title'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label class="small font-weight-bold">Instructor</label>
                        <select name="faculty_id" class="form-control form-control-sm" required>
                            <option value="">-- Select --</option>
                            <?php
                            $faculty_res->data_seek(0);
                            while ($r = $faculty_res->fetch_assoc()):
                                $selected = (isset($_POST['faculty_id']) && $_POST['faculty_id'] == $r['id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $r['id'] ?>"><?= $r['lastname'] ?>, <?= $r['firstname'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label class="small font-weight-bold">Special Assignment</label>
                        <select name="special" class="form-control form-control-sm">
                            <?php $curr_spec = $_POST['special'] ?? 'N/A'; ?>
                            <option value="N/A" <?= $curr_spec == 'N/A' ? 'selected' : '' ?>>None (Regular)</option>
                            <option value="SWIMMING" <?= $curr_spec == 'SWIMMING' ? 'selected' : '' ?>>Swimming</option>
                            <option value="BADMINTON" <?= $curr_spec == 'BADMINTON' ? 'selected' : '' ?>>Badminton</option>
                            <option value="MODULAR" <?= $curr_spec == 'MODULAR' ? 'selected' : '' ?>>Modular</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 3fr; gap: 15px; margin-top: 15px; align-items: end;">
                    <div class="input-group">
                        <label class="small font-weight-bold">Room</label>
                        <input type="text" name="room_name" class="form-control form-control-sm" required placeholder="Room #" value="<?= htmlspecialchars($_POST['room_name'] ?? '') ?>">
                    </div>
                    <div class="input-group">
                        <label class="small font-weight-bold">Start</label>
                        <input type="time" name="time_start" class="form-control form-control-sm" required value="<?= $_POST['time_start'] ?? '' ?>">
                    </div>
                    <div class="input-group">
                        <label class="small font-weight-bold">End</label>
                        <input type="time" name="time_end" class="form-control form-control-sm" required value="<?= $_POST['time_end'] ?? '' ?>">
                    </div>
                    <div class="input-group">
                        <label class="small font-weight-bold">Days</label>
                        <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                            <?php foreach (['mon' => 'M', 'tue' => 'T', 'wed' => 'W', 'thu' => 'Th', 'fri' => 'F', 'sat' => 'S', 'sun' => 'Sun'] as $k => $v): ?>
                                <label style="font-size: 0.7rem; border: 1px solid #ddd; padding: 2px 5px; border-radius: 3px; cursor: pointer;">
                                    <input type="checkbox" name="days[<?= $k ?>]" <?= isset($_POST['days'][$k]) ? 'checked' : '' ?>> <?= $v ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <button type="submit" name="add_schedule" class="login-btn" style="width: auto; padding: 8px 25px; margin-top: 15px;">
                    <i class="fas fa-save"></i> Save Schedule
                </button>
            </form>
        </div>

        <div class="table-responsive">
            <table id="schedTable" class="display">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th>Code</th>
                        <th>Course Title</th>
                        <th>Instructor</th>
                        <th>Schedule</th>
                        <th>Room</th>
                        <th class="no-sort">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT s.*, sec.section_name, c.course_code, c.course_title, u.lastname, u.firstname
                            FROM schedules s
                            LEFT JOIN sections sec ON s.section_id = sec.id
                            LEFT JOIN courses c ON s.course_id = c.course_id
                            LEFT JOIN users u ON s.faculty_id = u.id
                            WHERE s.school_year = '$active_sy' AND s.semester = '$active_semester'";
                    $res = $conn->query($sql);
                    while ($row = $res->fetch_assoc()):
                        $days = [];
                        if ($row['day_mon']) $days[] = "M";
                        if ($row['day_tue']) $days[] = "T";
                        if ($row['day_wed']) $days[] = "W";
                        if ($row['day_thu']) $days[] = "Th";
                        if ($row['day_fri']) $days[] = "F";
                        if ($row['day_sat']) $days[] = "S";
                        if ($row['day_sun']) $days[] = "Sun";
                    ?>
                        <tr>
                            <td>
                                <strong><?= $row['section_name'] ?></strong>
                                <?php if ($row['special'] !== 'N/A'): ?>
                                    <br><span class="special-badge"><?= $row['special'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="prog-pill"><?= $row['course_code'] ?></span></td>
                            <td><?= $row['course_title'] ?></td>
                            <td><?= $row['lastname'] ?>, <?= $row['firstname'] ?></td>
                            <td>
                                <span style="color: var(--cdl-green); font-weight: bold;"><?= implode('', $days) ?></span><br>
                                <small><?= date("g:i A", strtotime($row['time_start'])) ?> - <?= date("g:i A", strtotime($row['time_end'])) ?></small>
                            </td>
                            <td><?= $row['room_name'] ?></td>
                            <td>
                                <a href="edit_schedule.php?id=<?= $row['id'] ?>" class="text-success"><i class="fas fa-edit"></i></a>
                                <a href="../actions/delete_schedule.php?id=<?= $row['id'] ?>" class="text-danger" onclick="return confirm('Remove?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#schedTable').DataTable({
                "pageLength": 10,
                "order": [
                    [0, "asc"]
                ],
                "columnDefs": [{
                    "targets": 'no-sort',
                    "orderable": false
                }]
            });
        });
    </script>
</body>

</html>