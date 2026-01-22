<?php
session_start();
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/auth_check.php';

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
        // Check if booking belongs to manager's events
        $checkOwnership = $conn->prepare("
            SELECT b.id 
            FROM bookings b 
            INNER JOIN events e ON b.event_id = e.id 
            WHERE b.id = ? AND e.manager_id = ? 
              AND LOWER(b.status) = 'pending'
            LIMIT 1
        ");
        if ($checkOwnership) {
            $checkOwnership->bind_param("ii", $bookingId, $managerId);
            $checkOwnership->execute();
            $checkOwnership->store_result();
            
            if ($checkOwnership->num_rows === 1) {
                $update = $conn->prepare("UPDATE bookings SET status = 'approved' WHERE id = ?");
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
            } else {
                $statusMessage = "You cannot approve this booking.";
                $statusType = 'danger';
            }
            $checkOwnership->close();
        }
    }
}

// Handle booking rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_booking'])) {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    
    if ($bookingId > 0) {
        // Check if booking belongs to manager's events
        $checkOwnership = $conn->prepare("
            SELECT b.id 
            FROM bookings b 
            INNER JOIN events e ON b.event_id = e.id 
            WHERE b.id = ? AND e.manager_id = ?
            LIMIT 1
        ");
        if ($checkOwnership) {
            $checkOwnership->bind_param("ii", $bookingId, $managerId);
            $checkOwnership->execute();
            $checkOwnership->store_result();
            
            if ($checkOwnership->num_rows === 1) {
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
            } else {
                $statusMessage = "You cannot reject this booking.";
                $statusType = 'danger';
            }
            $checkOwnership->close();
        }
    }
}

$bookingTableExists = tableExists($conn, 'bookings');
$eventsTableExists = tableExists($conn, 'events');
$usersTableExists = tableExists($conn, 'users');

$bookings = [];
if ($bookingTableExists && $eventsTableExists && $usersTableExists) {
    // Get only PAID pending bookings for manager's events
    $query = "
        SELECT b.*, 
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
               e.title AS event_title, e.location AS event_location, e.event_date AS event_date,
               e.category AS event_category
        FROM bookings b
        INNER JOIN users u ON b.user_id = u.id
        INNER JOIN events e ON b.event_id = e.id
        WHERE e.manager_id = ? 
          AND LOWER(b.status) = 'pending'
          AND LOWER(b.payment_status) = 'paid'
        ORDER BY b.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $managerId);
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
    <title>Pending Approvals - Event Ease</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #5a2ca0;
            --primary-dark: #431f75;
        }
        body {
            margin: 0;
            background: #f5f3ff;
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
        .page-shell {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 0;
        }
        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #fff;
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 10px 25px rgba(90,44,160,0.08);
        }
        .stat-card span {
            font-size: 12px;
            color: #7a7691;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stat-card h3 {
            margin: 8px 0 0;
            color: var(--primary-dark);
            font-size: 32px;
        }
        .table-board {
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 15px 35px rgba(90,44,160,0.07);
            margin-bottom: 24px;
        }
        .booking-card {
            background: #fff;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 6px 18px rgba(27,20,58,0.06);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .booking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(27,20,58,0.1);
        }
        .booking-card.pending {
            border-left: 4px solid #ffc107;
        }
        .badge-status {
            padding: 6px 14px;
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
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .info-label {
            font-weight: 600;
            width: 150px;
            color: #666;
            font-size: 14px;
        }
        .info-value {
            flex: 1;
            color: #333;
            font-size: 14px;
        }
        .btn-approve {
            background: #28a745;
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        .btn-approve:hover {
            background: #218838;
            color: white;
        }
        .btn-reject {
            background: #dc3545;
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
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
    <a class="nav-link-custom active" href="pending_approval.php">Pending Approvals</a>
    <a class="nav-link-custom" href="manage_events.php">Manage Events</a>
    <a class="nav-link-custom" href="manage_booking.php">Booking Hub</a>
    <a class="nav-link-custom" href="manage_addOns.php">Add-on & Pickup</a>
    <a class="nav-link-custom" href="manage_gallary.php">Gallery Uploads</a>
    <a class="nav-link-custom" href="manage_payments.php">Payment History</a>
    <a class="nav-link-custom" href="manage_feedback.php">Event Ratings</a>
    <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

<main class="main-content">
<div class="page-shell">
    <div class="page-header">
        <div>
            <p class="text-uppercase text-muted mb-1" style="letter-spacing:2px;">Manager Workspace</p>
            <h2 class="mb-0">Pending Approvals</h2>
            <small class="text-muted">Approve bookings for your events after payment confirmation | Signed in as <strong><?= htmlspecialchars($managerName); ?></strong></small>
        </div>
    </div>

    <?php if ($statusMessage): ?>
        <div class="alert alert-<?= $statusType; ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($statusMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($bookings)): ?>
        <div class="table-board text-center py-5">
            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
            <h4 class="text-muted">No Pending Approvals</h4>
            <p class="text-muted mb-0">All paid bookings for your events have been processed.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($bookings as $booking): 
                    $addonsJson = trim($booking['addons'] ?? '');
                    $addons = json_decode($addonsJson, true);
                    if (!is_array($addons)) {
                        $addons = [];
                    }
                ?>
                    <div class="col-md-6">
                        <div class="booking-card pending">
                            <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom">
                                <div>
                                    <h5 class="mb-2" style="color: var(--primary-dark);"><?= htmlspecialchars($booking['booking_code']); ?></h5>
                                    <span class="badge-status badge-pending">Pending Approval</span>
                                    <?php 
                                        $paymentStatus = strtolower($booking['payment_status'] ?? 'unpaid');
                                        $paymentBadgeClass = $paymentStatus === 'paid' ? 'badge-paid' : 'badge-unpaid';
                                    ?>
                                    <span class="badge-status <?= $paymentBadgeClass; ?> ms-2"><?= ucfirst($paymentStatus); ?></span>
                                </div>
                                <div class="text-end">
                                    <div class="text-success" style="font-size: 24px; font-weight: 700;">₹<?= number_format($booking['total_amount'], 2); ?></div>
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
                                    <span class="info-value fw-bold" style="color: var(--primary);"><?= date('d M Y', strtotime($displayDate)); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($booking['event_location'])): ?>
                                <div class="info-row">
                                    <span class="info-label">Location:</span>
                                    <span class="info-value"><?= htmlspecialchars($booking['event_location']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($addons)): ?>
                                <div class="mt-3 p-3" style="background: #f8f9fa; border-radius: 10px;">
                                    <strong style="color: var(--primary-dark);">Booking Details:</strong>
                                    <ul class="mb-0 mt-2" style="font-size: 14px;">
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
                            <?php elseif (!empty($addonsJson)): ?>
                                <div class="mt-3 p-3" style="background: #f8f9fa; border-radius: 10px;">
                                    <strong style="color: var(--primary-dark);">Booking Notes:</strong>
                                    <p class="mb-0 mt-2" style="font-size: 14px;"><?= htmlspecialchars($addonsJson); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($booking['notes'])): ?>
                                <div class="mt-3 pt-3 border-top">
                                    <strong style="color: var(--primary-dark); font-size: 14px;">Notes:</strong>
                                    <p class="mb-0 mt-2" style="font-size: 14px; color: #666;"><?= htmlspecialchars($booking['notes']); ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> Booked on: <?= date('d M Y, h:i A', strtotime($booking['created_at'])); ?>
                                    </small>
                                </div>
                                <div>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id']; ?>">
                                        <button type="submit" name="approve_booking" class="btn btn-approve me-2" onclick="return confirm('Are you sure you want to approve this booking?');">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id']; ?>">
                                        <button type="submit" name="reject_booking" class="btn btn-reject" onclick="return confirm('Are you sure you want to reject this booking?');">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </div>
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

