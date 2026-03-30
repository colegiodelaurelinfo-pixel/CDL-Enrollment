<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Security: Only Admins can edit users
if ($_SESSION['level'] !== 'ADMINISTRATOR') {
    header("Location: ../admin/dashboard.php");
    exit();
}

if (isset($_POST['btn_update_user'])) {
    $id         = (int)$_POST['user_id'];
    $firstname  = mysqli_real_escape_string($conn, $_POST['firstname']);
    $middlename = mysqli_real_escape_string($conn, $_POST['middlename']);
    $lastname   = mysqli_real_escape_string($conn, $_POST['lastname']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $faculty_id = mysqli_real_escape_string($conn, $_POST['faculty_id']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $level      = $_POST['level']; // ADMINISTRATOR, FACULTY, or STAFF
    $status     = $_POST['status']; // active or inactive

    // 1. Update query including new columns
    $sql = "UPDATE users SET 
            firstname = ?, 
            middlename = ?,
            lastname = ?, 
            email = ?, 
            faculty_id = ?, 
            department = ?, 
            level = ?, 
            status = ? 
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    
    // Updated bind_param: 7 strings ('s') and 1 integer ('i') for the ID
    $stmt->bind_param("ssssssssi", 
        $firstname, 
        $middlename,
        $lastname, 
        $email, 
        $faculty_id, 
        $department, 
        $level, 
        $status, 
        $id
    );

    if ($stmt->execute()) {
        // Success! Redirect back to management list
        header("Location: ../admin/manage_users.php?msg=user_updated");
        $log_details = "Updated the data of User $lastname, $firstname, ' ' $middlename with email: $email and level: $level";
        // CALL THE LOG FUNCTION
        log_system_activity($conn, 'UPDATE_USER', $log_details);
        exit();
    } else {
        // Error handling (e.g., if faculty_id is a duplicate)
        header("Location: ../admin/manage_users.php?msg=error&details=" . urlencode($conn->error));
        exit();
    }
} else {
    header("Location: ../admin/manage_users.php");
    exit();
}