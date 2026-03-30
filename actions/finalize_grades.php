<?php
require_once '../includes/config.php';
session_start();
function getPointEquivalent($f)
{
    if ($f == null || $f === "") return "---";
    if ($f >= 98) return "1.00";
    if ($f >= 95) return "1.25";
    if ($f >= 92) return "1.50";
    if ($f >= 89) return "1.75";
    if ($f >= 86) return "2.00";
    if ($f >= 83) return "2.25";
    if ($f >= 79) return "2.50";
    if ($f >= 76) return "2.75";
    if ($f == 75) return "3.00";
    return "5.00";
}
// Ensure user is Faculty and parameters exist
if ($_SESSION['level'] === 'FACULTY' && isset($_GET['subject']) && isset($_GET['sect'])) {

    $subj_id = (int)$_GET['subject'];
    $sy      = $_GET['sy'];
    $sem     = $_GET['sem'];
    $sect_id = (int)$_GET['sect']; // This is the numeric ID from the URL

    // 1. Fetch the section name because the 'students' table stores names, not IDs
    $name_query = $conn->prepare("SELECT section_name FROM sections WHERE id = ?");
    $name_query->bind_param("i", $sect_id);
    $name_query->execute();
    $name_result = $name_query->get_result()->fetch_assoc();
    $section_name = $name_result['section_name'] ?? '';

    if (!empty($section_name)) {
        // 2. Move temp to final for the specific class roster using the looked-up name
        // 2. Move temp to final using SQL CASE logic
        $sql = "UPDATE grades g
        JOIN students s ON g.student_id = s.student_id
        SET g.final_grade = CASE 
            WHEN g.temp_final_grade >= 98 THEN '1.00'
            WHEN g.temp_final_grade >= 95 THEN '1.25'
            WHEN g.temp_final_grade >= 92 THEN '1.50'
            WHEN g.temp_final_grade >= 89 THEN '1.75'
            WHEN g.temp_final_grade >= 86 THEN '2.00'
            WHEN g.temp_final_grade >= 83 THEN '2.25'
            WHEN g.temp_final_grade >= 79 THEN '2.50'
            WHEN g.temp_final_grade >= 76 THEN '2.75'
            WHEN g.temp_final_grade = 75  THEN '3.00'
            ELSE '5.00'
        END
        WHERE g.course_id = ? 
        AND g.academic_year = ? 
        AND g.semester = ? 
        AND s.section = ?
        AND g.temp_final_grade IS NOT NULL";

        $stmt = $conn->prepare($sql);
        // Using $section_name (string) for the fourth parameter
        $stmt->bind_param("isss", $subj_id, $sy, $sem, $section_name);

        if ($stmt->execute()) {
            // We send back the section ID and the msg=finalized flag
            $log_details = "Locked and finalized grading sheet for Course: $subj_id, Section: $section_name";
            log_system_activity($conn, 'FINALIZED_GRADES', $log_details);
            header("Location: ../faculty/add_grades.php?section=" . $sect_id . "&subject=" . $subj_id . "&btn_load=1&msg=finalized");
            exit();
        } else {
            die("Error finalizing grades: " . $conn->error);
        }
    } else {
        die("Invalid Section ID.");
    }
} else {
    header("Location: ../index.php");
    exit();
}
