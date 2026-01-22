<?php 
// Start the session (for flashing messages, etc.)
session_start();

// Calculate backend path for form action - go up from /user to root, then to /backend
$scriptPath = $_SERVER['PHP_SELF']; // e.g., /Final_Year_Project/Final_Year_Project/user/register.php
$dirPath = dirname($scriptPath); // e.g., /Final_Year_Project/Final_Year_Project/user
$backendPath = dirname($dirPath) . '/backend/register.php'; // e.g., /Final_Year_Project/Final_Year_Project/backend/register.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <!-- Viewport is important for mobile responsiveness -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Registration</title>

  <!-- Bootstrap core CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css">

  <!-- jQuery -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <!-- Custom CSS file -->
  <link rel="stylesheet" href="assets/css/layout.css?v=2">

  <style>

    /* styling  */
    body {
      background: #f8f9fa;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      padding: 0;
    }
    .register-wrapper {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center; 
      padding: 40px 20px;
      margin-top: 80px;
    }
    .register-box {
      max-width: 420px;
      width: 100%;
    }
    h2 {
      color: #6f42c1;
    }
    .btn-primary {
      background-color: #6f42c1;
      border: none;
    }
    .btn-primary:hover {
      background-color: #5a2d91;
    }
    .error-msg {
      color: red;
      font-size: 0.85rem;
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

<div class="register-wrapper">
  <div class="register-box">

  <!-- Show error messages from session -->
  <?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <strong>Oops!</strong> <?= $_SESSION['error']; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>

  <!-- Show success message -->
  <?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success">
      <?= $_SESSION['success']; ?>
    </div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <!-- Registration form -->
  <form id="signupForm" action="<?php echo htmlspecialchars($backendPath); ?>" method="POST" class="bg-white border rounded p-4 shadow-sm">
    <!-- Logo and Brand -->
    <div class="text-center mb-4">
      <img src="assets/1.logo.png" alt="Event Ease" style="width: 70px; height: 70px; border-radius: 50%; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
      <h3 style="color: #6f42c1; font-size: 1.5rem; font-weight: 700; margin: 0;">Event Ease</h3>
      <p style="color: #6c757d; font-size: 0.85rem; margin: 5px 0 0 0;">Plan, Book & Celebrate</p>
    </div>
    
    <hr style="margin: 20px 0;">
    
    <h2 class="text-center mb-4" style="font-size: 1.3rem;">Create Your Account</h2>

    <div class="mb-3">
      <label for="fullname" class="form-label">Full Name <span class="required-star">*</span></label>
      <input type="text" class="form-control" id="fullname" name="name" placeholder="Your full name here">
      <small class="error-msg" id="nameErr"></small>
    </div>

    <div class="mb-3">
      <label for="emailAddr" class="form-label">Email <span class="required-star">*</span></label>
      <input type="email" class="form-control" id="emailAddr" name="email" placeholder="Your Email">
      <small class="error-msg" id="emailErr"></small>
    </div>

    <div class="mb-3">
      <label for="pass" class="form-label">Password <span class="required-star">*</span></label>
      <input type="password" class="form-control" id="pass" name="password" placeholder="Choose a password">
      <small class="error-msg" id="passErr"></small>
    </div>

    <div class="mb-3">
      <label for="pass2" class="form-label">Confirm Password <span class="required-star">*</span></label>
      <input type="password" class="form-control" id="pass2" name="confirm_password" placeholder="Retype password">
      <small class="error-msg" id="pass2Err"></small>
    </div>

    <div class="mb-3">
      <label for="phoneNo" class="form-label">Phone <span class="required-star">*</span></label>
      <input type="text" class="form-control" id="phoneNo" name="phone" placeholder="Enter number">
      <small class="error-msg" id="phoneErr"></small>
    </div>

    <button type="submit" class="btn btn-primary w-100">Register</button>

    <p class="text-center mt-3">
      Already signed up? <a href="login.php" class="text-purple">Login here</a>
    </p>
  </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>

// Little bit of vanilla JS for validation (nothing too fancy)
document.getElementById("signupForm").addEventListener("submit", function(e) {
  let isOkay = true;
  document.querySelectorAll(".error-msg").forEach(el => el.innerText = "");

  // full name
  let nm = document.getElementById("fullname").value.trim();
  if (!nm) {
    document.getElementById("nameErr").innerText = "Name is required.";
    isOkay = false;
  } else if (nm.length < 3) {
    document.getElementById("nameErr").innerText = "Name should be 3+ chars.";
    isOkay = false;
  }

  // email
  let mail = document.getElementById("emailAddr").value.trim();
  let emailRegex = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;
  if (!mail) {
    document.getElementById("emailErr").innerText = "Email required.";
    isOkay = false;
  } else if (!emailRegex.test(mail)) {
    document.getElementById("emailErr").innerText = "That doesn’t look like a valid email.";
    isOkay = false;
  }

  // password
  let pw1 = document.getElementById("pass").value.trim();
  if (!pw1) {
    document.getElementById("passErr").innerText = "Password required.";
    isOkay = false;
  } else if (pw1.length < 6) {
    document.getElementById("passErr").innerText = "At least 6 characters please.";
    isOkay = false;
  }

  // confirm password
  let pw2 = document.getElementById("pass2").value.trim();
  if (pw2 !== pw1) {
    document.getElementById("pass2Err").innerText = "Passwords don’t match.";
    isOkay = false;
  }

  // phone
  let ph = document.getElementById("phoneNo").value.trim();
  let phoneRegex = /^[0-9]{10}$/;
  if (!ph) {
    document.getElementById("phoneErr").innerText = "Phone number required.";
    isOkay = false;
  } else if (!phoneRegex.test(ph)) {
    document.getElementById("phoneErr").innerText = "Enter 10 digit phone only.";
    isOkay = false;
  }

  // stop submission if invalid
  if (!isOkay) e.preventDefault();
});

// Clear error message when typing
["fullname","emailAddr","phoneNo","pass","pass2"].forEach(field => {
  document.getElementById(field).addEventListener("input", function() {
    let errId = field.replace("fullname","name").replace("emailAddr","email")
                     .replace("phoneNo","phone").replace("pass2","pass2").replace("pass","pass");
    document.getElementById(errId+"Err").innerText = "";
  });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/layout.js?v=3"></script>
</body>
</html>
