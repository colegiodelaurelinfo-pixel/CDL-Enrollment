<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';


// Role Protection
if ($_SESSION['level'] !== 'ADMINISTRATOR') {
    header("Location: ../index.php");
    exit();
}

// Handle Filter
$filter_action = $_GET['action_type'] ?? '';
$where_clause = "";
if (!empty($filter_action)) {
    $where_clause = " WHERE action = '" . $conn->real_escape_string($filter_action) . "'";
}

// Fetch Logs with User Names
$logs_query = "SELECT l.*, u.firstname, u.lastname 
               FROM system_logs l 
               LEFT JOIN users u ON l.user_id = u.id 
               $where_clause
               ORDER BY l.created_at DESC LIMIT 500";
$logs = $conn->query($logs_query);

// Get unique actions for the filter dropdown
$actions_list = $conn->query("SELECT DISTINCT action FROM system_logs ORDER BY action ASC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>System Logs | CDL</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .log-action-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            background: #eee;
            color: #444;
        }

        .action-LOGIN {
            background: #e3f2fd;
            color: #1976d2;
        }

        .action-UPDATE_GRADE {
            background: #fff3e0;
            color: #e65100;
        }

        .action-DELETE {
            background: #ffebee;
            color: #c62828;
        }

        .ip-text {
            font-family: monospace;
            color: #888;
            font-size: 0.8rem;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="dashboard-header-card">
            <div>
                <h2><i class="fas fa-history"></i> System Audit Logs</h2>
                <p>Track all Create, Update, and Delete activities across the system.</p>
            </div>
            <div class="no-print">
                <form method="GET" style="display: flex; gap: 10px;">
                    <select name="action_type" class="form-control" onchange="this.form.submit()" style="width: 200px;">
                        <option value="">All Activities</option>
                        <?php while ($a = $actions_list->fetch_assoc()): ?>
                            <option value="<?= $a['action'] ?>" <?= $filter_action == $a['action'] ? 'selected' : '' ?>>
                                <?= $a['action'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <a href="view_logs.php" class="btn-link" style="padding: 10px;"><i class="fas fa-sync"></i></a>
                </form>
            </div>
        </div>

        <div class="table-responsive" style="background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <table class="admin-table">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while ($row = $logs->fetch_assoc()): ?>
                            <tr>
                                <td style="white-space: nowrap; color: #666;">
                                    <i class="far fa-clock"></i> <?= date("M d, Y | g:i A", strtotime($row['created_at'])) ?>
                                </td>
                                <td>
                                    <strong><?= $row['firstname'] ? strtoupper($row['lastname'] . ", " . $row['firstname']) : 'SYSTEM' ?></strong>
                                    <br><small class="text-muted">ID: <?= $row['user_id'] ?></small>
                                </td>
                                <td>
                                    <span class="log-action-badge action-<?= $row['action'] ?>">
                                        <?= str_replace('_', ' ', $row['action']) ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.85rem; color: #444;">
                                    <?= htmlspecialchars($row['details']) ?>
                                </td>
                                <td class="ip-text"><?= $row['ip_address'] ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 50px; color: #999;">
                                <i class="fas fa-folder-open fa-3x"></i><br><br>No logs found for this criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>

</html>