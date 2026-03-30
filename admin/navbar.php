<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nav_settings = $conn->query("SELECT * FROM system_settings");
$sys = [];
while ($r = $nav_settings->fetch_assoc()) {
    $sys[$r['setting_key']] = $r['setting_value'];
}
$display_sy = $sys['active_sy'] ?? "None";
$display_sem = $sys['active_semester'] ?? "None";

$user_display_name = isset($_SESSION['firstname']) ? $_SESSION['firstname'] . " " . $_SESSION['lastname'] : "User";
$user_level = $_SESSION['level'] ?? '';

// Handle directory prefixing
$prefix = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false ||
    strpos($_SERVER['PHP_SELF'], '/student/') !== false ||
    strpos($_SERVER['PHP_SELF'], '/faculty/') !== false ||
    strpos($_SERVER['PHP_SELF'], '/registrar/') !== false) ? '../' : '';

// Set Home Link based on Role
$home_link = $prefix . "admin/dashboard.php";
if ($user_level === 'STUDENT') {
    $home_link = $prefix . "student/student_dashboard.php";
} elseif ($user_level === 'FACULTY') {
    $home_link = $prefix . "faculty/faculty_dashboard.php";
} elseif ($user_level === 'REGISTRAR') {
    // Assuming Registrar shares the admin dashboard or has a specific one
    $home_link = $prefix . "admin/registrar_dashboard.php";
}
?>



<nav class="main-nav">
    <div class="nav-left">
        <img src="<?= $prefix ?>assets/img/CDL_seal.png" alt="CDL Logo" class="nav-logo">
        <div class="school-info">
            <span class="school-name">Colegio de Laurel</span><br>
            <span class="system-tag">
                <i class="fas fa-calendar-day"></i> <?= $display_sy; ?> | <?= $display_sem; ?>
            </span>
        </div>
    </div>

    <div id="mobile-toggle" style="display: none; color: white; font-size: 24px; cursor: pointer;">
        <i class="fas fa-bars"></i>
    </div>

    <div class="nav-right" id="nav-menu">
        <ul class="nav-links">
            <li><a href="<?= $home_link ?>"><i class="fas fa-house"></i> Dashboard</a></li>

            <?php if ($user_level === 'ADMINISTRATOR'): ?>
                <li class="dropdown">
                    <a href="javascript:void(0)" class="dropbtn"><i class="fas fa-file-lines"></i> Reports <i class="fas fa-caret-down"></i></a>
                    <div class="dropdown-content">

                        <a href="<?= $prefix ?>admin/enrollment_stats.php"><i class="fas fa-chart-pie"> </i> Enrollment Status</a>
                        <a href="<?= $prefix ?>admin/grading_status.php"><i class="fas fa-check-double"></i> Grading Status</a>
                        <a href="<?= $prefix ?>admin/view_grading_sheet.php"><i class="fas fa-eye"> </i> View Grades</a>
                        <a href="<?= $prefix ?>admin/room_schedules.php"> <i class="fas fa-door-open"> </i> Room Schedule</a>
                        <a href="<?= $prefix ?>admin/faculty_summary_report.php"><i class="fas fa-chalkboard-teacher"> </i> List of Faculty</a>
                        <a href="<?= $prefix ?>admin/faculty_loading.php"><i class="fas fa-calendar-check"> </i> Faculty Loading</a>
                        <a href="<?= $prefix ?>admin/section_schedule.php"><i class="fas fa-clock"> </i> Section Schedule</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="javascript:void(0)" class="dropbtn"><i class="fas fa-list-check"></i> Manage <i class="fas fa-caret-down"></i></a>
                    <div class="dropdown-content">
                        <a href="<?= $prefix ?>admin/manage_users.php"><i class="fas fa-users-cog"></i> Users</a>
                        <a href="<?= $prefix ?>admin/manage_students.php"><i class="fas fa-user-graduate"></i> Students</a>
                        <a href="<?= $prefix ?>admin/manage_programs.php"><i class="fas fa-graduation-cap"></i> Programs</a>
                        <a href="<?= $prefix ?>admin/manage_courses.php"><i class="fas fa-book"></i> Courses</a>
                        <a href="<?= $prefix ?>admin/manage_sections.php"><i class="fas fa-layer-group"></i> Sections</a>
                        <a href="<?= $prefix ?>admin/manage_schedules.php"><i class="fas fa-calendar-alt"></i> Schedules</a>
                        <hr style="margin: 5px 0; border: 0; border-top: 1px solid #eee;">
                        <a href="<?= $prefix ?>admin/view_logs.php"><i class="fas fa-history"></i> User logs</a>
                        <a href="<?= $prefix ?>admin/settings.php"><i class="fas fa-cogs"></i> Settings</a>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($user_level === 'REGISTRAR'): ?>
                <li class="dropdown">
                    <a href="javascript:void(0)" class="dropbtn"><i class="fas fa-user-pen"></i> Enrollment <i class="fas fa-caret-down"></i></a>
                    <div class="dropdown-content">
                        <a href="<?= $prefix ?>admin/manage_students.php"><i class="fas fa-address-book"></i> Student Records</a>
                        <a href="<?= $prefix ?>admin/new_enrollment.php"><i class="fas fa-user-plus"></i> New Enrollment</a>
                        <a href="<?= $prefix ?>admin/enrollment_stats.php"><i class="fas fa-chart-bar"></i> Statistics</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="javascript:void(0)" class="dropbtn"><i class="fas fa-graduation-cap"></i> Academic <i class="fas fa-caret-down"></i></a>
                    <div class="dropdown-content">
                        <a href="<?= $prefix ?>admin/grading_status.php"><i class="fas fa-file-signature"></i> Grading Compliance</a>
                        <a href="<?= $prefix ?>admin/manage_schedules.php"><i class="fas fa-calendar-alt"></i> Class Schedules</a>
                        <a href="<?= $prefix ?>admin/manage_sections.php"><i class="fas fa-users"></i> Sections & Loading</a>
                        <a href="<?= $prefix ?>admin/room_schedules.php"><i class="fas fa-door-closed"></i> Room Utilization</a>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($user_level === 'FACULTY'): ?>
                <li><a href="<?= $prefix ?>faculty/my_load.php"><i class="fas fa-calendar-alt"></i> My Load</a></li>
                <li class="dropdown">
                    <a href="javascript:void(0)" class="dropbtn"><i class="fas fa-pen-to-square"></i> Faculty Tools <i class="fas fa-caret-down"></i></a>
                    <div class="dropdown-content">
                        <a href="<?= $prefix ?>faculty/add_grades.php"><i class="fas fa-pen-to-square"></i> Encode Grades</a>
                        <a href="<?= $prefix ?>faculty/class_list.php"><i class="fas fa-users-rectangle"></i> My Students</a>
                        <a href="<?= $prefix ?>faculty/grade_report.php"><i class="fas fa-print"></i> Print Grades</a>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($user_level === 'STUDENT'): ?>
                <li><a href="<?= $prefix ?>student/class_schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
                <li class="dropdown">
                    <a href="javascript:void(0)" class="dropbtn"><i class="fas fa-folder-open"></i> My Records <i class="fas fa-caret-down"></i></a>
                    <div class="dropdown-content">
                        <a href="<?= $prefix ?>student/view_grades.php"><i class="fas fa-file-invoice"></i> My Grades</a>
                        <a href="<?= $prefix ?>student/view_prospectus.php"><i class="fas fa-scroll"></i> Prospectus</a>
                    </div>
                </li>
            <?php endif; ?>

            <li class="user-profile dropdown">
                <a href="javascript:void(0)" class="dropbtn">
                    <small class="role-badge"><?= $user_level; ?></small><br>
                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($user_display_name); ?> <i class="fas fa-caret-down"></i>
                </a>
                <div class="dropdown-content">
                    <a href="<?= $prefix ?>admin/change_password.php"><i class="fas fa-lock"></i> Change Password</a>
                    <a href="<?= $prefix ?>logout.php" style="color: #c62828 !important;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </li>
        </ul>
    </div>
</nav>