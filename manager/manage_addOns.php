<?php
session_start();
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/auth_check.php';

function tableExists(mysqli $conn, string $tableName): bool {
    $safe = $conn->real_escape_string($tableName);
    $check = $conn->query("SHOW TABLES LIKE '$safe'");
    return $check && $check->num_rows > 0;
}

$addonsTableExists = tableExists($conn, 'addons');
$statusMessage = '';
$statusType = 'success';
$statusOptions = ['active', 'inactive'];

if ($addonsTableExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addon_id'])) {
    $addonId = (int) $_POST['addon_id'];
    $newStatus = strtolower(trim($_POST['status'] ?? 'active'));
    $note = trim($_POST['note'] ?? '');

    if (!in_array($newStatus, $statusOptions, true)) {
        $statusMessage = "Invalid status selection.";
        $statusType = 'danger';
    } else {
        $stmt = $conn->prepare("UPDATE addons SET status = ?, description = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssi", $newStatus, $note, $addonId);
            if ($stmt->execute()) {
                $statusMessage = "Add-on updated successfully.";
            } else {
                $statusMessage = "Failed to update add-on.";
                $statusType = 'warning';
            }
            $stmt->close();
        } else {
            $statusMessage = "Unable to prepare update statement.";
            $statusType = 'danger';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$addons = [];
$categories = [];

if ($addonsTableExists) {
    $categoriesResult = $conn->query("SELECT DISTINCT category FROM addons WHERE category IS NOT NULL AND category != '' ORDER BY category");
    if ($categoriesResult) {
        while ($row = $categoriesResult->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }
    $query = "SELECT * FROM addons WHERE 1=1";
    $params = [];
    $types = "";
    if ($search !== '') {
        $query .= " AND (title LIKE CONCAT('%', ?, '%') OR type LIKE CONCAT('%', ?, '%'))";
        $params[] = $search;
        $params[] = $search;
        $types .= "ss";
    }
    if ($categoryFilter !== '') {
        $query .= " AND category = ?";
        $params[] = $categoryFilter;
        $types .= "s";
    }
    if ($statusFilter !== '' && in_array($statusFilter, $statusOptions, true)) {
        $query .= " AND status = ?";
        $params[] = $statusFilter;
        $types .= "s";
    }
    $query .= " ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $addons[] = $row;
            }
        }
        $stmt->close();
    }
} else {
    $categories = ["Food", "Decor", "Entertainment"];
    $addons = [
        ["id" => 1, "title" => "Premium Buffet", "category" => "Food", "type" => "Catering", "price" => 55000, "status" => "active", "description" => "Multi-cuisine menu for 300 guests.", "created_at" => date('Y-m-d H:i:s', strtotime('-2 days'))],
        ["id" => 2, "title" => "Royal Stage Decor", "category" => "Decor", "type" => "Decoration", "price" => 42000, "status" => "active", "description" => "Gold and floral stage theme.", "created_at" => date('Y-m-d H:i:s', strtotime('-5 days'))],
        ["id" => 3, "title" => "DJ + Percussion", "category" => "Entertainment", "type" => "Music", "price" => 28000, "status" => "inactive", "description" => "3-hour live music combo.", "created_at" => date('Y-m-d H:i:s', strtotime('-8 days'))],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager · Add-on Requests</title>
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
        .board {
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 15px 35px rgba(90,44,160,0.07);
            margin-bottom: 24px;
        }
        .badge-status {
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            text-transform: capitalize;
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
    <a class="nav-link-custom" href="manage_booking.php">Booking Hub</a>
    <a class="nav-link-custom active" href="manage_addOns.php">Add-on & Pickup</a>
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
            <h2 class="mb-0">Add-on Configuration</h2>
            <small class="text-muted">Add-on Configuration | Signed in as <strong><?= htmlspecialchars($managerName); ?></strong></small>
        </div>
    </div>

    <?php if (!$addonsTableExists): ?>
        <div class="alert alert-warning">Add-on catalog is not created yet. Please ask Admin to set up master add-ons.</div>
    <?php endif; ?>

    <?php if ($statusMessage): ?>
        <div class="alert alert-<?= htmlspecialchars($statusType); ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($statusMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="board">
        <h5 class="mb-3">Filter Add-ons</h5>
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search by title or type" value="<?= htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Category</label>
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat); ?>" <?= $categoryFilter === $cat ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($statusOptions as $opt): ?>
                        <option value="<?= $opt; ?>" <?= $statusFilter === $opt ? 'selected' : ''; ?>><?= ucfirst($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>
        </form>
    </div>

    <div class="board">
        <?php if (empty($addons)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-box-open fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No add-ons available yet.</h5>
                <p class="text-muted mb-0">When Admin publishes add-ons, they will appear here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Type</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($addons as $addon): ?>
                            <?php
                                $status = strtolower($addon['status'] ?? 'inactive');
                                $badgeClass = $status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary';
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($addon['title']); ?></strong></td>
                                <td><?= htmlspecialchars($addon['category'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($addon['type'] ?? ''); ?></td>
                                <td>₹<?= number_format((float) ($addon['price'] ?? 0), 2); ?></td>
                                <td><span class="badge-status <?= $badgeClass; ?>"><?= ucfirst($status); ?></span></td>
                                <td><small class="text-muted"><?= htmlspecialchars($addon['description'] ?? ''); ?></small></td>
                                <td>
                                    <?php if ($addonsTableExists): ?>
                                        <form method="POST" class="row g-2">
                                            <input type="hidden" name="addon_id" value="<?= (int) $addon['id']; ?>">
                                            <div class="col-6">
                                                <select name="status" class="form-select form-select-sm">
                                                    <?php foreach ($statusOptions as $opt): ?>
                                                        <option value="<?= $opt; ?>" <?= $status === $opt ? 'selected' : ''; ?>>
                                                            <?= ucfirst($opt); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <textarea name="note" rows="2" class="form-control" placeholder="Internal note..."><?= htmlspecialchars($addon['description'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" class="btn btn-sm btn-primary w-100">Save</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">Demo data</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-muted">
                <small>Total Add-ons: <strong><?= count($addons); ?></strong></small>
            </div>
        <?php endif; ?>
    </div>
</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

