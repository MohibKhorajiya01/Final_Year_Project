<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Fetch approved bookings for ticket download
$bookings = [];
if ($conn && $isLoggedIn) {
    $stmt = $conn->prepare("
        SELECT b.*, 
               e.title as event_title, 
               e.location, 
               e.event_date as original_event_date,
               e.category,
               u.name as user_name,
               u.email as user_email,
               u.phone as user_phone
        FROM bookings b
        LEFT JOIN events e ON b.event_id = e.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.user_id = ? AND b.status = 'approved' AND b.payment_status = 'paid'
        ORDER BY b.created_at DESC
    ");
    
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
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
    <title>Download Tickets - Event Ease</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout.css?v=2">
    <style>
        body {
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .page-container {
            flex: 1 0 auto;
            padding: 20px;
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }
        .page-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ddd;
        }
        .page-header h4 {
            color: #333;
            margin-bottom: 5px;
            font-size: 1.6rem;
            font-weight: 600;
        }
        .ticket-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .ticket-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .ticket-header h5 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 1.4rem;
            font-weight: 600;
        }
        .ticket-code {
            color: #666;
            font-family: monospace;
            font-size: 0.95rem;
        }
        .ticket-info {
            margin-bottom: 15px;
        }
        .info-item {
            padding: 8px 0;
            display: flex;
            border-bottom: 1px solid #f5f5f5;
        }
        .info-item strong {
            width: 130px;
            color: #666;
            font-weight: 600;
        }
        .info-item span {
            color: #333;
        }
        .ticket-footer {
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .approved-badge {
            background: #28a745;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .btn-download {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .btn-download:hover {
            background: #0056b3;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .empty-state i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 15px;
        }
        .site-footer {
            flex-shrink: 0;
            margin-top: auto;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="page-container">
    <div class="page-header">
        <h4><i class="fa-solid fa-ticket"></i> My Tickets</h4>
        <p class="text-muted small">Download your event tickets for approved bookings</p>
    </div>

    <?php if (!$isLoggedIn): ?>
        <div class="empty-state">
            <i class="fa-solid fa-lock"></i>
            <h5>Login Required</h5>
            <p class="small text-muted mb-3">Please login to view and download your event tickets.</p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="login.php" class="btn btn-sm btn-primary" style="background: var(--primary); border: none;">Login</a>
                <a href="register.php" class="btn btn-sm btn-outline-primary">Register</a>
            </div>
        </div>
    <?php elseif (empty($bookings)): ?>
        <div class="empty-state">
            <i class="fa-regular fa-ticket"></i>
            <h5>No Tickets Available</h5>
            <p class="text-muted mb-3">You don't have any approved bookings yet.</p>
            <p class="text-muted small">Tickets will appear here once your bookings are approved by the admin.</p>
            <a href="events.php" class="btn btn-primary mt-3" style="background: var(--primary); border: none;">Browse Events</a>
        </div>
    <?php else: ?>
        <?php foreach ($bookings as $booking): 
            $imagePath = !empty($booking['image_path']) ? "../" . ltrim($booking['image_path'], './') : '';
            $eventDate = !empty($booking['preferred_date']) ? $booking['preferred_date'] : $booking['original_event_date'];
            $addons = !empty($booking['addons']) ? json_decode($booking['addons'], true) : [];
        ?>
            <div class="ticket-card">
                <div class="ticket-header">
                    <div>
                        <h5><?= htmlspecialchars($booking['event_title'] ?? 'Event'); ?></h5>
                        <div class="ticket-code">#<?= htmlspecialchars($booking['booking_code']); ?></div>
                    </div>
                </div>
                
                <div class="ticket-info">
                    <div class="info-item">
                        <strong>Booking Date:</strong>
                        <span class="fw-bold text-primary"><?= !empty($eventDate) ? date('d M Y', strtotime($eventDate)) : 'TBA' ?></span>
                    </div>
                    
                    <div class="info-item">
                        <strong>Venue:</strong>
                        <span><?= htmlspecialchars($booking['location'] ?? 'Location TBA'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <strong>Guest Name:</strong>
                        <span><?= htmlspecialchars($booking['user_name'] ?? 'Guest'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <strong>Guests:</strong>
                        <span><?= $booking['guest_count'] ?? 1 ?> person(s)</span>
                    </div>
                    
                    <div class="info-item">
                        <strong>Total Paid:</strong>
                        <span>₹<?= number_format($booking['total_amount'], 2); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <strong>Booked On:</strong>
                        <span><?= date('d M Y', strtotime($booking['created_at'])); ?></span>
                    </div>

                    <?php if (!empty($addons)): ?>
                        <div class="mt-3">
                            <small class="text-muted d-block mb-1">Services Included:</small>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if (!empty($addons['food_package'])): ?>
                                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-utensils me-1"></i> <?= ucfirst($addons['food_package']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($addons['pickup_service']) && $addons['pickup_service'] === 'yes'): ?>
                                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-car me-1"></i> Pickup</span>
                                <?php endif; ?>
                                <?php if (!empty($addons['decoration']) && $addons['decoration'] !== 'none'): ?>
                                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> <?= ucfirst($addons['decoration']); ?> Deco</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="ticket-footer">
                    <span class="approved-badge">Approved</span>
                    <a href="ticket.php?booking_code=<?= htmlspecialchars($booking['booking_code']); ?>" 
                       class="btn-download">
                        Download Ticket
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/layout.js?v=2"></script>
</body>
</html>
