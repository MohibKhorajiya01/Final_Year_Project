<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

// If someone opens login while already authenticated, clear the old session
if (isset($_SESSION['manager_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$loginError = "";
if (!empty($_SESSION['manager_error'])) {
    $loginError = $_SESSION['manager_error'];
    unset($_SESSION['manager_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $managerEmail = $conn->real_escape_string($_POST['email'] ?? '');
    $managerPass  = $_POST['password'] ?? '';

    if ($managerEmail && $managerPass) {
        $checkManager = $conn->query("SELECT * FROM managers WHERE email='$managerEmail' LIMIT 1");

        if ($checkManager && $checkManager->num_rows > 0) {
            $row = $checkManager->fetch_assoc();

            if (password_verify($managerPass, $row['password'])) {
                $_SESSION['manager_id'] = $row['id'];
                $_SESSION['manager_name'] = $row['name'];
                $_SESSION['manager_role'] = 'manager';

                header("Location: index.php");
                exit();
            } else {
                $_SESSION['manager_error'] = "Invalid password!";
            }
        } else {
            $_SESSION['manager_error'] = "Manager not found!";
        }
    } else {
        $_SESSION['manager_error'] = "All fields are required.";
    }

    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manager Login - Event Ease</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css">
  <style>
    body {
      background: #f8f9fa;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
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

<div class="login-box">
  <form method="POST" action="" class="bg-white shadow-sm p-4 rounded border">
    <!-- Logo and Brand -->
    <div class="text-center mb-4">
      <img src="../user/assets/1.logo.png" alt="Event Ease" style="width: 70px; height: 70px; border-radius: 50%; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
      <h3 style="color: #6f42c1; font-size: 1.5rem; font-weight: 700; margin: 0;">Event Ease</h3>
      <p style="color: #6c757d; font-size: 0.85rem; margin: 5px 0 0 0;">Manager Dashboard</p>
    </div>
    
    <hr style="margin: 20px 0;">
    
    <h2 class="text-center mb-4" style="font-size: 1.3rem;">Manager Login</h2>

    <?php if (!empty($loginError)): ?>
      <div class="alert alert-danger text-center py-2">
        <?= htmlspecialchars($loginError); ?>
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <label for="managerEmail" class="form-label">Manager Email <span class="required-star">*</span></label>
      <input type="email" id="managerEmail" name="email" class="form-control" placeholder="manager@eventease.com" required>
    </div>

    <div class="mb-3">
      <label for="managerPass" class="form-label">Password <span class="required-star">*</span></label>
      <input type="password" id="managerPass" name="password" class="form-control" placeholder="Enter password" required>
    </div>

    <button type="submit" class="btn btn-login w-100">Access Manager Panel</button>
  </form>
</div>

</body>
</html>

<script>
    // Auto-dismiss alerts
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            }, 3000);
        });
    });
</script>

