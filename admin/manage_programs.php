<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

checkAccess(['ADMINISTRATOR']);

// 1. Handle Add Program Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_program'])) {
    $code = strtoupper(trim($_POST['program_code']));
    $name = trim($_POST['program_name']);

    $check = $conn->prepare("SELECT id FROM programs WHERE program_code = ?");
    $check->bind_param("s", $code);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        header("Location: manage_programs.php?msg=exists");
    } else {
        $stmt = $conn->prepare("INSERT INTO programs (program_code, program_name, status) VALUES (?, ?, 'active')");
        $stmt->bind_param("ss", $code, $name);
        $stmt->execute();
        $log_details = "Added a program $$code, $name";
        // CALL THE LOG FUNCTION
        log_system_activity($conn, 'ADDED_PROGRAM', $log_details);
        header("Location: manage_programs.php?msg=added");
    }
    exit();
}

// 2. Handle Search Query
$search = $_GET['search'] ?? '';
$search_query = "";
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $search_query = " WHERE program_code LIKE '%$s%' OR program_name LIKE '%$s%'";
}

// 3. Fetch Programs
$programs = $conn->query("SELECT * FROM programs $search_query ORDER BY status ASC, program_code ASC");
if (!$programs) {
    die("Query Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Manage Programs - CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <style>
        :root {
            --cdl-green: #2d5a27;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }

        .active {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .inactive {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .search-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .search-bar input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .btn-action {
            text-decoration: none;
            font-size: 1.2rem;
            transition: 0.3s;
            padding: 5px;
            cursor: pointer;
        }

        .btn-action:hover {
            transform: scale(1.1);
        }

        .table-responsive {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        /* Local alert styling to match your navbar style */
        .local-alert {
            background: var(--cdl-green);
            color: white;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
    </style>
</head>

<body style="background: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">

    <?php include 'navbar.php'; ?>

    <div class="container" style="max-width: 1100px; margin: 30px auto; padding: 0 20px;">

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'status_updated'): ?>
            <div id="localToggleAlert" class="local-alert">
                <span><i class="fas fa-sync-alt"></i> Program status has been updated successfully!</span>
                <span style="cursor: pointer; font-size: 20px;" onclick="this.parentElement.style.display='none'">&times;</span>
            </div>
            <script>
                setTimeout(() => {
                    const alert = document.getElementById('localToggleAlert');
                    if (alert) alert.style.display = 'none';
                }, 4000);
            </script>
        <?php endif; ?>

        <div class="profile-header" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 25px; border-left: 5px solid var(--cdl-green);">
            <div class="profile-title" style="display: flex; align-items: center; gap: 15px;">
                <i class="fas fa-graduation-cap" style="font-size: 2.5rem; color: var(--cdl-green);"></i>
                <div>
                    <h2 style="margin:0; color: #333;">Manage Programs</h2>
                    <p style="margin:0; color: #666; font-size: 0.9rem;">Manage academic offerings and availability</p>
                </div>
            </div>
        </div>

        <div class="form-card-modern" style="background: white; padding: 25px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <h4 style="margin-top:0; color: var(--cdl-green);"><i class="fas fa-plus-circle"></i> Quick Add</h4>
            <form method="POST" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 150px;">
                    <label style="display:block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem;">Program Code</label>
                    <input type="text" name="program_code" placeholder="e.g. BSIT" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                </div>
                <div style="flex: 2; min-width: 250px;">
                    <label style="display:block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem;">Program Name</label>
                    <input type="text" name="program_name" placeholder="Full Title" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                </div>
                <button type="submit" name="add_program" class="login-btn" style="width: auto; padding: 11px 30px; background: var(--cdl-green); color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer;">
                    Add Program
                </button>
            </form>
        </div>

        <form method="GET" class="search-bar">
            <input type="text" name="search" placeholder="Search by code or name..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="login-btn" style="width: auto; background: #555; padding: 0 25px; color: white; border: none; border-radius: 8px; cursor: pointer;">Search</button>
            <?php if ($search): ?>
                <a href="manage_programs.php" style="padding: 10px 15px; background: #eee; color: #333; text-decoration: none; border-radius: 8px; font-size: 0.9rem;">Clear</a>
            <?php endif; ?>
        </form>

        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #fcfcfc; border-bottom: 2px solid #eee;">
                        <th style="padding: 18px; text-align: left; color: #555;">Code</th>
                        <th style="text-align: left; color: #555;">Program Name</th>
                        <th style="text-align: center; color: #555;">Status</th>
                        <th style="text-align: center; color: #555;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($programs->num_rows > 0): ?>
                        <?php while ($row = $programs->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #f1f1f1;">
                                <td style="padding: 18px;"><strong><?= $row['program_code']; ?></strong></td>
                                <td style="color: #444;"><?= htmlspecialchars($row['program_name']); ?></td>
                                <td style="text-align: center;">
                                    <span class="status-badge <?= $row['status'] ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 15px; justify-content: center; align-items: center;">
                                        <a href="edit_program.php?id=<?= $row['id']; ?>" class="btn-action" title="Edit" style="color: var(--cdl-green);">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="../actions/toggle_program.php?id=<?= $row['id']; ?>&status=<?= $row['status'] ?>"
                                            class="btn-action"
                                            style="color: #888;"
                                            title="Toggle Status">
                                            <i class="fas fa-power-off"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding: 40px; color: #999;">No records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>