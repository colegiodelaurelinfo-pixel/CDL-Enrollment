<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';


// Handle Manual Section Creation (If you use a simple form elsewhere)
// Handle Dynamic Section Creation
if (isset($_POST['add_section_dynamic'])) {
    $raw_prog = $_POST['prog_code'];
    $year = $_POST['year_lvl'];
    $id   = strtoupper($_POST['sec_id']);
    $max  = $_POST['max_capacity'];

    // LOGIC: Check if "Special" is selected
    if ($raw_prog === 'Special') {
        $prog = "SPECIAL";
        // For special sections, the name could just be "SPECIAL [ID]" or "SPECIAL [YEAR][ID]"
        // Using "SPECIAL 1A" format for consistency
        $full_name = "SPECIAL " . $year . " " . $id;
    } else {
        $prog = $raw_prog;
        $full_name = $prog . " " . $year . $id;
    }

    $stmt = $conn->prepare("INSERT INTO sections (section_name, program_id, year_level, max_capacity) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssii", $full_name, $prog, $year, $max);

    if ($stmt->execute()) {
        echo "<script>alert('Section $full_name Created!'); window.location='manage_sections.php';</script>";

        $log_details = "Added section $full_name (Program: $prog) for Year $year";
        log_system_activity($conn, 'CREATE_SECTION', $log_details);
    } else {
        echo "<script>alert('Error: Section might already exist.');</script>";
    }
}

// Fetch Sections - Ordered by Year then Name
$query = "SELECT s.*, 
          (SELECT COUNT(*) FROM students st WHERE st.section = s.section_name) as current_enrolled 
          FROM sections s ORDER BY year_level ASC, section_name ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Manage Sections | Colegio de Laurel</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <style>
        :root {
            --cdl-green: #2d5a27;
        }

        .progress-container {
            background: #eee;
            height: 8px;
            border-radius: 5px;
            margin: 10px 0;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            transition: width 0.3s;
        }

        .status-pill {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: bold;
        }

        .year-badge {
            background: #e8f5e9;
            color: #2d5a27;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-bottom: 5px;
            display: inline-block;
        }

        .section-card {
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .btn-mini {
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: bold;
            text-align: center;
            transition: 0.2s;
        }

        .btn-mini-edit {
            background: var(--cdl-green);
            color: white !important;
            flex: 2;
        }

        .btn-mini-del {
            background: #0a0a0a;
            color: white !important;
            flex: 1;
        }

        .btn-mini:hover {
            opacity: 0.8;
            transform: translateY(-1px);
        }
    </style>
</head>

<body style="background: #f4f7f6;">
    <?php include 'navbar.php'; ?>
    <div class="container" style="margin-top: 30px;">
        <div style="display: grid; grid-template-columns: 320px 1fr; gap: 25px;">

            <div>
                <div class="form-card-modern" style="border-top: 5px solid #2d5a27; position: sticky; top: 20px;">
                    <h3 style="font-size: 1.1rem;"><i class="fas fa-plus-circle"></i> Create Section</h3>
                    <form method="POST">
                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 0.85rem;">Program</label>
                            <select name="prog_code" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                                <option value='Special'>SPECIAL</option>
                                <?php
                                $progs = $conn->query("SELECT program_code FROM programs");
                                while ($p = $progs->fetch_assoc()) echo "<option value='" . $p['program_code'] . "'>" . $p['program_code'] . "</option>";
                                ?>
                            </select>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 0.85rem;">Year Level</label>
                            <select name="year_lvl" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 0.85rem;">Identifier (A, B, C...)</label>
                            <input type="text" name="sec_id" placeholder="e.g. A" required maxlength="20"
                                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; text-transform: uppercase;">
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="font-size: 0.85rem;">Max Capacity</label>
                            <input type="number" name="max_capacity" value="50" required
                                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>

                        <button type="submit" name="add_section_dynamic" class="login-btn" style="background: #2d5a27; padding: 10px; font-size: 0.9rem;">Create Section</button>
                    </form>
                </div>
            </div>

            <div class="form-card-modern">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; font-size: 1.1rem;"><i class="fas fa-layer-group"></i> Real-time Section Load</h3>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px;">
                    <?php while ($row = $result->fetch_assoc()):
                        $percent = ($row['current_enrolled'] / $row['max_capacity']) * 100;
                        $color = ($percent >= 100) ? '#c0392b' : (($percent >= 80) ? '#f39c12' : '#2d5a27');
                    ?>
                        <div class="section-card">
                            <div>
                                <div class="year-badge">Year <?= $row['year_level'] ?></div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong style="font-size: 1rem;"><?= htmlspecialchars($row['section_name']) ?></strong>
                                    <span class="status-pill" style="background: <?= $color ?>22; color: <?= $color ?>;">
                                        <?= ($row['current_enrolled'] >= $row['max_capacity']) ? 'FULL' : 'OPEN' ?>
                                    </span>
                                </div>

                                <div style="margin-top: 8px; font-size: 0.8rem;">
                                    <strong><?= $row['current_enrolled'] ?></strong>
                                    <span style="color: #888;">/ <?= $row['max_capacity'] ?> filled</span>
                                </div>

                                <div class="progress-container">
                                    <div class="progress-bar" style="width: <?= min($percent, 100) ?>%; background: <?= $color ?>;"></div>
                                </div>

                                <p style="font-size: 0.7rem; color: #666; margin: 0;">
                                    <?= max(0, $row['max_capacity'] - $row['current_enrolled']) ?> seats left
                                </p>
                            </div>

                            <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid #f8f8f8; display: flex; gap: 5px;">
                                <a href="edit_section.php?id=<?= $row['id'] ?>" class="btn-mini btn-mini-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="../actions/delete_section.php?id=<?= $row['id'] ?>"
                                    onclick="return confirm('Delete section <?= $row['section_name'] ?>?')"
                                    class="btn-mini btn-mini-del">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

</body>

</html>