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

function ensureGalleryTable(mysqli $conn): void {
    $table = 'gallery_items';
    if (!tableExists($conn, $table)) {
        $conn->query("CREATE TABLE IF NOT EXISTS $table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            description TEXT NULL,
            tags VARCHAR(255) NULL,
            event_id INT NULL,
            shoot_date DATE NULL,
            image_path VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'published',
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    $columnsToEnsure = [
        'updated_at' => "ALTER TABLE $table ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at",
        'status' => "ALTER TABLE $table ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'published' AFTER image_path",
        'is_featured' => "ALTER TABLE $table ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER status"
    ];

    foreach ($columnsToEnsure as $column => $alterSql) {
        $columnSafe = $conn->real_escape_string($column);
        $columnCheck = $conn->query("SHOW COLUMNS FROM $table LIKE '$columnSafe'");
        if ($columnCheck && $columnCheck->num_rows === 0) {
            $conn->query($alterSql);
        }
    }
}

function fetchScalar(mysqli $conn, string $sql, $default = 0) {
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_row()) {
        return $row[0] ?? $default;
    }
    return $default;
}

ensureGalleryTable($conn);

$statuses = ['published', 'draft', 'archived'];
$successMessage = "";
$errorMessage = "";
$uploadDir = __DIR__ . '/../uploads/gallery/';
$relativeUploadPath = 'uploads/gallery/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$hasEvents = tableExists($conn, 'events');
$eventsList = [];
if ($hasEvents) {
    $resultEvents = $conn->query("SELECT id, title FROM events ORDER BY title ASC");
    if ($resultEvents) {
        while ($row = $resultEvents->fetch_assoc()) {
            $eventsList[] = $row;
        }
    }
}

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $galleryId = (int) $_GET['id'];
    if ($galleryId > 0) {
        $imageResult = $conn->query("SELECT image_path FROM gallery_items WHERE id = $galleryId");
        if ($imageResult && $imageRow = $imageResult->fetch_assoc()) {
            $filePath = __DIR__ . '/../' . $imageRow['image_path'];
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
        $conn->query("DELETE FROM gallery_items WHERE id = $galleryId");
        $successMessage = "Gallery item deleted.";
    }
}

if (isset($_GET['toggle_status'], $_GET['id'])) {
    $galleryId = (int) $_GET['id'];
    $nextStatus = trim($_GET['toggle_status']);
    if ($galleryId > 0 && in_array($nextStatus, $statuses, true)) {
        $stmt = $conn->prepare("UPDATE gallery_items SET status = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $nextStatus, $galleryId);
            $stmt->execute();
            $stmt->close();
            $successMessage = "Gallery status updated.";
        }
    }
}

if (isset($_GET['toggle_feature'], $_GET['id'])) {
    $galleryId = (int) $_GET['id'];
    if ($galleryId > 0) {
        $conn->query("UPDATE gallery_items SET is_featured = 1 - is_featured, updated_at = NOW() WHERE id = $galleryId");
        $successMessage = "Gallery highlight toggled.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_gallery') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $status = trim($_POST['status'] ?? 'published');
    $shootDate = trim($_POST['shoot_date'] ?? '');
    $eventId = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int) $_POST['event_id'] : null;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

    if ($title === '') {
        $errorMessage = "Title is required.";
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = "Image upload is required.";
    } elseif (!in_array($status, $statuses, true)) {
        $errorMessage = "Invalid status selected.";
    } else {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            $errorMessage = "Only JPG, PNG, and WEBP images are allowed.";
        } else {
            $newFilename = uniqid('gallery_', true) . '.' . $extension;
            $targetPath = $uploadDir . $newFilename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $storedPath = $relativeUploadPath . $newFilename;
                $stmt = $conn->prepare("INSERT INTO gallery_items (title, description, tags, event_id, shoot_date, image_path, status, is_featured, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $adminId = $_SESSION['admin_id'];
                    $shootDateValue = ($shootDate !== '') ? $shootDate : NULL;
$stmt->bind_param(
    "sssisssii",
    $title,
    $description,
    $tags,
    $eventId,
    $shootDateValue,
    $storedPath,
    $status,
    $isFeatured,
    $adminId
);
                    if ($stmt->execute()) {
                        $successMessage = "Gallery item added successfully.";
                    } else {
                        $errorMessage = "Failed to save gallery item.";
                        @unlink($targetPath);
                    }
                    $stmt->close();
                } else {
                    $errorMessage = "Unable to prepare insert statement.";
                }
            } else {
                $errorMessage = "Failed to upload image.";
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$eventFilter = trim($_GET['event_id'] ?? '');
$featuredFilter = trim($_GET['featured'] ?? '');

$galleryItems = [];
$query = "SELECT g.*, ";
if ($hasEvents) {
    $query .= "e.title AS event_title, ";
}
$query .= "DATE_FORMAT(g.created_at, '%d %b %Y') AS created_label FROM gallery_items g";
if ($hasEvents) {
    $query .= " LEFT JOIN events e ON g.event_id = e.id";
}
$query .= " WHERE 1=1";

if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $query .= " AND (g.title LIKE '%$safe%' OR g.description LIKE '%$safe%' OR g.tags LIKE '%$safe%')";
}

if ($statusFilter !== '' && in_array($statusFilter, $statuses, true)) {
    $safeStatus = $conn->real_escape_string($statusFilter);
    $query .= " AND g.status = '$safeStatus'";
}

if ($eventFilter !== '' && $hasEvents) {
    $eventIdFilter = (int) $eventFilter;
    $query .= " AND g.event_id = $eventIdFilter";
}

if ($featuredFilter === 'featured') {
    $query .= " AND g.is_featured = 1";
} elseif ($featuredFilter === 'standard') {
    $query .= " AND g.is_featured = 0";
}

$query .= " ORDER BY g.created_at DESC";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $galleryItems[] = $row;
    }
}

$totalImages = fetchScalar($conn, "SELECT COUNT(*) FROM gallery_items");
$publishedImages = fetchScalar($conn, "SELECT COUNT(*) FROM gallery_items WHERE status = 'published'");
$featuredImages = fetchScalar($conn, "SELECT COUNT(*) FROM gallery_items WHERE is_featured = 1");
$latestUpload = fetchScalar($conn, "SELECT DATE_FORMAT(created_at, '%d %b %Y %h:%i %p') FROM gallery_items ORDER BY created_at DESC LIMIT 1", "—");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Manager - Admin Panel</title>
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px;
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 8px 18px rgba(0,0,0,0.04);
        }
        .stat-card span {
            display: block;
            font-size: 13px;
            color: #7a7a7a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .stat-card h3 {
            margin: 0;
            font-size: 30px;
            color: #2f2f2f;
        }
        .card-section {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 8px 20px rgba(0,0,0,0.04);
            margin-bottom: 25px;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }
        .gallery-card {
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 15px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
        }
        .gallery-card .image-container {
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .gallery-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
            min-width: 100%;
            min-height: 100%;
        }
        .badge-status {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
        }
        }
        .form-label span {
            color: #dc3545 !important; /* Red star as requested */
            font-weight: 700;
        }
        .required-star {
            color: #dc3545 !important;
            font-weight: 700;
        }
        .btn-primary {
            background: var(--primary);
            border: none;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        @media (max-width: 992px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
        .validation-error {
            display: none;
            color: #dc3545;
            font-size: 13px;
            margin-top: 6px;
            padding: 8px 12px;
            background: #fff5f5;
            border: 1px solid #ffcdd2;
            border-radius: 6px;
            animation: errorFadeIn 0.3s ease;
        }
        input.is-invalid, select.is-invalid, textarea.is-invalid {
            border-color: #dc3545 !important;
            background-color: #fff8f8 !important;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.15) !important;
        }
        input.is-valid, select.is-valid, textarea.is-valid {
            border-color: #28a745 !important;
        }
        @keyframes errorFadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
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
    <a class="nav-link-custom" href="payment.php">Payment Detail</a>
    <a class="nav-link-custom" href="AddOns.php">Add Ons</a>
    <a class="nav-link-custom active" href="gallery.php">Gallery Content</a>
    <a class="nav-link-custom" href="feedback.php">Event Ratings</a>
    <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

<main class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Gallery Content</h2>
            <p class="text-muted mb-0">Showcase event highlights, featured shots and behind-the-scenes.</p>
        </div>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
             <?= htmlspecialchars($successMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
             <?= htmlspecialchars($errorMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <section class="stats-grid">
        <div class="stat-card">
            <span>Total Images</span>
            <h3><?= number_format($totalImages); ?></h3>
        </div>
        <div class="stat-card">
            <span>Published</span>
            <h3><?= number_format($publishedImages); ?></h3>
        </div>
        <div class="stat-card">
            <span>Last Upload</span>
            <h3><?= $latestUpload ?: '—'; ?></h3>
        </div>
    </section>

    <section class="card-section mb-4">
        <h4 class="mb-3">Upload New Highlight</h4>
        <form method="POST" enctype="multipart/form-data" class="row g-3" novalidate>
            <input type="hidden" name="action" value="add_gallery">
            <div class="col-md-4">
                <label class="form-label">Title <span class="required-star">*</span></label>
                <input type="text" name="title" class="form-control" required placeholder="Sunset Sangeet, Corporate Gala...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Link Event</label>
                <select name="event_id" class="form-select">
                    <option value="">None</option>
                    <?php foreach ($eventsList as $event): ?>
                        <option value="<?= $event['id']; ?>"><?= htmlspecialchars($event['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Status <span class="required-star">*</span></label>
                <select name="status" class="form-select" required>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= $status; ?>"><?= ucfirst($status); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Description <span class="required-star">*</span></label>
                <textarea name="description" class="form-control" rows="2" placeholder="Add short story or highlight about this image." required></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Image <span class="required-star">*</span></label>
                <input type="file" name="image" class="form-control" accept="image/*" required>
            </div>

            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-cloud-upload-alt"></i> Save Item
                </button>
            </div>
        </form>
    </section>

    <section class="card-section">
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Title, description, tags..." value="<?= htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= $status; ?>" <?= $statusFilter === $status ? 'selected' : ''; ?>><?= ucfirst($status); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($hasEvents): ?>
            <div class="col-md-2">
                <label class="form-label">Event</label>
                <select name="event_id" class="form-select">
                    <option value="">All events</option>
                    <?php foreach ($eventsList as $event): ?>
                        <option value="<?= $event['id']; ?>" <?= (string) $eventFilter === (string) $event['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($event['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
        </form>

        <?php if (empty($galleryItems)): ?>
            <div class="text-center py-5">
                <i class="fas fa-images fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No gallery items yet</h5>
                <p class="text-muted mb-0">Upload an image to start showcasing your work.</p>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($galleryItems as $item): ?>
                    <?php
                        $statusClass = match ($item['status']) {
                            'draft' => 'bg-warning text-dark',
                            'archived' => 'bg-secondary',
                            default => 'bg-success'
                        };
                        $imageSrc = "../" . htmlspecialchars($item['image_path']);
                    ?>
                    <div class="gallery-card">
                        <div class="image-container">
                            <img src="<?= $imageSrc; ?>" alt="<?= htmlspecialchars($item['title']); ?>" onerror="this.src='https://via.placeholder.com/400x200?text=Image+Not+Found'">
                        </div>
                        <div class="p-3 flex-grow-1 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge badge-status <?= $statusClass; ?>"><?= ucfirst($item['status']); ?></span>
                                <?php if ((int) $item['is_featured'] === 1): ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-star"></i> Featured</span>
                                <?php endif; ?>
                            </div>
                            <h5><?= htmlspecialchars($item['title']); ?></h5>
                            <?php if (!empty($item['description'])): ?>
                                <p class="text-muted mb-2"><?= htmlspecialchars(mb_strimwidth($item['description'], 0, 120, '...')); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($item['tags'])): ?>
                                <div class="mb-2">
                                    <?php foreach (explode(',', $item['tags']) as $tag): ?>
                                        <span class="badge bg-light text-dark me-1 mb-1">#<?= htmlspecialchars(trim($tag)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="mt-auto">
                                <small class="text-muted">
                                    <?= $item['created_label']; ?>
                                    <?php if ($hasEvents && !empty($item['event_title'])): ?>
                                        • <?= htmlspecialchars($item['event_title']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="d-flex gap-2 mt-3 flex-wrap">
                                <a href="<?= $imageSrc; ?>" target="_blank" class="btn btn-sm btn-outline-secondary flex-grow-1">
                                    View Image
                                </a>
                <a href="?toggle_feature=1&id=<?= (int) $item['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <?= (int) $item['is_featured'] === 1 ? 'Remove Highlight' : 'Mark Highlight'; ?>
                                </a>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        Update Status
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <?php foreach ($statuses as $status): ?>
                                            <a class="dropdown-item" href="?toggle_status=<?= $status; ?>&id=<?= (int) $item['id']; ?>">
                                                Mark as <?= ucfirst($status); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <a href="?delete=1&id=<?= (int) $item['id']; ?>" class="btn btn-sm btn-outline-secondary text-danger" onclick="return confirm('Delete this gallery item?');">
                                    Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Target the form that has an action or is the upload form
        const form = document.querySelector('form[enctype="multipart/form-data"]');
        
        if (form) {
            form.setAttribute('novalidate', '');
            
            form.addEventListener('submit', function(e) {
                let isValid = true;
                const inputs = form.querySelectorAll('input, select, textarea');
                
                inputs.forEach(function(input) {
                    if (!validateField(input)) {
                        isValid = false;
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                    }
                }
            });
            
            // Real-time validation
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(function(input) {
                /*
                input.addEventListener('blur', function() {
                     if (input.hasAttribute('required')) validateField(input);
                });
                */
                input.addEventListener('input', function() {
                    if (input.classList.contains('is-invalid')) {
                        validateField(input);
                    }
                });
            });
        }

        // Auto-dismiss alerts
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

    function validateField(input) {
        let errorContainer = input.parentNode.querySelector('.validation-error');
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.className = 'validation-error';
            input.parentNode.appendChild(errorContainer);
        }
        
        let errorMessage = '';
        
        // Required validation
        if (input.hasAttribute('required')) {
            if (input.type === 'file') {
                if (input.files.length === 0) {
                    errorMessage = 'Please upload an image';
                }
            } else if (!input.value.trim()) {
                // Try to find label text
                let labelText = 'This field';
                const label = input.closest('div').querySelector('label');
                if (label) {
                    labelText = label.textContent.replace('*', '').trim();
                }
                errorMessage = `${labelText} is required`;
            }
        }
        
        // Show/hide error
        if (errorMessage) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            errorContainer.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${errorMessage}`;
            errorContainer.style.display = 'block';
            return false;
        } else {
             input.classList.remove('is-invalid');
            if (input.value.trim() || (input.type === 'file' && input.files.length > 0)) {
                input.classList.add('is-valid');
            }
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';
            return true;
        }
    }
</script>
</body>
</html>

