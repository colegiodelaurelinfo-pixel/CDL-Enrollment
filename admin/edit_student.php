<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Check if the user is neither an ADMINISTRATOR nor a REGISTRAR
if ($_SESSION['level'] !== 'ADMINISTRATOR' && $_SESSION['level'] !== 'REGISTRAR') {
    header("Location: dashboard.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: manage_students.php");
    exit();
}

$student_id = $_GET['id'];

// Fetch Existing Student Data
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    die("Student record not found.");
}

// Fetch Programs and Sections
$programs_list = $conn->query("SELECT DISTINCT program_code FROM programs ORDER BY program_code ASC");
$sections_list = $conn->query("SELECT DISTINCT section_name, year_level FROM sections ORDER BY year_level ASC, section_name ASC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Edit Student | CDL</title>
    <link rel="icon" type="image/png" href="../assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="../assets/img/CDL_seal.png">
    <style>
        .edit-card {
            max-width: 1000px;
            margin: 30px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .section-header {
            background: #f0f4f0;
            padding: 12px;
            border-left: 5px solid #2d5a27;
            margin: 25px 0 15px 0;
            font-weight: bold;
            color: #2d5a27;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #555;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        input,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 0.9rem;
            box-sizing: border-box;
        }

        .btn-container {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
    </style>
</head>

<body style="background: #f4f7f6;">
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="edit-card" style="border-top: 5px solid #2d5a27;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                <h2 style="margin:0;"><i class="fas fa-user-edit"></i> Full Student Profile</h2>
                <div style="text-align: right;">
                    <span style="font-size: 0.8rem; color: #777;">Date Enrolled: <?= $student['date_enrolled'] ?></span>
                </div>
            </div>

            <form action="../actions/process_edit_student.php" method="POST">
                <input type="hidden" name="old_student_id" value="<?php echo $student['student_id']; ?>">
                <input type="hidden" name="old_email" value="<?php echo $student['email']; ?>">

                <div class="section-header"><i class="fas fa-university"></i> Academic & Enrollment Status</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Student ID Number</label>
                        <input type="text" name="student_id" value="<?= htmlspecialchars($student['student_id']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <?php $stats = ["Enrolled", "DO (Dropped Officially)", "DU (Dropped Unofficially)", "LOA (Leave of Absence)", "Transfer", "Graduated"];
                            foreach ($stats as $st): ?>
                                <option value="<?= $st ?>" <?= ($student['status'] == $st) ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School Year</label>
                        <input type="text" name="school_year" value="<?= htmlspecialchars($student['school_year']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Semester</label>
                        <select name="semester">
                            <option value="1st Semester" <?= ($student['semester'] == '1st Semester') ? 'selected' : '' ?>>1st Semester</option>
                            <option value="2nd Semester" <?= ($student['semester'] == '2nd Semester') ? 'selected' : '' ?>>2nd Semester</option>
                            <option value="Summer" <?= ($student['semester'] == 'Summer') ? 'selected' : '' ?>>Summer</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Current Program</label>
                        <select name="program">
                            <?php $programs_list->data_seek(0);
                            while ($p = $programs_list->fetch_assoc()): ?>
                                <option value="<?= $p['program_code'] ?>" <?= ($student['program'] == $p['program_code']) ? 'selected' : '' ?>><?= $p['program_code'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year Level</label>
                        <select name="year_level">
                            <option value="1" <?= ($student['year_level'] == '1') ? 'selected' : '' ?>>1st Year</option>
                            <option value="2" <?= ($student['year_level'] == '2') ? 'selected' : '' ?>>2nd Year</option>
                            <option value="3" <?= ($student['year_level'] == '3') ? 'selected' : '' ?>>3rd Year</option>
                            <option value="4" <?= ($student['year_level'] == '4') ? 'selected' : '' ?>>4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <select name="section">
                            <option value="">-- No Section --</option>
                            <?php $sections_list->data_seek(0);
                            while ($s = $sections_list->fetch_assoc()): ?>
                                <option value="<?= $s['section_name'] ?>" <?= ($student['section'] == $s['section_name']) ? 'selected' : '' ?>><?= $s['section_name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Enroll to PFT04</label>
                        <select name="special">
                            <option value="N/A" <?= ($student['special'] === null || $student['special'] == 'N/A') ? 'selected' : '' ?>>N/A (None)</option>
                            <option value="SWIMMING" <?= ($student['special'] == 'SWIMMING') ? 'selected' : '' ?>>SWIMMING</option>
                            <option value="BADMINTON" <?= ($student['special'] == 'BADMINTON') ? 'selected' : '' ?>>BADMINTON</option>
                        </select>
                    </div>
                </div>

                <div class="section-header"><i class="fas fa-user"></i> Personal Details</div>
                <div class="form-grid" style="grid-template-columns: 2fr 2fr 2fr 1fr;">
                    <div class="form-group"><label>First Name</label><input type="text" name="firstname" value="<?= htmlspecialchars($student['firstname']) ?>"></div>
                    <div class="form-group"><label>Middle Name</label><input type="text" name="middlename" value="<?= htmlspecialchars($student['middlename']) ?>"></div>
                    <div class="form-group"><label>Last Name</label><input type="text" name="lastname" value="<?= htmlspecialchars($student['lastname']) ?>"></div>
                    <div class="form-group"><label>Ext.</label><input type="text" name="extension" value="<?= htmlspecialchars($student['extension']) ?>" placeholder="Jr/III"></div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Sex</label>
                        <select name="sex">
                            <option value="Male" <?= ($student['sex'] == 'Male') ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($student['sex'] == 'Female') ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Birthdate</label><input type="date" name="birthdate" value="<?= $student['birthdate'] ?>"></div>
                    <div class="form-group u-span-2"><label>Birthplace</label><input type="text" name="birthplace" value="<?= htmlspecialchars($student['birthplace']) ?>"></div>
                </div>

                <div class="form-grid" style="grid-template-columns: 2fr 2fr;">
                    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($student['email']) ?>"></div>
                    <div class="form-group"><label>Mobile Number</label><input type="text" name="mobile_number" value="<?= htmlspecialchars($student['mobile_number']) ?>"></div>
                </div>

                <div class="section-header"><i class="fas fa-map-marker-alt"></i> Complete Address</div>
                <div class="form-grid">
                    <div class="form-group u-span-2"><label>House No./Street</label><input type="text" name="house_no_street" value="<?= htmlspecialchars($student['house_no_street']) ?>"></div>
                    <div class="form-group"><label>Barangay</label><input type="text" name="barangay" value="<?= htmlspecialchars($student['barangay']) ?>"></div>
                    <div class="form-group"><label>City/Municipality</label><input type="text" name="city" value="<?= htmlspecialchars($student['city']) ?>"></div>
                </div>
                <div class="form-grid" style="grid-template-columns: 2fr 2fr;">
                    <div class="form-group"><label>Province</label><input type="text" name="province" value="<?= htmlspecialchars($student['province']) ?>"></div>
                    <div class="form-group"><label>Region</label><input type="text" name="region" value="<?= htmlspecialchars($student['region']) ?>"></div>
                </div>

                <div class="section-header"><i class="fas fa-users-cog"></i> Guardian & Educational History</div>
                <div class="form-grid" style="grid-template-columns: 1.5fr 1fr 1.5fr;">
                    <div class="form-group"><label>Guardian Name</label><input type="text" name="guardian_name" value="<?= htmlspecialchars($student['guardian_name']) ?>"></div>
                    <div class="form-group"><label>Guardian Contact</label><input type="text" name="guardian_contact" value="<?= htmlspecialchars($student['guardian_contact']) ?>"></div>
                    <div class="form-group"><label>Last School Attended</label><input type="text" name="last_school_attended" value="<?= htmlspecialchars($student['last_school_attended']) ?>"></div>
                </div>

                <div class="section-header"><i class="fas fa-clipboard-list"></i> Original Admission Choices</div>
                <div class="form-grid" style="grid-template-columns: 2fr 2fr;">
                    <div class="form-group">
                        <label>1st Choice Program</label>
                        <select name="first_choice_program">
                            <option value="">-- None --</option>
                            <?php $programs_list->data_seek(0);
                            while ($p = $programs_list->fetch_assoc()): ?>
                                <option value="<?= $p['program_code'] ?>" <?= ($student['first_choice_program'] == $p['program_code']) ? 'selected' : '' ?>><?= $p['program_code'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>2nd Choice Program</label>
                        <select name="second_choice_program">
                            <option value="">-- None --</option>
                            <?php $programs_list->data_seek(0);
                            while ($p = $programs_list->fetch_assoc()): ?>
                                <option value="<?= $p['program_code'] ?>" <?= ($student['second_choice_program'] == $p['program_code']) ? 'selected' : '' ?>><?= $p['program_code'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-container">
                    <button type="submit" name="btn_update_student" class="login-btn" style="flex:2; background:#2d5a27; color: white; border: none; cursor: pointer;">Save All Changes</button>
                    <a href="manage_students.php" class="login-btn" style="flex:1; background:#666; text-align:center; text-decoration:none; color: white; line-height: 40px; border-radius: 4px;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>