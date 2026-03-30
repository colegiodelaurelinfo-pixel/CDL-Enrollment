<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Security check
if ($_SESSION['level'] !== 'ADMINISTRATOR') {
    exit("Unauthorized access");
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = (int)$_GET['id'];
    $current_status = $_GET['status'];
    
    // Switch status
    $new_status = ($current_status == 'active') ? 'inactive' : 'active';

    $stmt = $conn->prepare("UPDATE programs SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);

    if ($stmt->execute()) {
        // Redirect back to the admin page
        header("Location: ../admin/manage_programs.php?msg=updated");
    } else {
        header("Location: ../admin/manage_programs.php?msg=error");
    }
    exit();
}