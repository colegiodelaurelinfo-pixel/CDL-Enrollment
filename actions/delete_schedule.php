<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Restricted to Administrators
checkAccess(['ADMINISTRATOR']);

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Prepared statement for security
    $stmt = $conn->prepare("DELETE FROM schedules WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // 'deleted' is a key your navbar recognizes to show a green success box
        $log_details = "Deleted schedule $id";

        // CALL THE LOG FUNCTION
        log_system_activity($conn, 'DELETED_SCHEDULE', $log_details);
        header("Location: ../admin/manage_schedules.php?msg=deleted");
    } else {
        // If something goes wrong with the database
        header("Location: ../admin/manage_schedules.php?msg=error");
    }
} else {
    // If someone tries to access the file directly without an ID
    header("Location: ../admin/manage_schedules.php");
}
exit();
