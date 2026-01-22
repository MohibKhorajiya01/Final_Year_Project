<?php
session_start();
require_once __DIR__ . '/../backend/config.php';   

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// error message handle
$loginError = "";
if (!empty($_SESSION['error'])) {
    $loginError = $_SESSION['error'];
    unset($_SESSION['error']);   // ek bar dikha ke hata diya
}

// handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // sanitizing (using real_escape_string just to be safe, though prepared stmt would be better)
    $userEmail = $conn->real_escape_string($_POST['email']);
    $userPass  = $conn->real_escape_string($_POST['password']);

    // only fetch verified users 
    $checkUser = $conn->query("SELECT * FROM users WHERE email='$userEmail' AND status=1 LIMIT 1");

    if ($checkUser && $checkUser->num_rows > 0) {
        $row = $checkUser->fetch_assoc();

        // check password hash
        if (password_verify($userPass, $row['password'])) {
            $_SESSION['user_id']   = $row['id'];
            $_SESSION['user_name'] = $row['name'];

            // Redirect to booking page if redirect parameter is set
            if (isset($_GET['redirect']) && $_GET['redirect'] === 'booking.php' && isset($_GET['event_id'])) {
                header("Location: booking.php?event_id=" . urlencode($_GET['event_id']));
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $_SESSION['error'] = "Wrong password entered!";
            header("Location: login.php");
            exit();
        }
    } else {
        // either email not found or not verified
        $_SESSION['error'] = "Email not found / not active.";
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Login</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/layout.css?v=2">
  <style>
    /* quick css (probably should move to style.css later) */
    body {
      background: #f8f9fa;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      padding: 0;
    }
    .login-wrapper {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
      margin-top: 80px;
    }
    .login-box {
      max-width: 420px;
      width: 100%;
    }
    h2 {
      color: #6f42c1;
    }
    .btn-login {
      background: #6f42c1;
      border: none;
      color: white;
    }
    .btn-login:hover {
      background: #5a2d91;
    }
    a.text-purple {
      color: #6f42c1 !important;
    }
    a.text-purple:hover {
      color: #5a2d91 !important;
    }

    .required-star {
      color: #dc3545;
      font-weight: 700;
      margin-left: 2px;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="login-wrapper">
  <div class="login-box">
  <form method="POST" action="" class="bg-white shadow-sm p-4 rounded border">
    <!-- Logo and Brand -->
    <div class="text-center mb-4">
      <img src="assets/1.logo.png" alt="Event Ease" style="width: 70px; height: 70px; border-radius: 50%; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
      <h3 style="color: #6f42c1; font-size: 1.5rem; font-weight: 700; margin: 0;">Event Ease</h3>
      <p style="color: #6c757d; font-size: 0.85rem; margin: 5px 0 0 0;">Plan, Book & Celebrate</p>
    </div>
    
    <hr style="margin: 20px 0;">
    
    <h2 class="text-center mb-4" style="font-size: 1.3rem;">Login to Your Account</h2>

    <!-- error -->
    <?php if (!empty($loginError)): ?>
      <div class="alert alert-danger text-center py-2">
        <?= $loginError; ?>
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <label for="loginEmail" class="form-label">Email <span class="required-star">*</span></label>
      <input type="email" id="loginEmail" name="email" class="form-control" placeholder="Enter Gmail" required>
    </div>

    <div class="mb-3">
      <label for="loginPass" class="form-label">Password <span class="required-star">*</span></label>
      <input type="password" id="loginPass" name="password" class="form-control" placeholder="Your password" required>
    </div>

    <div class="mb-3 text-end">

      <!-- maybe implement later -->
      <a href="forgot_password.php" class="text-purple text-decoration-none">Forgot Password?</a>
    </div>

    <button type="submit" class="btn btn-login w-100">Login</button>

    <div class="text-center mt-3">
      <p>No account yet? <a href="register.php" class="text-purple">Register here</a></p>
    </div>
  </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/layout.js?v=3"></script>
</body>
</html>
