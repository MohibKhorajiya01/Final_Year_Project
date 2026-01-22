<?php

session_start();
require_once __DIR__ . '/../backend/config.php';

function tableExists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $check = $conn->query("SHOW TABLES LIKE '$safe'");
    return $check && $check->num_rows > 0;
}



$events = [];
$categories = [];

$searchTerm   = trim($_GET['search'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$dateRange    = trim($_GET['date_range'] ?? '');
$sortBy       = trim($_GET['sort'] ?? 'upcoming');

if (tableExists($conn, 'events')) {
    $catResult = $conn->query("SELECT DISTINCT category FROM events WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
    if ($catResult) {
        while ($row = $catResult->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }

    $query  = "SELECT e.id, e.title, e.description, e.category, e.event_date, e.location, e.price, e.image_path, e.status,
               COUNT(b.id) as booking_count
               FROM events e
               LEFT JOIN bookings b ON e.id = b.event_id
                WHERE (e.status IS NULL OR e.status NOT IN ('Cancelled', 'Inactive', 'cancelled', 'inactive'))
               AND (e.event_date >= CURDATE())
               GROUP BY e.id";
    $types  = '';
    $params = [];

    if ($searchTerm !== '') {
        $query .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ?)";
        $like = '%' . $searchTerm . '%';
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($categorySlug !== '') {
        $query .= " AND category = ?";
        $types .= 's';
        $params[] = $categorySlug;
    }

    if ($dateRange === 'today') {
        $query .= " AND DATE(event_date) = CURDATE()";
    } elseif ($dateRange === 'week') {
        $query .= " AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($dateRange === 'month') {
        $query .= " AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    }

    switch ($sortBy) {
        case 'price_low':
            $query .= " ORDER BY price ASC";
            break;
        case 'price_high':
            $query .= " ORDER BY price DESC";
            break;
        case 'recent':
            $query .= " ORDER BY created_at DESC";
            break;
        default:
            $query .= " ORDER BY event_date ASC";
    }

    $stmt = $conn->prepare($query);
    if ($stmt) {
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
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
    <title>Event Ease - Browse Events</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout.css?v=2">
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
            background: var(--bg);
            margin: 0;
            padding-top: 90px;
        }
        .page-hero {
            background: linear-gradient(135deg, rgba(90,44,160,0.95), rgba(67,31,117,0.9)), url('https://images.unsplash.com/photo-1519677100203-a0e668c92439?auto=format&fit=crop&w=1500&q=80') center/cover;
            color: #fff;
            padding: 80px 20px 60px;
            text-align: center;
        }
        .page-hero h1 {
            font-size: 42px;
            margin-bottom: 10px;
        }
        .filter-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(90,44,160,0.08);
            padding: 25px;
            margin-top: -50px;
            position: relative;
            z-index: 10;
        }
        .filter-card label {
            font-size: 14px;
            color: #6c6c6c;
        }
        .form-control,
        .form-select {
            border-radius: 12px;
            border-color: rgba(90,44,160,0.2);
        }
        .form-control:focus,
        .form-select:focus {
            box-shadow: 0 0 0 0.2rem rgba(90,44,160,0.15);
            border-color: var(--primary);
        }
        .events-wrapper {
            padding: 40px 20px 60px;
        }
        .event-card {
            display: flex;
            flex-direction: column;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 12px 35px rgba(90,44,160,0.08);
            overflow: hidden;
            border: 1px solid rgba(90,44,160,0.05);
            height: 100%;
        }
        .event-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
        }
        .event-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex-grow: 1;
        }
        .event-body h5 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 20px;
        }
        .event-body p {
            margin: 0;
            color: #6c6c6c;
            font-size: 15px;
        }
        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 14px;
            color: #5a2ca0;
        }
        .event-meta i {
            margin-right: 6px;
        }
        .price-tag {
            font-weight: 700;
            color: #18a558;
            font-size: 18px;
        }
        .card-actions {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .btn-register {
            background: var(--primary);
            border: none;
            color: #fff;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            text-decoration: none;
        }
        .btn-register:hover {
            background: var(--primary-dark);
            color: #fff;
        }
        .status-pill {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-planning { background: rgba(255, 165, 0, 0.15); color: #d97706; }
        .status-active { background: rgba(34,197,94,0.15); color: #15803d; }
        @media (max-width: 992px) {
            .event-card {
                margin-bottom: 25px;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<header class="page-hero">
    <h1>Discover curated experiences</h1>
    <p>Browse events crafted by Event Ease. Pick your favourite, add services, and book in a few clicks.</p>
</header>

<section class="container filter-card">
    <form class="row g-3 align-items-end" method="GET">
        <div class="col-md-6 col-lg-5">
            <label class="form-label">Search events</label>
            <input type="text" name="search" value="<?= htmlspecialchars($searchTerm); ?>" class="form-control" placeholder="Search by title, city or keyword">
        </div>
        <div class="col-md-4 col-lg-4">
            <label class="form-label">Category</label>
            <select name="category" class="form-select">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category); ?>" <?= $category === $categorySlug ? 'selected' : ''; ?>>
                        <?= ucfirst(htmlspecialchars($category)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 col-lg-3 text-end">
            <button type="submit" class="btn btn-register w-100 mt-3 mt-md-0">Search</button>
        </div>
    </form>
</section>

<section class="events-wrapper container">
    <?php if (empty($events)): ?>
        <div class="text-center py-5">
            <i class="fa-solid fa-calendar-xmark fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No events found</h5>
            <p class="text-muted">Try adjusting your filters or check back soon for new launches.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($events as $event): ?>
                <?php
                    $imagePath = !empty($event['image_path']) ? "../" . ltrim($event['image_path'], './') : "https://images.unsplash.com/photo-1489515217757-5fd1be406fef?auto=format&fit=crop&w=800&q=60";
                    $status    = strtolower($event['status'] ?? 'planning');
                    $statusClass = $status === 'active' ? 'status-active' : 'status-planning';
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="event-card">
                        <img src="<?= htmlspecialchars($imagePath); ?>" alt="<?= htmlspecialchars($event['title']); ?>">
                        <div class="event-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-light text-dark"><?= htmlspecialchars(ucfirst($event['category'] ?? 'Event')); ?></span>
                                <span class="status-pill <?= $statusClass; ?>"><?= ucfirst($event['status'] ?? 'Planning'); ?></span>
                            </div>
                            <h5><?= htmlspecialchars($event['title']); ?></h5>
                            <p><?= htmlspecialchars(mb_strimwidth($event['description'] ?? '', 0, 140, '...')); ?></p>
                            <div class="event-meta">
                                <span><i class="fa-solid fa-calendar-days"></i>
                                    Booking till <?= !empty($event['event_date']) ? date('d M Y', strtotime($event['event_date'])) : 'TBA'; ?>
                                </span>
                            </div>
                            <div class="card-actions">
                                <div>
                                    <div class="price-tag">₹<?= number_format((float) ($event['price'] ?? 0), 2); ?></div>
                                    <small class="text-muted">per booking</small>
                                </div>
                                <div class="d-flex flex-column gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="event.details.php?id=<?= htmlspecialchars((string) $event['id']); ?>">Details</a>
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <a class="btn btn-register btn-sm text-center" href="booking.php?event_id=<?= htmlspecialchars((string) $event['id']); ?>">Register</a>
                                    <?php else: ?>
                                        <a class="btn btn-register btn-sm text-center" href="login.php?redirect=booking.php&event_id=<?= htmlspecialchars((string) $event['id']); ?>">Register</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/layout.js?v=2"></script>
</body>
</html>

