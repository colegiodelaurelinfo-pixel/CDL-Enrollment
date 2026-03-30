<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

if ($_SESSION['level'] !== 'ADMINISTRATOR') {
    header("Location: dashboard.php");
    exit();
}

$message = "";

// Handle Form Submission
if (isset($_POST['btn_save_settings'])) {
    $sy = mysqli_real_escape_string($conn, $_POST['active_sy']);
    $sem = mysqli_real_escape_string($conn, $_POST['active_semester']);

    // Update School Year
    $stmt1 = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'active_sy'");
    $stmt1->bind_param("s", $sy);

    // Update Semester
    $stmt2 = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'active_semester'");
    $stmt2->bind_param("s", $sem);

    if ($stmt1->execute() && $stmt2->execute()) {
        $message = "<div class='alert success'><i class='fas fa-check'></i> Settings updated! System is now running S.Y. $sy</div>";
        $log_details = "Updated system settings to $sy and $sem";

        // CALL THE LOG FUNCTION
        log_system_activity($conn, 'UPDATE_GRADE', $log_details);
        // Refresh the local data immediately for the form display
    } else {
        $message = "<div class='alert danger'>Error updating database.</div>";
    }
}

// Fetch Current Settings (Freshly)
$res = $conn->query("SELECT * FROM system_settings");
$current = [];
while ($row = $res->fetch_assoc()) {
    $current[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>System Settings | CDL</title>
    <link rel="icon" type="image/png" href="../assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="../assets/img/CDL_seal.png">
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container" style="margin-top: 50px; max-width: 600px;">
        <div class="form-card-modern">
            <h3><i class="fas fa-gears"></i> Global System Settings</h3>
            <div class="warning-box" style="
    background-color: #fff4f4;
    border-left: 5px solid var(--cdl-red);
    padding: 15px 20px;
    border-radius: 8px;
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 15px;
">
                <i class="fas fa-exclamation-triangle" style="color: var(--cdl-red); font-size: 1.5rem;"></i>
                <div>
                    <strong style="color: var(--cdl-red); display: block; margin-bottom: 2px;">Critical System Notice</strong>
                    <p style="color: #666; font-size: 0.9rem; margin: 0;">
                        Changing these values will affect the entire system, including <strong>TOR generation</strong> and <strong>active enrollments</strong>.
                    </p>
                </div>
            </div>
            <hr>

            <?= $message ?>

            <form method="POST" style="margin-top: 20px;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Active School Year</label>
                    <input type="text" name="active_sy" class="form-control"
                        value="<?= htmlspecialchars($current['active_sy'] ?? '') ?>"
                        placeholder="e.g., 2025-2026" required>
                    <small>Current: <strong><?= $current['active_sy'] ?? 'Not Set' ?></strong></small>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Active Semester</label>
                    <select name="active_semester" class="form-control" required>
                        <option value="1st Semester" <?= ($current['active_semester'] == '1st Semester') ? 'selected' : '' ?>>1st Semester</option>
                        <option value="2nd Semester" <?= ($current['active_semester'] == '2nd Semester') ? 'selected' : '' ?>>2nd Semester</option>
                        <option value="Summer" <?= ($current['active_semester'] == 'Summer') ? 'selected' : '' ?>>Summer</option>
                    </select>
                </div>

                <button type="submit" name="btn_save_settings" class="login-btn" style="background: #2d5a27; width: 100%;">
                    <i class="fas fa-save"></i> Save Configuration
                </button>
            </form>
        </div>
    </div>

    <style>
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success {
            background: #e8f5e9;
            color: #2d5a27;
            border: 1px solid #2d5a27;
        }

        .danger {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #c62828;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
    </style>
</body>

</html>