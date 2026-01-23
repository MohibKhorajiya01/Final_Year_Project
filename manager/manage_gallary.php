<?php
session_start();
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/auth_check.php';

function tableExists(mysqli $conn, string $tableName): bool {
    $safe = $conn->real_escape_string($tableName);
    $check = $conn->query("SHOW TABLES LIKE '$safe'");
    return $check && $check->num_rows > 0;
}

$hasGalleryTable = tableExists($conn, 'gallery_items');
$hasEventsTable = tableExists($conn, 'events');

$statusTypes = ['published', 'draft', 'archived'];
$statusMessage = '';
$statusType = 'success';
$uploadDir = __DIR__ . '/../uploads/gallery/';
$relativeUploadPath = 'uploads/gallery/';

if ($hasGalleryTable && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gallery_id'])) {
    $galleryId = (int) $_POST['gallery_id'];
    $newStatus = trim($_POST['status'] ?? 'published');
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $caption = trim($_POST['description'] ?? '');

    if (!in_array($newStatus, $statusTypes, true)) {
        $statusMessage = "Invalid status provided.";
        $statusType = 'danger';
    } else {
        $stmt = $conn->prepare("UPDATE gallery_items SET status = ?, is_featured = ?, description = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("sisi", $newStatus, $isFeatured, $caption, $galleryId);
            if ($stmt->execute()) {
                $statusMessage = "Gallery item updated.";
            } else {
                $statusMessage = "Failed to update gallery item.";
                $statusType = 'warning';
            }
            $stmt->close();
        } else {
            $statusMessage = "Unable to prepare update statement.";
            $statusType = 'danger';
        }
    }
}

if ($hasGalleryTable && $hasEventsTable && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'manager_upload') {
    $title = trim($_POST['title'] ?? '');
    $notes = trim($_POST['description'] ?? '');
    $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

    if ($title === '') {
        $statusMessage = "Title is required.";
        $statusType = 'danger';
    } elseif (!$hasEventsTable) {
        $statusMessage = "Events table is missing. Please contact Admin.";
        $statusType = 'danger';
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $statusMessage = "Please upload an image.";
        $statusType = 'danger';
    } elseif (!$hasGalleryTable) {
        $statusMessage = "Gallery table is not available.";
        $statusType = 'danger';
    } else {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed, true)) {
            $statusMessage = "Only JPG, PNG, WEBP images are allowed.";
            $statusType = 'danger';
        } else {
            $filename = uniqid('mgr_', true) . '.' . $extension;
            $targetPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $storedPath = $relativeUploadPath . $filename;
                $stmt = $conn->prepare("INSERT INTO gallery_items (title, description, event_id, image_path, status, created_by) VALUES (?, ?, ?, ?, 'draft', ?)");
                if ($stmt) {
                    $stmt->bind_param("ssisi", $title, $notes, $eventId, $storedPath, $managerId);
                    if ($stmt->execute()) {
                        $statusMessage = "Gallery upload submitted for approval.";
                        $statusType = 'success';
                    } else {
                        $statusMessage = "Failed to save image.";
                        $statusType = 'warning';
                        @unlink($targetPath);
                    }
                    $stmt->close();
                } else {
                    $statusMessage = "Unable to prepare insert statement.";
                    $statusType = 'danger';
                }
            } else {
                $statusMessage = "Upload failed.";
                $statusType = 'danger';
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$featuredFilter = trim($_GET['featured'] ?? '');

$galleryItems = [];
if ($hasGalleryTable && $hasEventsTable) {
    $query = "
        SELECT g.*, e.title AS event_title, DATE_FORMAT(g.created_at, '%d %b %Y') AS created_label
        FROM gallery_items g
        LEFT JOIN events e ON g.event_id = e.id
        WHERE 1=1
    ";
    $types = "";
    $params = [];

    if ($search !== '') {
        $query .= " AND (g.title LIKE CONCAT('%', ?, '%') OR g.description LIKE CONCAT('%', ?, '%'))";
        $types .= "ss";
        $params[] = $search;
        $params[] = $search;
    }
    if ($statusFilter !== '' && in_array($statusFilter, $statusTypes, true)) {
        $query .= " AND g.status = ?";
        $types .= "s";
        $params[] = $statusFilter;
    }
    if ($featuredFilter === 'featured') {
        $query .= " AND g.is_featured = 1";
    } elseif ($featuredFilter === 'standard') {
        $query .= " AND g.is_featured = 0";
    }
    $query .= " ORDER BY g.created_at DESC";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $galleryItems[] = $row;
            }
        }
        $stmt->close();
    }
} elseif ($hasGalleryTable) {
    $query = "
        SELECT g.*, DATE_FORMAT(g.created_at, '%d %b %Y') AS created_label
        FROM gallery_items g
        ORDER BY g.created_at DESC
    ";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $galleryItems[] = $row;
            }
        }
        $stmt->close();
    }
} else {
    $galleryItems = [
        ['id' => 0, 'title' => 'Setup Walkthrough', 'description' => 'Upload sample', 'event_title' => 'Demo Event', 'status' => 'draft', 'is_featured' => 0, 'image_path' => 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f', 'created_label' => date('d M Y')]
    ];
}

$eventsOptions = [];
if ($hasEventsTable) {
    $stmtEvents = $conn->prepare("SELECT id, title FROM events ORDER BY title");
    if ($stmtEvents) {
        if ($stmtEvents->execute()) {
            $resultEvents = $stmtEvents->get_result();
            while ($row = $resultEvents->fetch_assoc()) {
                $eventsOptions[] = $row;
            }
        }
        $stmtEvents->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager · Gallery Hub</title>
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
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }
        .gallery-card {
            border: 1px solid rgba(90,44,160,0.1);
            border-radius: 18px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 12px 30px rgba(90,44,160,0.08);
            display: flex;
            flex-direction: column;
        }
        .gallery-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
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
        /* Validation Styles */
        .required-star {
            color: #dc3545;
            font-weight: 700;
        }
        .validation-error {
            display: none;
            color: #dc3545;
            font-size: 13px;
            margin-top: 6px;
        }
        input.is-invalid, select.is-invalid {
            border-color: #dc3545 !important;
            background-color: #fff8f8 !important;
        }
        input.is-valid, select.is-valid {
            border-color: #28a745 !important;
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
    <a class="nav-link-custom active" href="manage_gallary.php">Gallery Uploads</a>
    <a class="nav-link-custom" href="manage_payments.php">Payment History</a>
    <a class="nav-link-custom" href="manage_feedback.php">Event Ratings</a>
    <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

<main class="main-content">
<div class="page-shell">
    <div class="page-header">
        <div>
            <p class="text-uppercase text-muted mb-1" style="letter-spacing:2px;">Manager Workspace</p>
            <h2 class="mb-0">Gallery Upload & Review</h2>
            <small class="text-muted">Gallery Upload & Review | Signed in as <strong><?= htmlspecialchars($managerName); ?></strong></small>
        </div>
    </div>

    <?php if ($statusMessage): ?>
        <div class="alert alert-<?= htmlspecialchars($statusType); ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($statusMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="board">
        <h5 class="mb-3">Upload Highlight</h5>
        <form method="POST" enctype="multipart/form-data" class="row g-3" novalidate id="galleryUploadForm">
            <input type="hidden" name="action" value="manager_upload">
            <div class="col-md-5">
                <label class="form-label">Title <span class="required-star">*</span></label>
                <input type="text" name="title" class="form-control" placeholder="Sangeet Sneak Peek" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Link Event</label>
                <select name="event_id" class="form-select" <?= empty($eventsOptions) ? 'disabled' : ''; ?>>
                    <option value="">None / Other</option>
                    <?php if (!empty($eventsOptions)): ?>
                        <?php foreach ($eventsOptions as $event): ?>
                            <option value="<?= $event['id']; ?>"><?= htmlspecialchars($event['title']); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <?php if (empty($eventsOptions)): ?>
                    <small class="text-muted">Request admin to assign events.</small>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label">Image <span class="required-star">*</span></label>
                <input type="file" name="image" class="form-control" accept="image/*" required>
            </div>
            <div class="col-12">
                <label class="form-label">Notes <span class="required-star">*</span></label>
                <textarea name="description" rows="2" class="form-control" placeholder="Behind-the-scenes, vendor shoutouts, etc." required></textarea>
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-cloud-upload-alt"></i> Submit for Review
                </button>
            </div>
        </form>
    </div>

    <div class="board">
        <h5 class="mb-3">Filter Gallery</h5>
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Title or notes" value="<?= htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($statusTypes as $opt): ?>
                        <option value="<?= $opt; ?>" <?= $statusFilter === $opt ? 'selected' : ''; ?>><?= ucfirst($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Highlight</label>
                <select name="featured" class="form-select">
                    <option value="">All</option>
                    <option value="featured" <?= $featuredFilter === 'featured' ? 'selected' : ''; ?>>Featured</option>
                    <option value="standard" <?= $featuredFilter === 'standard' ? 'selected' : ''; ?>>Standard</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <div class="board">
        <?php if (empty($galleryItems)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-image fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No gallery submissions yet.</h5>
                <p class="text-muted mb-0">Upload highlights from your events to showcase progress.</p>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($galleryItems as $item): ?>
                    <?php
                        $status = $item['status'] ?? 'draft';
                        $statusClass = $status === 'published' ? 'bg-success-subtle text-success' : ($status === 'archived' ? 'bg-secondary-subtle text-secondary' : 'bg-warning-subtle text-warning');
                        $imageSrc = str_starts_with($item['image_path'], 'http') ? $item['image_path'] : "../" . ltrim($item['image_path'], './');
                    ?>
                    <div class="gallery-card">
                        <img src="<?= htmlspecialchars($imageSrc); ?>" alt="<?= htmlspecialchars($item['title'] ?? 'Gallery image'); ?>">
                        <div class="p-3 flex-grow-1 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge badge-status <?= $statusClass; ?>"><?= ucfirst($status); ?></span>
                                <?php if (!empty($item['is_featured'])): ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-star"></i> Featured</span>
                                <?php endif; ?>
                            </div>
                            <h5><?= htmlspecialchars($item['title'] ?? 'Untitled'); ?></h5>
                            <?php if (!empty($item['description'])): ?>
                                <p class="text-muted mb-2"><?= htmlspecialchars(mb_strimwidth($item['description'], 0, 110, '...')); ?></p>
                            <?php endif; ?>
                            <small class="text-muted mb-3">
                                <?= htmlspecialchars($item['created_label'] ?? ''); ?>
                                <?php if (!empty($item['event_title'])): ?>
                                    • <?= htmlspecialchars($item['event_title']); ?>
                                <?php endif; ?>
                            </small>
                            <?php if ($hasGalleryTable): ?>
                                <form method="POST" class="mt-auto">
                                    <input type="hidden" name="gallery_id" value="<?= (int) $item['id']; ?>">
                                    <div class="mb-2">
                                        <select name="status" class="form-select form-select-sm">
                                            <?php foreach ($statusTypes as $opt): ?>
                                                <option value="<?= $opt; ?>" <?= $status === $opt ? 'selected' : ''; ?>><?= ucfirst($opt); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" name="is_featured" value="1" <?= !empty($item['is_featured']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Mark highlight</label>
                                    </div>
                                    <textarea name="description" rows="2" class="form-control mb-2" placeholder="Update caption..."><?= htmlspecialchars($item['description'] ?? ''); ?></textarea>
                                    <button type="submit" class="btn btn-sm btn-primary w-100">Save Changes</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Validation Script
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form[novalidate]');
        if (form) {
            form.addEventListener('submit', function(e) {
                let isValid = true;
                const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
                
                inputs.forEach(function(input) {
                    if (!validateField(input)) {
                        isValid = false;
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    // Focus on first invalid field
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                    }
                }
            });
            
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(function(input) {
                /*
                input.addEventListener('blur', function() {
                     if (input.hasAttribute('required')) validateField(input);
                });
                */
                input.addEventListener('input', function() {
                    // Remove error as they type
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
            errorContainer.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> This field is required';
            input.parentNode.appendChild(errorContainer);
        }
        
        let isValid = true;
        if (input.hasAttribute('required') && !input.value.trim()) {
            isValid = false;
        }
        
        if (!isValid) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            errorContainer.style.display = 'block';

            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                input.classList.remove('is-invalid');
                if (input.value.trim()) {
                    input.classList.add('is-valid');
                }
                errorContainer.style.display = 'none';
            }, 3000);

            return false;
        } else {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            errorContainer.style.display = 'none';
            return true;
        }
    }
</script>
</body>
</html>
