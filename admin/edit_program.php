<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
checkAccess(['ADMINISTRATOR']);

// 1. Fetch current program details
if (!isset($_GET['id'])) {
    header("Location: manage_programs.php");
    exit();
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM programs WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$program = $stmt->get_result()->fetch_assoc();

if (!$program) {
    header("Location: manage_programs.php?msg=error");
    exit();
}

// 2. Handle Update Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_program'])) {
    $code = strtoupper(trim($_POST['program_code']));
    $name = trim($_POST['program_name']);
    $status = $_POST['status'];

    $update = $conn->prepare("UPDATE programs SET program_code = ?, program_name = ?, status = ? WHERE id = ?");
    $update->bind_param("sssi", $code, $name, $status, $id);

    if ($update->execute()) {
        header("Location: manage_programs.php?msg=updated");
        $log_details = "Updated program $code | $name";
        log_system_activity($conn, 'UPDATE_PROGRAM', $log_details);
    } else {
        $error = "Update failed: " . $conn->error;
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Edit Program - CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <style>
        :root {
            --cdl-green: #2d5a27;
        }

        .edit-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border-top: 5px solid var(--cdl-green);
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .btn-save {
            flex: 2;
            background: var(--cdl-green);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-cancel {
            flex: 1;
            background: #666;
            color: white;
            text-decoration: none;
            text-align: center;
            padding: 12px;
            border-radius: 6px;
            font-weight: bold;
            transition: 0.3s;
        }

        .btn-save:hover,
        .btn-cancel:hover {
            opacity: 0.9;
        }
    </style>
</head>

<body style="background: #f4f7f6; font-family: 'Segoe UI', sans-serif;">

    <?php include 'navbar.php'; ?>

    <div class="edit-container">
        <div class="card">
            <h2 style="margin-top: 0; color: #333;"><i class="fas fa-edit"></i> Edit Program</h2>
            <p style="color: #666; margin-bottom: 25px;">Update the details for <strong><?= htmlspecialchars($program['program_code']) ?></strong></p>

            <form method="POST">
                <div class="form-group">
                    <label>Program Code</label>
                    <input type="text" name="program_code" value="<?= htmlspecialchars($program['program_code']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Program Description / Name</label>
                    <input type="text" name="program_name" value="<?= htmlspecialchars($program['program_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Current Status</label>
                    <select name="status">
                        <option value="active" <?= $program['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $program['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="btn-group">
                    <a href="manage_programs.php" class="btn-cancel">Cancel</a>
                    <button type="submit" name="update_program" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

</body>

</html>