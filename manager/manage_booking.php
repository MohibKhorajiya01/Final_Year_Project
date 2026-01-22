<?php
session_start();
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/auth_check.php';

function tableExists(mysqli $conn, string $tableName): bool {
    $safe = $conn->real_escape_string($tableName);
    $check = $conn->query("SHOW TABLES LIKE '$safe'");
    return $check && $check->num_rows > 0;
}

$bookingStatuses = ['pending', 'approved', 'confirmed', 'completed', 'cancelled'];
$paymentStatuses = ['unpaid', 'partial', 'paid', 'refunded'];
$statusMessage = '';
$statusType = 'success';

$bookingTableExists = tableExists($conn, 'bookings');
$eventsTableExists = tableExists($conn, 'events');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id']) && $bookingTableExists && $eventsTableExists) {
    $bookingId = (int) $_POST['booking_id'];
    $newStatus = strtolower(trim($_POST['status'] ?? ''));
    $newPayment = strtolower(trim($_POST['payment_status'] ?? ''));

    if (!in_array($newStatus, $bookingStatuses, true) || !in_array($newPayment, $paymentStatuses, true)) {
        $statusMessage = "Invalid status selection.";
        $statusType = "danger";
    } else {
        $ownership = $conn->prepare("
            SELECT b.id 
            FROM bookings b 
            INNER JOIN events e ON b.event_id = e.id 
            WHERE b.id = ? AND e.manager_id = ?
            LIMIT 1
        ");
        if ($ownership) {
            $ownership->bind_param("ii", $bookingId, $managerId);
            $ownership->execute();
            $ownership->store_result();
            if ($ownership->num_rows === 1) {
                $update = $conn->prepare("UPDATE bookings SET status = ?, payment_status = ? WHERE id = ?");
                if ($update) {
                    $update->bind_param("ssi", $newStatus, $newPayment, $bookingId);
                    if ($update->execute()) {
                        $statusMessage = "Booking updated successfully.";
                    } else {
                        $statusMessage = "Unable to update booking.";
                        $statusType = "warning";
                    }
                    $update->close();
                } else {
                    $statusMessage = "Failed to prepare update statement.";
                    $statusType = "danger";
                }
            } else {
                $statusMessage = "You cannot update this booking.";
                $statusType = "danger";
            }
            $ownership->close();
        }
    }
}

$search = trim($_GET['search'] ?? '');
$statusFilter = strtolower(trim($_GET['status'] ?? ''));
$paymentFilter = strtolower(trim($_GET['payment_status'] ?? ''));

$bookings = [];
if ($bookingTableExists && $eventsTableExists) {
    $query = "
        SELECT b.*, 
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
               e.title AS event_title, e.location AS event_location, e.event_date AS event_date
        FROM bookings b
        INNER JOIN events e ON b.event_id = e.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE e.manager_id = ?
    ";
    $types = "i";
    $params = [$managerId];

    if ($search !== '') {
        $query .= " AND (b.booking_code LIKE CONCAT('%', ?, '%') OR u.name LIKE CONCAT('%', ?, '%') OR e.title LIKE CONCAT('%', ?, '%'))";
        $types .= "sss";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }

    if ($statusFilter !== '' && in_array($statusFilter, $bookingStatuses, true)) {
        $query .= " AND b.status = ?";
        $types .= "s";
        $params[] = $statusFilter;
    }

    if ($paymentFilter !== '' && in_array($paymentFilter, $paymentStatuses, true)) {
        $query .= " AND b.payment_status = ?";
        $types .= "s";
        $params[] = $paymentFilter;
    }

    $query .= " ORDER BY b.created_at DESC";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
            }
        }
        $stmt->close();
    }
}

$stats = [
    'total' => count($bookings),
    'pending' => 0,
    'approved' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
];

foreach ($bookings as $booking) {
    $key = $booking['status'] ?? 'pending';
    if (isset($stats[$key])) {
        $stats[$key]++;
    }
}

function badgeClass(string $status): string {
    switch ($status) {
        case 'approved':
        case 'confirmed':
            return 'bg-info-subtle text-info';
        case 'completed':
            return 'bg-success-subtle text-success';
        case 'cancelled':
            return 'bg-danger-subtle text-danger';
        default:
            return 'bg-warning-subtle text-warning';
    }
}

function paymentBadge(string $status): string {
    switch ($status) {
        case 'paid':
            return 'bg-success-subtle text-success';
        case 'partial':
            return 'bg-info-subtle text-info';
        case 'refunded':
            return 'bg-secondary-subtle text-secondary';
        default:
            return 'bg-danger-subtle text-danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager · Booking Hub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #5a2ca0;
            --primary-dark: #431f75;
            --bg: #f5f3ff;
        }
        body {
            margin: 0;
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
        .filter-board,
        .table-board {
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 15px 35px rgba(90,44,160,0.07);
            margin-bottom: 24px;
        }
        .client-badge {
            font-size: 0.85rem;
        }
        .btn-primary {
            background: var(--primary);
            border: none;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(90,44,160,0.25);
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
    <a class="nav-link-custom active" href="manage_booking.php">Booking Hub</a>
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
            <h2 class="mb-0">Booking Processing Hub</h2>
            <small class="text-muted">Booking Processing Hub | Signed in as <strong><?= htmlspecialchars($managerName); ?></strong></small>
        </div>
    </div>

    <?php if ($statusMessage): ?>
        <div class="alert alert-<?= htmlspecialchars($statusType); ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($statusMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-2 col-6"><div class="stat-card"><span>Total</span><h3><?= $stats['total']; ?></h3></div></div>
        <div class="col-md-2 col-6"><div class="stat-card"><span>Pending</span><h3><?= $stats['pending']; ?></h3></div></div>
        <div class="col-md-2 col-6"><div class="stat-card"><span>Approved</span><h3><?= $stats['approved']; ?></h3></div></div>
        <div class="col-md-2 col-6"><div class="stat-card"><span>Confirmed</span><h3><?= $stats['confirmed']; ?></h3></div></div>
        <div class="col-md-2 col-6"><div class="stat-card"><span>Completed</span><h3><?= $stats['completed']; ?></h3></div></div>
        <div class="col-md-2 col-6"><div class="stat-card"><span>Cancelled</span><h3><?= $stats['cancelled']; ?></h3></div></div>
    </div>

    <div class="filter-board">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Booking code, client name, event" value="<?= htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Booking Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($bookingStatuses as $statusOption): ?>
                        <option value="<?= $statusOption; ?>" <?= $statusFilter === $statusOption ? 'selected' : ''; ?>>
                            <?= ucfirst($statusOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Payment Status</label>
                <select name="payment_status" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($paymentStatuses as $paymentOption): ?>
                        <option value="<?= $paymentOption; ?>" <?= $paymentFilter === $paymentOption ? 'selected' : ''; ?>>
                            <?= ucfirst($paymentOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>
        </form>
    </div>

    <div class="table-board">
        <?php if (!$bookingTableExists): ?>
            <div class="text-center py-5">
                <h5 class="text-muted">Bookings table not found.</h5>
                <p class="text-muted mb-0">Once users start booking events, records will appear here.</p>
            </div>
        <?php elseif (!$eventsTableExists): ?>
            <div class="text-center py-5">
                <h5 class="text-muted">Events table missing.</h5>
                <p class="text-muted mb-0">Please ask Admin to create events before processing bookings.</p>
            </div>
        <?php elseif (empty($bookings)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-clipboard-list fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No bookings found for your events.</h5>
                <p class="text-muted mb-0">Once clients book your assigned events, they will appear here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Booking</th>
                            <th>Client</th>
                            <th>Event</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($booking['booking_code']); ?></strong><br>
                                    <small class="text-muted">Created <?= date('d M Y', strtotime($booking['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($booking['user_name'] ?? 'Unknown'); ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($booking['user_email'] ?? ''); ?></small><br>
                                    <?php if (!empty($booking['user_phone'])): ?>
                                        <span class="badge bg-light text-dark client-badge">
                                            <i class="fa-solid fa-phone"></i> <?= htmlspecialchars($booking['user_phone']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($booking['event_title'] ?? 'Event removed'); ?></div>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($booking['event_location'] ?? ''); ?>
                                        <?php 
                                            $displayDate = !empty($booking['preferred_date']) ? $booking['preferred_date'] : $booking['event_date'];
                                            if (!empty($displayDate)): 
                                        ?>
                                            · <span class="text-primary fw-bold"><?= date('d M Y', strtotime($displayDate)); ?></span>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>₹<?= number_format((float) ($booking['total_amount'] ?? 0), 2); ?></td>
                                <td>
                                    <span class="badge <?= badgeClass($booking['status'] ?? 'pending'); ?>">
                                        <?= ucfirst($booking['status'] ?? 'pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= paymentBadge($booking['payment_status'] ?? 'unpaid'); ?>">
                                        <?= ucfirst($booking['payment_status'] ?? 'unpaid'); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="row g-2 align-items-center">
                                        <input type="hidden" name="booking_id" value="<?= (int) $booking['id']; ?>">
                                        <div class="col-6">
                                            <select name="status" class="form-select form-select-sm">
                                                <?php foreach ($bookingStatuses as $statusOption): ?>
                                                    <option value="<?= $statusOption; ?>" <?= ($booking['status'] ?? 'pending') === $statusOption ? 'selected' : ''; ?>>
                                                        <?= ucfirst($statusOption); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <select name="payment_status" class="form-select form-select-sm">
                                                <?php foreach ($paymentStatuses as $paymentOption): ?>
                                                    <option value="<?= $paymentOption; ?>" <?= ($booking['payment_status'] ?? 'unpaid') === $paymentOption ? 'selected' : ''; ?>>
                                                        <?= ucfirst($paymentOption); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-sm btn-primary w-100">Update</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
