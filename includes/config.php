<?php
$host = 'localhost';
$db   = 'colegio_de_laurel';
$user = 'root';
$pass = ''; // Default for XAMPP is empty

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Fetch the current active term
$active_query = "SELECT * FROM academic_settings WHERE is_active = 1 LIMIT 1";
$active_result = $conn->query($active_query);
$current_term = $active_result->fetch_assoc();

// This is where we add the "S.Y." for the UI
$display_sy = "S.Y. " . ($current_term['school_year'] ?? "N/A");
$display_sem = $current_term['semester'] ?? "N/A";

// config.php
$settings_query = "SELECT school_year, semester FROM academic_settings WHERE is_active = 1 LIMIT 1";
$settings_result = $conn->query($settings_query);

if ($settings_result->num_rows > 0) {
    $current_term = $settings_result->fetch_assoc();
    $display_sy = "S.Y. " . $current_term['school_year'];
    $display_sem = $current_term['semester'];
} else {
    // Fallback if no year is set as active
    $display_sy = "S.Y. Not Set";
    $display_sem = "";
}

function insert_log($conn, $action, $details, $user_id = 'System') {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $user_id, $action, $details, $ip);
    $stmt->execute();
}

/**
 * Records a user action into the system_logs table
 * @param mysqli $conn The database connection object
 * @param string $action The category of action (e.g., 'UPDATE_GRADE', 'DELETE_STUDENT')
 * @param string $details Specific description of what was changed
 */
function log_system_activity($conn, $action, $details)
{
    // 1. Get User ID from Session (fallback to '0' or 'System' if not logged in)
    $user_id = $_SESSION['user_id'] ?? 'SYSTEM';

    // 2. Get IP Address
    $ip_address = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }

    // 3. Prepare and Execute the Insert
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $user_id, $action, $details, $ip_address);
    $stmt->execute();
    $stmt->close();
}
?>

