<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

if (isset($_POST['btn_save_course'])) {
    $program_code  = mysqli_real_escape_string($conn, $_POST['program_code']);
    $course_code   = strtoupper(trim(mysqli_real_escape_string($conn, $_POST['course_code'])));
    $course_title  = mysqli_real_escape_string($conn, $_POST['course_title']);
    $lec_units     = (int)$_POST['lec_units'];
    $lab_units     = (int)$_POST['lab_units'];
    $year_level    = (int)$_POST['year_level'];
    $semester      = mysqli_real_escape_string($conn, $_POST['semester']);
    // This is now an array from the multi-select
    $pre_requisites = isset($_POST['pre_requisites']) ? $_POST['pre_requisites'] : [];

    $conn->begin_transaction();

    try {
        // 1. Get the Program ID
        $get_prog = $conn->prepare("SELECT id FROM programs WHERE program_code = ?");
        $get_prog->bind_param("s", $program_code);
        $get_prog->execute();
        $prog_data = $get_prog->get_result()->fetch_assoc();

        if (!$prog_data) {
            throw new Exception("Error: Program code not found.");
        }
        $program_id = $prog_data['id'];

        // 2. CHECK IF THE COURSE EXISTS IN THE MASTER LIST
        $check_course = $conn->prepare("SELECT course_id FROM courses WHERE course_code = ? LIMIT 1");
        $check_course->bind_param("s", $course_code);
        $check_course->execute();
        $course_res = $check_course->get_result();

        if ($course_res->num_rows > 0) {
            $course_id = $course_res->fetch_assoc()['course_id'];
        } else {
            // Create new course (Note: We keep the old column for backward compatibility or set to NULL)
            $stmt1 = $conn->prepare("INSERT INTO courses (program_code, course_code, course_title, lec_units, lab_units) VALUES (?, ?, ?, ?, ?)");
            $stmt1->bind_param("sssii", $program_code, $course_code, $course_title, $lec_units, $lab_units);
            $stmt1->execute();
            $course_id = $conn->insert_id;

            $log_details = "Added course with code $course_code and title $course_title";
            // CALL THE LOG FUNCTION
            log_system_activity($conn, 'UPDATE_GRADE', $log_details);
        }

        // 3. Handle Multiple Prerequisites
        // Clear existing links for this course (useful if re-saving)
        $del_pre = $conn->prepare("DELETE FROM course_prerequisites WHERE course_id = ?");
        $del_pre->bind_param("i", $course_id);
        $del_pre->execute();

        if (!empty($pre_requisites)) {
            $ins_pre = $conn->prepare("INSERT INTO course_prerequisites (course_id, prerequisite_course_id) VALUES (?, ?)");
            foreach ($pre_requisites as $pre_id) {
                if ($pre_id == "" || $pre_id == "None") continue;
                $ins_pre->bind_param("ii", $course_id, $pre_id);
                $ins_pre->execute();
            }
        }

        // 4. THE VALIDATION: Check if this course is already in THIS specific program
        $check_duplicate = $conn->prepare("SELECT id FROM program_curriculum WHERE program_id = ? AND course_id = ?");
        $check_duplicate->bind_param("ii", $program_id, $course_id);
        $check_duplicate->execute();

        if ($check_duplicate->get_result()->num_rows > 0) {
            throw new Exception("Validation Error: This course is already part of the $program_code curriculum.");
        }

        // 5. Link it to the curriculum
        $stmt2 = $conn->prepare("INSERT INTO program_curriculum (program_id, program_code, course_id, year_level, semester) VALUES (?, ?, ?, ?, ?)");
        $stmt2->bind_param("isiis", $program_id, $program_code, $course_id, $year_level, $semester);
        $stmt2->execute();

        $conn->commit();
        header("Location: manage_courses.php?msg=success");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = urlencode($e->getMessage());
        header("Location: add_course.php?error=$error_msg");
        exit();
    }
}

// FETCH DATA FOR DROPDOWNS
$programs_res = $conn->query("SELECT id, program_code, program_name FROM programs WHERE status = 'active' ORDER BY program_code ASC");
$all_courses_res = $conn->query("SELECT course_id, course_code, course_title FROM courses ORDER BY course_code ASC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Course | CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container {
            max-width: 700px;
            margin: 40px auto;
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .section-label {
            color: #2d5a27;
            font-weight: bold;
            border-bottom: 2px solid #eef5ee;
            padding-bottom: 5px;
            margin-bottom: 20px;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        .select2-container .select2-selection--single,
        .select2-container .select2-selection--multiple {
            min-height: 38px !important;
            border: 1px solid #ced4da !important;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #2d5a27;
            border-color: #1e3d1a;
            color: #fff;
            padding: 2px 8px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #fff;
            margin-right: 5px;
        }
    </style>
</head>

<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="form-container">
            <h4 class="text-success font-weight-bold mb-4"><i class="fas fa-plus-circle mr-2"></i>Add New Course</h4>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="section-label">1. Program Assignment</div>
                <div class="form-group mb-4">
                    <label class="font-weight-bold text-muted small">Target Program</label>
                    <select name="program_code" class="form-control select2-dropdown" required>
                        <option value="">-- Select Program --</option>
                        <?php while ($p = $programs_res->fetch_assoc()): ?>
                            <option value="<?= $p['program_code'] ?>"><?= $p['program_code'] ?> - <?= $p['program_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="section-label">2. Course Details</div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold text-muted small">Course Code</label>
                        <input type="text" name="course_code" class="form-control" required placeholder="GE 01">
                    </div>
                    <div class="col-md-8 form-group">
                        <label class="font-weight-bold text-muted small">Course Title</label>
                        <input type="text" name="course_title" class="form-control" required placeholder="Understanding the Self">
                    </div>
                </div>

                <div class="row">
                    <div class="col-6 form-group">
                        <label class="font-weight-bold text-muted small">Lec Units</label>
                        <input type="number" name="lec_units" class="form-control" value="0" min="0">
                    </div>
                    <div class="col-6 form-group">
                        <label class="font-weight-bold text-muted small">Lab Units</label>
                        <input type="number" name="lab_units" class="form-control" value="0" min="0">
                    </div>
                </div>

                <div class="section-label mt-3">3. Curriculum Placement</div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold text-muted small">Year Level</label>
                        <select name="year_level" class="form-control select2-dropdown" required>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold text-muted small">Semester</label>
                        <select name="semester" class="form-control select2-dropdown" required>
                            <option value="1st Semester">1st Semester</option>
                            <option value="2nd Semester">2nd Semester</option>
                        </select>
                    </div>
                </div>

                <div class="form-group mt-3">
                    <label class="font-weight-bold text-muted small">Pre-requisite Subject(s)</label>
                    <select name="pre_requisites[]" class="form-control select2-multiple" multiple="multiple">
                        <?php
                        if ($all_courses_res && $all_courses_res->num_rows > 0):
                            $all_courses_res->data_seek(0);
                            while ($c = $all_courses_res->fetch_assoc()):
                        ?>
                                <option value="<?= $c['course_id'] ?>">
                                    <?= htmlspecialchars($c['course_code']) ?> | <?= htmlspecialchars($c['course_title']) ?>
                                </option>
                        <?php
                            endwhile;
                        endif;
                        ?>
                    </select>
                    <small class="text-muted">You can select multiple subjects. Leave blank if none.</small>
                </div>

                <div class="mt-5">
                    <button type="submit" name="btn_save_course" class="btn btn-success btn-block btn-lg shadow-sm font-weight-bold">
                        SAVE COURSE
                    </button>
                    <a href="manage_courses.php" class="btn btn-block btn-outline-secondary mt-2 text-uppercase small">Back to List</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.select2-dropdown').select2({
                width: '100%'
            });

            $('.select2-multiple').select2({
                placeholder: "Search for subjects...",
                allowClear: true,
                width: '100%'
            });
        });
    </script>
</body>

</html>