<?php
session_start();
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/auth_check.php';

// Fetch events with their ratings overview
$eventsQuery = "
    SELECT 
        e.id,
        e.title,
        e.description,
        e.event_date,
        e.location,
        e.status,
        COUNT(DISTINCT b.id) as total_bookings,
        COUNT(DISTINCT f.id) as total_feedback,
        COALESCE(AVG(f.rating), 0) as avg_rating,
        SUM(CASE WHEN f.rating = 5 THEN 1 ELSE 0 END) as rating_5,
        SUM(CASE WHEN f.rating = 4 THEN 1 ELSE 0 END) as rating_4,
        SUM(CASE WHEN f.rating = 3 THEN 1 ELSE 0 END) as rating_3,
        SUM(CASE WHEN f.rating = 2 THEN 1 ELSE 0 END) as rating_2,
        SUM(CASE WHEN f.rating = 1 THEN 1 ELSE 0 END) as rating_1
    FROM events e
    LEFT JOIN bookings b ON e.id = b.event_id
    LEFT JOIN feedback f ON b.id = f.booking_id
    WHERE 1=1
    GROUP BY e.id
    ORDER BY e.event_date DESC
";

$events = [];
if ($stmt = $conn->prepare($eventsQuery)) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();
}

// Fetch detailed feedback for a specific event if requested
$selectedEventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;
$feedbackDetails = [];
if ($selectedEventId) {
    $feedbackQuery = "
        SELECT 
            f.*,
            u.name as user_name,
            u.email as user_email,
            b.ticket_count,
            DATE_FORMAT(f.created_at, '%d %b %Y, %h:%i %p') as feedback_date
        FROM feedback f
        INNER JOIN bookings b ON f.booking_id = b.id
        INNER JOIN events e ON b.event_id = e.id
        LEFT JOIN users u ON f.user_id = u.id
        WHERE e.id = ?
        ORDER BY f.created_at DESC
    ";
    
    if ($stmt = $conn->prepare($feedbackQuery)) {
        $stmt->bind_param("i", $selectedEventId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $feedbackDetails[] = $row;
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
    <title>Event Feedback & Ratings Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            background: #f5f3ff;
            font-family: Arial, sans-serif;
        }
        .sidebar {
            width: 250px;
            background: #fff;
            box-shadow: 4px 0 20px rgba(0,0,0,0.05);
            padding: 24px 20px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(90,44,160,0.1);
        }
        .brand-logo {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            object-fit: cover;
        }
        .brand-text {
            display: flex;
            flex-direction: column;
        }
        .brand-text strong {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.2;
        }
        .brand-text span {
            font-size: 12px;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .nav-link-custom {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            color: #6c6c6c;
            text-decoration: none;
            margin-bottom: 8px;
            transition: background 0.2s, color 0.2s;
        }
        .nav-link-custom.active {
            background: rgba(90,44,160,0.1);
            color: var(--primary);
        }
        .nav-link-custom:hover {
            background: rgba(90,44,160,0.1);
            color: var(--primary);
        }
        .main-content {
            margin-left: 250px;
            padding: 30px 40px;
            min-height: 100vh;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #5a2ca0;
        }
        .top-bar h2 {
            color: #5a2ca0;
        }
        .board {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .board h4 {
            margin-bottom: 15px;
            color: #5a2ca0;
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f5f3ff;
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background-color: #f5f3ff;
            border-bottom: 2px solid #ddd;
            font-weight: 600;
            font-size: 13px;
            color: #5a2ca0;
        }
        .table tbody tr:hover {
            background-color: #f5f3ff;
        }
        .btn-primary {
            background-color: #5a2ca0;
            border-color: #5a2ca0;
        }
        .btn-primary:hover {
            background-color: #431f75;
            border-color: #431f75;
        }
        .btn-outline-secondary {
            border-color: #5a2ca0;
            color: #5a2ca0;
        }
        .btn-outline-secondary:hover {
            background-color: #5a2ca0;
            color: #fff;
        }
        .badge {
            border-radius: 5px;
        }
        @media (max-width: 992px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <img src="../user/assets/1.logo.png" alt="EventEase" class="brand-logo">
        <div class="brand-text">
            <strong>Event Ease</strong>
            <span>Manager Suite</span>
        </div>
    </div>
    <a class="nav-link-custom" href="index.php">Dashboard</a>
    <a class="nav-link-custom" href="pending_approval.php">Pending Approvals</a>
    <a class="nav-link-custom" href="manage_events.php">Manage Events</a>
    <a class="nav-link-custom" href="manage_booking.php">Booking Hub</a>
    <a class="nav-link-custom" href="manage_addOns.php">Add-on & Pickup</a>
    <a class="nav-link-custom" href="manage_gallary.php">Gallery Uploads</a>
    <a class="nav-link-custom" href="manage_payments.php">Payment History</a>
    <a class="nav-link-custom active" href="manage_feedback.php">Event Ratings</a>
    <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div>
            <h2 class="mb-1" style="font-size: 24px;">Event Feedback & Ratings</h2>
            <small class="text-muted">View feedback for your events | Signed in as <strong><?= htmlspecialchars($managerName); ?></strong></small>
        </div>
    </div>

    <?php if ($selectedEventId && !empty($feedbackDetails)): ?>
        <?php
            $eventDetails = null;
            foreach ($events as $evt) {
                if ($evt['id'] == $selectedEventId) {
                    $eventDetails = $evt;
                    break;
                }
            }
        ?>
        <section class="board">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4><?= htmlspecialchars($eventDetails['title'] ?? 'Event Feedback') ?></h4>
                    <small class="text-muted">
                        <?= count($feedbackDetails) ?> feedback · Average: <?= number_format($eventDetails['avg_rating'], 1) ?>/5.0
                    </small>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbackDetails as $feedback): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($feedback['user_name'] ?? 'Anonymous'); ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($feedback['user_email'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <span style="color: #fbbf24; font-size: 18px;">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?= $i <= $feedback['rating'] ? '★' : '☆' ?>
                                        <?php endfor; ?>
                                    </span>
                                    <br><small class="text-muted"><?= $feedback['rating'] ?>/5</small>
                                </td>
                                <td><?= !empty($feedback['comment']) ? htmlspecialchars($feedback['comment']) : '<span class="text-muted">No comment</span>'; ?></td>
                                <td><small><?= $feedback['feedback_date'] ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php else: ?>
        <section class="board">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Event Feedback & Ratings</h4>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Total Feedback</th>
                            <th>Average Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No events found. <a href="manage_events.php">Create your first event</a></td></tr>
                        <?php else: ?>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($event['title']); ?></strong></td>
                                    <td><?= date('d M Y', strtotime($event['event_date'])); ?></td>
                                    <td><?= (int)$event['total_feedback']; ?></td>
                                    <td>
                                        <?php if ((int)$event['total_feedback'] > 0): ?>
                                            <span style="color: #fbbf24;">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?= $i <= round($event['avg_rating']) ? '★' : '☆' ?>
                                                <?php endfor; ?>
                                            </span>
                                            <br><small><?= number_format($event['avg_rating'], 1) ?>/5.0</small>
                                        <?php else: ?>
                                            <span class="text-muted">No feedback yet</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>

</body>
</html>
