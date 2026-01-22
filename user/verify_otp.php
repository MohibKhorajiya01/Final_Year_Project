<?php
// OTP Verification Page

session_start();
include('../backend/config.php');   

// Disable error display for production
error_reporting(E_ALL);
ini_set('display_errors', 0);

// --- Agar form submit thayo hoy to hi check karisu ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $enteredOtp = trim($_POST['otp']);   // user input OTP

    // Pehla check karie ke session ma temporary user data che ke nai
    if (!isset($_SESSION['temp_user'])) {
        $_SESSION['error'] = "Session expired. Please register again ";
        header("Location: register.php");
        exit();
    }

    // Session ma je store karyu hatu (name, email, phone, etc.)
    $tempUser = $_SESSION['temp_user'];

    // --- OTP match thayu ke nai ---
    if ($enteredOtp == $tempUser['otp']) {
        
        // SQL injection avoid karva prepared statements use karie
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, status) VALUES (?, ?, ?, ?, 1)");
        if ($stmt) {
            $stmt->bind_param("ssss", $tempUser['name'], $tempUser['email'], $tempUser['phone'], $tempUser['password']);
            
            if ($stmt->execute()) {
                // Session cleanup karie, jo user properly insert thai gayo
                unset($_SESSION['temp_user']);
                unset($_SESSION['email']);

                $_SESSION['success'] = " OTP Verified Successfully! Account created, now you can login.";
                header("Location: login.php");
                exit();
            } else {
                $_SESSION['error'] = "Database error Please try again.";
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Something went wrong with DB query!";
        }

    } else {
        // OTP khoto hoy to user ne feedback apiye
        $_SESSION['error'] = "Invalid OTP Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OTP Verification</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 15px;
    }
    h2 {
      color: #6f42c1;
    }
    .btn-primary {
      background-color: #6f42c1;
      border: none;
    }
    .btn-primary:hover {
      background-color: #5a32a3;
    }
    .otp-box {
      width: 100%;
      max-width: 400px;
    }

  </style>
</head>
<body>

  <div class="otp-box">
    <form method="POST" action="" class="p-4 border rounded bg-white shadow-sm">
      <h2 class="text-center mb-4">OTP Verification</h2>

      <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger text-center"><?= $_SESSION['error']; ?></div>
        <?php unset($_SESSION['error']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success text-center"><?= $_SESSION['success']; ?></div>
        <?php unset($_SESSION['success']); ?>
      <?php endif; ?>

      <div class="mb-3">
        <label for="otp" class="form-label">Enter OTP</label>
        <input type="text" class="form-control" name="otp" id="otp" 
               placeholder="Enter the OTP sent to your email" required>
      </div>

      <button type="submit" class="btn btn-primary w-100">Verify OTP</button>

      <!-- Back option -->
      <div class="text-center mt-3">
        <a href="register.php" class="text-primary text-decoration-none">⬅ Back to Registration</a>
      </div>
    </form>
  </div>

</body>
</html>
