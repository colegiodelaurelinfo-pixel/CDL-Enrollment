<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';


$user_id = $_SESSION['user_id'];
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass !== $confirm_pass) {
        $message = "<div class='error-msg'><i class='fas fa-times-circle'></i> New passwords do not match.</div>";
    } elseif (strlen($new_pass) < 6) {
        $message = "<div class='error-msg'><i class='fas fa-info-circle'></i> Password must be at least 6 characters.</div>";
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (password_verify($current_pass, $user['password'])) {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $hashed_pass, $user_id);

            if ($update->execute()) {
                $message = "<div class='success-msg'><i class='fas fa-check-circle'></i> Password updated successfully!</div>";
                $log_details = "Updated the password";

                // CALL THE LOG FUNCTION
                log_system_activity($conn, 'UPDATE_PASSWORD', $log_details);
            }
        } else {
            $message = "<div class='error-msg'><i class='fas fa-exclamation-triangle'></i> Current password is incorrect.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Change Password | CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .pass-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .form-card-modern {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper input {
            width: 100%;
            padding: 12px;
            padding-right: 40px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #444;
        }

        .btn-update {
            background: #2d5a27;
            color: white;
            border: none;
            padding: 14px;
            width: 100%;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: 0.3s;
            display: block;
        }

        .btn-update:hover {
            background: #1e3d1a;
        }

        .status-text {
            font-size: 0.85rem;
            margin-top: 5px;
            display: block;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="pass-container">
        <div class="profile-header" style="margin-bottom: 25px; border-bottom: none;">
            <div class="profile-title">
                <i class="fas fa-key" style="background: #2d5a27; color: white; padding: 15px; border-radius: 50%;"></i>
                <div>
                    <h2 style="margin:0; color: #2d5a27;">Change Password</h2>
                    <small>Secure your account.</small>
                </div>
            </div>
        </div>

        <?= $message ?>

        <div class="form-card-modern">
            <form method="POST">
                <div class="input-group">
                    <label>Current Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="current_password" required placeholder="Enter old password">
                        <i class="fas fa-eye toggle-password"></i>
                    </div>
                </div>

                <hr style="border:0; border-top:1px solid #eee; margin: 25px 0;">

                <div class="input-group">
                    <label>New Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="new_password" required placeholder="At least 6 characters">
                        <i class="fas fa-eye toggle-password"></i>
                    </div>
                </div>

                <div class="input-group">
                    <label>Confirm New Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" required placeholder="Repeat new password">
                        <i class="fas fa-eye toggle-password"></i>
                    </div>
                    <span id="match_msg" class="status-text"></span>
                </div>

                <button type="submit" class="btn-update">
                    <i class="fas fa-save"></i> SAVE CHANGES
                </button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle Visibility
            $('.toggle-password').on('click', function() {
                const input = $(this).siblings('input');
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    $(this).removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    $(this).removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            // Real-time Match Check
            $('#confirm_password, #new_password').on('keyup', function() {
                const p1 = $('#new_password').val();
                const p2 = $('#confirm_password').val();
                if (p1 === "" || p2 === "") {
                    $('#match_msg').text('');
                } else if (p1 === p2) {
                    $('#match_msg').html('<i class="fas fa-check"></i> Match').css('color', 'green');
                } else {
                    $('#match_msg').html('<i class="fas fa-times"></i> Do not match').css('color', 'red');
                }
            });
        });
    </script>
</body>

</html>