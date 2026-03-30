<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
checkAccess(['FACULTY']);

$user_id = $_SESSION['user_id'];
$view = $_GET['view'] ?? 'weekly'; 

// 1. Get Faculty Details
$fac_query = $conn->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
$fac_query->bind_param("i", $user_id);
$fac_query->execute();
$faculty = $fac_query->get_result()->fetch_assoc();

// 2. Get Active Term
$sys_res = $conn->query("SELECT * FROM system_settings");
$sys = []; while($r = $sys_res->fetch_assoc()) { $sys[$r['setting_key']] = $r['setting_value']; }
$active_sy = $sys['active_sy'] ?? '';
$active_sem = $sys['active_semester'] ?? '';

// 3. Fetch Teaching Load
$sql = "SELECT s.*, c.course_code, c.course_title, sec.section_name 
        FROM schedules s
        JOIN courses c ON s.course_id = c.course_id
        JOIN sections sec ON s.section_id = sec.id
        WHERE s.faculty_id = ? AND s.school_year = ? AND s.semester = ?
        ORDER BY s.time_start ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $active_sy, $active_sem);
$stmt->execute();
$result = $stmt->get_result();

$load = [];
$min_hour = 7; // Fixed start for grid consistency
$max_hour = 19; // Fixed end (7:00 PM)

while($row = $result->fetch_assoc()){ 
    $load[] = $row; 
}
$total_classes = count($load);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teaching Load | CDL</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { 
            --cdl-green: #2d5a27; 
            --cdl-green-dark: #1a3617;
            --slot-height: 50px; 
        }
        body { background: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
        
        /* --- NAVIGATION & TOGGLES --- */
        .view-toggle { display: flex; background: #e0e0e0; border-radius: 50px; padding: 5px; width: fit-content; margin-bottom: 25px; }
        .view-toggle a { padding: 8px 22px; text-decoration: none; color: #555; border-radius: 50px; font-weight: bold; font-size: 0.9rem; transition: 0.3s; }
        .view-toggle a.active { background: var(--cdl-green); color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }

        /* --- WEEKLY TIMETABLE (PIXEL-PERFECT) --- */
        .tt-wrapper { display: flex; border: 1px solid #ddd; background: white; position: relative; overflow-x: auto; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .tt-time-col { width: 85px; flex-shrink: 0; background: #f9f9f9; border-right: 1px solid #ddd; }
        .tt-day-col { flex: 1; min-width: 150px; border-right: 1px solid #eee; position: relative; }
        .tt-header { height: 50px; background: var(--cdl-green); color: white; text-align: center; line-height: 50px; font-weight: bold; font-size: 0.8rem; letter-spacing: 1px; }
        .tt-time-slot { height: var(--slot-height); border-bottom: 1px solid #eee; font-size: 0.75rem; text-align: center; color: #888; line-height: var(--slot-height); font-weight: 600; }
        .tt-grid-container { position: relative; height: calc(var(--slot-height) * 13); background-image: linear-gradient(#eee 1px, transparent 1px); background-size: 100% var(--slot-height); }
        
        /* Schedule Blocks in Weekly View */
        .sched-block {
            position: absolute; left: 5px; right: 5px; background: rgba(45, 90, 39, 0.94);
            color: white; border-radius: 6px; padding: 8px; font-size: 0.7rem; 
            overflow: hidden; border-left: 4px solid var(--cdl-green-dark); line-height: 1.3;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15); z-index: 5; transition: 0.2s;
        }
        .sched-block:hover { z-index: 10; transform: translateY(-2px); background: var(--cdl-green); }

        /* --- LIST VIEW CARDS --- */
        .load-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .load-card { 
            background: white; padding: 20px; border-radius: 15px; border-top: 6px solid var(--cdl-green); 
            box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s; position: relative;
        }
        .load-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        
        .day-bubble {
            width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; 
            border-radius: 50%; font-size: 0.7rem; font-weight: bold; transition: 0.3s;
        }

        /* --- PRINT STYLES --- */
        @media print {
            .no-print, nav, .navbar, .view-toggle, button { display: none !important; }
            body { background: white; }
            .container { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .tt-wrapper { border: none; box-shadow: none; }
            .sched-block { border: 1px solid #333 !important; color: black !important; background: white !important; }
            .tt-header { background: #f0f0f0 !important; color: black !important; border: 1px solid #ddd; }
            .load-card { border: 1px solid #ddd !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <?php include_once '../admin/navbar.php'; ?>
    </div>

    <div class="container" style="max-width: 1350px; margin: 30px auto; padding: 0 20px;">
        
        <div class="header-card" style="background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; border-left: 10px solid var(--cdl-green); display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <div>
                <h1 style="margin: 0; color: #222; font-size: 1.8rem;">Prof. <?= $faculty['firstname'] . ' ' . $faculty['lastname'] ?></h1>
                <p style="margin: 8px 0 0 0; color: #666; font-size: 1rem; font-weight: 500;">
                    <i class="fas fa-calendar-alt" style="color: var(--cdl-green);"></i> <?= $active_sem ?> | A.Y. <?= $active_sy ?> 
                    <span style="margin-left: 15px; color: #888;">(<?= $total_classes ?> Total Classes)</span>
                </p>
            </div>
            <button onclick="window.print()" class="no-print" style="padding: 12px 25px; background: var(--cdl-green); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 10px; transition: 0.3s;">
                <i class="fas fa-print"></i> PRINT SCHEDULE
            </button>
        </div>

        <div class="view-toggle no-print">
            <a href="?view=weekly" class="<?= $view == 'weekly' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Weekly View</a>
            <a href="?view=list" class="<?= $view == 'list' ? 'active' : '' ?>"><i class="fas fa-list-ul"></i> List View</a>
        </div>

        <?php if($view == 'weekly'): ?>
        <div class="tt-wrapper">
            <div class="tt-time-col">
                <div class="tt-header" style="background: #f1f1f1; color: #333;">TIME</div>
                <?php for($i=$min_hour; $i<=$max_hour; $i++): ?>
                    <div class="tt-time-slot"><?= date("g A", strtotime("$i:00")) ?></div>
                <?php endfor; ?>
            </div>

            <?php 
            $week_days = [
                'day_mon' => 'MONDAY', 'day_tue' => 'TUESDAY', 'day_wed' => 'WEDNESDAY', 
                'day_thu' => 'THURSDAY', 'day_fri' => 'FRIDAY', 'day_sat' => 'SATURDAY'
            ];
            
            foreach($week_days as $db_col => $label): ?>
                <div class="tt-day-col">
                    <div class="tt-header"><?= $label ?></div>
                    <div class="tt-grid-container">
                        <?php foreach($load as $s): 
                            if($s[$db_col] == 1):
                                $start_time = strtotime($s['time_start']);
                                $end_time = strtotime($s['time_end']);
                                
                                // Precise Positioning
                                $start_min = (date('G', $start_time) * 60) + date('i', $start_time);
                                $cal_start_min = $min_hour * 60; 
                                
                                $top_px = ($start_min - $cal_start_min) * (var_get_slot_height() / 60); 
                                $duration_min = ($end_time - $start_time) / 60;
                                $height_px = $duration_min * (50/60); // 50 is the slot height in px
                        ?>
                            <div class="sched-block" style="top: <?= $top_px ?>px; height: <?= $height_px - 3 ?>px;">
                                <div style="font-weight: 800; border-bottom: 1px solid rgba(255,255,255,0.3); margin-bottom: 4px; padding-bottom: 2px;">
                                    <?= $s['course_code'] ?>
                                </div>
                                <div style="font-weight: 600;"><?= $s['section_name'] ?></div>
                                <div style="margin: 2px 0;"><i class="fas fa-map-marker-alt" style="font-size: 0.6rem;"></i> <?= $s['room_name'] ?></div>
                                <div style="font-size: 0.6rem; opacity: 0.9;"><?= date("h:i A", $start_time) ?> - <?= date("h:i A", $end_time) ?></div>
                            </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php else: ?>
            <div class="load-grid">
                <?php foreach($load as $row): ?>
                    <div class="load-card">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <div>
                                <h3 style="margin: 0; color: var(--cdl-green); font-size: 1.3rem; letter-spacing: -0.5px;"><?= $row['course_code'] ?></h3>
                                <div style="background: #f0f2f5; color: #444; display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; margin-top: 5px;">
                                    <?= $row['section_name'] ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span style="display: block; background: #e8f5e9; color: var(--cdl-green); padding: 5px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 800;">
                                    <i class="fas fa-door-open"></i> <?= $row['room_name'] ?>
                                </span>
                            </div>
                        </div>

                        <div style="font-weight: 600; color: #333; margin-bottom: 15px; font-size: 0.95rem; line-height: 1.4; min-height: 45px;">
                            <?= $row['course_title'] ?>
                        </div>

                        <div style="margin-bottom: 20px; display: flex; gap: 6px;">
                            <?php 
                                $days_map = ['day_mon'=>'M', 'day_tue'=>'T', 'day_wed'=>'W', 'day_thu'=>'Th', 'day_fri'=>'F', 'day_sat'=>'S'];
                                foreach($days_map as $key => $label):
                                    $active = ($row[$key] == 1);
                            ?>
                                <div class="day-bubble" style="<?= $active ? 'background: var(--cdl-green); color: white;' : 'background: #f0f0f0; color: #ccc;' ?>">
                                    <?= $label ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="border-top: 1px solid #f0f0f0; padding-top: 15px; display: flex; align-items: center; justify-content: space-between; color: #555; font-size: 0.9rem;">
                            <span><i class="far fa-clock" style="margin-right: 5px; color: var(--cdl-green);"></i> 
                                <?= date("h:i A", strtotime($row['time_start'])) ?> — <?= date("h:i A", strtotime($row['time_end'])) ?>
                            </span>
                            <i class="fas fa-chevron-right" style="font-size: 0.7rem; color: #ccc;"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="no-print" style="margin-top: 40px; text-align: center; border-top: 1px solid #ddd; padding-top: 20px;">
            <a href="faculty_dashboard.php" style="text-decoration: none; color: #888; font-weight: 600; font-size: 0.95rem; transition: 0.3s;">
                <i class="fas fa-arrow-left"></i> RETURN TO DASHBOARD
            </a>
        </div>
    </div>

    <?php 
    // Helper function to pass slot height to the loop
    function var_get_slot_height() { return 50; } 
    ?>
</body>
</html>