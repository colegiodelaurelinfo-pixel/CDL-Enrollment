<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['student_id'])) {
    $sid = $_POST['student_id'];
    $new_year = (int)$_POST['new_year_level'];
    $new_section = $_POST['new_section'];

    $stmt = $conn->prepare("UPDATE students SET year_level = ?, section = ? WHERE student_id = ?");
    $stmt->bind_param("iss", $new_year, $new_section, $sid);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "Error: " . $conn->error;
    }
}
