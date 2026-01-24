<?php
session_start();
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/auth_check.php';

// Helper function to check table existence
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

// --- Fetch Statistics ---

// 1. Live Events (Active) - Check multiple status formats
$liveEventsCount = 0;
if (tableExists($conn, 'events')) {
    // Try different status formats
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM events 
        WHERE (LOWER(status) = 'active' OR status = 'Active' OR status = 'active')
    ");
    if ($stmt) {
        if ($stmt->execute()) {
            $stmt->bind_result($liveEventsCount);
            $stmt->fetch();
        }
        $stmt->close();
    }
}

// 2. Pending Approvals (Pending Bookings)
$pendingApprovalsCount = 0;
if (tableExists($conn, 'bookings') && tableExists($conn, 'events')) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM bookings b 
        JOIN events e ON b.event_id = e.id 
        WHERE (LOWER(b.status) = 'pending' OR b.status = 'Pending' OR b.status = 'pending')
          AND b.payment_status = 'paid'
    ");
    if ($stmt) {
        if ($stmt->execute()) {
            $stmt->bind_result($pendingApprovalsCount);
            $stmt->fetch();
        }
        $stmt->close();
    }
}

// 3. Total Bookings (Replacing Vendor Tasks)
$totalBookingsCount = 0;
if (tableExists($conn, 'bookings') && tableExists($conn, 'events')) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM bookings b 
        JOIN events e ON b.event_id = e.id
    ");
    if ($stmt) {
        if ($stmt->execute()) {
            $stmt->bind_result($totalBookingsCount);
            $stmt->fetch();
        }
        $stmt->close();
    }
}


// 5. Total Revenue (Paid Bookings)
$totalRevenue = 0;
if (tableExists($conn, 'bookings') && tableExists($conn, 'events')) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(b.total_amount), 0) as revenue
        FROM bookings b 
        JOIN events e ON b.event_id = e.id 
        WHERE (LOWER(b.payment_status) = 'paid' OR b.payment_status = 'Paid' OR b.payment_status = 'paid')
    ");
    if ($stmt) {
        if ($stmt->execute()) {
            $stmt->bind_result($totalRevenue);
            $stmt->fetch();
        }
        $stmt->close();
    }
}

// 6. Pending Revenue (Unpaid Bookings)
$pendingRevenue = 0;
if (tableExists($conn, 'bookings') && tableExists($conn, 'events')) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(b.total_amount), 0) as pending_amount
        FROM bookings b 
        JOIN events e ON b.event_id = e.id 
        WHERE (LOWER(b.payment_status) IN ('unpaid', 'pending') OR b.payment_status IN ('Unpaid', 'Pending', 'unpaid', 'pending'))
    ");
    if ($stmt) {
        if ($stmt->execute()) {
            $stmt->bind_result($pendingRevenue);
            $stmt->fetch();
        }
        $stmt->close();
    }
}

// 7. Average Rating
$averageRating = 0;
if (tableExists($conn, 'feedback') && tableExists($conn, 'bookings') && tableExists($conn, 'events')) {
    $stmt = $conn->prepare("
        SELECT COALESCE(AVG(f.rating), 0) as avg_rating
        FROM feedback f 
        JOIN bookings b ON f.booking_id = b.id 
        JOIN events e ON b.event_id = e.id
    ");
    if ($stmt) {
        if ($stmt->execute()) {
            $stmt->bind_result($averageRating);
            $stmt->fetch();
        }
        $stmt->close();
    }
}
$averageRating = round($averageRating, 1);

// 8. Total Events (All Statuses)
$totalEventsCount = 0;
if (tableExists($conn, 'events')) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM events
    ");
    if ($stmt) {
        if ($stmt->execute()) {
            $stmt->bind_result($totalEventsCount);
            $stmt->fetch();
        }
        $stmt->close();
    }
}

// --- Fetch Tables Data ---

// 9. Execution Monitor (Upcoming 5 Events)
$upcomingEvents = [];
if (tableExists($conn, 'events')) {
    $stmt = $conn->prepare("
        SELECT id, title, event_date, event_time, status, category, location 
        FROM events 
        WHERE event_date >= CURDATE()
        ORDER BY event_date ASC, event_time ASC
        LIMIT 5
    ");
    if ($stmt) {
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $upcomingEvents[] = $row;
            }
        }
        $stmt->close();
    }
}

// 10. Operations Activity (Recent 5 Bookings)
$recentBookings = [];
if (tableExists($conn, 'bookings') && tableExists($conn, 'events')) {
    $usersTableExists = tableExists($conn, 'users');
    if ($usersTableExists) {
        $stmt = $conn->prepare("
            SELECT b.id, b.created_at, b.booking_code, b.total_amount, b.status, b.payment_status,
                   u.name as user_name, u.email as user_email,
                   e.title as event_title, e.event_date
            FROM bookings b
            JOIN events e ON b.event_id = e.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE b.payment_status = 'paid'
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT b.id, b.created_at, b.booking_code, b.total_amount, b.status, b.payment_status,
                   'Guest' as user_name, '' as user_email,
                   e.title as event_title, e.event_date
            FROM bookings b
            JOIN events e ON b.event_id = e.id
            WHERE b.payment_status = 'paid'
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
    }
    if ($stmt) {
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $recentBookings[] = $row;
            }
        }
        $stmt->close();
    }
}

// 11. Recent Payments
$recentPayments = [];
// Check if payments table exists first
$paymentsTableExists = false;
$checkTable = $conn->query("SHOW TABLES LIKE 'payments'");
if ($checkTable && $checkTable->num_rows > 0) {
    $paymentsTableExists = true;
    $stmt = $conn->prepare("
        SELECT p.id, p.amount, p.method, p.status, p.created_at, p.transaction_id,
               u.name as user_name,
               e.title as event_title
        FROM payments p
        JOIN bookings b ON p.booking_id = b.id
        JOIN events e ON b.event_id = e.id
        LEFT JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recentPayments[] = $row;
        }
        $stmt->close();
    }
}

// 12. Monthly Revenue (Last 6 months)
$monthlyRevenue = [];
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(b.created_at, '%Y-%m') as month,
        COALESCE(SUM(b.total_amount), 0) as revenue
    FROM bookings b
    JOIN events e ON b.event_id = e.id
    WHERE LOWER(b.payment_status) = 'paid'
      AND b.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(b.created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $monthlyRevenue[] = $row;
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Meta Tags - Hidden from Search Engines (Security Best Practice) -->
    <title>EventEase Manager Dashboard - Internal Panel</title>
    <meta name="description" content="EventEase Manager Panel - Internal Management System">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="googlebot" content="noindex, nofollow">
    
    <!-- Prevent Search Engine Indexing -->
    <meta name="robots" content="noindex">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            margin: 0;
            background: var(--bg);
            min-height: 100vh;
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
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-grid-bottom {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: #fff;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(90,44,160,0.08);
            border: 1px solid rgba(90,44,160,0.08);
        }
        .summary-card span {
            display: block;
            font-size: 14px;
            color: #818181;
            margin-bottom: 8px;
        }
        .summary-card h3 {
            margin: 0;
            font-size: 34px;
            color: var(--primary-dark);
        }
        .summary-card small {
            color: #4db28f;
            font-size: 13px;
        }
        .board {
            background: #fff;
            border-radius: 24px;
            padding: 25px;
            box-shadow: 0 20px 45px rgba(90,44,160,0.07);
            margin-bottom: 30px;
        }
        .board h4 {
            margin-bottom: 20px;
            color: var(--primary-dark);
        }
        .table {
            margin-bottom: 0;
        }
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        .activity-list li {
            list-style: none;
            padding: 12px 0;
            border-bottom: 1px dashed rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            color: #5b5b5b;
        }
        .activity-list li:last-child {
            border-bottom: none;
        }
        @media (max-width: 992px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }
            .summary-grid {
                grid-template-columns: 1fr;
            }
            .summary-grid-bottom {
                grid-template-columns: 1fr;
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
    <div class="brand">
        <img src="../user/assets/1.logo.png" alt="EventEase" class="brand-logo">
        <div class="brand-text">
            <strong>Event Ease</strong>
            <span>Manager Suite</span>
        </div>
    </div>
    <a class="nav-link-custom active" href="index.php">Dashboard</a>
    <a class="nav-link-custom" href="pending_approval.php">Pending Approvals</a>
    <a class="nav-link-custom" href="manage_events.php">Manage Events</a>
    <a class="nav-link-custom" href="manage_booking.php">Booking Hub</a>
    <a class="nav-link-custom" href="manage_addOns.php">Add-on & Pickup</a>
    <a class="nav-link-custom" href="manage_gallary.php">Gallery Uploads</a>
    <a class="nav-link-custom" href="manage_payments.php">Payment History</a>
    <a class="nav-link-custom" href="manage_feedback.php">Event Ratings</a>
    <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div>
            <h2 class="mb-1">Event Ease Manager Dashboard</h2>
            <small class="text-muted">Track events, bookings and revenue | Signed in as <strong><?= htmlspecialchars($managerName); ?></strong></small>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_events.php" class="btn btn-outline-secondary">View Events</a>
            <button class="btn btn-primary" style="background: var(--primary); border: none;">Generate Report</button>
        </div>
    </div>

    <section class="summary-grid">
        <div class="summary-card">
            <span>Live Events</span>
            <h3><?= str_pad($liveEventsCount, 2, '0', STR_PAD_LEFT); ?></h3>
            <small>Currently Active</small>
        </div>
        <div class="summary-card">
            <span>Total Events</span>
            <h3><?= str_pad($totalEventsCount, 2, '0', STR_PAD_LEFT); ?></h3>
            <small>All events created</small>
        </div>
        <div class="summary-card">
            <span>Pending Approvals</span>
            <h3><?= str_pad($pendingApprovalsCount, 2, '0', STR_PAD_LEFT); ?></h3>
            <small>Bookings awaiting action</small>
        </div>
    </section>

    <section class="summary-grid-bottom">
        <div class="summary-card">
            <span>Total Bookings</span>
            <h3><?= str_pad($totalBookingsCount, 2, '0', STR_PAD_LEFT); ?></h3>
            <small>All time bookings</small>
        </div>
        <div class="summary-card">
            <span>Total Revenue</span>
            <h3 style="font-size: 26px; line-height: 1.2; word-break: break-word;">₹<?= number_format($totalRevenue, 2); ?></h3>
            <small>From paid bookings</small>
        </div>
        <div class="summary-card">
            <span>Pending Revenue</span>
            <h3 style="font-size: 26px; line-height: 1.2; word-break: break-word;">₹<?= number_format($pendingRevenue, 2); ?></h3>
            <small>Awaiting payment</small>
        </div>
        <div class="summary-card">
            <span>Average Rating</span>
            <h3><?= $averageRating > 0 ? number_format($averageRating, 1) : '0.0'; ?><small style="font-size: 18px;">/5</small></h3>
            <small>From customer feedback</small>
        </div>
    </section>



    <section class="board">
        <h4>Upcoming Events</h4>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date & Time</th>
                        <th>Location</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcomingEvents)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No upcoming events found. <a href="manage_events.php">Create your first event</a></td></tr>
                    <?php else: ?>
                        <?php foreach ($upcomingEvents as $event): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($event['title']); ?></strong></td>
                                <td>
                                    <?= date('d M Y', strtotime($event['event_date'])); ?><br>
                                    <small class="text-muted"><?= isset($event['event_time']) ? date('h:i A', strtotime($event['event_time'])) : 'N/A'; ?></small>
                                </td>
                                <td><small><?= htmlspecialchars($event['location'] ?? 'N/A'); ?></small></td>
                                <td><?= htmlspecialchars($event['category']); ?></td>
                                <td>
                                    <?php 
                                        $statusValue = strtolower($event['status'] ?? 'planning');
                                        $statusLabel = ucfirst($statusValue);
                                        $statusClass = 'bg-secondary-subtle text-secondary';
                                        if ($statusValue === 'active') $statusClass = 'bg-success-subtle text-success';
                                        elseif ($statusValue === 'planning') $statusClass = 'bg-warning-subtle text-warning';
                                        elseif ($statusValue === 'cancelled') $statusClass = 'bg-danger-subtle text-danger';
                                        elseif ($statusValue === 'completed') $statusClass = 'bg-info-subtle text-info';
                                    ?>
                                    <span class="badge <?= $statusClass; ?>"><?= $statusLabel; ?></span>
                                </td>
                                <td>
                                    <a href="manage_events.php?edit=<?= $event['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="board">
        <h4>Recent Bookings</h4>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Booking Code</th>
                        <th>Customer</th>
                        <th>Event</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentBookings)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No recent bookings found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentBookings as $booking): ?>
                            <tr>
                                <td><code class="text-primary"><?= htmlspecialchars($booking['booking_code'] ?? 'N/A'); ?></code></td>
                                <td><strong><?= htmlspecialchars($booking['user_name'] ?? 'Guest'); ?></strong></td>
                                <td><?= htmlspecialchars($booking['event_title']); ?></td>
                                <td><strong>₹<?= number_format($booking['total_amount'] ?? 0, 2); ?></strong></td>
                                <td>
                                    <?php 
                                        $bookingStatus = strtolower($booking['status'] ?? 'pending');
                                        $statusClass = 'bg-warning-subtle text-warning';
                                        if ($bookingStatus === 'approved') $statusClass = 'bg-info-subtle text-info';
                                        elseif ($bookingStatus === 'confirmed') $statusClass = 'bg-success-subtle text-success';
                                        elseif ($bookingStatus === 'completed') $statusClass = 'bg-primary-subtle text-primary';
                                        elseif ($bookingStatus === 'cancelled') $statusClass = 'bg-danger-subtle text-danger';
                                    ?>
                                    <span class="badge <?= $statusClass; ?>"><?= ucfirst($bookingStatus); ?></span>
                                </td>
                                <td><small><?= date('d M Y', strtotime($booking['created_at'])); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

</body>
</html>

