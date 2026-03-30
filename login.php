<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Added id to the select to ensure we can target the correct user for the update
    $stmt = $conn->prepare("SELECT id, password, level, firstname, lastname, status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            if (strtolower($user['status']) === 'active') {

                // 1. UPDATE LAST LOGIN TIMESTAMP
                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();

                // 2. SET SESSION VARIABLES
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['level']     = $user['level'];
                $_SESSION['firstname'] = $user['firstname'];
                $_SESSION['lastname']  = $user['lastname'];
                $_SESSION['email']     = $email;

                session_write_close();

                // 3. REDIRECT BASED ON ROLE
                if ($user['level'] === 'STUDENT') {
                    header("Location: student/student_dashboard.php");
                } elseif ($user['level'] === 'FACULTY') {
                    header("Location: faculty/faculty_dashboard.php");
                } elseif ($user['level'] === 'REGISTRAR') {
                    // Redirect to the specialized Registrar view
                    header("Location: admin/registrar_dashboard.php");
                } else {
                    header("Location: admin/dashboard.php");
                }
                exit();
            } else {
                $error = "Access Denied: Your account is currently inactive.";
            }
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "Email address not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Login | Colegio de Laurel</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            /* Deep Green Gradient Background */
            background: linear-gradient(135deg, #2d5a27 0%, #1a3316 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            /* Slightly off-white for better eye comfort */
            width: 100%;
            max-width: 400px;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            box-sizing: border-box;
        }

        .login-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .login-logo {
            width: 85px;
            height: auto;
            margin-bottom: 12px;
            /* Subtle drop shadow for the logo */
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }

        .login-header h2 {
            color: #2d5a27;
            margin: 0;
            font-size: 1.4rem;
            letter-spacing: 0.5px;
        }

        .login-header p {
            color: #555;
            margin: 5px 0 0;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .input-group {
            margin-bottom: 18px;
        }

        .input-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #444;
            font-size: 0.85rem;
        }

        .input-group input {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #e1e1e1;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            outline: none;
            border-color: #2d5a27;
            box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.1);
        }

        .password-container {
            position: relative;
        }

        .password-container i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: #2d5a27;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
            margin-top: 10px;
        }

        .login-btn:hover {
            background: #23461e;
            transform: translateY(-1px);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .error-msg {
            background: #fff5f5;
            color: #d63031;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            text-align: center;
            margin-bottom: 20px;
            border-left: 4px solid #d63031;
        }

        .back-nav {
            margin-bottom: 20px;
        }

        .btn-back {
            text-decoration: none;
            color: #2d5a27;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: opacity 0.2s;
        }

        .btn-back:hover {
            opacity: 0.7;
        }

        .login-footer {
            text-align: center;
            margin-top: 30px;
            color: #777;
            font-size: 0.75rem;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="back-nav">
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>

        <div class="login-header">
            <img src="assets/img/CDL_seal.png" alt="CDL Logo" class="login-logo">
            <h2>Colegio de Laurel</h2>
            <p>Enrollment Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="email@cdl.edu.ph" required autocomplete="email">
            </div>

            <div class="input-group">
                <label>Password</label>
                <div class="password-container">
                    <input type="password" name="password" id="password" placeholder="••••••••" required>
                    <i class="fas fa-eye" id="togglePassword"></i>
                </div>
            </div>

            <button type="submit" class="login-btn">Sign In</button>
        </form>

        <div class="login-footer">
            &copy; 2026 Colegio de Laurel. All rights reserved.
        </div>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>

</html>