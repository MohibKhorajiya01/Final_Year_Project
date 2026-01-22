<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';

function tableExists(mysqli $conn, string $tableName): bool {
    $safe = $conn->real_escape_string($tableName);
    $check = $conn->query("SHOW TABLES LIKE '$safe'");
    return $check && $check->num_rows > 0;
}

$statusMessage = '';
$statusType = 'success';

// Handle booking approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_booking'])) {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    
    if ($bookingId > 0) {
        $update = $conn->prepare("UPDATE bookings SET status = 'approved' WHERE id = ? AND LOWER(status) = 'pending'");
        if ($update) {
            $update->bind_param("i", $bookingId);
            if ($update->execute()) {
                $statusMessage = "Booking approved successfully!";
                $statusType = 'success';
            } else {
                $statusMessage = "Failed to approve booking.";
                $statusType = 'danger';
            }
            $update->close();
        }
    }
}

// Handle booking rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_booking'])) {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    
    if ($bookingId > 0) {
        $update = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
        if ($update) {
            $update->bind_param("i", $bookingId);
            if ($update->execute()) {
                $statusMessage = "Booking rejected successfully.";
                $statusType = 'warning';
            } else {
                $statusMessage = "Failed to reject booking.";
                $statusType = 'danger';
            }
            $update->close();
        }
    }
}

$bookingTableExists = tableExists($conn, 'bookings');
$eventsTableExists = tableExists($conn, 'events');
$usersTableExists = tableExists($conn, 'users');

$bookings = [];
if ($bookingTableExists && $eventsTableExists && $usersTableExists) {
    // Get only PAID bookings that are pending approval
    $query = "
        SELECT b.*, 
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
               e.title AS event_title, e.location AS event_location, e.event_date AS event_date,
               e.category AS event_category
        FROM bookings b
        INNER JOIN users u ON b.user_id = u.id
        LEFT JOIN events e ON b.event_id = e.id
        WHERE b.status = 'pending' AND b.payment_status = 'paid'
        ORDER BY b.created_at DESC
    ";
    
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approvals - Event Ease</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #5a2ca0;
            --primary-dark: #431f75;
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
        .header-section {
            background: linear-gradient(135deg, #5a2ca0 0%, #7d3cbd 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        .booking-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .booking-card.pending {
            border-left: 4px solid #ffc107;
        }
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        .badge-paid {
            background: #d1e7dd;
            color: #0f5132;
        }
        .badge-unpaid {
            background: #ffe5e5;
            color: #dc3545;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: 600;
            width: 150px;
            color: #666;
        }
        .info-value {
            flex: 1;
            color: #333;
        }
        .btn-approve {
            background: #28a745;
            border: none;
            color: white;
        }
        .btn-approve:hover {
            background: #218838;
            color: white;
        }
        .btn-reject {
            background: #dc3545;
            border: none;
            color: white;
        }
        .btn-reject:hover {
            background: #c82333;
            color: white;
        }
        .booking-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .booking-card.pending {
            border-left: 4px solid #ffc107;
        }
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        .badge-paid {
            background: #d1e7dd;
            color: #0f5132;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: 600;
            width: 150px;
            color: #666;
        }
        .info-value {
            flex: 1;
            color: #333;
        }
        .btn-approve {
            background: #28a745;
            border: none;
            color: white;
        }
        .btn-approve:hover {
            background: #218838;
            color: white;
        }
        .btn-reject {
            background: #dc3545;
            border: none;
            color: white;
        }
        .btn-reject:hover {
            background: #c82333;
            color: white;
        }
        @media (max-width: 992px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
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
    <a class="nav-link-custom active" href="pending_approval.php">Pending Approvals</a>
    <a class="nav-link-custom" href="add_event.php">Add Event</a>
    <a class="nav-link-custom" href="manage_events.php">Manage Events</a>
    <a class="nav-link-custom" href="payment.php">Payment Detail</a>
    <a class="nav-link-custom" href="AddOns.php">Add Ons</a>
    <a class="nav-link-custom" href="gallery.php">Gallery Content</a>
    <a class="nav-link-custom" href="feedback.php">Event Ratings</a>
    <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div>
            <h2><i class="fas fa-clock"></i> Pending Approvals</h2>
            <p>Approve bookings after payment confirmation</p>
        </div>
    </div>

    <div>
        <?php if ($statusMessage): ?>
            <div class="alert alert-<?= $statusType; ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($statusMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($bookings)): ?>
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h4>No Pending Approvals</h4>
                <p class="text-muted">All paid bookings have been processed.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($bookings as $booking): 
                    $addons = json_decode($booking['addons'] ?? '{}', true);
                ?>
                    <div class="col-md-6 mb-4">
                        <div class="booking-card pending">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($booking['booking_code']); ?></h5>
                                    <span class="badge-status badge-pending">Pending Approval</span>
                                    <?php 
                                        $paymentStatus = strtolower($booking['payment_status'] ?? 'unpaid');
                                        $paymentBadgeClass = $paymentStatus === 'paid' ? 'badge-paid' : 'badge-unpaid';
                                    ?>
                                    <span class="badge-status <?= $paymentBadgeClass; ?> ms-2"><?= ucfirst($paymentStatus); ?></span>
                                </div>
                                <div class="text-end">
                                    <strong class="text-success">₹<?= number_format($booking['total_amount'], 2); ?></strong>
                                </div>
                            </div>

                            <div class="info-row">
                                <span class="info-label">Event:</span>
                                <span class="info-value"><?= htmlspecialchars($booking['event_title'] ?? 'N/A'); ?></span>
                            </div>

                            <div class="info-row">
                                <span class="info-label">User Name:</span>
                                <span class="info-value"><?= htmlspecialchars($booking['user_name']); ?></span>
                            </div>

                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?= htmlspecialchars($booking['user_email']); ?></span>
                            </div>

                            <div class="info-row">
                                <span class="info-label">Phone:</span>
                                <span class="info-value"><?= htmlspecialchars($booking['user_phone']); ?></span>
                            </div>

                            <?php 
                                $displayDate = !empty($booking['preferred_date']) ? $booking['preferred_date'] : $booking['event_date'];
                                if (!empty($displayDate)): 
                            ?>
                                <div class="info-row">
                                    <span class="info-label">Booking Date:</span>
                                    <span class="info-value fw-bold text-primary"><?= date('d M Y', strtotime($displayDate)); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($booking['event_location'])): ?>
                                <div class="info-row">
                                    <span class="info-label">Location:</span>
                                    <span class="info-value"><?= htmlspecialchars($booking['event_location']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($addons)): ?>
                                <div class="mt-3 p-3 bg-light rounded">
                                    <strong>Booking Details:</strong>
                                    <ul class="mb-0 mt-2">
                                        <?php if (isset($addons['food_package'])): ?>
                                            <li>Food Package: <?= ucfirst($addons['food_package']); ?></li>
                                        <?php endif; ?>
                                        <?php if (isset($addons['pickup_service']) && $addons['pickup_service'] !== 'no'): ?>
                                            <li>Pickup Service: <?= ucfirst($addons['pickup_service']); ?></li>
                                            <?php if (!empty($addons['pickup_address'])): ?>
                                                <li>Pickup Address: <?= htmlspecialchars($addons['pickup_address']); ?></li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (isset($addons['decoration']) && $addons['decoration'] !== 'none'): ?>
                                            <li>Decoration: <?= ucfirst($addons['decoration']); ?> (₹<?= number_format($addons['decoration_cost'] ?? 0, 2); ?>)</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($booking['notes'])): ?>
                                <div class="mt-2">
                                    <small class="text-muted"><?= htmlspecialchars($booking['notes']); ?></small>
                                </div>
                            <?php endif; ?>

                            <div class="mt-3 pt-3 border-top">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="booking_id" value="<?= $booking['id']; ?>">
                                    <button type="submit" name="approve_booking" class="btn btn-approve btn-sm me-2" onclick="return confirm('Are you sure you want to approve this booking?');">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="booking_id" value="<?= $booking['id']; ?>">
                                    <button type="submit" name="reject_booking" class="btn btn-reject btn-sm" onclick="return confirm('Are you sure you want to reject this booking?');">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            </div>

                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> Booked on: <?= date('d M Y, h:i A', strtotime($booking['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 3.5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.classList.remove('show');
                    alert.classList.add('fade');
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 150);
                }, 3500);
            });
        });
    </script>
</body>
</html>

