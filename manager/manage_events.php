<?php
session_start();
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/auth_check.php';

// Handle Delete Event
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $eventId = (int) $_GET['id'];
    
    // Check if event belongs to this manager
    $checkStmt = $conn->prepare("SELECT id FROM events WHERE id = ? AND manager_id = ?");
    if ($checkStmt) {
        $checkStmt->bind_param("ii", $eventId, $managerId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            // Check if event has any bookings
            $bookingCheck = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE event_id = ?");
            if ($bookingCheck) {
                $bookingCheck->bind_param("i", $eventId);
                $bookingCheck->execute();
                $bookingResult = $bookingCheck->get_result();
                $bookingRow = $bookingResult->fetch_assoc();
                $bookingCheck->close();
                
                if ($bookingRow['count'] > 0) {
                    $_SESSION['msg_error'] = "Cannot delete event. It has " . $bookingRow['count'] . " booking(s). Please cancel bookings first.";
                } else {
                    // Delete event
                    $deleteStmt = $conn->prepare("DELETE FROM events WHERE id = ? AND manager_id = ?");
                    if ($deleteStmt) {
                        $deleteStmt->bind_param("ii", $eventId, $managerId);
                        if ($deleteStmt->execute()) {
                            $_SESSION['msg_success'] = "Event deleted successfully!";
                        } else {
                            $_SESSION['msg_error'] = "Error deleting event.";
                        }
                        $deleteStmt->close();
                    }
                }
            }
        } else {
            $_SESSION['msg_error'] = "Event not found or you don't have permission to delete it.";
        }
        $checkStmt->close();
    }
    header("Location: manage_events.php");
    exit();
}

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'], $_POST['status'])) {
    $eventId = (int) $_POST['event_id'];
    $newStatus = trim($_POST['status']);
    
    $updateStmt = $conn->prepare("UPDATE events SET status = ? WHERE id = ? AND manager_id = ?");
    if ($updateStmt) {
        $updateStmt->bind_param("sii", $newStatus, $eventId, $managerId);
        if ($updateStmt->execute()) {
            $_SESSION['msg_success'] = "Status updated successfully!";
        } else {
            $_SESSION['msg_error'] = "Error updating status.";
        }
        $updateStmt->close();
    }
    header("Location: manage_events.php");
    exit();
}

// Get flash messages
$message = "";
$msgType = "";
if (isset($_SESSION['msg_success'])) {
    $message = $_SESSION['msg_success'];
    $msgType = "success";
    unset($_SESSION['msg_success']);
} elseif (isset($_SESSION['msg_error'])) {
    $message = $_SESSION['msg_error'];
    $msgType = "danger";
    unset($_SESSION['msg_error']);
}

// Initialize stats
$stats = [
    'total' => 0,
    'active' => 0,
    'planning' => 0,
    'completed' => 0,
    'cancelled' => 0
];

// Get events for this manager with booking count
$events = [];
$query = "SELECT e.id, e.title, e.category, e.event_date, e.location, e.price, e.status, e.image_path, e.created_at,
          COUNT(b.id) as booking_count
          FROM events e
          LEFT JOIN bookings b ON e.id = b.event_id
          WHERE e.manager_id = ?
          GROUP BY e.id
          ORDER BY e.created_at DESC";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
        
        // Count by status (Case-insensitive check)
        $status = ucfirst(strtolower(trim($row['status'] ?? 'Planning')));
        
        $stats['total']++;
        
        if ($status === 'Active') $stats['active']++;
        elseif ($status === 'Planning') $stats['planning']++;
        elseif ($status === 'Completed') $stats['completed']++;
        elseif ($status === 'Cancelled') $stats['cancelled']++;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Manager Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
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
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header-card {
            background: #fff;
            padding: 20px;
            border-radius: 14px;
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 6px 18px rgba(27,20,58,0.06);
        }
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 14px;
            border: 1px solid rgba(0,0,0,0.06);
            box-shadow: 0 6px 18px rgba(27,20,58,0.04);
        }
        .stat-card small {
            display: block;
            text-transform: uppercase;
            font-size: 12px;
            color: #8a8a8a;
            letter-spacing: 1px;
        }
        .stat-card h3 {
            font-size: 2.2rem;
            font-weight: 600;
            margin: 6px 0 0;
            color: var(--primary-dark);
        }
        .table-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 6px 18px rgba(27,20,58,0.05);
        }
        .event-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
        }
        .status-select {
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.9rem;
            border: 1px solid #dee2e6;
            cursor: pointer;
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
    <a class="nav-link-custom active" href="manage_events.php">Manage Events</a>
    <a class="nav-link-custom" href="manage_booking.php">Booking Hub</a>
    <a class="nav-link-custom" href="manage_addOns.php">Add-on & Pickup</a>
    <a class="nav-link-custom" href="manage_gallary.php">Gallery Uploads</a>
    <a class="nav-link-custom" href="manage_payments.php">Payment History</a>
    <a class="nav-link-custom" href="manage_feedback.php">Event Ratings</a>
    <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

<main class="main-content">
    <div class="container">
        <!-- Header -->
        <div class="header-card d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1" style="color: var(--primary);">Manage Assigned Events</h2>
            <small class="text-muted">Manage your events | Signed in as <strong><?= htmlspecialchars($managerName); ?></strong></small>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_events.php" class="btn btn-outline-primary">
                <i class="fa-solid fa-rotate-right"></i> Refresh
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msgType; ?> alert-dismissible fade show">
            <?= $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <small>Total Events</small>
                <h3><?= $stats['total']; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <small>Active</small>
                <h3><?= $stats['active']; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <small>Planning</small>
                <h3><?= $stats['planning']; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <small>Completed</small>
                <h3><?= $stats['completed']; ?></h3>
            </div>
        </div>
    </div>

    <!-- Events Table -->
    <div class="table-card">
        <?php if (empty($events)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-calendar-xmark fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No Events Assigned</h5>
                <p class="text-muted">Contact admin to assign events to you.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Event Details</th>
                            <th>Category</th>
                            <th>Date & Location</th>
                            <th>Price</th>
                            <th>Current Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if (!empty($event['image_path'])): ?>
                                            <img src="../<?= htmlspecialchars($event['image_path']); ?>" class="event-img" alt="Event">
                                        <?php else: ?>
                                            <div class="event-img bg-light d-flex align-items-center justify-content-center">
                                                <i class="fa-solid fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($event['title']); ?></div>
                                            <small class="text-muted">ID: #<?= $event['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($event['category']); ?></span></td>
                                <td>
                                    <div><i class="fa-regular fa-calendar me-1"></i> <?= date('d M Y', strtotime($event['event_date'])); ?></div>
                                    <small class="text-muted"><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($event['location']); ?></small>
                                </td>
                                <td>₹<?= number_format($event['price']); ?></td>
                                <td>
                                    <?php 
                                        $status = ucfirst(strtolower($event['status']));
                                        $badgeClass = 'bg-secondary';
                                        if ($status == 'Active') $badgeClass = 'bg-success';
                                        if ($status == 'Planning') $badgeClass = 'bg-warning text-dark';
                                        if ($status == 'Cancelled') $badgeClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $badgeClass; ?>"><?= $status; ?></span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2 align-items-center">
                                        <a href="edit_event.php?id=<?= $event['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Details">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <form method="POST" class="d-flex gap-2" onsubmit="return confirm('Are you sure you want to update status?');">
                                            <input type="hidden" name="event_id" value="<?= $event['id']; ?>">
                                            <select name="status" class="form-select form-select-sm" style="width: 130px;">
                                                <option value="Planning" <?= $status == 'Planning' ? 'selected' : ''; ?>>Planning</option>
                                                <option value="Active" <?= $status == 'Active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="Completed" <?= $status == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="Cancelled" <?= $status == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                        </form>
                                        <?php if ((int)($event['booking_count'] ?? 0) == 0): ?>
                                            <a href="manage_events.php?delete=1&id=<?= $event['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               title="Delete Event"
                                               onclick="return confirm('Are you sure you want to delete this event? This action cannot be undone.');">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
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
