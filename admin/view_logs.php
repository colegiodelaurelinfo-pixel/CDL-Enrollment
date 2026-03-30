<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Only Admin should see this
if ($_SESSION['level'] !== 'ADMINISTRATOR') {
    header("Location: ../dashboard.php");
    exit();
}


$query = "SELECT email, firstname, lastname, level, last_login 
          FROM users 
          WHERE last_login IS NOT NULL 
          AND TRIM(last_login) != '' 
          ORDER BY last_login DESC 
          LIMIT 100";

$logs = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Login Logs | CDL</title>
    <link rel="icon" type="image/png" href="../assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="../assets/img/CDL_seal.png">
</head>

<body style="background: #f4f7f6;">
    <?php include 'navbar.php'; ?>
    <div class="container" style="margin-top: 30px; max-width: 1000px;">
        <div class="form-card-modern" style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; color: #1a3617;"><i class="fas fa-history"></i> User Login History</h3>
                <span style="font-size: 0.85rem; color: #666;">Showing last 100 sign-ins</span>
            </div>

            <table class="admin-table" style="width: 100%;">
                <thead>
                    <tr style="background: #2d5a27; color: white;">
                        <th style="padding: 12px; text-align: left;">Name</th>
                        <th style="padding: 12px; text-align: left;">Username</th>
                        <th style="padding: 12px; text-align: center;">Account Level</th>
                        <th style="padding: 12px; text-align: right;">Last Login Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while ($l = $logs->fetch_assoc()):
                            // Double-check the string isn't just whitespace before processing
                            $raw_login = trim($l['last_login']);
                            if (empty($raw_login)) continue;

                            $login_time = strtotime($raw_login);
                            $is_online = (time() - $login_time < 60);
                        ?>
                            <tr style="border-bottom: 1px solid #eee; font-size: 0.95rem;">
                                <td style="padding: 12px;">
                                    <strong><?= htmlspecialchars($l['lastname'] . ", " . $l['firstname']) ?></strong>
                                    <?php if ($is_online): ?>
                                        <span style="margin-left:8px; height:8px; width:8px; background:#27ae60; border-radius:50%; display:inline-block;" title="Active recently"></span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; color: #666; font-family: monospace;"><?= htmlspecialchars($l['email']) ?></td>
                                <td style="padding: 12px; text-align: center;">
                                    <span style="background: #f0f0f0; color: #333; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; border: 1px solid #ddd;">
                                        <?= $l['level'] ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; text-align: right; font-weight: bold; color: #2d5a27;">
                                    <?= date('M d, Y | h:i A', $login_time) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding:50px; color: #ccc;">No login history recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>