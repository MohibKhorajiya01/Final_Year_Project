<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../backend/config.php';

$pendingPayment = $_SESSION['pending_payment'] ?? null;
$amountParam = isset($_GET['amount']) ? (float) $_GET['amount'] : ($pendingPayment['amount'] ?? 0);
$bookingCode = $_GET['booking'] ?? ($pendingPayment['booking_code'] ?? null);

$errorMessage = '';

// Get booking details
$bookingDetails = null;
if ($bookingCode && $conn) {
    $stmt = $conn->prepare("SELECT b.*, e.title as event_title FROM bookings b LEFT JOIN events e ON b.event_id = e.id WHERE b.booking_code = ? AND b.user_id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $bookingCode, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $bookingDetails = $result->fetch_assoc();
        $stmt->close();
        
        if ($bookingDetails && $amountParam == 0) {
            $amountParam = (float) $bookingDetails['total_amount'];
        }
    }
}

if (!$bookingCode || !$bookingDetails) {
    $errorMessage = "Invalid booking reference. Please try booking again.";
}

// Generate unique transaction reference
function generateTransactionId() {
    return 'TXN' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
}

$transactionRef = generateTransactionId();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Event Ease</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout.css?v=2">
    
    <style>
        :root {
            --primary: #5a2ca0;
            --primary-dark: #431f75;
            --primary-light: #7c4dcc;
            --bg: #f5f3ff;
            --success: #18a558;
            --card-bg: #ffffff;
        }
        
        * {
            box-sizing: border-box;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: var(--bg);
            margin: 0;
        }
        
        .payment-wrapper {
            min-height: calc(100vh - 120px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            margin-top: 20px;
        }
        
        .payment-container {
            width: 100%;
            max-width: 480px;
        }
        
        .payment-card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(90, 44, 160, 0.15);
            overflow: hidden;
        }
        
        .payment-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 24px;
            color: white;
            text-align: center;
        }
        
        .payment-header h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
            font-weight: 500;
            opacity: 0.9;
        }
        
        .payment-amount {
            font-size: 36px;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .payment-event {
            font-size: 14px;
            opacity: 0.85;
            margin-top: 8px;
        }
        
        .payment-body {
            padding: 24px;
        }
        
        /* Payment Method Tabs */
        .payment-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        
        .payment-tab {
            flex: 1;
            min-width: 70px;
            padding: 12px 8px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            background: white;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .payment-tab:hover {
            border-color: var(--primary-light);
        }
        
        .payment-tab.active {
            border-color: var(--primary);
            background: rgba(90, 44, 160, 0.05);
        }
        
        .payment-tab i {
            font-size: 20px;
            display: block;
            margin-bottom: 5px;
            color: var(--primary);
        }
        
        .payment-tab span {
            font-size: 11px;
            font-weight: 600;
            color: #555;
        }
        
        /* Payment Forms */
        .payment-form {
            display: none;
        }
        
        .payment-form.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #444;
            margin-bottom: 6px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(90, 44, 160, 0.1);
        }
        
        .form-row {
            display: flex;
            gap: 12px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        /* Card Input Styling */
        .card-input-wrapper {
            position: relative;
        }
        
        .card-input-wrapper .card-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
            color: #999;
        }
        
        /* UPI Section */
        .upi-apps {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        
        .upi-app {
            flex: 1;
            min-width: 70px;
            padding: 12px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .upi-app:hover, .upi-app.selected {
            border-color: var(--primary);
            background: rgba(90, 44, 160, 0.05);
        }
        
        .upi-app img {
            width: 32px;
            height: 32px;
            margin-bottom: 5px;
        }
        
        .upi-app span {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #555;
        }
        
        /* Bank List */
        .bank-list {
            max-height: 200px;
            overflow-y: auto;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
        }
        
        .bank-item {
            padding: 14px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .bank-item:last-child {
            border-bottom: none;
        }
        
        .bank-item:hover, .bank-item.selected {
            background: rgba(90, 44, 160, 0.05);
        }
        
        .bank-item i {
            font-size: 18px;
            color: var(--primary);
        }
        
        .bank-item span {
            font-size: 14px;
            color: #333;
        }
        
        /* Wallet Section */
        .wallet-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .wallet-item {
            padding: 16px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .wallet-item:hover, .wallet-item.selected {
            border-color: var(--primary);
            background: rgba(90, 44, 160, 0.05);
        }
        
        .wallet-item i {
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 8px;
            display: block;
        }
        
        .wallet-item span {
            font-size: 13px;
            font-weight: 600;
            color: #444;
        }
        
        /* Pay Button */
        .btn-pay {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 14px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(90, 44, 160, 0.35);
        }
        
        .btn-pay:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-pay .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        .btn-pay.loading .spinner {
            display: inline-block;
        }
        
        .btn-pay.loading .btn-text {
            display: none;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Security Badge */
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            padding: 12px;
            background: rgba(24, 165, 88, 0.08);
            border-radius: 10px;
            color: var(--success);
            font-size: 13px;
            font-weight: 500;
        }
        
        .security-badge i {
            font-size: 16px;
        }
        
        /* Footer Links */
        .payment-footer {
            text-align: center;
            padding: 16px 24px 24px;
            border-top: 1px solid #f0f0f0;
        }
        
        .payment-footer a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
        }
        
        .payment-footer a:hover {
            text-decoration: underline;
        }
        
        /* Error Box */
        .error-box {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: 12px;
            padding: 20px;
            color: #dc3545;
            text-align: center;
        }
        
        /* Payment Modal Overlay */
        .payment-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(4px);
        }
        
        .payment-overlay.show {
            display: flex;
        }
        
        .processing-modal {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            max-width: 320px;
            animation: scaleIn 0.3s ease;
        }
        
        @keyframes scaleIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        .processing-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .processing-icon i {
            font-size: 36px;
            color: white;
        }
        
        .processing-icon .spinner-border {
            width: 40px;
            height: 40px;
            color: white;
        }
        
        .processing-modal h4 {
            color: var(--primary-dark);
            margin-bottom: 10px;
        }
        
        .processing-modal p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        
        /* Success Animation */
        .success-checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: successPop 0.5s ease;
        }
        
        @keyframes successPop {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .success-checkmark i {
            font-size: 40px;
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .payment-tabs {
                gap: 6px;
            }
            .payment-tab {
                padding: 10px 6px;
            }
            .payment-amount {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="payment-wrapper">
    <div class="payment-container">
        <?php if ($errorMessage): ?>
            <div class="payment-card">
                <div class="payment-body">
                    <div class="error-box">
                        <i class="fa-solid fa-exclamation-circle fa-2x mb-3"></i>
                        <h4>Payment Error</h4>
                        <p><?= htmlspecialchars($errorMessage); ?></p>
                        <a href="mybooking.php" class="btn btn-outline-primary btn-sm mt-3">View My Bookings</a>
                    </div>
                </div>
            </div>
        <?php elseif ($bookingDetails): ?>
            <div class="payment-card">
                <div class="payment-header">
                    <h3>Complete Payment</h3>
                    <div class="payment-amount">₹<?= number_format($amountParam, 2); ?></div>
                    <div class="payment-event">
                        <i class="fa-solid fa-ticket"></i> <?= htmlspecialchars($bookingDetails['event_title'] ?? 'Event Booking'); ?>
                    </div>
                </div>
                
                <div class="payment-body">
                    <!-- Payment Method Tabs -->
                    <div class="payment-tabs">
                        <div class="payment-tab active" data-method="card">
                            <i class="fa-solid fa-credit-card"></i>
                            <span>Card</span>
                        </div>
                        <div class="payment-tab" data-method="upi">
                            <i class="fa-solid fa-mobile-screen"></i>
                            <span>UPI</span>
                        </div>
                        <div class="payment-tab" data-method="netbanking">
                            <i class="fa-solid fa-building-columns"></i>
                            <span>Net Banking</span>
                        </div>
                        <div class="payment-tab" data-method="wallet">
                            <i class="fa-solid fa-wallet"></i>
                            <span>Wallet</span>
                        </div>
                    </div>
                    
                    <!-- Card Payment Form -->
                    <form id="paymentForm" class="payment-form active" data-method="card">
                        <div class="form-group">
                            <label>Card Number</label>
                            <div class="card-input-wrapper">
                                <input type="text" class="form-control" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19" required>
                                <i class="fa-brands fa-cc-visa card-icon" id="cardBrand"></i>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Expiry Date</label>
                                <input type="text" class="form-control" id="cardExpiry" placeholder="MM/YY" maxlength="5" required>
                            </div>
                            <div class="form-group">
                                <label>CVV</label>
                                <input type="password" class="form-control" id="cardCvv" placeholder="•••" maxlength="4" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Cardholder Name</label>
                            <input type="text" class="form-control" id="cardName" placeholder="Name on card" required>
                        </div>
                    </form>
                    
                    <!-- UPI Payment Form -->
                    <div class="payment-form" data-method="upi">
                        <div class="upi-apps">
                            <div class="upi-app selected" data-app="gpay">
                                <i class="fa-brands fa-google-pay" style="font-size: 32px; color: #4285f4;"></i>
                                <span>Google Pay</span>
                            </div>
                            <div class="upi-app" data-app="phonepe">
                                <i class="fa-solid fa-mobile-screen-button" style="font-size: 28px; color: #5f259f;"></i>
                                <span>PhonePe</span>
                            </div>
                            <div class="upi-app" data-app="paytm">
                                <i class="fa-solid fa-wallet" style="font-size: 28px; color: #00baf2;"></i>
                                <span>Paytm</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Enter UPI ID</label>
                            <input type="text" class="form-control" id="upiId" placeholder="yourname@upi">
                        </div>
                    </div>
                    
                    <!-- Net Banking Form -->
                    <div class="payment-form" data-method="netbanking">
                        <div class="form-group">
                            <label>Select Your Bank</label>
                            <div class="bank-list">
                                <div class="bank-item selected" data-bank="sbi">
                                    <i class="fa-solid fa-building-columns"></i>
                                    <span>State Bank of India</span>
                                </div>
                                <div class="bank-item" data-bank="hdfc">
                                    <i class="fa-solid fa-building-columns"></i>
                                    <span>HDFC Bank</span>
                                </div>
                                <div class="bank-item" data-bank="icici">
                                    <i class="fa-solid fa-building-columns"></i>
                                    <span>ICICI Bank</span>
                                </div>
                                <div class="bank-item" data-bank="axis">
                                    <i class="fa-solid fa-building-columns"></i>
                                    <span>Axis Bank</span>
                                </div>
                                <div class="bank-item" data-bank="kotak">
                                    <i class="fa-solid fa-building-columns"></i>
                                    <span>Kotak Mahindra Bank</span>
                                </div>
                                <div class="bank-item" data-bank="pnb">
                                    <i class="fa-solid fa-building-columns"></i>
                                    <span>Punjab National Bank</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Wallet Form -->
                    <div class="payment-form" data-method="wallet">
                        <div class="wallet-list">
                            <div class="wallet-item selected" data-wallet="paytm">
                                <i class="fa-solid fa-wallet"></i>
                                <span>Paytm Wallet</span>
                            </div>
                            <div class="wallet-item" data-wallet="amazonpay">
                                <i class="fa-brands fa-amazon"></i>
                                <span>Amazon Pay</span>
                            </div>
                            <div class="wallet-item" data-wallet="mobikwik">
                                <i class="fa-solid fa-mobile-screen"></i>
                                <span>MobiKwik</span>
                            </div>
                            <div class="wallet-item" data-wallet="freecharge">
                                <i class="fa-solid fa-bolt"></i>
                                <span>Freecharge</span>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" id="payBtn" class="btn-pay">
                        <span class="btn-text"><i class="fa-solid fa-lock"></i> Pay ₹<?= number_format($amountParam, 2); ?></span>
                        <span class="spinner"></span>
                    </button>
                    
                    <div class="security-badge">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>100% Secure Payment</span>
                    </div>
                </div>
                
                <div class="payment-footer">
                    <a href="mybooking.php"><i class="fa-solid fa-arrow-left"></i> Cancel and return to bookings</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Processing Overlay -->
<div class="payment-overlay" id="processingOverlay">
    <div class="processing-modal" id="processingModal">
        <div class="processing-icon">
            <div class="spinner-border" role="status"></div>
        </div>
        <h4>Processing Payment</h4>
        <p>Please wait while we securely process your payment...</p>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="assets/js/layout.js?v=2"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentTabs = document.querySelectorAll('.payment-tab');
    const paymentForms = document.querySelectorAll('.payment-form');
    const payBtn = document.getElementById('payBtn');
    const overlay = document.getElementById('processingOverlay');
    const modal = document.getElementById('processingModal');
    
    // Tab switching
    paymentTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const method = this.dataset.method;
            
            paymentTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            paymentForms.forEach(form => {
                form.classList.remove('active');
                if (form.dataset.method === method) {
                    form.classList.add('active');
                }
            });
        });
    });
    
    // Card number formatting
    const cardNumber = document.getElementById('cardNumber');
    if (cardNumber) {
        cardNumber.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
            let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formatted;
            
            // Update card brand icon
            const cardBrand = document.getElementById('cardBrand');
            if (value.startsWith('4')) {
                cardBrand.className = 'fa-brands fa-cc-visa card-icon';
            } else if (value.startsWith('5')) {
                cardBrand.className = 'fa-brands fa-cc-mastercard card-icon';
            } else if (value.startsWith('3')) {
                cardBrand.className = 'fa-brands fa-cc-amex card-icon';
            } else {
                cardBrand.className = 'fa-solid fa-credit-card card-icon';
            }
        });
    }
    
    // Expiry formatting
    const cardExpiry = document.getElementById('cardExpiry');
    if (cardExpiry) {
        cardExpiry.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
    }
    
    // UPI app selection
    document.querySelectorAll('.upi-app').forEach(app => {
        app.addEventListener('click', function() {
            document.querySelectorAll('.upi-app').forEach(a => a.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
    
    // Bank selection
    document.querySelectorAll('.bank-item').forEach(bank => {
        bank.addEventListener('click', function() {
            document.querySelectorAll('.bank-item').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
    
    // Wallet selection
    document.querySelectorAll('.wallet-item').forEach(wallet => {
        wallet.addEventListener('click', function() {
            document.querySelectorAll('.wallet-item').forEach(w => w.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
    
    // Pay button click
    if (payBtn) {
        payBtn.addEventListener('click', function() {
            const activeTab = document.querySelector('.payment-tab.active');
            const method = activeTab.dataset.method;
            let isValid = true;
            let paymentMethod = '';
            
            // Validate based on payment method
            if (method === 'card') {
                const cardNum = document.getElementById('cardNumber').value.replace(/\s/g, '');
                const expiry = document.getElementById('cardExpiry').value;
                const cvv = document.getElementById('cardCvv').value;
                const name = document.getElementById('cardName').value;
                
                if (cardNum.length < 15 || !expiry || cvv.length < 3 || !name) {
                    alert('Please fill in all card details correctly');
                    isValid = false;
                }
                paymentMethod = 'Card';
            } else if (method === 'upi') {
                const upiId = document.getElementById('upiId').value;
                if (!upiId || !upiId.includes('@')) {
                    alert('Please enter a valid UPI ID');
                    isValid = false;
                }
                paymentMethod = 'UPI';
            } else if (method === 'netbanking') {
                paymentMethod = 'Net Banking';
            } else if (method === 'wallet') {
                paymentMethod = 'Wallet';
            }
            
            if (!isValid) return;
            
            // Show processing overlay
            payBtn.classList.add('loading');
            payBtn.disabled = true;
            overlay.classList.add('show');
            
            // Simulate payment processing
            setTimeout(function() {
                // Show success
                modal.innerHTML = `
                    <div class="success-checkmark">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h4 style="color: #18a558;">Payment Successful!</h4>
                    <p>Redirecting to confirmation...</p>
                `;
                
                // Redirect to success page
                setTimeout(function() {
                    const transactionId = 'TXN<?= substr($transactionRef, 3); ?>';
                    window.location.href = 'payment_success.php?txn_id=' + transactionId + 
                        '&booking=<?= urlencode($bookingCode); ?>' +
                        '&amount=<?= $amountParam; ?>' +
                        '&method=' + encodeURIComponent(paymentMethod);
                }, 1500);
            }, 2500);
        });
    }
});
</script>
</body>
</html>
