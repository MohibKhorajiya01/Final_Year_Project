<?php
session_start();

// Disable error display
error_reporting(E_ALL);
ini_set('display_errors', 0);

// PHPMailer includes 
require __DIR__ . '/../PHPMailer/src/Exception.php';
require __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// handle form post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../backend/config.php';   // db connection file

    $userEmail = trim($_POST['email'] ?? '');   // email from form

    if (empty($userEmail)) {
        $_SESSION['error'] = "Please enter your email address.";
        header("Location: forgot_password.php");
        exit();
    }

    // step 1: check if this email exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    if (!$checkStmt) {
        error_log("Password Reset DB Error: " . $conn->error);
        $_SESSION['error'] = "Database error. Please try again later.";
        header("Location: forgot_password.php");
        exit();
    }
    $checkStmt->bind_param("s", $userEmail);
    $checkStmt->execute();
    $res = $checkStmt->get_result();

    if ($res && $res->num_rows > 0) {
        $u = $res->fetch_assoc();
        $userId = $u['id'];

        // step 2: make a random reset token
        $resetToken = bin2hex(random_bytes(16));  // 32 chars
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour")); // valid only 1 hr

        // step 3: insert into reset table
        $ins = $conn->prepare("INSERT INTO password_resets (id, token, expires_at) VALUES (?, ?, ?)");
        if (!$ins) {
            error_log("Password Reset Insert Error: " . $conn->error);
            $_SESSION['error'] = "Database error. Please try again later.";
            header("Location: forgot_password.php");
            exit();
        }
        $ins->bind_param("iss", $userId, $resetToken, $expiry);
        $ins->execute();

        // step 4: prepare reset link
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $basePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
        $link = $protocol . "://" . $host . $basePath . "/reset_password.php?token=" . $resetToken;

        // step 5: send mail using PHPMailer with improved error handling
        $mailer = new PHPMailer(true);
        $emailSent = false;
        $lastError = '';

        // Try STARTTLS first (Port 587)
        try {
            $mailer->isSMTP();
            $mailer->Host       = 'smtp.gmail.com';
            $mailer->SMTPAuth   = true;
            $mailer->Username   = 'eventease99@gmail.com';   
            $mailer->Password   = 'swzm xqax ismz radb';              
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port       = 587;
            $mailer->SMTPDebug = 0;
            $mailer->Timeout = 30;

            $mailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // mail content
            $mailer->setFrom('eventease99@gmail.com', 'Event Ease Support');
            $mailer->addAddress($userEmail);
            $mailer->isHTML(true);
            $mailer->Subject = "Password Reset Request - Event Ease";
            $mailer->Body    = "<div style='font-family: Arial, sans-serif; padding: 20px;'>
                                <h3 style='color: #5a2ca0;'>Password Reset Request</h3>
                                <p>Hello,</p>
                                <p>You requested to reset your password. Click the link below to reset:</p>
                                <p style='margin: 20px 0;'><a href='$link' style='background: #5a2ca0; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Reset Password</a></p>
                                <p>Or copy this link: <br><small style='color: #666; word-break: break-all;'>$link</small></p>
                                <p style='color: #666; font-size: 12px; margin-top: 20px;'>This link will expire in 1 hour.</p>
                                <p style='color: #666; font-size: 12px;'>If you didn't request this, please ignore this email.</p>
                               </div>";

            $mailer->send();
            $emailSent = true;
        } catch (Exception $ex) {
            $lastError = $mailer->ErrorInfo;
            // Try SSL as fallback (Port 465)
            try {
                $mailer = new PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host       = 'smtp.gmail.com';
                $mailer->SMTPAuth   = true;
                $mailer->Username   = 'eventease99@gmail.com';   
                $mailer->Password   = 'swzm xqax ismz radb';              
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mailer->Port       = 465;
                $mailer->SMTPDebug = 0;
                $mailer->Timeout = 30;

                $mailer->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                $mailer->setFrom('eventease99@gmail.com', 'Event Ease Support');
                $mailer->addAddress($userEmail);
                $mailer->isHTML(true);
                $mailer->Subject = "Password Reset Request - Event Ease";
                $mailer->Body    = "<div style='font-family: Arial, sans-serif; padding: 20px;'>
                                    <h3 style='color: #5a2ca0;'>Password Reset Request</h3>
                                    <p>Hello,</p>
                                    <p>You requested to reset your password. Click the link below to reset:</p>
                                    <p style='margin: 20px 0;'><a href='$link' style='background: #5a2ca0; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Reset Password</a></p>
                                    <p>Or copy this link: <br><small style='color: #666; word-break: break-all;'>$link</small></p>
                                    <p style='color: #666; font-size: 12px; margin-top: 20px;'>This link will expire in 1 hour.</p>
                                    <p style='color: #666; font-size: 12px;'>If you didn't request this, please ignore this email.</p>
                                   </div>";

                $mailer->send();
                $emailSent = true;
            } catch (Exception $ex2) {
                $lastError = $mailer->ErrorInfo;
            }
        }

        if ($emailSent) {
            $_SESSION['success'] = "Password reset link has been sent to your email. Please check your inbox.";
            header("Location: forgot_password.php");
            exit();
        } else {
            error_log("Password Reset Email Error for $userEmail: " . $lastError);
            $_SESSION['error'] = "Unable to send password reset email. Please check your email address and try again. If the problem persists, please contact support.";
            header("Location: forgot_password.php");
            exit();
        }

    } else {
        // email not found in database - don't reveal this for security
        $_SESSION['success'] = "If the email exists, a password reset link has been sent.";
        header("Location: forgot_password.php");
        exit();
    }
}
?>
