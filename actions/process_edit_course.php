<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

if (isset($_POST['btn_update_course'])) {
    $course_id      = (int)$_POST['course_id'];
    $curr_id        = (int)$_POST['curriculum_id'];
    $course_code    = strtoupper(trim($_POST['course_code']));
    $course_title   = mysqli_real_escape_string($conn, $_POST['course_title']);
    $lec_units      = (int)$_POST['lec_units'];
    $lab_units      = (int)$_POST['lab_units'];
    $year_level     = (int)$_POST['year_level'];
    $semester       = mysqli_real_escape_string($conn, $_POST['semester']);
    
    // Capture the array of prerequisite IDs from the multi-select
    $pre_requisites = isset($_POST['pre_requisites']) ? $_POST['pre_requisites'] : [];

    $conn->begin_transaction();

    try {
        // 1. Update Global Course Data 
        // Note: We removed pre_requisite string column update as it's now a separate table
        $stmt1 = $conn->prepare("UPDATE courses SET course_code=?, course_title=?, lec_units=?, lab_units=? WHERE course_id=?");
        $stmt1->bind_param("ssiii", $course_code, $course_title, $lec_units, $lab_units, $course_id);
        $stmt1->execute();
        $log_details = "Updated Course $course_code, $course_title";

        // CALL THE LOG FUNCTION
        log_system_activity($conn, 'UPDATE_COURSE', $log_details);
        // 2. Update Curriculum Placement
        $stmt2 = $conn->prepare("UPDATE program_curriculum SET year_level=?, semester=? WHERE id=?");
        $stmt2->bind_param("isi", $year_level, $semester, $curr_id);
        $stmt2->execute();
        
        // 3. Update Multi-Prerequisites
        // First, wipe existing prerequisites for this course
        $del_pre = $conn->prepare("DELETE FROM course_prerequisites WHERE course_id = ?");
        $del_pre->bind_param("i", $course_id);
        $del_pre->execute();

        // Then, insert the new selections
        if (!empty($pre_requisites)) {
            $ins_pre = $conn->prepare("INSERT INTO course_prerequisites (course_id, prerequisite_course_id) VALUES (?, ?)");
            foreach ($pre_requisites as $pre_id) {
                // Prevent a course from being its own prerequisite
                if ($pre_id == $course_id) continue; 
                
                $ins_pre->bind_param("ii", $course_id, $pre_id);
                $ins_pre->execute();
            }
        }

        $conn->commit();
        header("Location: ../admin/manage_courses.php?msg=updated");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        // Redirect with error message
        header("Location: ../admin/manage_courses.php?msg=error&details=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../admin/manage_courses.php");
    exit();
}