<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}


function checkAccess($allowed_levels) {
    checkLogin();
    if (!in_array($_SESSION['level'], $allowed_levels)) {
        // Redirect to a "denied" page or dashboard if they don't have permission
        echo "<script>alert('Unauthorized Access'); window.location='dashboard.php';</script>";
        exit();
    }
}

// Automatically check if user is logged in when this file is included
checkLogin();
?>
<?php

$timeout_duration = 1200;

if (isset($_SESSION['LAST_ACTIVITY'])) {
    $elapsed_time = time() - $_SESSION['LAST_ACTIVITY'];
    if ($elapsed_time > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: ../login.php?reason=timeout");
        exit();
    }
}
// Update last activity time stamp
$_SESSION['LAST_ACTIVITY'] = time();