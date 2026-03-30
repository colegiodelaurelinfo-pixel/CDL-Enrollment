<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// 1. Security Check
if ($_SESSION['level'] !== 'ADMINISTRATOR') {
    header("Location: dashboard.php");
    exit();
}

// 2. Get User ID from URL
if (!isset($_GET['id'])) {
    header("Location: manage_users.php");
    exit();
}

$user_id = (int)$_GET['id'];

// 3. Fetch User Data (Selects everything including faculty_id and department)
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Edit User | CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <style>
        .edit-container {
            max-width: 700px;
            margin: 40px auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1rem;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>

<body style="background: #f4f7f6;">
    <?php include 'navbar.php'; ?>

    <div class="container edit-container">
        <div class="form-card-modern" style="padding: 30px;">
            <div style="border-bottom: 2px solid #f0f0f0; margin-bottom: 25px; padding-bottom: 10px;">
                <h2 style="margin:0; color: #2d5a27;"><i class="fas fa-user-edit"></i> Edit User Account</h2>
                <small>Modifying details for: <strong><?= htmlspecialchars($user['email']) ?></strong></small>
            </div>

            <form action="../actions/process_edit_user.php" method="POST">
                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">

                <div class="grid-2">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="firstname" class="form-control" value="<?= htmlspecialchars($user['firstname']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Middlename</label>
                        <input type="text" name="middlename" class="form-control" value="<?= htmlspecialchars($user['middlename']) ?>" required>
                    </div>


                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="lastname" class="form-control" value="<?= htmlspecialchars($user['lastname']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>

                <div class="grid-2" id="facultySection">
                    <div class="form-group">
                        <label>Faculty / Staff ID</label>
                        <input type="text" name="faculty_id" class="form-control" value="<?= htmlspecialchars($user['faculty_id']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" class="form-control">
                            <option value="" <?= empty($user['department']) ? 'selected' : '' ?>>-- Select Department --</option>
                            <option value="Early Childhood Education" <?= $user['department'] == 'Early Childhood Education' ? 'selected' : '' ?>>Early Childhood Education</option>
                            <option value="Criminology" <?= $user['department'] == 'Criminology' ? 'selected' : '' ?>>Criminology</option>
                            <option value="General Education" <?= $user['department'] == 'General Education' ? 'selected' : '' ?>>General Education</option>
                            <option value="Agribusiness" <?= $user['department'] == 'Agribusiness' ? 'selected' : '' ?>>Agribusiness</option>
                            <option value="Administrative Office" <?= $user['department'] == 'Administrative Office' ? 'selected' : '' ?>>Administrative Office</option>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Access Level</label>
                        <select name="level" id="roleSelect" class="form-control" required onchange="toggleFacultyFields()">
                            <option value="ADMINISTRATOR" <?= $user['level'] == 'ADMINISTRATOR' ? 'selected' : '' ?>>ADMINISTRATOR</option>
                            <option value="FACULTY" <?= $user['level'] == 'FACULTY' ? 'selected' : '' ?>>FACULTY</option>
                            <option value="REGISTRAR" <?= $user['level'] == 'REGISTRAR' ? 'selected' : '' ?>>REGISTRAR</option>
                            <option value="STAFF" <?= $user['level'] == 'STAFF' ? 'selected' : '' ?>>STAFF</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Account Status</label>
                        <select name="status" class="form-control" required>
                            <option value="active" <?= $user['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $user['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="btn-container">
                    <button type="submit" name="btn_update_user" class="login-btn" style="flex: 2;">Update Account</button>
                    <a href="manage_users.php" class="login-btn" style="flex: 1; background: #666; text-align: center; text-decoration: none;">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleFacultyFields() {
            const role = document.getElementById('roleSelect').value;
            const section = document.getElementById('facultySection');
            section.style.display = (role === 'ADMINISTRATOR') ? 'none' : 'grid';
        }
        // Initialize on load
        window.onload = toggleFacultyFields;
    </script>
</body>

</html>