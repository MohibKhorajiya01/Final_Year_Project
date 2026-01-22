<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

function tableExists(mysqli $conn, string $tableName): bool {
    $tableName = $conn->real_escape_string($tableName);
    $check = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $check && $check->num_rows > 0;
}

function fetchCount(mysqli $conn, string $tableName): int {
    if (!tableExists($conn, $tableName)) {
        return 0;
    }
    $result = $conn->query("SELECT COUNT(*) AS total FROM $tableName");
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['total'];
    }
    return 0;
}

$totalUsers      = fetchCount($conn, "users");
$totalBookings   = fetchCount($conn, "bookings");

// Count actual pending approvals from bookings table
$pendingApproval = 0;
if (tableExists($conn, "bookings")) {
    $result = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE payment_status = 'paid' AND status = 'pending'");
    if ($result && $row = $result->fetch_assoc()) {
        $pendingApproval = (int)$row['total'];
    }
}

$upcomingEvents  = fetchCount($conn, "events");

// Legacy quick actions and big events timeline removed per request

// Fetch real pending approvals from database
$pendingApprovalsData = [];
if (tableExists($conn, 'bookings') && tableExists($conn, 'users') && tableExists($conn, 'events')) {
    $query = "SELECT u.name AS client, e.title AS event, b.total_amount AS budget, b.status
              FROM bookings b
              LEFT JOIN users u ON b.user_id = u.id
              LEFT JOIN events e ON b.event_id = e.id
              WHERE b.payment_status = 'paid' AND b.status = 'pending'
              ORDER BY b.created_at DESC
              LIMIT 5";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pendingApprovalsData[] = [
                'client' => $row['client'] ?? 'N/A',
                'event' => $row['event'] ?? 'N/A',
                'budget' => '₹' . number_format($row['budget'] ?? 0, 2),
                'status' => 'Awaiting'
            ];
        }
    }
}

// Helper to get time elapsed
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

$operationsActivity = [];

// Fetch real activity from DB
if (tableExists($conn, 'bookings') && tableExists($conn, 'payments') && tableExists($conn, 'events') && tableExists($conn, 'users')) {
    $unionQuery = "
        (SELECT CONCAT('New booking #', b.booking_code, ' by ', IFNULL(u.name, 'Guest')) as text, b.created_at as created_at 
         FROM bookings b LEFT JOIN users u ON b.user_id = u.id)
        UNION ALL
        (SELECT CONCAT('Payment of ₹', p.amount, ' received from ', IFNULL(u.name, 'User')) as text, p.created_at as created_at 
         FROM payments p LEFT JOIN users u ON p.user_id = u.id)
        UNION ALL
        (SELECT CONCAT('New Event \"', e.title, '\" created') as text, e.created_at as created_at 
         FROM events e)
        ORDER BY created_at DESC LIMIT 6
    ";
    
    // Check if feedback exists before adding to union to avoid errors if table missing
    if (tableExists($conn, 'feedback')) {
        $unionQuery = "
            (SELECT CONCAT('New booking #', b.booking_code, ' by ', IFNULL(u.name, 'Guest')) as text, b.created_at as created_at 
             FROM bookings b LEFT JOIN users u ON b.user_id = u.id)
            UNION ALL
            (SELECT CONCAT('Payment of ₹', p.amount, ' received from ', IFNULL(u.name, 'User')) as text, p.created_at as created_at 
             FROM payments p LEFT JOIN users u ON p.user_id = u.id)
            UNION ALL
            (SELECT CONCAT('New Event \"', e.title, '\" created') as text, e.created_at as created_at 
             FROM events e)
            UNION ALL
            (SELECT CONCAT('New ', f.rating, '-star feedback from ', IFNULL(u.name, 'User')) as text, f.created_at as created_at
             FROM feedback f LEFT JOIN users u ON f.user_id = u.id)
            ORDER BY created_at DESC LIMIT 6
        ";
    }

    $actResult = $conn->query($unionQuery);
    if ($actResult) {
        while ($row = $actResult->fetch_assoc()) {
            $operationsActivity[] = [
                "text" => $row['text'],
                "time" => time_elapsed_string($row['created_at'])
            ];
        }
    }
}
// Fallback if empty (new install)
if (empty($operationsActivity)) {
    $operationsActivity[] = ["text" => "System initialized", "time" => "Just now"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Meta Tags - Hidden from Search Engines (Security Best Practice) -->
  <title>EventEase Admin Dashboard - Internal Panel</title>
  <meta name="description" content="EventEase Admin Panel - Internal Management System">
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
  <meta name="googlebot" content="noindex, nofollow">
  
  <!-- Prevent Search Engine Indexing -->
  <meta name="robots" content="noindex">
  
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 30px rgba(90,44,160,0.05);
      margin-bottom: 30px;
      border: 1px solid rgba(90,44,160,0.05);
    }
    .board h4 {
      margin-bottom: 25px;
      color: var(--primary-dark);
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .empty-state {
      padding: 40px 20px;
      text-align: center;
      background: #faf9ff;
      border: 1px dashed #d1c4e9;
      border-radius: 16px;
    }
    .empty-state i {
      font-size: 48px;
      color: #b39ddb;
      margin-bottom: 15px;
      display: block;
    }
    .empty-state p {
      color: #7e57c2;
      margin: 0;
      font-weight: 500;
    }
    .table thead th {
      background: #f8f7ff;
      color: #5a2ca0;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 12px;
      letter-spacing: 0.5px;
      padding: 15px;
      border: none;
    }
    .table tbody td {
      padding: 15px;
      border-bottom: 1px solid #f0f0f0;
    }
    .status-badge {
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
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
  <a class="nav-link-custom active" href="index.php">Dashboard</a>
  <a class="nav-link-custom" href="pending_approval.php">Pending Approvals</a>
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
      <h2 class="mb-1">Event Ease Admin Dashboard</h2>
      <small class="text-muted">Track booking health, approvals and big events | Signed in as <strong><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></strong></small>
    </div>
    <div class="d-flex gap-2">
      <a href="add_event.php" class="btn btn-outline-secondary">+ New Event</a>
      <button class="btn btn-primary" style="background: var(--primary); border: none;">Generate Report</button>
    </div>
  </div>

  <section class="summary-grid">
    <div class="summary-card">
      <span>Total Users</span>
      <h3><?= number_format($totalUsers); ?></h3>
      <small>+12 new this week</small>
    </div>
    <div class="summary-card">
      <span>Total Bookings</span>
      <h3><?= number_format($totalBookings); ?></h3>
      <small>+4 in last 24h</small>
    </div>
    <div class="summary-card">
      <span>Pending Approval</span>
      <h3><?= number_format($pendingApproval); ?></h3>
      <small>Needs follow up</small>
    </div>
    <div class="summary-card">
      <span>Big Events</span>
      <h3><?= number_format($upcomingEvents); ?></h3>
      <small>Upcoming highlights</small>
    </div>
  </section>

  <section class="board">
    <h4><i class="fa-solid fa-clock-rotate-left"></i> Pending Bookings & Approvals</h4>
    <?php if (empty($pendingApprovalsData)): ?>
      <div class="empty-state">
        <i class="fa-solid fa-circle-check"></i>
        <p>All caught up! There are no bookings waiting for approval.</p>
        <small class="text-muted mt-2 d-block">Check back later for new registration requests.</small>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Client</th>
              <th>Requested Event</th>
              <th>Budget Amount</th>
              <th class="text-end">Action Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingApprovalsData as $row): ?>
              <tr>
                <td class="fw-bold text-dark"><?= htmlspecialchars($row['client']); ?></td>
                <td><span class="text-muted"><?= htmlspecialchars($row['event']); ?></span></td>
                <td><span class="fw-bold color-primary"><?= htmlspecialchars($row['budget']); ?></span></td>
                <td class="text-end">
                  <span class="status-badge bg-warning-subtle text-warning text-capitalize">
                    <i class="fa-solid fa-hourglass-half me-1"></i> <?= htmlspecialchars($row['status']); ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="text-center mt-3">
        <a href="pending_approval.php" class="btn btn-sm btn-link text-decoration-none">View All Pending Requests</a>
      </div>
    <?php endif; ?>
  </section>

  <section class="board">
    <h4>Operations Activity</h4>
    <ul class="activity-list">
      <?php foreach ($operationsActivity as $item): ?>
        <li>
          <span><?= htmlspecialchars($item['text']); ?></span>
          <small><?= htmlspecialchars($item['time']); ?></small>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</main>

</body>
</html>

