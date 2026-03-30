<?php
session_start();
require_once 'config.php';

// Optional: Log the logout event to your new logs table
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, 'LOGOUT', 'User session expired or logged out', ?)");
    $stmt->bind_param("ss", $uid, $ip);
    $stmt->execute();
}

session_unset();
session_destroy();
header("Location: ../index.php");
exit();