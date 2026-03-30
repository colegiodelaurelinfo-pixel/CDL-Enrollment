<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

if (isset($_POST['btn_save'])) {
    $sid = $_POST['student_id'];
    $course_id = $_POST['course_id'];
    $section_id = $_POST['section_id'];
    $sy = $_POST['sy'];
    $sem = $_POST['sem'];

    $status_mode = $_POST['grade_status'] ?? 'REGULAR';

    if ($status_mode === 'REGULAR') {
        $grade = $_POST['grade'];
        $remarks = ($grade >= 75) ? 'PASSED' : 'FAILED';
    } else {
        $grade = $status_mode; // "INC", "DO", or "DU"
        $remarks = $status_mode;
    }

    // 1. Check if record exists
    $check = $conn->prepare("SELECT grade_id FROM grades WHERE student_id = ? AND course_id = ? AND academic_year = ? AND semester = ?");
    $check->bind_param("siss", $sid, $course_id, $sy, $sem);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        // --- FIXED BIND_PARAM ORDER ---
        // SQL: SET temp_final_grade(1), remarks(2) WHERE student_id(3), course_id(4), academic_year(5), semester(6)
        $stmt = $conn->prepare("UPDATE grades SET temp_final_grade = ?, remarks = ? WHERE student_id = ? AND course_id = ? AND academic_year = ? AND semester = ?");

        // Ensure student_id uses "s" if it's a string ID like "2024-0001"
        $stmt->bind_param("sssiss", $grade, $remarks, $sid, $course_id, $sy, $sem);
        $log_details = "Updated grade for Student $sid, $course_id with grade: $grade | $remarks";
        log_system_activity($conn, 'UPDATE_GRADES', $log_details);
    } else {
        // INSERT
        $stmt = $conn->prepare("INSERT INTO grades (student_id, course_id, academic_year, semester, temp_final_grade, remarks) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sissss", $sid, $course_id, $sy, $sem, $grade, $remarks);
        $log_details = "ADDED grade for Student $sid, $course_id with grade: $grade | $remarks";
        log_system_activity($conn, 'ADDED_GRADES', $log_details);
    }

    if ($stmt->execute()) {
        // Get the schedule ID for redirect
        $get_sched = $conn->prepare("SELECT id FROM schedules WHERE course_id = ? AND section_id = ? AND school_year = ? AND semester = ? LIMIT 1");
        $get_sched->bind_param("iiss", $course_id, $section_id, $sy, $sem);
        $get_sched->execute();
        $sched_row = $get_sched->get_result()->fetch_assoc();
        $sched_id = $sched_row['id'];

        header("Location: ../faculty/add_grades.php?sched_id=$sched_id&btn_load=1&msg=success");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}
