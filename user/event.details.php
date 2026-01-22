<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

function tableExists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $check = $conn->query("SHOW TABLES LIKE '$safe'");
    return $check && $check->num_rows > 0;
}

function getSampleEvents(): array {
    return [
        [
            'id' => 'sample-luxury-gala',
            'title' => 'Luxury Wedding Gala',
            'description' => 'Experience a royal themed wedding showcase with curated decor, gourmet dining and live music.',
            'category' => 'Wedding',
            'event_date' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Jaipur Palace Grounds',
            'price' => 150000,
            'image_path' => 'assets/images/placeholders/wedding.jpg',
            'status' => 'Active'
        ],
        [
            'id' => 'sample-corporate-townhall',
            'title' => 'Corporate Townhall Summit',
            'description' => 'A leadership summit covering FY achievements, market outlook and an immersive networking dinner.',
            'category' => 'Corporate',
            'event_date' => date('Y-m-d', strtotime('+18 days')),
            'location' => 'Mumbai Convention Center',
            'price' => 90000,
            'image_path' => 'assets/images/placeholders/corporate.jpg',
            'status' => 'Planning'
        ],
        [
            'id' => 'sample-bollywood-sangeet',
            'title' => 'Bollywood Sangeet Night',
            'description' => 'Dance the night away with celebrity choreographers, LED stage, and interactive performances.',
            'category' => 'Entertainment',
            'event_date' => date('Y-m-d', strtotime('+25 days')),
            'location' => 'Bangalore Signature Club',
            'price' => 65000,
            'image_path' => 'assets/images/placeholders/sangeet.jpg',
            'status' => 'Active'
        ]
    ];
}

$eventKey = trim($_GET['id'] ?? '');
$event    = null;
$addons   = [];
$eventLoadedFromDb = false;

if ($eventKey !== '' && ctype_digit($eventKey) && (int) $eventKey > 0 && tableExists($conn, 'events')) {
    $numericId = (int) $eventKey;
    $stmt = $conn->prepare("SELECT e.*, m.name AS manager_name, m.email AS manager_email, m.phone AS manager_phone,
                            COUNT(b.id) as booking_count
                            FROM events e 
                            LEFT JOIN managers m ON e.manager_id = m.id
                            LEFT JOIN bookings b ON e.id = b.event_id AND b.user_id = ? AND b.payment_status = 'paid'
                            WHERE e.id = ?
                            GROUP BY e.id");
    if ($stmt) {
        $currUserId = $_SESSION['user_id'] ?? 0;
        $stmt->bind_param("ii", $currUserId, $numericId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $event = $result->fetch_assoc();
            $eventLoadedFromDb = (bool) $event;
        }
        $stmt->close();
    }
}

if (!$event) {
    foreach (getSampleEvents() as $sample) {
        if ($eventKey !== '' && (string) $sample['id'] === $eventKey) {
            $event = $sample;
            break;
        }
    }
}

if (!$event) {
    $samples = getSampleEvents();
    if (!empty($samples)) {
        $event = $samples[0];
        $eventKey = (string) $event['id'];
    }
}

if ($eventLoadedFromDb && tableExists($conn, 'addons')) {
    $addonStmt = $conn->prepare("SELECT * FROM addons WHERE status = 'active' ORDER BY category ASC");
    if ($addonStmt) {
        if ($addonStmt->execute()) {
            $addonResult = $addonStmt->get_result();
            while ($row = $addonResult->fetch_assoc()) {
                $addons[] = $row;
            }
        }
        $addonStmt->close();
    }
}

$imagePath = !empty($event['image_path']) ? "../" . ltrim($event['image_path'], './') : "https://images.unsplash.com/photo-1519681393784-d120267933ba?auto=format&fit=crop&w=900&q=60";
$bookingParam = $eventKey !== '' ? $eventKey : (string) ($event['id'] ?? '');
$isBooked = $eventLoadedFromDb && (int)($event['booking_count'] ?? 0) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['title']); ?> - Event Details</title>
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
        * { box-sizing: border-box; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
        body { background: var(--bg); margin: 0; }
        .hero {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px;
            padding: 50px 20px 30px; align-items: center;
        }
        .hero img { width: 100%; border-radius: 24px; box-shadow: 0 20px 35px rgba(90,44,160,0.15); }
        .hero-content h1 { color: var(--primary-dark); font-size: 36px; margin-bottom: 10px; }
        .hero-content p { color: #5c5c5c; line-height: 1.6; }
        .detail-card {
            background: #fff; border-radius: 20px; padding: 25px; box-shadow: 0 12px 35px rgba(90,44,160,0.08);
            margin-bottom: 25px; border: 1px solid rgba(90,44,160,0.05);
        }
        .detail-card h4 { color: var(--primary-dark); margin-bottom: 18px; }
        .meta-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
        .meta-list li { display: flex; align-items: center; gap: 12px; color: #5c5c5c; }
        .meta-list i { width: 36px; height: 36px; border-radius: 12px; background: rgba(90,44,160,0.08); color: var(--primary); display: flex; align-items: center; justify-content: center; }
        .price-block { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }
        .price-tag { font-size: 32px; font-weight: 700; color: #18a558; }
        .btn-primary-custom {
            background: var(--primary); border: none; color: #fff; border-radius: 14px; padding: 12px 28px;
            font-weight: 600; text-decoration: none;
        }
        .btn-primary-custom:hover { background: var(--primary-dark); color: #fff; }
        .addon-card {
            border: 1px solid rgba(90,44,160,0.12); border-radius: 16px; padding: 18px; background: #fff;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
        }
        .addon-card h6 { margin-bottom: 4px; color: var(--primary-dark); }
        .badge-category { background: rgba(90,44,160,0.1); color: var(--primary); padding: 4px 10px; border-radius: 999px; font-size: 12px; }
        .manager-card {
            border-left: 4px solid var(--primary); padding-left: 15px; margin-top: 15px; color: #4b4b4b;
        }
    </style>
</head>
<body>

  <?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="container hero">
    <img src="<?= htmlspecialchars($imagePath); ?>" alt="<?= htmlspecialchars($event['title']); ?>">
    <div class="hero-content">
        <span class="badge-category mb-2"><?= htmlspecialchars(ucfirst($event['category'] ?? 'Event')); ?></span>
        <h1><?= htmlspecialchars($event['title']); ?></h1>
        <p><?= nl2br(htmlspecialchars($event['description'] ?? '')); ?></p>
        <div class="meta-list mt-3">
            <li><i class="fa-solid fa-calendar-days"></i> 
                Booking Available till: <?= !empty($event['event_date']) ? date('d F Y', strtotime($event['event_date'])) : 'Date to be announced'; ?>
            </li>
            <li><i class="fa-solid fa-clock"></i> <?= !empty($event['event_time']) ? date('h:i A', strtotime($event['event_time'])) : 'Time to be announced'; ?></li>
            <!-- <li><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($event['location'] ?? 'Venue to be confirmed'); ?></li> -->
        </div>
        <div class="price-block">
            <div>
                <div class="text-muted">Starting from</div>
                <div class="price-tag">₹<?= number_format((float) ($event['price'] ?? 0), 2); ?></div>
            </div>
            <?php if ($isBooked): ?>
                <button class="btn btn-secondary" disabled>
                    <i class="fa-solid fa-lock"></i> Already Booked
                </button>
            <?php else: ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a class="btn-primary-custom" href="booking.php?event_id=<?= htmlspecialchars($bookingParam); ?>">
                        <i class="fa-solid fa-ticket"></i> Book this event
                    </a>
                <?php else: ?>
                    <a class="btn-primary-custom" href="login.php?redirect=booking.php&event_id=<?= htmlspecialchars($bookingParam); ?>">
                        <i class="fa-solid fa-ticket"></i> Book this event
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="container mb-4">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="detail-card">
                <h4>About this experience</h4>
                <p><?= nl2br(htmlspecialchars($event['description'] ?? 'Details will be available soon.')); ?></p>

                <h5 class="mt-4">What’s included</h5>
                <ul class="meta-list">
                    <li><i class="fa-solid fa-couch"></i> Venue setup & ambience managed by Event Ease</li>
                    <li><i class="fa-solid fa-users"></i> Dedicated operations manager for the event day</li>
                    <li><i class="fa-solid fa-shield-heart"></i> Vendor verification & quality assurance</li>
                </ul>
            </div>

            <?php if (!empty($addons)): ?>
                <div class="detail-card">
                    <h4>Popular add-ons</h4>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($addons as $addon): ?>
                            <div class="addon-card">
                                <div>
                                    <h6><?= htmlspecialchars($addon['title']); ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($addon['description'] ?? ''); ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge-category"><?= htmlspecialchars($addon['category']); ?></span>
                                    <div class="fw-semibold mt-2">₹<?= number_format((float) ($addon['price'] ?? 0), 2); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-muted mt-3"><small>You can choose add-ons during the booking process.</small></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="detail-card">
                <h4>Event owner</h4>
                <?php if (!empty($event['manager_name'])): ?>
                    <div class="manager-card">
                        <strong><?= htmlspecialchars($event['manager_name']); ?></strong><br>
                        <small><?= htmlspecialchars($event['manager_email'] ?? ''); ?></small><br>
                        <small><?= htmlspecialchars($event['manager_phone'] ?? ''); ?></small>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Manager assignment in progress. Our admin team monitors every event.</p>
                <?php endif; ?>
            </div>

            <div class="detail-card">
                <h4>Need help?</h4>
                <p class="text-muted">Connect with Event Ease concierge for custom décor, artist bookings, travel or hospitality arrangements.</p>
                <a class="btn btn-outline-primary w-100" href="contact.php">
                    <i class="fa-solid fa-phone-volume"></i> Contact support
                </a>
            </div>
        </div>
    </div>
</section>

  <?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/layout.js"></script>
</body>
</html>

