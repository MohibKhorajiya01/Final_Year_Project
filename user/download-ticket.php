<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Please login first. <a href='login.php'>Login here</a>");
}

require_once __DIR__ . '/../backend/config.php';

$userId = $_SESSION['user_id'];
$bookingCode = isset($_GET['booking_code']) ? trim($_GET['booking_code']) : '';

if (empty($bookingCode)) {
    die("Invalid ticket request - No booking code provided. <a href='tickets.php'>Go back to tickets</a>");
}

// Fetch booking details (must be approved and belong to logged-in user)
$booking = null;
if ($conn) {
    $stmt = $conn->prepare("
        SELECT b.*, 
               e.title as event_title, 
               e.location, 
               e.event_date as original_event_date,
               e.category,
               e.description,
               u.name as user_name,
               u.email as user_email,
               u.phone as user_phone
        FROM bookings b
        LEFT JOIN events e ON b.event_id = e.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.booking_code = ? AND b.user_id = ?
        LIMIT 1
    ");
    
    if ($stmt) {
        $stmt->bind_param("si", $bookingCode, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        $stmt->close();
    }
}

if (!$booking) {
    die("Ticket not found or doesn't belong to you. <a href='tickets.php'>Go back to tickets</a>");
}

// Check if booking is approved and paid
if ($booking['status'] !== 'approved') {
    die("This booking has not been approved yet. Status: " . htmlspecialchars($booking['status']) . ". <a href='tickets.php'>Go back</a>");
}

if ($booking['payment_status'] !== 'paid') {
    die("Payment is not complete for this booking. Payment Status: " . htmlspecialchars($booking['payment_status']) . ". <a href='tickets.php'>Go back</a>");
}

$eventDate = !empty($booking['preferred_date']) ? $booking['preferred_date'] : $booking['original_event_date'];
$addons = json_decode($booking['addons'] ?? '{}', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Ticket - <?= htmlspecialchars($booking['booking_code']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
            }
        }
        
        body {
            background: #f0f0f0;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .ticket-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .ticket-header {
            background: linear-gradient(135deg, #5a2ca0, #7d3cbd);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        
        .ticket-header::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(135deg, #5a2ca0, #7d3cbd);
            clip-path: polygon(
                0 0, 5% 50%, 10% 0, 15% 50%, 20% 0, 25% 50%, 30% 0, 35% 50%, 
                40% 0, 45% 50%, 50% 0, 55% 50%, 60% 0, 65% 50%, 70% 0, 75% 50%, 
                80% 0, 85% 50%, 90% 0, 95% 50%, 100% 0, 100% 100%, 0 100%
            );
        }
        
        .ticket-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .ticket-header .event-category {
            font-size: 0.9rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .ticket-body {
            padding: 40px 30px;
        }
        
        .ticket-code-section {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px dashed #5a2ca0;
        }
        
        .ticket-code-section .code {
            font-family: 'Courier New', monospace;
            font-size: 1.8rem;
            font-weight: 700;
            color: #5a2ca0;
            letter-spacing: 3px;
        }
        
        .ticket-code-section small {
            display: block;
            margin-top: 5px;
            color: #666;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-section h5 {
            color: #5a2ca0;
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.1rem;
            border-bottom: 2px solid #5a2ca0;
            padding-bottom: 5px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            gap: 10px;
        }
        
        .info-item i {
            color: #5a2ca0;
            font-size: 1.2rem;
            margin-top: 2px;
        }
        
        .info-item .label {
            font-weight: 600;
            color: #666;
            font-size: 0.85rem;
        }
        
        .info-item .value {
            color: #333;
            font-weight: 600;
        }
        
        .ticket-footer {
            background: #f8f9fa;
            padding: 20px 30px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
        }
        
        .ticket-footer .qr-placeholder {
            width: 120px;
            height: 120px;
            background: white;
            border: 2px solid #ddd;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 0.8rem;
        }
        
        .btn-actions {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        
        .btn-print {
            background: #5a2ca0;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(90,44,160,0.3);
        }
        
        .btn-print:hover {
            background: #431f75;
        }
        
        .approved-stamp {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.85rem;
            transform: rotate(15deg);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>

<div class="btn-actions no-print">
    <button onclick="window.print()" class="btn-print">
        <i class="fa-solid fa-print"></i> Print Ticket
    </button>
    <a href="tickets.php" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Back
    </a>
</div>

<div class="ticket-container">
    <div class="ticket-header">
        <div class="approved-stamp">APPROVED</div>
        <div class="event-category"><?= htmlspecialchars($booking['category'] ?? 'Event'); ?></div>
        <h1><?= htmlspecialchars($booking['event_title']); ?></h1>
    </div>
    
    <div class="ticket-body">
        <div class="ticket-code-section">
            <div class="code">#<?= htmlspecialchars($booking['booking_code']); ?></div>
            <small>Booking Confirmation Code</small>
        </div>
        
        <div class="info-section">
            <h5><i class="fa-solid fa-calendar-alt"></i> Event Details</h5>
            <div class="info-grid">
                <div class="info-item">
                    <i class="fa-solid fa-calendar-day"></i>
                    <div>
                        <div class="label">Event Date</div>
                        <div class="value"><?= !empty($eventDate) ? date('l, d M Y', strtotime($eventDate)) : 'To Be Announced' ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fa-solid fa-location-dot"></i>
                    <div>
                        <div class="label">Venue</div>
                        <div class="value"><?= htmlspecialchars($booking['location'] ?? 'Location TBA'); ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fa-solid fa-users"></i>
                    <div>
                        <div class="label">Number of Guests</div>
                        <div class="value"><?= $booking['guest_count'] ?? 1 ?> Person(s)</div>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fa-solid fa-rupee-sign"></i>
                    <div>
                        <div class="label">Amount Paid</div>
                        <div class="value">₹<?= number_format($booking['total_amount'], 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="info-section">
            <h5><i class="fa-solid fa-user-circle"></i> Guest Information</h5>
            <div class="info-grid">
                <div class="info-item">
                    <i class="fa-solid fa-user"></i>
                    <div>
                        <div class="label">Name</div>
                        <div class="value"><?= htmlspecialchars($booking['user_name']); ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fa-solid fa-envelope"></i>
                    <div>
                        <div class="label">Email</div>
                        <div class="value"><?= htmlspecialchars($booking['user_email']); ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fa-solid fa-phone"></i>
                    <div>
                        <div class="label">Phone</div>
                        <div class="value"><?= htmlspecialchars($booking['user_phone']); ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fa-solid fa-calendar-check"></i>
                    <div>
                        <div class="label">Booked On</div>
                        <div class="value"><?= date('d M Y', strtotime($booking['created_at'])); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($addons) && is_array($addons)): ?>
            <div class="info-section">
                <h5><i class="fa-solid fa-gift"></i> Add-ons & Extras</h5>
                <ul>
                    <?php if (isset($addons['food_package']) && $addons['food_package'] !== 'none'): ?>
                        <li>Food Package: <strong><?= ucfirst($addons['food_package']); ?></strong></li>
                    <?php endif; ?>
                    <?php if (isset($addons['decoration']) && $addons['decoration'] !== 'none'): ?>
                        <li>Decoration: <strong><?= ucfirst($addons['decoration']); ?></strong> (₹<?= number_format($addons['decoration_cost'] ?? 0, 2); ?>)</li>
                    <?php endif; ?>
                    <?php if (isset($addons['pickup_service']) && $addons['pickup_service'] !== 'no'): ?>
                        <li>Pickup Service: <strong><?= ucfirst($addons['pickup_service']); ?></strong></li>
                        <?php if (!empty($addons['pickup_address'])): ?>
                            <li>Pickup Address: <?= htmlspecialchars($addons['pickup_address']); ?></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($booking['notes'])): ?>
            <div class="info-section">
                <h5><i class="fa-solid fa-note-sticky"></i> Special Notes</h5>
                <p class="text-muted"><?= htmlspecialchars($booking['notes']); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="ticket-footer">
        <div class="qr-placeholder">
            [QR Code]<br><?= htmlspecialchars($booking['booking_code']); ?>
        </div>
        <small class="text-muted">Please present this ticket at the event venue</small>
        <p class="mt-3 mb-0" style="font-size: 0.85rem; color: #999;">
            <strong>Event Ease</strong> | Premium Event Management<br>
            For support: support@eventease.com | +91-XXXXXXXXXX
        </p>
    </div>
</div>

<script>
// Auto-print option (commented out - uncomment if needed)
// window.onload = function() { window.print(); }
</script>

</body>
</html>
