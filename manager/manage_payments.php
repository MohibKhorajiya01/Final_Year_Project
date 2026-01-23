<?php
session_start();
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/auth_check.php';

// Helper function
function tableExists(mysqli $conn, string $tableName): bool {
    $safe = $conn->real_escape_string($tableName);
    $check = $conn->query("SHOW TABLES LIKE '$safe'");
    return $check && $check->num_rows > 0;
}

$paymentStatuses = ['unpaid', 'partial', 'paid', 'refunded'];
$paymentFilter = strtolower(trim($_GET['payment_status'] ?? ''));
$search = trim($_GET['search'] ?? '');

$bookings = [];
$totalRevenue = 0;
$usersTableExists = tableExists($conn, 'users');

if (tableExists($conn, 'bookings') && tableExists($conn, 'events')) {
    // Build query based on whether users table exists
    if ($usersTableExists) {
        $query = "
            SELECT b.*, 
                   u.name AS user_name, u.email AS user_email,
                   e.title AS event_title, e.event_date, e.manager_id
            FROM bookings b
            INNER JOIN events e ON b.event_id = e.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE b.payment_status = 'paid'
        ";
    } else {
        $query = "
            SELECT b.*, 
                   e.title AS event_title, e.event_date, e.manager_id
            FROM bookings b
            INNER JOIN events e ON b.event_id = e.id
            WHERE b.payment_status = 'paid'
        ";
    }
    
    $types = "";
    $params = [];

    if ($paymentFilter !== '' && in_array($paymentFilter, $paymentStatuses, true)) {
        $query .= " AND b.payment_status = ?";
        $types .= "s";
        $params[] = $paymentFilter;
    }

    if ($search !== '') {
        if ($usersTableExists) {
            $query .= " AND (b.booking_code LIKE CONCAT('%', ?, '%') OR u.name LIKE CONCAT('%', ?, '%') OR e.title LIKE CONCAT('%', ?, '%'))";
            $types .= "sss";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        } else {
            $query .= " AND (b.booking_code LIKE CONCAT('%', ?, '%') OR e.title LIKE CONCAT('%', ?, '%'))";
            $types .= "ss";
            $params[] = $search;
            $params[] = $search;
        }
    }

    $query .= " ORDER BY b.created_at DESC";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
                if ($row['payment_status'] === 'paid') {
                    $totalRevenue += (float)$row['total_amount'];
                }
            }
        }
        $stmt->close();
    }
}

function paymentBadge(string $status): string {
    switch ($status) {
        case 'paid': return 'bg-success-subtle text-success';
        case 'partial': return 'bg-info-subtle text-info';
        case 'refunded': return 'bg-secondary-subtle text-secondary';
        default: return 'bg-danger-subtle text-danger';
    }
}

// Calculate payment statistics
$paymentStats = [
    'total' => count($bookings),
    'paid' => 0,
    'unpaid' => 0,
    'partial' => 0,
    'refunded' => 0
];

foreach ($bookings as $booking) {
    $status = $booking['payment_status'] ?? 'unpaid';
    if (isset($paymentStats[$status])) {
        $paymentStats[$status]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Event Ease</title>
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
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .revenue-card {
            background: linear-gradient(135deg, #5a2ca0, #7d3cbd);
            color: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(90, 44, 160, 0.2);
            margin-bottom: 30px;
        }
        .revenue-card h3 {
            font-size: 2.5rem;
            margin: 0;
            font-weight: 700;
        }
        .table-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .badge-custom {
            padding: 6px 12px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.8rem;
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
    <a class="nav-link-custom active" href="manage_payments.php">Payment History</a>
    <a class="nav-link-custom" href="manage_feedback.php">Event Ratings</a>
    <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

<main class="main-content">
<div class="page-shell">
    <div class="page-header">
        <div>
            <h2 class="mb-1" style="color: var(--primary-dark);">Payment History</h2>
            <small class="text-muted">Track all transaction records | Signed in as <strong><?= htmlspecialchars($managerName); ?></strong></small>
        </div>
    </div>

    <!-- Payment Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="table-card text-center">
                <small class="text-muted text-uppercase d-block mb-1" style="font-size: 0.75rem; letter-spacing: 1px;">Total Bookings</small>
                <h4 class="mb-0" style="color: var(--primary);"><?= $paymentStats['total']; ?></h4>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="table-card text-center">
                <small class="text-success text-uppercase d-block mb-1" style="font-size: 0.75rem; letter-spacing: 1px;">Paid</small>
                <h4 class="mb-0 text-success"><?= $paymentStats['paid']; ?></h4>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="table-card text-center">
                <small class="text-danger text-uppercase d-block mb-1" style="font-size: 0.75rem; letter-spacing: 1px;">Unpaid</small>
                <h4 class="mb-0 text-danger"><?= $paymentStats['unpaid']; ?></h4>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="table-card text-center">
                <small class="text-info text-uppercase d-block mb-1" style="font-size: 0.75rem; letter-spacing: 1px;">Partial</small>
                <h4 class="mb-0 text-info"><?= $paymentStats['partial']; ?></h4>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="revenue-card">
                <span class="text-uppercase opacity-75" style="font-size: 0.85rem; letter-spacing: 1px;">Total Revenue (Paid)</span>
                <h3>₹<?= number_format($totalRevenue, 2); ?></h3>
            </div>
        </div>
        <div class="col-md-8">
            <div class="table-card h-100 d-flex align-items-center">
                <form class="row g-3 w-100" method="GET">
                    <div class="col-md-6">
                        <input type="text" name="search" class="form-control" placeholder="Search by code, client or event" value="<?= htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <select name="payment_status" class="form-select">
                            <option value="">All Statuses</option>
                            <?php foreach ($paymentStatuses as $status): ?>
                                <option value="<?= $status; ?>" <?= $paymentFilter === $status ? 'selected' : ''; ?>>
                                    <?= ucfirst($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100" style="background: var(--primary); border: none;">Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="table-card">
        <?php if (empty($bookings)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No payment records found.</h5>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Booking Code</th>
                            <th>Client</th>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($booking['booking_code']); ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($booking['user_name'] ?? 'Unknown'); ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($booking['user_email'] ?? ''); ?></small>
                                </td>
                                <td><?= htmlspecialchars($booking['event_title'] ?? 'N/A'); ?></td>
                                <td><?= date('d M Y', strtotime($booking['created_at'])); ?></td>
                                <td class="fw-bold">₹<?= number_format($booking['total_amount'], 2); ?></td>
                                <td>
                                    <span class="badge-custom <?= paymentBadge($booking['payment_status']); ?>">
                                        <?= ucfirst($booking['payment_status']); ?>
                                    </span>
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
