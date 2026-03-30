<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

$message = "";

// 1. Fetch Dynamic System Settings
$settings_query = $conn->query("SELECT * FROM system_settings");
$sys = [];
while ($row = $settings_query->fetch_assoc()) {
    $sys[$row['setting_key']] = $row['setting_value'];
}
$active_sy  = $sys['active_sy'] ?? '2025-2026';
$active_sem = $sys['active_semester'] ?? '1st Semester';

// 2. Fetch All Sections 
$sections_data = [];
$sec_query = $conn->query("SELECT s.section_name, s.program_id, s.year_level, s.max_capacity,
             (SELECT COUNT(*) FROM students st WHERE st.section = s.section_name AND st.school_year = '$active_sy') as current_enrolled
             FROM sections s ORDER BY s.section_name ASC");
while ($s = $sec_query->fetch_assoc()) {
    $sections_data[] = $s;
}

// 3. Process Enrollment Logic
if (isset($_POST['btn_enroll'])) {
    // Collect and Sanitize Inputs
    $sid       = mysqli_real_escape_string($conn, $_POST['student_id']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $fname     = mysqli_real_escape_string($conn, $_POST['firstname']);
    $mname     = mysqli_real_escape_string($conn, $_POST['middlename']);
    $lname     = mysqli_real_escape_string($conn, $_POST['lastname']);
    $ext       = mysqli_real_escape_string($conn, $_POST['extension']);
    $sex       = mysqli_real_escape_string($conn, $_POST['sex']);
    $bday      = mysqli_real_escape_string($conn, $_POST['birthdate']);
    $bplace    = mysqli_real_escape_string($conn, $_POST['birthplace']);
    $school    = mysqli_real_escape_string($conn, $_POST['last_school_attended']);
    $choice1   = mysqli_real_escape_string($conn, $_POST['first_choice_program']);
    $choice2   = mysqli_real_escape_string($conn, $_POST['second_choice_program']);
    $program   = mysqli_real_escape_string($conn, $_POST['program']);
    $year      = mysqli_real_escape_string($conn, $_POST['year_level']);
    $section   = mysqli_real_escape_string($conn, $_POST['section']);

    // Logic for NULL-able 'special' field
    $special_val = $_POST['special'] ?? 'N/A';
    $special   = ($special_val === 'N/A') ? null : mysqli_real_escape_string($conn, $special_val);

    $mobile    = mysqli_real_escape_string($conn, $_POST['mobile_number']);
    $street    = mysqli_real_escape_string($conn, $_POST['house_no_street']);
    $brgy      = mysqli_real_escape_string($conn, $_POST['barangay']);
    $city      = mysqli_real_escape_string($conn, $_POST['city']);
    $prov      = mysqli_real_escape_string($conn, $_POST['province']);
    $reg       = mysqli_real_escape_string($conn, $_POST['region']);
    $gname     = mysqli_real_escape_string($conn, $_POST['guardian_name']);
    $gcontact  = mysqli_real_escape_string($conn, $_POST['guardian_contact']);

    // Check if Student ID or Email already exists
    $check = $conn->prepare("SELECT email FROM users WHERE email = ? UNION SELECT email FROM students WHERE student_id = ? OR email = ?");
    $check->bind_param("sss", $email, $sid, $email);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        $message = "<div class='alert error'><strong>Error:</strong> Student ID or Email already exists.</div>";
    } else {
        $conn->begin_transaction();

        try {
            // 1. Insert into students table (Added 'special' column)
            $sql_student = "INSERT INTO students (
                        student_id, email, firstname, middlename, lastname, extension, 
                        sex, birthdate, birthplace, last_school_attended, 
                        first_choice_program, second_choice_program, program, 
                        year_level, section, special, mobile_number, house_no_street, 
                        barangay, city, province, region, guardian_name, 
                        guardian_contact, school_year, semester, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Enrolled')";

            $stmt_st = $conn->prepare($sql_student);
            // 26 placeholders require 26 types in bind_param
            $stmt_st->bind_param(
                "ssssssssssssssssssssssssss",
                $sid,
                $email,
                $fname,
                $mname,
                $lname,
                $ext,
                $sex,
                $bday,
                $bplace,
                $school,
                $choice1,
                $choice2,
                $program,
                $year,
                $section,
                $special,
                $mobile,
                $street,
                $brgy,
                $city,
                $prov,
                $reg,
                $gname,
                $gcontact,
                $active_sy,
                $active_sem
            );
            $stmt_st->execute();

            $log_details = "Added a student with ID: $sid. $fname $lname (Special: " . ($special ?? 'N/A') . ")";
            log_system_activity($conn, 'ADDED_STUDENT', $log_details);

            // 2. Insert into users table
            $hashed_pass  = password_hash($bday, PASSWORD_DEFAULT);
            $user_level   = "STUDENT";
            $user_status  = "active";

            $sql_user = "INSERT INTO users (
                            email, password, level, firstname, middlename, lastname, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt_usr = $conn->prepare($sql_user);
            $stmt_usr->bind_param("sssssss", $email, $hashed_pass, $user_level, $fname, $mname, $lname, $user_status);
            $stmt_usr->execute();

            $conn->commit();
            $_POST = array();
            $message = "<div class='alert success'><strong>Success!</strong> Student $sid enrolled. Initial Password: <strong>$bday</strong></div>";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='alert error'><strong>System Error:</strong> Enrollment failed. (" . $e->getMessage() . ")</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Enrollment | CDL</title>
    <link rel="icon" type="image/png" href="../assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="../assets/img/CDL_seal.png">
    <style>
        .compact-body {
            background: #f4f7f6;
            font-size: 0.82rem;
        }

        .main-wrapper {
            max-width: 980px;
            margin: 10px auto;
            padding: 0 10px;
        }

        .form-card-mini {
            background: #fff;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .section-tag {
            background: #2d5a27;
            color: #fff;
            padding: 3px 8px;
            font-size: 0.7rem;
            border-radius: 3px;
            display: inline-block;
            margin: 10px 0 5px 0;
            font-weight: bold;
        }

        .dense-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
        }

        .u-span-2 {
            grid-column: span 2;
        }

        .u-span-3 {
            grid-column: span 3;
        }

        .mini-input {
            margin-bottom: 2px;
        }

        .mini-input label {
            display: block;
            font-size: 0.65rem;
            font-weight: 700;
            color: #555;
            margin-bottom: 1px;
            text-transform: uppercase;
        }

        .mini-input input,
        .mini-input select {
            width: 100%;
            padding: 5px 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.85rem;
            box-sizing: border-box;
        }

        .instruction-bar {
            background: #e7f3ff;
            color: #0c5460;
            padding: 6px 10px;
            border-radius: 4px;
            margin-bottom: 8px;
            font-size: 0.75rem;
            border-left: 4px solid #17a2b8;
        }
    </style>
</head>

<body class="compact-body">
    <?php include 'navbar.php'; ?>

    <div class="main-wrapper">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
            <h4 style="margin:0;"><i class="fas fa-user-plus"></i> Student Registration (<?= $active_sy ?>)</h4>
            <a href="manage_students.php" class="btn-outline" style="padding: 3px 8px; font-size: 0.7rem;">Back to List</a>
        </div>

        <?php if ($message) echo $message; ?>

        <div class="instruction-bar">
            <i class="fas fa-edit"></i> <strong>Form Guide:</strong> Please input data on all blank fields.
        </div>

        <form method="POST" class="form-card-mini">
            <div class="section-tag" style="margin-top:0;">1. IDENTITY</div>
            <div class="dense-grid">
                <div class="mini-input u-span-2"><label>Student ID *</label><input type="text" name="student_id" value="<?= $_POST['student_id'] ?? '' ?>" required></div>
                <div class="mini-input u-span-2"><label>Email Address *</label><input type="email" name="email" value="<?= $_POST['email'] ?? '' ?>" required></div>

                <div class="mini-input"><label>First Name *</label><input type="text" name="firstname" value="<?= $_POST['firstname'] ?? '' ?>" required></div>
                <div class="mini-input"><label>Middle Name</label><input type="text" name="middlename" value="<?= $_POST['middlename'] ?? '' ?>"></div>
                <div class="mini-input"><label>Last Name *</label><input type="text" name="lastname" value="<?= $_POST['lastname'] ?? '' ?>" required></div>
                <div class="mini-input"><label>Ext. Name</label><input type="text" name="extension" placeholder="ex: Jr/Sr/III" value="<?= $_POST['extension'] ?? '' ?>"></div>

                <div class="mini-input"><label>Sex *</label>
                    <select name="sex" required>
                        <option value="Male" <?= (($_POST['sex'] ?? '') == 'Male') ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= (($_POST['sex'] ?? '') == 'Female') ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="mini-input"><label>Birthdate *</label><input type="date" name="birthdate" value="<?= $_POST['birthdate'] ?? '' ?>" required></div>
                <div class="mini-input u-span-2"><label>Birthplace</label><input type="text" name="birthplace" value="<?= $_POST['birthplace'] ?? '' ?>"></div>
            </div>

            <div class="section-tag">2. ACADEMIC CHOICES</div>
            <div class="dense-grid">
                <div class="mini-input u-span-2"><label>Last School Attended</label><input type="text" name="last_school_attended" value="<?= $_POST['last_school_attended'] ?? '' ?>" required></div>

                <div class="mini-input"><label>1st Choice Program *</label>
                    <select name="first_choice_program" id="choice1" required onchange="syncChoices()">
                        <option value="">-- Select --</option>
                        <?php $prog_query = $conn->query("SELECT program_code FROM programs ORDER BY program_code ASC");
                        while ($p = $prog_query->fetch_assoc()): ?>
                            <option value="<?= $p['program_code'] ?>" <?= (($_POST['first_choice_program'] ?? '') == $p['program_code']) ? 'selected' : '' ?>><?= $p['program_code'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mini-input"><label>2nd Choice Program *</label>
                    <select name="second_choice_program" id="choice2" required>
                        <option value="">-- Select --</option>
                        <?php mysqli_data_seek($prog_query, 0);
                        while ($p = $prog_query->fetch_assoc()): ?>
                            <option value="<?= $p['program_code'] ?>" <?= (($_POST['second_choice_program'] ?? '') == $p['program_code']) ? 'selected' : '' ?>><?= $p['program_code'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mini-input"><label>Enrolling In:</label>
                    <select name="program" id="program_select" required onchange="filterSections()">
                        <option value="">--</option>
                        <?php mysqli_data_seek($prog_query, 0);
                        while ($p = $prog_query->fetch_assoc()): ?>
                            <option value="<?= $p['program_code'] ?>" <?= (($_POST['program'] ?? '') == $p['program_code']) ? 'selected' : '' ?>><?= $p['program_code'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mini-input"><label>Year Level</label>
                    <select name="year_level" id="year_select" onchange="filterSections()">
                        <option value="1" <?= (($_POST['year_level'] ?? '') == '1') ? 'selected' : '' ?>>1st Year</option>
                        <option value="2" <?= (($_POST['year_level'] ?? '') == '2') ? 'selected' : '' ?>>2nd Year</option>
                        <option value="3" <?= (($_POST['year_level'] ?? '') == '3') ? 'selected' : '' ?>>3rd Year</option>
                        <option value="4" <?= (($_POST['year_level'] ?? '') == '4') ? 'selected' : '' ?>>4th Year</option>
                    </select>
                </div>

                <div class="mini-input"><label>Section Assignment</label>
                    <select name="section" id="section_select" required>
                        <option value="">-- Select Program --</option>
                    </select>
                </div>

                <div class="mini-input">
                    <label>Enroll to PFT04 (Swimming/Badminton)</label>
                    <select name="special">
                        <option value="N/A" <?= (($_POST['special'] ?? '') == 'N/A') ? 'selected' : '' ?>>N/A (None)</option>
                        <option value="SWIMMING" <?= (($_POST['special'] ?? '') == 'SWIMMING') ? 'selected' : '' ?>>SWIMMING</option>
                        <option value="BADMINTON" <?= (($_POST['special'] ?? '') == 'BADMINTON') ? 'selected' : '' ?>>BADMINTON</option>
                    </select>
                </div>
            </div>

            <div class="section-tag">3. CONTACT & LOCATION</div>
            <div class="dense-grid">
                <div class="mini-input"><label>Mobile No.</label><input type="text" name="mobile_number" value="<?= $_POST['mobile_number'] ?? '' ?>"></div>
                <div class="mini-input u-span-3"><label>Street Address</label><input type="text" name="house_no_street" value="<?= $_POST['house_no_street'] ?? '' ?>"></div>

                <div class="mini-input"><label>Barangay</label><input type="text" name="barangay" value="<?= $_POST['barangay'] ?? '' ?>"></div>
                <div class="mini-input"><label>City/Municipality</label><input type="text" name="city" value="<?= $_POST['city'] ?? '' ?>"></div>
                <div class="mini-input"><label>Province</label><input type="text" name="province" value="<?= $_POST['province'] ?? '' ?>"></div>
                <div class="mini-input"><label>Region</label><input type="text" name="region" value="<?= $_POST['region'] ?? '' ?>"></div>

                <div class="mini-input u-span-2"><label>Guardian Name</label><input type="text" name="guardian_name" value="<?= $_POST['guardian_name'] ?? '' ?>"></div>
                <div class="mini-input u-span-2"><label>Guardian Contact</label><input type="text" name="guardian_contact" value="<?= $_POST['guardian_contact'] ?? '' ?>"></div>
            </div>

            <button type="submit" name="btn_enroll" class="login-btn" style="background: #2d5a27; width: 100%; padding: 8px; margin-top: 15px; font-weight: bold; color: white; border: none; border-radius: 4px; cursor: pointer;">PROCESS ENROLLMENT</button>
        </form>
    </div>

    <script>
        const allSections = <?php echo json_encode($sections_data); ?>;
        const oldSec = "<?= $_POST['section'] ?? '' ?>";

        function syncChoices() {
            const c1 = document.getElementById('choice1').value;
            const c2Dropdown = document.getElementById('choice2');

            for (let i = 0; i < c2Dropdown.options.length; i++) {
                c2Dropdown.options[i].disabled = false;
                c2Dropdown.options[i].style.display = 'block';
            }

            if (c1) {
                for (let i = 0; i < c2Dropdown.options.length; i++) {
                    if (c2Dropdown.options[i].value === c1) {
                        c2Dropdown.options[i].disabled = true;
                        c2Dropdown.options[i].style.display = 'none';
                        if (c2Dropdown.value === c1) c2Dropdown.value = "";
                    }
                }
                document.getElementById('program_select').value = c1;
                filterSections();
            }
        }

        function filterSections() {
            const prog = document.getElementById('program_select').value;
            const year = document.getElementById('year_select').value;
            const dropdown = document.getElementById('section_select');
            dropdown.innerHTML = '<option value="">-- Select Section --</option>';
            if (!prog) return;

            const filtered = allSections.filter(s => s.program_id === prog && String(s.year_level) === String(year));
            filtered.forEach(sec => {
                const isFull = parseInt(sec.current_enrolled) >= parseInt(sec.max_capacity);
                const opt = document.createElement('option');
                opt.value = sec.section_name;
                opt.disabled = isFull;
                if (sec.section_name === oldSec) opt.selected = true;
                opt.innerHTML = `${sec.section_name} (${sec.current_enrolled}/${sec.max_capacity}) ${isFull ? '[FULL]' : ''}`;
                dropdown.appendChild(opt);
            });
        }

        window.onload = function() {
            syncChoices();
            filterSections();
        };
    </script>
</body>

</html>