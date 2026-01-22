<?php
include 'config.php';
session_start();

// Disable error display during email sending
error_reporting(E_ALL);
ini_set('display_errors', 0);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $phone    = trim($_POST['phone']);

    // Validate inputs
    if (empty($name) || empty($email) || empty($phone)) {
        $_SESSION['error'] = "Please fill all required fields.";
        header("Location: ../user/register.php");
        exit();
    }

    // check if email already registered
    $check = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "Email is already registered. Please use another email.";
        header("Location: ../user/register.php");
        exit();
    }

    // generate OTP 
    $otp = rand(100000, 999999);

    // save data temporarily in session
    $_SESSION['temp_user'] = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'password' => $password,
        'otp' => $otp
    ];

    // send OTP mail with improved error handling
    $mail = new PHPMailer(true);
    $emailSent = false;
    $lastError = '';

    // Try SSL first (Port 465)
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'eventease99@gmail.com';
        $mail->Password = 'swzm xqax ismz radb';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port = 465;
        $mail->SMTPDebug = 0; // Disable debug output
        $mail->Timeout = 30; // 30 seconds timeout

        // Fix for XAMPP SSL certificate issues
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('eventease99@gmail.com', 'Event Ease');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = "OTP Verification - Event Ease";
        $mail->Body = "<div style='font-family: Arial, sans-serif; padding: 20px;'>
                        <h3 style='color: #5a2ca0;'>Hello $name,</h3>
                        <p>Thank you for registering with Event Ease!</p>
                        <p>Your OTP verification code is:</p>
                        <h2 style='color: #5a2ca0; background: #f5f3ff; padding: 15px; text-align: center; border-radius: 8px; letter-spacing: 5px;'>$otp</h2>
                        <p style='color: #666; font-size: 14px;'>This OTP will expire in 10 minutes.</p>
                        <p style='color: #666; font-size: 12px; margin-top: 20px;'>If you didn't request this, please ignore this email.</p>
                       </div>";

        $mail->send();
        $emailSent = true;
    } catch (Exception $e) {
        $lastError = $mail->ErrorInfo;
        // Try STARTTLS as fallback (Port 587)
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'eventease99@gmail.com';
            $mail->Password = 'swzm xqax ismz radb';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
            $mail->Port = 587;
            $mail->SMTPDebug = 0;
            $mail->Timeout = 30;

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom('eventease99@gmail.com', 'Event Ease');
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = "OTP Verification - Event Ease";
            $mail->Body = "<div style='font-family: Arial, sans-serif; padding: 20px;'>
                            <h3 style='color: #5a2ca0;'>Hello $name,</h3>
                            <p>Thank you for registering with Event Ease!</p>
                            <p>Your OTP verification code is:</p>
                            <h2 style='color: #5a2ca0; background: #f5f3ff; padding: 15px; text-align: center; border-radius: 8px; letter-spacing: 5px;'>$otp</h2>
                            <p style='color: #666; font-size: 14px;'>This OTP will expire in 10 minutes.</p>
                            <p style='color: #666; font-size: 12px; margin-top: 20px;'>If you didn't request this, please ignore this email.</p>
                           </div>";

            $mail->send();
            $emailSent = true;
        } catch (Exception $e2) {
            $lastError = $mail->ErrorInfo;
        }
    }

    if ($emailSent) {
        $_SESSION['email'] = $email;
        $_SESSION['success'] = "OTP has been sent to your email. Please check your inbox.";
        header("Location: ../user/verify_otp.php");
        exit();
    } else {
        // Log error for debugging (without exposing to user)
        error_log("OTP Email Error for $email: " . $lastError);
        
        // User-friendly error message
        $_SESSION['error'] = "Unable to send OTP email. Please check your email address and try again. If the problem persists, please contact support.";
        
        // Clean up session on error
        unset($_SESSION['temp_user']);
        
        header("Location: ../user/register.php");
        exit();
    }
}
?>
