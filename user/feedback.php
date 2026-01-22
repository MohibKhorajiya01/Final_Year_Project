<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;
$message = '';
$messageType = '';

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    if (!$isLoggedIn) {
        $message = "Please login to submit feedback.";
        $messageType = "warning";
    } else {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        
        if ($bookingId > 0 && $rating >= 1 && $rating <= 5) {
        $checkStmt = $conn->prepare("SELECT id, event_id FROM bookings WHERE id = ? AND user_id = ? AND status = 'approved' AND payment_status = 'paid'");
        if ($checkStmt) {
            $checkStmt->bind_param("ii", $bookingId, $userId);
            $checkStmt->execute();
            $checkStmt->bind_result($bookingRowId, $eventId);
            if ($checkStmt->fetch()) {
                $checkStmt->close();

                // Prevent duplicate feedback for the same event by the same user
                $duplicateStmt = $conn->prepare("SELECT COUNT(*) FROM feedback f INNER JOIN bookings b ON f.booking_id = b.id WHERE f.user_id = ? AND b.event_id = ?");
                if ($duplicateStmt) {
                    $duplicateStmt->bind_param("ii", $userId, $eventId);
                    $duplicateStmt->execute();
                    $duplicateStmt->bind_result($existingCount);
                    $duplicateStmt->fetch();
                    $duplicateStmt->close();

                    if ($existingCount > 0) {
                        $message = "You have already reviewed this event.";
                        $messageType = "warning";
                    } else {
                        $insertStmt = $conn->prepare("INSERT INTO feedback (booking_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
                        if ($insertStmt) {
                            $insertStmt->bind_param("iiis", $bookingId, $userId, $rating, $comment);
                            if ($insertStmt->execute()) {
                                $message = "Thank you for your feedback!";
                                $messageType = "success";
                            } else {
                                $message = "Failed to submit feedback: " . $insertStmt->error;
                                $messageType = "danger";
                            }
                            $insertStmt->close();
                        } else {
                            $message = "Database error: " . $conn->error;
                            $messageType = "danger";
                        }
                    }
                }
            } else {
                $checkStmt->close();
                $message = "Invalid booking or not approved yet.";
                $messageType = "danger";
            }
        }
        } else {
            $message = "Please provide a valid rating (1-5 stars).";
            $messageType = "warning";
        }
    }
}

// Fetch approved and paid bookings (whether or not feedback table exists)
$bookings = [];
if ($conn && $isLoggedIn) {
    // Simple query - show all approved AND paid bookings that don't have feedback
$query = "SELECT b.*, 
                     e.title as event_title, 
                     e.category, 
                     e.location,
                     e.event_date,
                     (SELECT COUNT(*) 
                        FROM feedback f 
                        INNER JOIN bookings b2 ON f.booking_id = b2.id 
                        WHERE f.user_id = ? AND b2.event_id = b.event_id
                     ) as event_feedback_count
              FROM bookings b
              LEFT JOIN events e ON b.event_id = e.id
              WHERE b.user_id = ? 
                AND b.status = 'approved'
                AND b.payment_status = 'paid'
              ORDER BY b.created_at DESC";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if ((int)$row['event_feedback_count'] === 0) {
                $bookings[] = $row;
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
    <title>Share Feedback - Event Ease</title>
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
            max-width: 800px;
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
        .feedback-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .event-info {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .event-info h5 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 1.2rem;
        }
        .event-info small {
            color: #666;
        }
        .rating-section {
            margin-bottom: 20px;
        }
        .rating-section label {
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
        }
        .star-rating {
            display: flex;
            gap: 8px;
            font-size: 2rem;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            cursor: pointer;
            color: #ddd;
            transition: color 0.2s;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #ffc107;
        }
        .btn-submit {
            background: #5a2ca0;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn-submit:hover {
            background: #431f75;
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
        <h4><i class="fa-solid fa-star"></i> Share Feedback</h4>
        <p class="text-muted small">Rate your experience with approved events</p>
    </div>



    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType; ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!$isLoggedIn): ?>
        <div class="empty-state">
            <i class="fa-solid fa-lock"></i>
            <h5>Login Required</h5>
            <p class="small text-muted mb-3">Please login to share your feedback and rate your event experiences.</p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="login.php" class="btn btn-sm btn-primary" style="background: var(--primary); border: none;">Login</a>
                <a href="register.php" class="btn btn-sm btn-outline-primary">Register</a>
            </div>
        </div>
    <?php elseif (empty($bookings)): ?>
        <div class="empty-state">
            <i class="fa-regular fa-comments"></i>
            <h5>No Events to Review</h5>
            <p class="text-muted mb-2">You'll be able to leave feedback after your events are approved and payment is complete.</p>
            <small class="text-muted">Make sure your booking is: <strong>Approved</strong> + <strong>Paid</strong></small>
        </div>
    <?php else: ?>
        <?php foreach ($bookings as $booking): ?>
            <div class="feedback-card">
                <div class="event-info">
                    <h5><?= htmlspecialchars($booking['event_title'] ?? 'Event'); ?></h5>
                    <small>Booking Code: <strong><?= htmlspecialchars($booking['booking_code']); ?></strong></small>
                    <?php if (!empty($booking['location'])): ?>
                        <br><small><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($booking['location']); ?></small>
                    <?php endif; ?>
                </div>
                
                <form method="POST" onsubmit="return validateRating(this)" novalidate>
                    <input type="hidden" name="booking_id" value="<?= $booking['id']; ?>">
                    
                    <div class="rating-section">
                        <label>Rate Your Experience:</label>
                        <div class="star-rating">
                            <input type="radio" name="rating" value="5" id="star5_<?= $booking['id']; ?>">
                            <label for="star5_<?= $booking['id']; ?>"><i class="fa-solid fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="4" id="star4_<?= $booking['id']; ?>">
                            <label for="star4_<?= $booking['id']; ?>"><i class="fa-solid fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="3" id="star3_<?= $booking['id']; ?>">
                            <label for="star3_<?= $booking['id']; ?>"><i class="fa-solid fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="2" id="star2_<?= $booking['id']; ?>">
                            <label for="star2_<?= $booking['id']; ?>"><i class="fa-solid fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="1" id="star1_<?= $booking['id']; ?>">
                            <label for="star1_<?= $booking['id']; ?>"><i class="fa-solid fa-star"></i></label>
                        </div>
                    </div>
                    
                    <div class="validation-error text-danger mb-2" style="display:none; font-size: 0.9rem;"></div>

                    <button type="submit" name="submit_feedback" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Submit
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/layout.js?v=2"></script>
<script>
function validateRating(form) {
    const rating = form.querySelector('input[name="rating"]:checked');
    const errorDiv = form.querySelector('.validation-error');
    
    if (!rating) {
        if (errorDiv) {
            errorDiv.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Please select a star rating to submit feedback.';
            errorDiv.style.display = 'block';
        } else {
            alert('Please select a rating.');
        }
        return false;
    }
    
    if (errorDiv) errorDiv.style.display = 'none';
    return true;
}
</script>

</body>
</html>
