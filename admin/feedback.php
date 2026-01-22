<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

function tableExists(mysqli $conn, string $tableName): bool {
    $safe = $conn->real_escape_string($tableName);
    $check = $conn->query("SHOW TABLES LIKE '$safe'");
    return $check && $check->num_rows > 0;
}

// Fetch all feedback
$feedbacks = [];
if (tableExists($conn, 'feedback') && tableExists($conn, 'bookings') && tableExists($conn, 'users') && tableExists($conn, 'events')) {
    $latestFeedbackSub = "
        SELECT MAX(f2.id) AS latest_id
        FROM feedback f2
        INNER JOIN bookings b2 ON f2.booking_id = b2.id
        GROUP BY f2.user_id, b2.event_id
    ";

    $query = "SELECT f.*, 
                     u.name as user_name, 
                     u.email as user_email,
                     e.title as event_title,
                     b.booking_code
              FROM feedback f
              INNER JOIN ($latestFeedbackSub) lf ON lf.latest_id = f.id
              LEFT JOIN bookings b ON f.booking_id = b.id
              LEFT JOIN users u ON f.user_id = u.id
              LEFT JOIN events e ON b.event_id = e.id
              ORDER BY f.created_at DESC";
    
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $feedbacks[] = $row;
        }
    }
}

// Calculate stats
$totalFeedback = count($feedbacks);
$avgRating = 0;
$ratingCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

if ($totalFeedback > 0) {
    $totalStars = 0;
    foreach ($feedbacks as $fb) {
        $rating = (int)$fb['rating'];
        $totalStars += $rating;
        if (isset($ratingCounts[$rating])) {
            $ratingCounts[$rating]++;
        }
    }
    $avgRating = $totalStars / $totalFeedback;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Ratings - Admin Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #5a2ca0;
            --primary-dark: #431f75;
            --bg: #f5f3ff;
        }
        body {
            background: var(--bg);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
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
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 32px;
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
        .nav-link-custom.active,
        .nav-link-custom:hover {
            background: rgba(90,44,160,0.1);
            color: var(--primary);
        }
        .main-content {
            margin-left: 250px;
            padding: 30px 40px;
            min-height: 100vh;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px;
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 8px 18px rgba(0,0,0,0.04);
        }
        .stat-card span {
            display: block;
            color: #7a7a7a;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .stat-card h3 {
            margin: 0;
            font-size: 30px;
            color: #2f2f2f;
        }
        .table-wrapper {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 8px 20px rgba(0,0,0,0.04);
            margin-bottom: 25px;
        }
        .rating-stars {
            color: #ffc107;
            font-size: 18px;
        }
        .rating-stars .inactive {
            color: #ddd;
        }
        .table thead {
            background: rgba(90,44,160,0.08);
        }
        .table thead th {
            border: none;
            color: var(--primary-dark);
        }
        .table tbody td {
            vertical-align: middle;
        }
        @media (max-width: 992px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">Event Ease Admin</div>
    <a class="nav-link-custom" href="index.php">Dashboard</a>
    <a class="nav-link-custom" href="pending_approval.php">Pending Approvals</a>
    <a class="nav-link-custom" href="add_event.php">Add Event</a>
    <a class="nav-link-custom" href="manage_events.php">Manage Events</a>
    <a class="nav-link-custom" href="payment.php">Payment Detail</a>
    <a class="nav-link-custom" href="AddOns.php">Add Ons</a>
    <a class="nav-link-custom" href="gallery.php">Gallery Content</a>
    <a class="nav-link-custom active" href="feedback.php">Event Ratings</a>
    <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Event Ratings & Feedback</h2>
            <p class="text-muted mb-0">View user ratings for approved events</p>
        </div>
    </div>

    <section class="stats-grid">
        <div class="stat-card">
            <span>Total Feedback</span>
            <h3><?= number_format($totalFeedback); ?></h3>
        </div>
        <div class="stat-card">
            <span>Average Rating</span>
            <h3><?= $totalFeedback > 0 ? number_format($avgRating, 1) : '-'; ?> <small style="font-size:18px;">★</small></h3>
        </div>
        <div class="stat-card">
            <span>5 Star Ratings</span>
            <h3><?= $ratingCounts[5]; ?></h3>
        </div>
        <div class="stat-card">
            <span>Low Ratings (1-2★)</span>
            <h3><?= $ratingCounts[1] + $ratingCounts[2]; ?></h3>
        </div>
    </section>

    <section class="table-wrapper">
        <?php if (empty($feedbacks)): ?>
            <div class="text-center py-5">
                <i class="fas fa-star fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Feedback Yet</h5>
                <p class="text-muted mb-0">User ratings will appear here after events are completed.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Event</th>
                            <th>Booking Code</th>
                            <th>Rating</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbacks as $feedback): ?>
                            <?php $ratingValue = (int)$feedback['rating']; ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($feedback['user_name'] ?? 'Guest'); ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($feedback['user_email'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($feedback['event_title'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($feedback['booking_code'] ?? '-'); ?></code>
                                </td>
                                <td>
                                    <div class="rating-stars">
                                        <?php for ($star = 1; $star <= 5; $star++): ?>
                                            <span class="<?= $star <= $ratingValue ? '' : 'inactive'; ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <small class="text-muted"><?= $ratingValue; ?>/5</small>
                                </td>
                                <td>
                                    <?= date('d M Y', strtotime($feedback['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
