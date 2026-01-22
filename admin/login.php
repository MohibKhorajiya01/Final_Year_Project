<?php
session_start();
require_once('../backend/config.php');

if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Error message handle
$loginError = "";
if (!empty($_SESSION['admin_error'])) {
    $loginError = $_SESSION['admin_error'];
    unset($_SESSION['admin_error']);
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminEmail = $conn->real_escape_string($_POST['email'] ?? '');
    $adminPass = $_POST['password'] ?? '';

    if ($adminEmail && $adminPass) {
        // Check admin credentials
        $checkAdmin = $conn->query("SELECT * FROM admins WHERE email='$adminEmail' LIMIT 1");

        if ($checkAdmin && $checkAdmin->num_rows > 0) {
            $row = $checkAdmin->fetch_assoc();
            
            // Verify password (plain text for demo, use password_hash in production)
            if (password_verify($adminPass, $row['password'])) {
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin_name'] = $row['name'];
                $_SESSION['admin_role'] = 'admin';
                
                header("Location: index.php");
                exit();
            } else {
                $_SESSION['admin_error'] = "Invalid password!";
            }
        } else {
            $_SESSION['admin_error'] = "Admin not found!";
        }
    } else {
        $_SESSION['admin_error'] = "All fields are required.";
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
    <title>Admin Login - Event Ease</title>
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
      <p style="color: #6c757d; font-size: 0.85rem; margin: 5px 0 0 0;">Admin Dashboard</p>
    </div>
    
    <hr style="margin: 20px 0;">
    
    <h2 class="text-center mb-4" style="font-size: 1.3rem;">Admin Login</h2>

    <?php if (!empty($loginError)): ?>
      <div class="alert alert-danger text-center py-2">
        <?= htmlspecialchars($loginError); ?>
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <label for="adminEmail" class="form-label">Admin Email <span class="required-star">*</span></label>
      <input type="email" id="adminEmail" name="email" class="form-control" placeholder="admin@eventease.com" required>
    </div>

    <div class="mb-3">
      <label for="adminPass" class="form-label">Password <span class="required-star">*</span></label>
      <input type="password" id="adminPass" name="password" class="form-control" placeholder="Enter admin password" required>
    </div>

    <button type="submit" class="btn btn-login w-100">Access Admin Panel</button>
  </form>
</div>

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
</body>
</html>