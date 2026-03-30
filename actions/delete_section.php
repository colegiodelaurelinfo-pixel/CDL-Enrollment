<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// 1. Security Check: Only Administrators can delete
checkAccess(['ADMINISTRATOR']);

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // 2. Fetch the section name and details
    $stmt = $conn->prepare("SELECT section_name FROM sections WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $section = $result->fetch_assoc();

    if ($section) {
        $section_name = $section['section_name'];

        // 3. PRE-DELETION CHECK: Are there students in this section?
        // This prevents breaking your enrollment records.
        $check_students = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE section = ?");
        $check_students->bind_param("s", $section_name);
        $check_students->execute();
        $student_count = $check_students->get_result()->fetch_assoc()['count'];

        if ($student_count > 0) {
            echo "<script>
                alert('Cannot delete section $section_name. There are currently $student_count student(s) enrolled in it. Please reassign them before deleting.');
                window.location.href = '../admin/manage_sections.php';
            </script>";
            exit();
        }

        // 4. Perform the deletion
        $delete = $conn->prepare("DELETE FROM sections WHERE id = ?");
        $delete->bind_param("i", $id);

        if ($delete->execute()) {
            $log_details = "Deleted section $section_name";
            // CALL THE LOG FUNCTION
            log_system_activity($conn, 'DELETED_SECTION', $log_details);
            echo "<script>
                alert('Section $section_name has been deleted.');
                window.location.href = '../admin/manage_sections.php';
            </script>";
        } else {
            // General DB error (e.g., Database connection lost or foreign key constraint in other tables)
            echo "<script>
                alert('Error: Database could not complete the request.');
                window.location.href = '../admin/manage_sections.php';
            </script>";
        }
    } else {
        header("Location: ../admin/manage_sections.php");
    }
} else {
    header("Location: ../admin/manage_sections.php");
}
exit();
