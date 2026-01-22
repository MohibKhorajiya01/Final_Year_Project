<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../backend/config.php';

function tableExists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $check = $conn->query("SHOW TABLES LIKE '$safe'");
    return $check && $check->num_rows > 0;
}

$transactionId = $_GET['txn_id'] ?? '';
$bookingCode = $_GET['booking'] ?? '';
$amount = isset($_GET['amount']) ? (float) $_GET['amount'] : 0;
$paymentMethod = $_GET['method'] ?? 'Online Payment';

$paymentVerified = false;
$errorMessage = '';
$bookingUpdated = false;
$bookingDetails = null;

// Verify payment parameters
if ($transactionId && $bookingCode && $amount > 0) {
    $paymentVerified = true;
    
    // Get booking details
    $stmt = $conn->prepare("SELECT id, user_id, total_amount, event_id FROM bookings WHERE booking_code = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $bookingCode, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        $stmt->close();
        
        if ($booking) {
            // Get event title
            $eventStmt = $conn->prepare("SELECT title FROM events WHERE id = ?");
            if ($eventStmt) {
                $eventStmt->bind_param("i", $booking['event_id']);
                $eventStmt->execute();
                $eventResult = $eventStmt->get_result();
                $event = $eventResult->fetch_assoc();
                $eventStmt->close();
                $bookingDetails = ['event_title' => $event['title'] ?? 'Event'];
            }
            
            // Update booking payment status
            $updateStmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid', status = 'pending' WHERE id = ? AND user_id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("ii", $booking['id'], $_SESSION['user_id']);
                if ($updateStmt->execute()) {
                    $bookingUpdated = true;
                    
                    // Insert payment record
                    if (tableExists($conn, 'payments')) {
                        // Check if payment already exists
                        $checkStmt = $conn->prepare("SELECT id FROM payments WHERE transaction_id = ?");
                        $checkStmt->bind_param("s", $transactionId);
                        $checkStmt->execute();
                        $existingPayment = $checkStmt->get_result()->fetch_assoc();
                        $checkStmt->close();
                        
                        if (!$existingPayment) {
                            $paymentStmt = $conn->prepare("INSERT INTO payments (booking_id, user_id, amount, method, status, transaction_id, payment_details) VALUES (?, ?, ?, ?, 'completed', ?, ?)");
                            if ($paymentStmt) {
                                $paymentDetails = json_encode([
                                    'transaction_id' => $transactionId,
                                    'payment_method' => $paymentMethod,
                                    'amount' => $amount,
                                    'timestamp' => date('Y-m-d H:i:s')
                                ]);
                                $paymentStmt->bind_param("iidsss", $booking['id'], $_SESSION['user_id'], $amount, $paymentMethod, $transactionId, $paymentDetails);
                                $paymentStmt->execute();
                                $paymentStmt->close();
                            }
                        }
                    }
                }
                $updateStmt->close();
            }
            
            // Clear pending payment session
            if (isset($_SESSION['pending_payment'])) {
                unset($_SESSION['pending_payment']);
            }
        } else {
            $errorMessage = "Booking not found or does not belong to your account.";
            $paymentVerified = false;
        }
    }
} else {
    $errorMessage = "Invalid payment parameters. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation - Event Ease</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout.css?v=2">
    <style>
        :root {
            --primary: #5a2ca0;
            --primary-dark: #431f75;
            --bg: #f5f3ff;
            --success: #18a558;
            --success-light: rgba(24, 165, 88, 0.1);
        }
        
        body {
            background: var(--bg);
        }
        
        .success-wrapper {
            min-height: calc(100vh - 120px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            margin-top: 20px;
        }
        
        .success-card {
            width: 100%;
            max-width: 500px;
            background: #fff;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 15px 50px rgba(90, 44, 160, 0.12);
            text-align: center;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: successPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        @keyframes successPop {
            0% { transform: scale(0); }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }
        
        .success-icon i {
            font-size: 48px;
            color: white;
        }
        
        .error-icon {
            background: #dc3545;
        }
        
        .success-card h2 {
            color: var(--success);
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .success-card h2.error {
            color: #dc3545;
        }
        
        .success-card .subtitle {
            color: #666;
            font-size: 15px;
            margin-bottom: 24px;
        }
        
        .payment-details {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            margin: 24px 0;
            text-align: left;
        }
        
        .payment-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .payment-detail-row:last-child {
            border-bottom: none;
        }
        
        .payment-detail-row .label {
            color: #666;
            font-size: 14px;
        }
        
        .payment-detail-row .value {
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .payment-detail-row .value.amount {
            color: var(--success);
            font-size: 18px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            background: var(--success-light);
            color: var(--success);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .info-box {
            background: rgba(90, 44, 160, 0.05);
            border-radius: 12px;
            padding: 16px;
            margin-top: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-align: left;
        }
        
        .info-box i {
            color: var(--primary);
            font-size: 18px;
            margin-top: 2px;
        }
        
        .info-box p {
            margin: 0;
            color: #555;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 24px;
        }
        
        .btn-action:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(90, 44, 160, 0.3);
        }
        
        .btn-secondary {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background: var(--primary);
            color: white;
        }
        
        .confetti {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: 999;
        }
        
        .confetti-piece {
            position: absolute;
            width: 10px;
            height: 10px;
            background: var(--primary);
            opacity: 0;
            animation: confetti-fall 3s ease-out forwards;
        }
        
        @keyframes confetti-fall {
            0% {
                opacity: 1;
                transform: translateY(-100vh) rotate(0deg);
            }
            100% {
                opacity: 0;
                transform: translateY(100vh) rotate(720deg);
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<?php if ($paymentVerified && $bookingUpdated): ?>
<div class="confetti" id="confetti"></div>
<?php endif; ?>

<section class="success-wrapper">
    <div class="success-card">
        <?php if ($paymentVerified && $bookingUpdated): ?>
            <div class="success-icon">
                <i class="fa-solid fa-check"></i>
            </div>
            <h2>Payment Successful!</h2>
            <p class="subtitle">Your payment has been processed successfully.</p>
            
            <div class="payment-details">
                <div class="payment-detail-row">
                    <span class="label">Transaction ID</span>
                    <span class="value"><?= htmlspecialchars($transactionId); ?></span>
                </div>
                <div class="payment-detail-row">
                    <span class="label">Booking Reference</span>
                    <span class="value"><?= htmlspecialchars($bookingCode); ?></span>
                </div>
                <?php if ($bookingDetails): ?>
                <div class="payment-detail-row">
                    <span class="label">Event</span>
                    <span class="value"><?= htmlspecialchars($bookingDetails['event_title']); ?></span>
                </div>
                <?php endif; ?>
                <div class="payment-detail-row">
                    <span class="label">Payment Method</span>
                    <span class="value"><?= htmlspecialchars($paymentMethod); ?></span>
                </div>
                <div class="payment-detail-row">
                    <span class="label">Amount Paid</span>
                    <span class="value amount">₹<?= number_format($amount, 2); ?></span>
                </div>
                <div class="payment-detail-row">
                    <span class="label">Status</span>
                    <span class="status-badge"><i class="fa-solid fa-circle-check"></i> Completed</span>
                </div>
            </div>
            
            <div class="info-box">
                <i class="fa-solid fa-circle-info"></i>
                <p>Your booking is now pending admin approval. You will receive a confirmation once your booking has been approved. You can download your ticket from the bookings page after approval.</p>
            </div>
            
            <div class="mt-4">
                <a href="mybooking.php" class="btn-action">
                    <i class="fa-solid fa-ticket"></i> View My Bookings
                </a>
                <a href="index.php" class="btn-action btn-secondary">
                    <i class="fa-solid fa-home"></i> Home
                </a>
            </div>
        <?php else: ?>
            <div class="success-icon error-icon">
                <i class="fa-solid fa-times"></i>
            </div>
            <h2 class="error">Payment Failed</h2>
            <p class="subtitle"><?= htmlspecialchars($errorMessage ?: 'There was an issue processing your payment.'); ?></p>
            
            <div class="info-box" style="background: rgba(220, 53, 69, 0.05);">
                <i class="fa-solid fa-triangle-exclamation" style="color: #dc3545;"></i>
                <p>If you believe this is an error, please contact our support team with your booking details.</p>
            </div>
            
            <div class="mt-4">
                <a href="mybooking.php" class="btn-action">
                    <i class="fa-solid fa-arrow-left"></i> Back to Bookings
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="assets/js/layout.js?v=2"></script>

<?php if ($paymentVerified && $bookingUpdated): ?>
<script>
// Confetti animation
document.addEventListener('DOMContentLoaded', function() {
    const confettiContainer = document.getElementById('confetti');
    const colors = ['#5a2ca0', '#7c4dcc', '#18a558', '#ffc107', '#e91e63', '#00bcd4'];
    
    for (let i = 0; i < 50; i++) {
        const piece = document.createElement('div');
        piece.className = 'confetti-piece';
        piece.style.left = Math.random() * 100 + '%';
        piece.style.background = colors[Math.floor(Math.random() * colors.length)];
        piece.style.animationDelay = Math.random() * 0.5 + 's';
        piece.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
        confettiContainer.appendChild(piece);
    }
    
    // Remove confetti after animation
    setTimeout(function() {
        confettiContainer.remove();
    }, 4000);
});
</script>
<?php endif; ?>

</body>
</html>
