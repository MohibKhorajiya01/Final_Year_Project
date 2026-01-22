<?php
require_once __DIR__ . '/../backend/config.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT id, expires_at FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($id, $expires_at);
    if ($stmt->fetch()) {
        if (strtotime($expires_at) < time()) {
            die("This reset link has expired.");
        }
    } else {
        die("Invalid reset link.");
    }
    $stmt->close();
} else {
    die("No token provided.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f8f9fc;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0px 3px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .btn-purple {
            background-color: #6f42c1;
            color: #fff;
        }
        .btn-purple:hover {
            background-color: #5a32a3;
            color: #fff;
        }
        h2 {
            color: #6f42c1;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="card">
                        <h2 class="text-center mb-4">Reset Password</h2>
                        <form action="update_password.php" method="POST">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" placeholder="Enter new password" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" name="confirm_password" placeholder="Confirm new password" required>
                            </div>

                            <button type="submit" class="btn btn-purple w-100">Update Password</button>
                        </form>
                        <p class="text-center mt-3">
                            <a href="login.php" class="text-decoration-none" style="color:#6f42c1;">Back to Login</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
