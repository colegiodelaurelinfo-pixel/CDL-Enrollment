<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
include '../admin/navbar.php';
// Security: Only Faculty
if ($_SESSION['level'] !== 'FACULTY') {
    header("Location: ../index.php");
    exit();
}



$active_sy = $display_sy;
$active_semester = $display_sem;
$faculty_id = $_SESSION['user_id'];

// 1. Get all classes assigned to this faculty
$class_list_query = $conn->prepare("
    SELECT 
        s.id AS sched_id, 
        s.course_id, 
        s.section_id, 
        c.course_code, 
        c.course_title, 
        sec.section_name,
        s.special
    FROM schedules s
    JOIN courses c ON s.course_id = c.course_id
    JOIN sections sec ON s.section_id = sec.id
    WHERE s.faculty_id = ? AND s.school_year = ? AND s.semester = ?
    ORDER BY sec.section_name ASC, c.course_code ASC
");
$class_list_query->bind_param("iss", $faculty_id, $active_sy, $active_semester);
$class_list_query->execute();
$my_classes = $class_list_query->get_result();

$students_list = null;
$filter_active = false;
$selected_sched = $_GET['sched_id'] ?? '';

$current_course_id = null;
$current_section_id = null;
$current_section_name = "";

// 2. LOAD LOGIC: Updated to handle LGU (MODULAR) and Special students
if (isset($_GET['btn_load']) && !empty($selected_sched)) {
    $info_stmt = $conn->prepare("
        SELECT s.course_id, s.section_id, sec.section_name, s.special 
        FROM schedules s 
        JOIN sections sec ON s.section_id = sec.id 
        WHERE s.id = ? AND s.faculty_id = ?
    ");
    $info_stmt->bind_param("ii", $selected_sched, $faculty_id);
    $info_stmt->execute();
    $info = $info_stmt->get_result()->fetch_assoc();

    if ($info) {
        $filter_active = true;
        $current_section_name = $info['section_name'];
        $current_course_id = $info['course_id'];
        $current_section_id = $info['section_id'];
        $special_type = $info['special'];

        // BASE QUERY (Common for all types)
        $base_sql = "SELECT st.student_id, st.firstname, st.lastname, st.middlename, 
                            g.temp_final_grade, g.final_grade, g.remarks
                     FROM students st
                     LEFT JOIN grades g ON st.student_id = g.student_id 
                        AND g.course_id = ? 
                        AND g.academic_year = ? 
                        AND g.semester = ?";

        // CONDITION 1: MODULAR / LGU Logic
        if ($special_type === 'MODULAR') {
            $stmt = $conn->prepare($base_sql . " WHERE st.lgu = 'YES' AND st.status = 'Enrolled' ORDER BY st.lastname ASC");
            $stmt->bind_param("iss", $current_course_id, $active_sy, $active_semester);
        }
        // CONDITION 2: SPECIAL Logic (Swimming/Badminton etc.)
        else if ($special_type !== 'N/A' && !empty($special_type)) {
            $stmt = $conn->prepare($base_sql . " WHERE st.special = ? AND st.status = 'Enrolled' ORDER BY st.lastname ASC");
            $stmt->bind_param("isss", $current_course_id, $active_sy, $active_semester, $special_type);
        }
        // CONDITION 3: REGULAR Logic (Section based)
        else {
            $stmt = $conn->prepare($base_sql . " WHERE st.section = ? AND st.status = 'Enrolled' ORDER BY st.lastname ASC");
            $stmt->bind_param("isss", $current_course_id, $active_sy, $active_semester, $current_section_name);
        }

        $stmt->execute();
        $students_list = $stmt->get_result();
    }
}


function getPointEquivalent($f)
{
    if (in_array($f, ['INC', 'DO', 'DU'])) return $f;
    if ($f == null || $f === "" || $f == 0) return "---";

    $f = floatval($f);
    if ($f >= 1.0 && $f <= 5.0) return number_format($f, 2);

    if ($f >= 98) return "1.00";
    if ($f >= 95) return "1.25";
    if ($f >= 92) return "1.50";
    if ($f >= 89) return "1.75";
    if ($f >= 86) return "2.00";
    if ($f >= 83) return "2.25";
    if ($f >= 79) return "2.50";
    if ($f >= 76) return "2.75";
    if ($f >= 75) return "3.00";
    return "5.00";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Class Grading | CDL</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(3px);
        }

        .modal-content {
            background-color: white;
            margin: 8% auto;
            padding: 30px;
            border-radius: 12px;
            width: 450px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            border-top: 8px solid var(--cdl-green);
        }

        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #aaa;
            transition: 0.3s;
        }

        .close:hover {
            color: var(--cdl-red);
        }

        .btn-locked {
            padding: 8px 15px;
            background: #f5f5f5;
            color: #999;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: not-allowed;
            font-size: 0.85rem;
        }

        .badge-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid;
        }

        .passed,
        .PASSED {
            background: #e8f5e9;
            color: #2d5a27;
            border-color: #a5d6a7;
        }

        .failed,
        .FAILED {
            background: #ffebee;
            color: #c62828;
            border-color: #ef9a9a;
        }

        .preview-box {
            background: var(--cdl-bg);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid var(--cdl-gold);
        }

        .admin-table th,
        .admin-table td {
            padding: 10px;
            font-size: 0.8rem;
        }

        .student-id-text {
            font-family: monospace;
            color: #666;
            font-size: 0.85rem;
        }

        .compact-name {
            font-size: 0.9rem;
            margin: 0;
        }

        .btn-action.btn-edit {
            padding: 4px 10px;
            font-size: 0.8rem;
        }

        .table-responsive {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            overflow: hidden;
        }

        .INC {
            background: #fff3e0;
            color: #e65100;
            border-color: #ffcc80;
        }

        .DO,
        .DU {
            background: #eceff1;
            color: #546e7a;
            border-color: #cfd8dc;
        }
    </style>
</head>

<body>
  
    <div class="container">
        <div class="dashboard-header-card">
            <div style="flex-grow: 1;">
                <h2 style="color: var(--cdl-green);"><i class="fas fa-file-signature"></i> Faculty Grading Sheet</h2>
                <p style="color: var(--cdl-text-muted); margin: 0;">Manage student grades for the current academic term.</p>
            </div>
            <div style="text-align: right;">
                <span class="status-badge status-active" style="padding: 10px 20px; font-size: 0.9rem;">
                    <?= htmlspecialchars($active_sy) ?> | <?= htmlspecialchars($active_semester) ?>
                </span>
            </div>
        </div>

        <div class="form-card-modern" style="margin-bottom: 30px;">
            <form method="GET" style="display: flex; gap: 15px; align-items: flex-end;">
                <div class="form-group" style="flex-grow: 1; margin-bottom: 0;">
                    <label>Select Assigned Class & Section</label>
                    <div class="select-wrapper">
                        <select name="sched_id" class="form-control" required>
                            <option value="">-- Choose Class (Section | Subject) --</option>
                            <?php
                            $my_classes->data_seek(0);
                            while ($c = $my_classes->fetch_assoc()):
                            ?>
                                <option value="<?= $c['sched_id'] ?>" <?= ($selected_sched == $c['sched_id']) ? 'selected' : '' ?>>
                                    <?= $c['section_name'] ?> — <?= $c['course_code'] ?> (<?= $c['course_title'] ?>) <?= ($c['special'] !== 'N/A') ? "[{$c['special']}]" : "" ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <i class="fas fa-chevron-down select-icon"></i>
                    </div>
                </div>
                <div>
                    <button type="submit" name="btn_load" value="1" class="login-btn" style="width: auto; padding: 12px 25px;">
                        <i class="fas fa-sync-alt"></i> Load Students
                    </button>
                </div>
            </form>
        </div>

        <?php if ($filter_active): ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th width="15%">ID</th>
                            <th width="35%">Full Name</th>
                            <th style="text-align: center;">Grade</th>
                            <th style="text-align: center;">Point</th>
                            <th style="text-align: center;">Remarks</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_students = 0;
                        $students_with_grades = 0;
                        $all_finalized = false;

                        if ($students_list && $students_list->num_rows > 0):
                            $total_students = $students_list->num_rows;
                            while ($row = $students_list->fetch_assoc()):
                                $sid = $row['student_id'];
                                $is_locked = (!empty($row['final_grade']));
                                $raw_grade = $row['temp_final_grade'];
                                $point_equivalent = $row['final_grade'];

                                if (!empty($raw_grade) || !empty($point_equivalent)) {
                                    $students_with_grades++;
                                }
                                if (!empty($point_equivalent)) {
                                    $all_finalized = true;
                                }
                        ?>
                                <tr>
                                    <td class="student-id-text"><?= $sid ?></td>
                                    <td>
                                        <p class="compact-name"><strong><?= strtoupper($row['lastname']) ?></strong>, <?= $row['firstname'] ?></p>
                                    </td>
                                    <td style="text-align: center; font-weight: bold;">
                                        <?php
                                        if (empty($raw_grade)) {
                                            echo '<span style="color:#ccc">--</span>';
                                        } elseif (is_numeric($raw_grade)) {
                                            echo number_format((float)$raw_grade, 1);
                                        } else {
                                            echo htmlspecialchars($raw_grade);
                                        }
                                        ?>
                                    </td>
                                    <td style="text-align: center; font-weight:bold; color:var(--cdl-green);">
                                        <?= ($is_locked) ? number_format((float)$point_equivalent, 2) : getPointEquivalent($raw_grade); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if (!empty($row['remarks'])): ?>
                                            <span class="badge-status <?= strtoupper($row['remarks']) ?>"><?= $row['remarks'] ?></span>
                                        <?php else: ?>
                                            <span style="color:#ccc; font-size: 0.75rem;">PENDING</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if (!$is_locked): ?>
                                            <button onclick="openGradeModal('<?= $sid ?>', '<?= addslashes($row['lastname'] . ', ' . $row['firstname']) ?>', '<?= $raw_grade ?? '' ?>', '<?= $row['remarks'] ?? '' ?>')" class="btn-action btn-edit">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                        <?php else: ?>
                                            <i class="fas fa-lock" style="color: #bbb;" title="Locked"></i>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 30px; color: #999;">No enrolled students found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_students > 0): ?>
                <div class="dashboard-header-card" style="margin-top: 20px; border-left-color: var(--cdl-gold); background: #fffcf0;">
                    <div style="flex-grow: 1;">
                        <h4 style="margin:0; color: #856404;"><i class="fas fa-tasks"></i> Completion: <?= $students_with_grades ?> / <?= $total_students ?> Students</h4>
                        <small>You must input grades for all students before you can finalize and lock this sheet.</small>
                    </div>

                    <?php if ($all_finalized): ?>
                        <button class="btn-locked" style="width: auto; padding: 12px 30px; cursor: default; background: #e8f5e9; color: #2d5a27; border-color: #a5d6a7;">
                            <i class="fas fa-check-circle"></i> Grades Submitted & Locked
                        </button>
                    <?php elseif ($students_with_grades == $total_students): ?>
                        <button onclick="confirmFinalSubmission()" class="login-btn" style="width: auto; background: var(--cdl-red); padding: 12px 30px;">
                            <i class="fas fa-file-export"></i> Finalize & Lock Grades
                        </button>
                    <?php else: ?>
                        <button class="btn-locked" title="Complete all grades first" style="padding: 12px 30px;">
                            <i class="fas fa-exclamation-triangle"></i> Grades Incomplete
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div id="gradeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 id="modalStudentName" style="color: var(--cdl-green); margin-bottom: 5px;"></h3>
            <p style="font-size: 0.8rem; color: #666; margin-bottom: 20px;">Enter final grade or select special status.</p>

            <form action="../actions/process_single_grade.php" method="POST">
                <input type="hidden" name="student_id" id="modal_sid">
                <input type="hidden" name="course_id" value="<?= $current_course_id ?>">
                <input type="hidden" name="section_id" value="<?= $current_section_id ?>">
                <input type="hidden" name="sy" value="<?= $active_sy ?>">
                <input type="hidden" name="sem" value="<?= $active_semester ?>">

                <div class="form-group">
                    <label>Grade Status</label>
                    <select name="grade_status" id="modal_grade_status" class="form-control" onchange="toggleGradeInput(this.value)">
                        <option value="REGULAR">Regular (Numeric Grade)</option>
                        <option value="INC">Incomplete (INC)</option>
                        <option value="DO">Dropped Officially (DO)</option>
                        <option value="DU">Dropped Unofficially (DU)</option>
                    </select>
                </div>

                <div class="form-group" id="numeric_grade_container">
                    <label>Final Grade Score</label>
                    <input type="number" name="grade" id="modal_temp_final_grade" class="form-control" step="0.01" min="50" max="100" oninput="updatePreview(this.value)" autofocus>
                </div>

                <div class="preview-box">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Point Equivalent:</span>
                        <strong id="pointPreview">---</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Remarks:</span>
                        <strong id="remarkPreview">---</strong>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="btn_save" class="login-btn">Save Changes</button>
                    <button type="button" class="btn-outline" onclick="closeModal()" style="width:100%">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'success') {
            Swal.fire({
                title: 'Saved!',
                text: 'Grade updated successfully.',
                icon: 'success',
                confirmButtonColor: '#1a5e1a'
            });
        } else if (urlParams.get('msg') === 'finalized') {
            Swal.fire({
                title: 'Finalized!',
                text: 'Grades are now locked and recorded.',
                icon: 'success',
                confirmButtonColor: '#1a5e1a'
            });
        }

        function openGradeModal(id, name, currentGrade, currentRemarks) {
            document.getElementById('modal_sid').value = id;
            document.getElementById('modalStudentName').innerText = name;
            const statusSelect = document.getElementById('modal_grade_status');
            const gradeInput = document.getElementById('modal_temp_final_grade');

            if (['INC', 'DO', 'DU'].includes(currentRemarks)) {
                statusSelect.value = currentRemarks;
                gradeInput.value = "";
                toggleGradeInput(currentRemarks);
            } else {
                statusSelect.value = 'REGULAR';
                gradeInput.value = currentGrade;
                toggleGradeInput('REGULAR');
            }
            document.getElementById('gradeModal').style.display = "block";
        }

        function closeModal() {
            document.getElementById('gradeModal').style.display = "none";
        }

        function toggleGradeInput(status) {
            const gradeInput = document.getElementById('modal_temp_final_grade');
            const container = document.getElementById('numeric_grade_container');
            if (status === 'REGULAR') {
                container.style.opacity = "1";
                gradeInput.disabled = false;
                gradeInput.required = true;
                updatePreview(gradeInput.value);
            } else {
                container.style.opacity = "0.5";
                gradeInput.disabled = true;
                gradeInput.required = false;
                updatePreview("");
            }
        }

        function updatePreview(val) {
            const status = document.getElementById('modal_grade_status').value;
            const pointPrev = document.getElementById('pointPreview');
            const remarkEl = document.getElementById('remarkPreview');
            let point = "---",
                remark = "---",
                remarkColor = "#666";

            if (status !== 'REGULAR') {
                point = status;
                remark = (status === 'INC') ? "INCOMPLETE" : "DROPPED";
                remarkColor = "#f39c12";
            } else {
                let f = parseFloat(val);
                if (!val || isNaN(f)) {
                    point = "---";
                    remark = "---";
                } else if (f >= 75) {
                    remark = "PASSED";
                    remarkColor = "#2e7d32";
                    if (f >= 98) point = "1.00";
                    else if (f >= 95) point = "1.25";
                    else if (f >= 92) point = "1.50";
                    else if (f >= 89) point = "1.75";
                    else if (f >= 86) point = "2.00";
                    else if (f >= 83) point = "2.25";
                    else if (f >= 79) point = "2.50";
                    else if (f >= 76) point = "2.75";
                    else point = "3.00";
                } else {
                    point = "5.00";
                    remark = "FAILED";
                    remarkColor = "#c62828";
                }
            }
            pointPrev.innerText = point;
            remarkEl.innerText = remark;
            remarkEl.style.color = remarkColor;
        }

        function confirmFinalSubmission() {
            Swal.fire({
                title: 'Finalize Grades?',
                text: "Warning: You will not be able to edit these grades after submitting!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#c62828',
                confirmButtonText: 'Yes, Submit Grades'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `../actions/finalize_grades.php?subject=<?= $current_course_id ?>&sect=<?= $current_section_id ?>&sy=<?= $active_sy ?>&sem=<?= $active_semester ?>`;
                }
            })
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('gradeModal')) closeModal();
        }
    </script>
</body>

</html>