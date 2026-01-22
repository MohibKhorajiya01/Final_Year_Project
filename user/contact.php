<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

function tableExists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $check = $conn->query("SHOW TABLES LIKE '$safe'");
    return $check && $check->num_rows > 0;
}

function ensureContactTable(mysqli $conn): void {
    if (!tableExists($conn, 'contact_messages')) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS contact_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                subject VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'new',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}

ensureContactTable($conn);

$successMessage = "";
$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        $errorMessage = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } else {
        // Save to database
        $stmt = $conn->prepare("INSERT INTO contact_messages (user_id, name, email, subject, message) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
            $stmt->bind_param("issss", $userId, $name, $email, $subject, $message);
            $stmt->execute();
            $stmt->close();
        }
        
        // Send email to eventease99@gmail.com
        $to = "eventease99@gmail.com";
        $emailSubject = "Contact Form: " . $subject;
        $emailBody = "Name: $name\n";
        $emailBody .= "Email: $email\n";
        $emailBody .= "Subject: $subject\n\n";
        $emailBody .= "Message:\n$message\n";
        
        $headers = "From: $email\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Try to send email
        if (mail($to, $emailSubject, $emailBody, $headers)) {
            $successMessage = "Thank you! Your message has been sent to eventease99@gmail.com. We'll get back to you soon.";
        } else {
            $successMessage = "Your message has been saved. We'll get back to you soon.";
        }
        $_POST = [];
    }
}

$userName = "";
$userEmail = "";
if (isset($_SESSION['user_id']) && tableExists($conn, 'users')) {
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $userName = $row['name'] ?? '';
                $userEmail = $row['email'] ?? '';
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Event Ease</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout.css?v=2">
    <style>
        :root {
            --primary: #5a2ca0;
            --primary-dark: #431f75;
            --accent: #ffb347;
            --bg: #f5f3ff;
        }
        * {
            box-sizing: border-box;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: var(--bg);
            margin: 0;
            padding-top: 70px;
        }
        .hero-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            padding: 60px 20px;
            text-align: center;
            margin-bottom: 40px;
        }
        .hero-section h1 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .hero-section p {
            font-size: 18px;
            opacity: 0.95;
        }
        .contact-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 40px;
        }
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        .contact-card {
            background: #fff;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(90,44,160,0.08);
            border: 1px solid rgba(90,44,160,0.08);
        }
        .contact-card h3 {
            color: var(--primary-dark);
            margin-bottom: 20px;
            font-size: 24px;
        }
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(90,44,160,0.1);
        }
        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .info-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(90,44,160,0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .info-content h5 {
            margin: 0 0 5px 0;
            color: var(--primary-dark);
            font-size: 16px;
        }
        .info-content p {
            margin: 0;
            color: #6c6c6c;
            font-size: 14px;
        }
        .form-card {
            background: #fff;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 25px rgba(90,44,160,0.08);
            border: 1px solid rgba(90,44,160,0.08);
        }
        .form-card h3 {
            color: var(--primary-dark);
            margin-bottom: 25px;
            font-size: 28px;
        }
        .form-label {
            color: var(--primary-dark);
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-control,
        .form-select {
            border: 2px solid rgba(90,44,160,0.15);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(90,44,160,0.25);
            outline: none;
        }
        .btn-submit {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 14px 35px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(90,44,160,0.3);
        }
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        .site-footer {
            background: linear-gradient(90deg, #431f75, #5a2ca0);
            color: #f3e9ff;
            margin-top: 60px;
            padding: 40px 20px 30px;
        }
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            justify-content: space-between;
            align-items: center;
            text-align: center;
        }
        .footer-brand h4 {
            margin: 0;
            font-size: 26px;
            letter-spacing: 1px;
        }
        .footer-brand p {
            margin: 8px 0 0 0;
            color: #d7c9f6;
            font-size: 14px;
        }
        .footer-links {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .footer-links a {
            color: #f3e9ff;
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: opacity 0.3s;
        }
        .footer-links a:hover {
            opacity: 0.8;
        }
        .footer-meta {
            width: 100%;
            margin-top: 15px;
            font-size: 14px;
            color: #d7c9f6;
        }
        @media (max-width: 992px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
            .nav-links {
                display: none;
            }
            .hero-section h1 {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="hero-section">
        <div class="container">
            <h1>Get in Touch</h1>
            <p>We're here to help! Reach out to us for any questions or support.</p>
        </div>
    </section>

    <div class="contact-container">
        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="contact-grid">
            <div class="contact-card">
                <h3>Contact Information</h3>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="info-content">
                        <h5>Address</h5>
                        <p>Rajkot, Gujarat<br>India</p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="info-content">
                        <h5>Phone</h5>
                        <p>+91 77790 33629<br>+91 70698 80850</p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="info-content">
                        <h5>Email</h5>
                        <p><a href="mailto:eventease99@gmail.com" style="color: #6c6c6c; text-decoration: none;">eventease99@gmail.com</a></p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="info-content">
                        <h5>Business Hours</h5>
                        <p>Monday - Friday: 9:00 AM - 6:00 PM<br>Saturday: 10:00 AM - 4:00 PM<br>Sunday: Closed</p>
                    </div>
                </div>
            </div>

            <div class="form-card">
                <h3>Send us a Message</h3>
                <p style="color: #6c6c6c; margin-bottom: 25px;">Click the button below to compose an email directly in your email app.</p>
                
                <div class="text-center">
                    <a href="mailto:eventease99@gmail.com?subject=Event%20Ease%20Query&body=Hi%20Event%20Ease%20Team,%0D%0A%0D%0AI%20would%20like%20to%20inquire%20about..." 
                       class="btn btn-submit" 
                       style="display: inline-block; text-decoration: none;">
                        <i class="fas fa-envelope me-2"></i> Send Email
                    </a>
                </div>
                
                <div style="margin-top: 25px; padding: 20px; background: #f8f9fa; border-radius: 12px;">
                    <p style="margin: 0; color: #6c6c6c; font-size: 14px;">
                        <strong>Email us directly:</strong><br>
                        <a href="mailto:eventease99@gmail.com" style="color: #5a2ca0; text-decoration: none; font-weight: 600;">
                            eventease99@gmail.com
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/layout.js?v=2"></script>
</body>
</html>


