<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
checkAccess(['ADMINISTRATOR']);

if (!isset($_GET['id'])) {
    header("Location: manage_sections.php");
    exit();
}

$id = intval($_GET['id']);

// 1. Fetch Current Data
$stmt = $conn->prepare("SELECT * FROM sections WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$section = $stmt->get_result()->fetch_assoc();

if (!$section) {
    header("Location: manage_sections.php");
    exit();
}

// 2. Handle Update
if (isset($_POST['update_section'])) {
    $name = $_POST['section_name'];
    $capacity = intval($_POST['max_capacity']);
    $raw_prog = $_POST['program_id'];
    $year = intval($_POST['year_level']);

    // LOGIC: Check if "Special" is selected to maintain consistency
    $prog = ($raw_prog === 'Special') ? 'SPECIAL' : $raw_prog;

    $update = $conn->prepare("UPDATE sections SET section_name = ?, max_capacity = ?, program_id = ?, year_level = ? WHERE id = ?");
    $update->bind_param("sisii", $name, $capacity, $prog, $year, $id);

    if ($update->execute()) {
        $log_details = "Updated section: $name (Prog: $prog, Year: $year, Cap: $capacity)";
        log_system_activity($conn, 'UPDATE_SECTION', $log_details);
        echo "<script>alert('Section updated successfully!'); window.location='manage_sections.php';</script>";
    } else {
        $error = "Error updating section: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Edit Section | CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
</head>

<body style="background: #f4f7f6;">
    <?php include 'navbar.php'; ?>

    <div class="container" style="max-width: 500px; margin: 50px auto;">
        <div class="form-card-modern" style="border-top: 5px solid #f39c12;">
            <h3 style="margin-top:0;"><i class="fas fa-edit"></i> Edit Section</h3>
            <p style="color: #666; font-size: 0.9rem;">Modify details for <strong><?= htmlspecialchars($section['section_name']) ?></strong></p>
            <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

            <?php if (isset($error)): ?>
                <div style="color: red; margin-bottom: 10px; font-size: 0.85rem;"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Section Name</label>
                    <input type="text" name="section_name" value="<?= htmlspecialchars($section['section_name']) ?>" required
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Program</label>
                        <select name="program_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="Special" <?= ($section['program_id'] == 'SPECIAL') ? 'selected' : '' ?>>SPECIAL</option>

                            <?php
                            $progs = $conn->query("SELECT program_code FROM programs ORDER BY program_code ASC");
                            while ($p = $progs->fetch_assoc()):
                                $selected = ($p['program_code'] == $section['program_id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $p['program_code'] ?>" <?= $selected ?>><?= $p['program_code'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Year Level</label>
                        <select name="year_level" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <?php
                            for ($i = 1; $i <= 4; $i++):
                                $y_selected = ($section['year_level'] == $i) ? 'selected' : '';
                            ?>
                                <option value="<?= $i ?>" <?= $y_selected ?>><?= $i . ($i == 1 ? 'st' : ($i == 2 ? 'nd' : ($i == 3 ? 'rd' : 'th'))) ?> Year</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Max Capacity</label>
                    <input type="number" name="max_capacity" value="<?= $section['max_capacity'] ?>" required
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="update_section" class="login-btn" style="background: #2d5a27; flex: 2; cursor: pointer; color: white; border: none; border-radius: 5px; height: 40px;">Save Changes</button>
                    <a href="manage_sections.php" class="login-btn" style="background: #666; flex: 1; text-align: center; text-decoration: none; color: white; border-radius: 5px; line-height: 40px;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>