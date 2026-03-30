<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../includes/PHPMailer/src/Exception.php';
require '../includes/PHPMailer/src/PHPMailer.php';
require '../includes/PHPMailer/src/SMTP.php';
require_once '../includes/config.php';

// Prevent random HTML output
ob_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['emails'])) {
    $emails = $_POST['emails'];
    $sy = htmlspecialchars($_POST['sy']);
    $sem = htmlspecialchars($_POST['sem']);

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'colegiodelaurel.info@gmail.com'; 
        $mail->Password   = 'ezxn rwpb oerz ewnv'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mail->SMTPKeepAlive = true; 

        $mail->setFrom('colegiodelaurel.info@gmail.com', 'CDL Registrar');
        $mail->addReplyTo('no-reply@no-reply.com', 'CDL Registrar');
        $mail->isHTML(true);
        $mail->Subject = "URGENT: Grade Submission Reminder ($sem SY $sy)";

        $success_count = 0;

        foreach ($emails as $email) {
            if (empty($email)) continue; 
            
            // --- 1. COOLDOWN CHECK ---
            // Skips sending if a reminder was sent to this email within the last 1 hour
            $check_stmt = $conn->prepare("SELECT sent_at FROM faculty_reminders WHERE instructor_email = ? AND sent_at > NOW() - INTERVAL 1 DAY");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            if($check_stmt->get_result()->num_rows > 0) {
                continue; // Move to the next email in the loop
            }

            try {
                $mail->clearAddresses();
                $mail->addAddress($email);
                
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; padding: 25px; border: 1px solid #2d5a27; border-radius: 10px; max-width: 600px;'>
                        <h2 style='color: #2d5a27;'>Action Required: Pending Grade Submissions</h2>
                        <p>Dear Faculty Member,</p>
                        <p>Our records show that you have <strong>one or more classes</strong> with pending grade entries for <strong>$sem, SY $sy</strong>.</p>
                        <p style='background: #fdf3f3; padding: 15px; border-left: 5px solid #dc3545;'>
                            Please log in to the <strong>CDL Faculty Portal</strong> immediately to finalize these records and avoid delays in student transcript processing.
                        </p>
                        <br>
                        <div style='text-align: center;'>
                            <a href='http://localhost/CDL-enrollment/index.php' style='background: #2d5a27; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Login to Faculty Portal</a>
                        </div>
                        <br>
                        <hr style='border:none; border-top: 1px solid #eee;'>
                        <p style='font-size: 0.85em; color: #777;'>This is an automated reminder. If you have recently submitted your grades, please disregard this message.</p>
                    </div>";

                if($mail->send()) {
                    $success_count++;
                    // --- 2. LOG THE REMINDER ---
                    $log_stmt = $conn->prepare("INSERT INTO faculty_reminders (instructor_email, academic_year, semester, sent_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE sent_at = NOW()");
                    $log_stmt->bind_param("sss", $email, $sy, $sem);
                    $log_stmt->execute();
                }
            } catch (Exception $e) {
                // Skip to next email if this specific one fails
                continue;
            }
        }
        
        $mail->smtpClose();
        ob_clean(); 
        echo json_encode(["status" => "success", "message" => "$success_count reminders sent successfully."]);

    } catch (Exception $e) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "SMTP Error: " . $mail->ErrorInfo]);
    }
} else {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Invalid Request"]);
}