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

// Handle delete action
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $deleteId = (int) $_GET['id'];
    if (tableExists($conn, 'events')) {
        $conn->query("DELETE FROM events WHERE id = $deleteId");
        header("Location: manage_events.php?deleted=1");
        exit();
    }
}

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

$events = [];
if (tableExists($conn, 'events')) {
    $query = "SELECT e.*, 
                     m.name AS manager_name
              FROM events e
              LEFT JOIN managers m ON e.manager_id = m.id
              WHERE 1=1";
    
    if ($search) {
        $searchSafe = $conn->real_escape_string($search);
        $query .= " AND (e.title LIKE '%$searchSafe%' OR e.location LIKE '%$searchSafe%')";
    }
    if ($categoryFilter) {
        $catSafe = $conn->real_escape_string($categoryFilter);
        $query .= " AND e.category = '$catSafe'";
    }
    if ($statusFilter) {
        $statusSafe = $conn->real_escape_string($statusFilter);
        $query .= " AND e.status = '$statusSafe'";
    }
    
    $query .= " ORDER BY e.event_date DESC, e.created_at DESC";
    
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
}

// Get unique categories for filter
$categories = [];
if (tableExists($conn, 'events')) {
    $catResult = $conn->query("SELECT DISTINCT category FROM events WHERE category IS NOT NULL AND category != ''");
    if ($catResult) {
        while ($row = $catResult->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Admin Panel</title>
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
        .btn-danger {
            background: #dc3545;
            border: none;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(90,44,160,0.25);
        }
        .event-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
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
    <a class="nav-link-custom active" href="manage_events.php">Manage Events</a>
    <a class="nav-link-custom" href="payment.php">Payment Detail</a>
    <a class="nav-link-custom" href="AddOns.php">Add Ons</a>
    <a class="nav-link-custom" href="gallery.php">Gallery Content</a>
    <a class="nav-link-custom" href="feedback.php">Event Ratings</a>
    <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

<main class="main-content">
    <div class="page-header">
        <div>
            <h2 class="mb-1">Manage Events</h2>
            <p class="text-muted mb-0">View, edit, and delete all events</p>
        </div>
        <a href="add_event.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Event
        </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ✅ Event deleted successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="filter-section">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Search by title or location..." 
                       value="<?= htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat); ?>" 
                                <?= $categoryFilter === $cat ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="Planning" <?= $statusFilter === 'Planning' ? 'selected' : ''; ?>>Planning</option>
                    <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
        <?php if (empty($events)): ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No events found</h5>
                <p class="text-muted">Start by adding your first event!</p>
                <a href="add_event.php" class="btn btn-primary mt-3">
                    <i class="fas fa-plus"></i> Add Event
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Event Title</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Price</th>
                            <th>Manager</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($event['image_path'])): ?>
                                        <img src="../<?= htmlspecialchars($event['image_path']); ?>" 
                                             alt="Event" class="event-image">
                                    <?php else: ?>
                                        <div class="event-image bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($event['title']); ?></strong>
                                    <?php if (!empty($event['description'])): ?>
                                        <br><small class="text-muted">
                                            <?= htmlspecialchars(substr($event['description'], 0, 50)); ?>...
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= htmlspecialchars($event['category'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($event['event_date'])): ?>
                                        <?= date('M d, Y', strtotime($event['event_date'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $locs = array_filter(array_map('trim', explode('|', $event['available_locations'] ?? '')));
                                    if (count($locs) > 1) {
                                        echo htmlspecialchars($locs[0]) . ' <span class="badge bg-light text-dark">+' . (count($locs) - 1) . ' more</span>';
                                    } else {
                                        echo htmlspecialchars($event['location'] ?? 'N/A');
                                    }
                                    ?>
                                </td>
                                <td>
                                    <strong>₹<?= number_format($event['price'] ?? 0, 2); ?></strong>
                                </td>
                                <td>
                                    <?php if (!empty($event['manager_name'])): ?>
                                        <?= htmlspecialchars($event['manager_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status = $event['status'] ?? 'Planning';
                                    $statusClass = match($status) {
                                        'Active' => 'bg-success',
                                        'Completed' => 'bg-secondary',
                                        'Cancelled' => 'bg-danger',
                                        default => 'bg-warning'
                                    };
                                    ?>
                                    <span class="badge badge-custom <?= $statusClass; ?>">
                                        <?= htmlspecialchars($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="edit_event.php?id=<?= $event['id']; ?>" 
                                           class="btn btn-sm btn-primary btn-sm-custom" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=1&id=<?= $event['id']; ?>" 
                                           class="btn btn-sm btn-danger btn-sm-custom" 
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this event?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3 text-muted">
                <small>Total Events: <strong><?= count($events); ?></strong></small>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

