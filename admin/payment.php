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

// Handle payment status update
if (isset($_GET['update_status']) && isset($_GET['id'])) {
    $bookingId = (int) $_GET['id'];
    $newStatus = $conn->real_escape_string($_GET['update_status']);
    
    if (tableExists($conn, 'bookings')) {
        $conn->query("UPDATE bookings SET payment_status = '$newStatus' WHERE id = $bookingId");
        header("Location: payment.php?updated=1");
        exit();
    }
}

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$dateFilter = isset($_GET['date_range']) ? trim($_GET['date_range']) : '';

$payments = [];
$totalRevenue = 0;
$pendingAmount = 0;
$paidAmount = 0;

if (tableExists($conn, 'bookings') && tableExists($conn, 'events')) {
    $latestBookingPerEvent = "
        SELECT MAX(id) AS latest_id
        FROM bookings
        GROUP BY user_id, event_id
    ";

    $query = "SELECT b.*, 
                     b.booking_code as transaction_id,
                     b.total_amount as amount,
                     b.status as booking_status,
                     b.payment_status as status,
                     b.preferred_date as event_date,
                     u.name AS user_name,
                     u.email AS user_email,
                     e.title AS event_title,
                     e.price AS event_price
              FROM bookings b
              INNER JOIN ($latestBookingPerEvent) lb ON lb.latest_id = b.id
              LEFT JOIN users u ON b.user_id = u.id
              LEFT JOIN events e ON b.event_id = e.id
              WHERE b.payment_status = 'paid'";
    
    if ($search) {
        $searchSafe = $conn->real_escape_string($search);
        $query .= " AND (b.booking_code LIKE '%$searchSafe%' OR u.name LIKE '%$searchSafe%' OR e.title LIKE '%$searchSafe%')";
    }
    if ($statusFilter) {
        $statusSafe = $conn->real_escape_string($statusFilter);
        $query .= " AND b.payment_status = '$statusSafe'";
    }
    if ($dateFilter) {
        if ($dateFilter === 'today') {
            $query .= " AND DATE(b.created_at) = CURDATE()";
        } elseif ($dateFilter === 'week') {
            $query .= " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($dateFilter === 'month') {
            $query .= " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }
    
    $query .= " ORDER BY b.created_at DESC";
    
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Add payment_method field for compatibility
            $row['payment_method'] = 'Online'; // Default value since bookings table doesn't have this
            $payments[] = $row;
            $totalRevenue += (float) ($row['amount'] ?? 0);
            if ($row['status'] === 'unpaid' || $row['status'] === 'pending') {
                $pendingAmount += (float) ($row['amount'] ?? 0);
            } elseif ($row['status'] === 'paid') {
                $paidAmount += (float) ($row['amount'] ?? 0);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details - Admin Panel</title>
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
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 18px;
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        .stat-card h3 {
            margin: 0;
            font-size: 26px;
            color: #2f2f2f;
        }
        .stat-card span {
            color: #7a7a7a;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-section {
            background: #fff;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(90,44,160,0.08);
        }
        .table-container {
            background: #fff;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(90,44,160,0.08);
            overflow-x: auto;
        }
        .table thead {
            background: rgba(90,44,160,0.1);
        }
        .table thead th {
            border: none;
            color: var(--primary-dark);
            font-weight: 600;
            padding: 15px;
        }
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
        }
        .badge-custom {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .btn-sm-custom {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 8px;
        }
        .btn-primary {
            background: var(--primary);
            border: none;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-success {
            background: #28a745;
            border: none;
        }
        .btn-success:hover {
            background: #218838;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(90,44,160,0.25);
        }
        @media (max-width: 992px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
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
    <a class="nav-link-custom active" href="payment.php">Payment Detail</a>
    <a class="nav-link-custom" href="AddOns.php">Add Ons</a>
    <a class="nav-link-custom" href="gallery.php">Gallery Content</a>
    <a class="nav-link-custom" href="feedback.php">Event Ratings</a>
    <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

<main class="main-content">
    <div class="page-header">
        <div>
            <h2 class="mb-1">Payment Details</h2>
            <p class="text-muted mb-0">Track all payments, invoices and transactions</p>
        </div>
    </div>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Payment status updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <span>Total Revenue</span>
            <h3>₹<?= number_format($totalRevenue, 2); ?></h3>
        </div>
        <div class="stat-card">
            <span>Paid Amount</span>
            <h3>₹<?= number_format($paidAmount, 2); ?></h3>
        </div>
        <div class="stat-card">
            <span>Pending Amount</span>
            <h3>₹<?= number_format($pendingAmount, 2); ?></h3>
        </div>
        <div class="stat-card">
            <span>Total Transactions</span>
            <h3><?= count($payments); ?></h3>
        </div>
    </div>

    <div class="filter-section">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Transaction ID, User, Event..." 
                       value="<?= htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Payment Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date Range</label>
                <select name="date_range" class="form-select">
                    <option value="">All Time</option>
                    <option value="today" <?= $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?= $dateFilter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?= $dateFilter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <div class="table-container">
        <?php if (empty($payments)): ?>
            <div class="text-center py-5">
                <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No payments found</h5>
                <p class="text-muted">Payment records will appear here once bookings are made.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Booking Status</th>
                            <th>Payment Status</th>
                            <th>Event Date</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <strong class="text-primary"><?= htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($payment['user_name'] ?? 'N/A'); ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($payment['user_email'] ?? ''); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($payment['event_title'] ?? 'N/A'); ?></strong>
                                        <?php if (isset($payment['guest_count'])): ?>
                                            <br><small class="text-muted"><?= $payment['guest_count']; ?> guests</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                    <strong>₹<?= number_format($payment['amount'] ?? 0, 2); ?></strong>
                                </td>
                                <td>
                                    <?php
                                    $bkStatus = $payment['booking_status'] ?? 'pending';
                                    $bkClass = match(strtolower($bkStatus)) {
                                        'approved' => 'bg-success',
                                        'completed' => 'bg-success',
                                        'pending' => 'bg-warning',
                                        'cancelled' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge badge-custom <?= $bkClass; ?>">
                                        <?= ucfirst(htmlspecialchars($bkStatus)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status = $payment['status'] ?? 'pending';
                                    $statusClass = match($status) {
                                        'paid' => 'bg-success',
                                        'completed' => 'bg-success',
                                        'pending' => 'bg-warning',
                                        'failed' => 'bg-danger',
                                        'refunded' => 'bg-secondary',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge badge-custom <?= $statusClass; ?>">
                                        <?= ucfirst(htmlspecialchars($status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($payment['event_date'])): ?>
                                        <?= date('d M Y', strtotime($payment['event_date'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($payment['created_at'])): ?>
                                        <?= date('d M Y, h:i A', strtotime($payment['created_at'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status === 'pending'): ?>
                                        <a href="?update_status=paid&id=<?= $payment['id']; ?>" 
                                           class="btn btn-sm btn-success btn-sm-custom" 
                                           title="Mark as Paid"
                                           onclick="return confirm('Mark this payment as paid?');">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-primary btn-sm-custom" 
                                            title="View Details"
                                            onclick="alert('Transaction ID: <?= htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?>\nAmount: ₹<?= number_format($payment['amount'] ?? 0, 2); ?>\nStatus: <?= ucfirst($status); ?>');">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3">
                <small class="text-muted">Total Records: <strong><?= count($payments); ?></strong></small>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

