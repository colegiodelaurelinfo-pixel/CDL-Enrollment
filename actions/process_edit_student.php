<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

if (isset($_POST['btn_update_student'])) {
    // 1. Capture Identifiers (Sanitized)
    $old_id    = mysqli_real_escape_string($conn, trim($_POST['old_student_id']));
    $old_email = mysqli_real_escape_string($conn, trim($_POST['old_email']));

    // 2. Personal Information
    $new_id      = mysqli_real_escape_string($conn, trim($_POST['student_id']));
    $fname       = mysqli_real_escape_string($conn, trim($_POST['firstname']));
    $mname       = mysqli_real_escape_string($conn, trim($_POST['middlename']));
    $lname       = mysqli_real_escape_string($conn, trim($_POST['lastname']));
    $ext         = mysqli_real_escape_string($conn, trim($_POST['extension']));
    $email       = mysqli_real_escape_string($conn, trim($_POST['email']));
    $sex         = mysqli_real_escape_string($conn, $_POST['sex']);
    $bday        = mysqli_real_escape_string($conn, trim($_POST['birthdate'])); // Format: YYYY-MM-DD
    $bplace      = mysqli_real_escape_string($conn, trim($_POST['birthplace']));

    // 3. Academic Information
    $status      = mysqli_real_escape_string($conn, $_POST['status']);
    $prog        = mysqli_real_escape_string($conn, $_POST['program']);
    $year        = mysqli_real_escape_string($conn, $_POST['year_level']);
    $sec         = mysqli_real_escape_string($conn, $_POST['section']);
    $sy          = mysqli_real_escape_string($conn, $_POST['school_year']);
    $sem         = mysqli_real_escape_string($conn, $_POST['semester']);
    $special     = mysqli_real_escape_string($conn, $_POST['special']);

    // 4. Background & Contact
    $last_school = mysqli_real_escape_string($conn, trim($_POST['last_school_attended']));
    $choice1     = mysqli_real_escape_string($conn, $_POST['first_choice_program']);
    $choice2     = mysqli_real_escape_string($conn, $_POST['second_choice_program']);
    $mobile      = mysqli_real_escape_string($conn, trim($_POST['mobile_number']));
    $street      = mysqli_real_escape_string($conn, trim($_POST['house_no_street']));
    $brgy        = mysqli_real_escape_string($conn, trim($_POST['barangay']));
    $city        = mysqli_real_escape_string($conn, trim($_POST['city']));
    $prov        = mysqli_real_escape_string($conn, trim($_POST['province']));
    $reg         = mysqli_real_escape_string($conn, trim($_POST['region']));
    $gname       = mysqli_real_escape_string($conn, trim($_POST['guardian_name']));
    $gcontact    = mysqli_real_escape_string($conn, trim($_POST['guardian_contact']));

    // 5. Password Logic: Manual entry takes priority; Default is YYYY-MM-DD
    $input_pw = trim($_POST['password'] ?? '');
    $raw_password = (!empty($input_pw)) ? $input_pw : $bday;
    $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

    $conn->begin_transaction();

    try {
        // --- STEP 1: Update Students Table ---
        $sql = "UPDATE students SET 
                student_id = ?, firstname = ?, middlename = ?, lastname = ?, extension = ?, 
                email = ?, sex = ?, birthdate = ?, birthplace = ?, 
                status = ?, program = ?, year_level = ?, section = ?, 
                school_year = ?, semester = ?, special = ?, last_school_attended = ?, 
                first_choice_program = ?, second_choice_program = ?,
                mobile_number = ?, house_no_street = ?, barangay = ?, 
                city = ?, province = ?, region = ?, 
                guardian_name = ?, guardian_contact = ?
                WHERE student_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssssssssssssssssssssss",
            $new_id,
            $fname,
            $mname,
            $lname,
            $ext,
            $email,
            $sex,
            $bday,
            $bplace,
            $status,
            $prog,
            $year,
            $sec,
            $sy,
            $sem,
            $special,
            $last_school,
            $choice1,
            $choice2,
            $mobile,
            $street,
            $brgy,
            $city,
            $prov,
            $reg,
            $gname,
            $gcontact,
            $old_id
        );
        $stmt->execute();

        // --- STEP 2: Update Users Table (Syncing Names, Email, and Password) ---
        // This ensures the student can log in using their email and birthday (YYYY-MM-DD)
        $user_sql = "UPDATE users SET 
                     firstname = ?, middlename = ?, lastname = ?, 
                     email = ?, password = ? 
                     WHERE email = ?";

        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("ssssss", $fname, $mname, $lname, $email, $hashed_password, $old_email);
        $user_stmt->execute();

        // --- STEP 3: Log the activity ---
        $log_details = "Updated Student: $lname. Auth synced. Default PW: $bday";
        if (function_exists('log_system_activity')) {
            log_system_activity($conn, 'UPDATE_STUDENT', $log_details);
        }

        $conn->commit();
        header("Location: ../admin/manage_students.php?msg=updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die("Critical Update Failure: " . $e->getMessage());
    }
}
