<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;
$bookings = [];

if ($conn && $isLoggedIn) {
    $stmt = $conn->prepare("
        SELECT b.*, e.title as event_title, e.image_path, e.location, e.event_date as original_event_date
        FROM bookings b
        LEFT JOIN events e ON b.event_id = e.id
        WHERE b.user_id = ? AND b.payment_status = 'paid'
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
    <title>My Bookings - Event Ease</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout.css?v=2">
    <style>
        :root {
            --primary: #5a2ca0;
            --primary-dark: #431f75;
            --bg: #f5f3ff;
        }
        html {
            height: 100%;
        }
        body {
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .page-container {
            flex: 1 0 auto;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }
        .page-header {
            margin-bottom: 20px;
        }
        .page-header h4 {
            color: var(--primary-dark);
            font-weight: 700;
            margin-bottom: 5px;
        }
        .booking-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }
        .booking-header {
            background: #fcfcfc;
            padding: 8px 15px;
            border-bottom: 1px solid rgba(0,0,0,0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: #888;
        }
        .booking-body {
            padding: 12px 15px;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .event-thumb {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }
        .booking-details {
            flex: 1;
        }
        .event-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 2px;
        }
        .event-meta {
            display: flex;
            gap: 10px;
            color: #6c757d;
            font-size: 0.8rem;
            margin-bottom: 5px;
        }
        .status-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        
        .booking-footer {
            padding: 8px 15px;
            background: #fff;
            border-top: 1px solid rgba(0,0,0,0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-amount {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary-dark);
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .empty-state i {
            font-size: 3rem;
            color: #dee2e6;
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
        <h4>My Bookings</h4>
        <p class="text-muted small">Manage your event reservations</p>
    </div>

    <?php if (!$isLoggedIn): ?>
        <div class="empty-state">
            <i class="fa-solid fa-lock"></i>
            <h5>Login Required</h5>
            <p class="small text-muted mb-3">Please login to view your bookings and manage your events.</p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="login.php" class="btn btn-sm btn-primary" style="background: var(--primary); border: none;">Login</a>
                <a href="register.php" class="btn btn-sm btn-outline-primary">Register</a>
            </div>
        </div>
    <?php elseif (empty($bookings)): ?>
        <div class="empty-state">
            <i class="fa-regular fa-calendar-xmark"></i>
            <h5>No Bookings Found</h5>
            <p class="small text-muted mb-3">You haven't booked any events yet.</p>
            <a href="events.php" class="btn btn-sm btn-primary" style="background: var(--primary); border: none;">Browse Events</a>
        </div>
    <?php else: ?>
        <?php foreach ($bookings as $booking): 
            $statusClass = 'status-pending';
            if ($booking['status'] == 'confirmed') $statusClass = 'status-confirmed';
            if ($booking['status'] == 'cancelled') $statusClass = 'status-cancelled';
            if ($booking['status'] == 'completed') $statusClass = 'status-completed';
            
            $imagePath = !empty($booking['image_path']) ? "../" . ltrim($booking['image_path'], './') : 'assets/images/placeholders/default.jpg';
        ?>
            <div class="booking-card">
                <div class="booking-header">
                    <span>#<?= htmlspecialchars($booking['booking_code']) ?></span>
                    <span><?= date('d M Y', strtotime($booking['created_at'])) ?></span>
                </div>
                <div class="booking-body">
                    <img src="<?= htmlspecialchars($imagePath) ?>" alt="Event" class="event-thumb" onerror="this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?auto=format&fit=crop&w=500&q=60'">
                    <div class="booking-details">
                        <div class="event-title"><?= htmlspecialchars($booking['event_title'] ?? 'Custom Event') ?></div>
                        <div class="event-meta">
                            <span class="fw-bold text-primary"><?= !empty($booking['preferred_date']) ? date('d M Y', strtotime($booking['preferred_date'])) : 'TBA' ?></span>
                            <span>•</span>
                            <span><?= htmlspecialchars($booking['location'] ?? 'Location TBA') ?></span>
                        </div>
                        <span class="status-badge <?= $statusClass ?>"><?= ucfirst($booking['status']) ?></span>
                    </div>
                </div>
                <div class="booking-footer">
                    <span class="total-amount">₹<?= number_format($booking['total_amount'], 2) ?></span>
                    <?php if ($booking['payment_status'] == 'unpaid' && $booking['status'] != 'cancelled'): ?>
                        <a href="peyment.php?booking=<?= urlencode($booking['booking_code']) ?>&amount=<?= urlencode($booking['total_amount']) ?>" class="btn btn-sm btn-outline-primary" style="font-size: 0.75rem; padding: 2px 8px;">Pay Now</a>
                    <?php else: ?>
                        <span class="text-muted small" style="font-size: 0.8rem;"><?= ucfirst($booking['payment_status']) ?></span>
                    <?php endif; ?>
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
