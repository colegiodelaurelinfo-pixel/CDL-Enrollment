<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// 1. Check if the user is an Admin
if ($_SESSION['level'] !== 'ADMINISTRATOR') {
    header("Location: dashboard.php");
    exit();
}

// 2. Capture Search and Filter Inputs
$search = $_GET['search'] ?? '';
$filter_level = $_GET['filter_level'] ?? '';

// 3. Build Search Query - Updated to include new columns
$query = "SELECT id, firstname, lastname,middlename, email, faculty_id, department, level, status FROM users";
$conditions = [];

if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    // Updated search to include Faculty ID and Department
    $conditions[] = "(firstname LIKE '%$s%' OR lastname LIKE '%$s%' OR email LIKE '%$s%' OR faculty_id LIKE '%$s%' OR department LIKE '%$s%')";
}

if (!empty($filter_level)) {
    $l = $conn->real_escape_string($filter_level);
    $conditions[] = "level = '$l'";
}

if (count($conditions) > 0) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

$query .= " ORDER BY level ASC, lastname ASC";
$user_result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>User Management | CDL</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <style>
        .search-container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        .filter-select {
            padding: 11px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #fff;
            min-width: 180px;
        }

        .btn-filter {
            background: #2d5a27;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-filter:hover {
            background: #1e3d1a;
        }

        .level-badge {
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }

        .level-admin {
            background: #fff3e0;
            color: #ef6c00;
            border: 1px solid #ffe0b2;
        }

        .level-faculty {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }

        .level-staff {
            background: #f3e5f5;
            color: #7b1fa2;
            border: 1px solid #e1bee7;
        }

        /* Added Staff Style */

        .status-dot {
            height: 9px;
            width: 9px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }

        .dot-active {
            background-color: #4caf50;
            box-shadow: 0 0 5px #4caf50;
        }

        .dot-inactive {
            background-color: #f44336;
        }

        .alert-msg {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        /* Utility for department text */
        .dept-text {
            font-size: 0.85rem;
            color: #666;
            display: block;
        }

        .faculty-id-text {
            font-family: monospace;
            font-size: 0.85rem;
            color: #2d5a27;
        }
    </style>
</head>

<body style="background: #f4f7f6;">
    <?php include 'navbar.php'; ?>

    <div class="container" style="margin-top:30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div>
                <h2 style="margin:0; color: #2d5a27;"><i class="fas fa-users-cog"></i> User Management</h2>
                <p style="color: #666; margin: 5px 0 0 0;">Control system access and view department assignments.</p>
            </div>
            <a href="register.php" class="login-btn" style="width: auto; padding: 12px 24px; text-decoration: none; border-radius: 6px;">
                <i class="fas fa-user-plus"></i> Register new Faculty/Staff
            </a>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'user_updated'): ?>
            <div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> User account has been updated successfully!</div>
        <?php endif; ?>

        <form method="GET" class="search-container">
            <input type="text" name="search" class="search-input" placeholder="Search by name, email, ID, or department..." value="<?= htmlspecialchars($search) ?>">

            <select name="filter_level" class="filter-select">
                <option value="">-- All Roles --</option>
                <option value="ADMINISTRATOR" <?= ($filter_level == 'ADMINISTRATOR') ? 'selected' : '' ?>>ADMINISTRATOR</option>
                <option value="FACULTY" <?= ($filter_level == 'FACULTY') ? 'selected' : '' ?>>FACULTY</option>
                <option value="STAFF" <?= ($filter_level == 'STAFF') ? 'selected' : '' ?>>STAFF</option>
            </select>

            <button type="submit" class="btn-filter">Apply Filter</button>
            <?php if (!empty($search) || !empty($filter_level)): ?>
                <a href="manage_users.php" style="color: #d32f2f; text-decoration: none; font-size: 0.85rem; font-weight: bold; margin-left: 10px;">RESET</a>
            <?php endif; ?>
        </form>

        <div class="form-card-modern" style="padding: 15px; border-radius: 12px;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>ID & Dept</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($user_result && $user_result->num_rows > 0): ?>
                        <?php while ($user = $user_result->fetch_assoc()):
                            $lvl_class = 'level-staff';
                            if ($user['level'] == 'ADMINISTRATOR') $lvl_class = 'level-admin';
                            if ($user['level'] == 'FACULTY') $lvl_class = 'level-faculty';
                        ?>
                            <tr>
                                <td style="font-weight: 600; color: #333;">
                                    <?= strtoupper(htmlspecialchars($user['lastname'] . ", " . $user['firstname'] . " " . $user['middlename'])) ?>
                                </td>
                                <td>
                                    <span class="faculty-id-text"><?= htmlspecialchars($user['faculty_id'] ?: 'N/A') ?></span>
                                    <span class="dept-text"><?= htmlspecialchars($user['department'] ?: 'System-wide') ?></span>
                                </td>
                                <td style="color: #555;"><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="level-badge <?= $lvl_class ?>">
                                        <?= $user['level'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-dot <?= ($user['status'] == 'active') ? 'dot-active' : 'dot-inactive' ?>"></span>
                                    <span style="font-weight: 500;"><?= ucfirst($user['status']) ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <a href="edit_user.php?id=<?= $user['id'] ?>" style="color: #2d5a27; font-size: 1.2rem;" title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 60px; color: #bbb;">
                                <i class="fas fa-user-slash" style="font-size: 3.5rem; display: block; margin-bottom: 15px;"></i>
                                <span style="font-size: 1.1rem;">No users found matching those criteria.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>