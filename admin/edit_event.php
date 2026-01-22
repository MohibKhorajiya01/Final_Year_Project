<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

// Check admin login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get Event ID
if (!isset($_GET['id'])) {
    header("Location: manage_events.php");
    exit();
}

$eventId = (int) $_GET['id'];
$success = "";
$error = "";

// Fetch existing event details
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: manage_events.php");
    exit();
}

$event = $result->fetch_assoc();
$stmt->close();

// Fetch managers for dropdown
$managers = [];
$managers_result = $conn->query("SELECT id, name FROM managers WHERE status='active'");
if ($managers_result) {
    while ($row = $managers_result->fetch_assoc()) {
        $managers[] = $row;
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $event_date = trim($_POST['event_date']);
    $location_options = $_POST['location_options'] ?? [];
    $available_locations = implode('|', array_filter(array_map('trim', $location_options)));
    $location = !empty($location_options[0]) ? trim($location_options[0]) : '';
    $price = (float) $_POST['price'];
    $manager_id = !empty($_POST['manager_id']) ? (int) $_POST['manager_id'] : null;
    $status = trim($_POST['status']);
    
    // Handle Image Upload
    $image_path = $event['image_path']; // Default to existing image
    
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === 0) {
        $target_dir = "../assets/images/uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
                $image_path = "assets/images/uploads/" . $new_filename;
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid file format. Only JPG, PNG, GIF allowed.";
        }
    }

    if (empty($error)) {
        $updateStmt = $conn->prepare("UPDATE events SET title=?, description=?, category=?, event_date=?, location=?, available_locations=?, price=?, manager_id=?, status=?, image_path=? WHERE id=?");
        $updateStmt->bind_param("ssssssdissi", $title, $description, $category, $event_date, $location, $available_locations, $price, $manager_id, $status, $image_path, $eventId);
        
        if ($updateStmt->execute()) {
            $_SESSION['success'] = "Event updated successfully!";
            header("Location: manage_events.php");
            exit();
        } else {
            $error = "Error updating event: " . $conn->error;
        }
        $updateStmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - Admin Panel</title>
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
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .required-star {
            color: #dc3545;
            font-weight: 700;
        }
        .image-preview {
            width: 100%;
            max-width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #ddd;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1" style="color: var(--primary);">Edit Event</h2>
            <p class="text-muted mb-0">Update event details</p>
        </div>
        <a href="manage_events.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Event Title <span class="required-star">*</span></label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($event['title']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Category <span class="required-star">*</span></label>
                    <select name="category" class="form-select" required>
                        <option value="Wedding" <?= $event['category'] == 'Wedding' ? 'selected' : ''; ?>>Wedding</option>
                        <option value="Corporate" <?= $event['category'] == 'Corporate' ? 'selected' : ''; ?>>Corporate</option>
                        <option value="Birthday" <?= $event['category'] == 'Birthday' ? 'selected' : ''; ?>>Birthday</option>
                        <option value="Concert" <?= $event['category'] == 'Concert' ? 'selected' : ''; ?>>Concert</option>
                        <option value="Festival" <?= $event['category'] == 'Festival' ? 'selected' : ''; ?>>Festival</option>
                        <option value="Other" <?= $event['category'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Description <span class="required-star">*</span></label>
                <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($event['description']); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Event End Date <span class="required-star">*</span></label>
                    <input type="date" name="event_date" class="form-control" value="<?= $event['event_date']; ?>" required>
                    <small class="text-muted">Event will be hidden after this date.</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Price (₹) <span class="required-star">*</span></label>
                    <input type="number" name="price" class="form-control" value="<?= $event['price']; ?>" step="0.01" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label d-flex justify-content-between align-items-center">
                    Event Locations (Cities/Venues) <span class="required-star">*</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addLocationBtn">
                        <i class="fas fa-plus"></i> Add Location
                    </button>
                </label>
                <div id="locationOptionsContainer">
                    <?php 
                    $existingLocations = array_filter(array_map('trim', explode('|', $event['available_locations'] ?? '')));
                    if (empty($existingLocations)) $existingLocations = [''];
                    foreach ($existingLocations as $index => $loc):
                    ?>
                    <div class="location-option-wrapper mb-3">
                        <div class="d-flex gap-2">
                            <span class="option-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; flex-shrink: 0; font-size: 12px;"><?= $index + 1 ?></span>
                            <div class="flex-grow-1">
                                <input type="text" name="location_options[]" class="form-control" value="<?= htmlspecialchars($loc); ?>" required placeholder="Enter city or venue name">
                            </div>
                            <button type="button" class="btn btn-outline-danger remove-location" <?= count($existingLocations) <= 1 ? 'style="display:none;"' : ''; ?>><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <small class="text-muted">Add one or more locations. These will be selectable by users during registration.</small>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Assign Manager</label>
                    <select name="manager_id" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach ($managers as $manager): ?>
                            <option value="<?= $manager['id']; ?>" <?= $event['manager_id'] == $manager['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($manager['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Planning" <?= $event['status'] == 'Planning' ? 'selected' : ''; ?>>Planning</option>
                        <option value="Active" <?= $event['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Completed" <?= $event['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?= $event['status'] == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Event Image</label>
                <input type="file" name="event_image" class="form-control" accept="image/*">
                <?php if (!empty($event['image_path'])): ?>
                    <div class="mt-2">
                        <small class="text-muted">Current Image:</small><br>
                        <img src="../<?= htmlspecialchars($event['image_path']); ?>" class="image-preview" alt="Event Image">
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary px-4">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Dynamic Location handling
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('locationOptionsContainer');
        const addBtn = document.getElementById('addLocationBtn');

        addBtn.addEventListener('click', function() {
            const wrappers = container.querySelectorAll('.location-option-wrapper');
            const nextNum = wrappers.length + 1;
            
            const div = document.createElement('div');
            div.className = 'location-option-wrapper mb-3';
            div.innerHTML = `
                <div class="d-flex gap-2">
                    <span class="option-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; flex-shrink: 0; font-size: 12px;">${nextNum}</span>
                    <div class="flex-grow-1">
                        <input type="text" name="location_options[]" class="form-control" required placeholder="Enter city or venue name">
                    </div>
                    <button type="button" class="btn btn-outline-danger remove-location"><i class="fas fa-trash"></i></button>
                </div>
            `;
            container.appendChild(div);
            updateRemoveButtons();
        });

        container.addEventListener('click', function(e) {
            if (e.target.closest('.remove-location')) {
                e.target.closest('.location-option-wrapper').remove();
                updateRemoveButtons();
            }
        });

        function updateRemoveButtons() {
            const wrappers = container.querySelectorAll('.location-option-wrapper');
            const buttons = container.querySelectorAll('.remove-location');
            
            // Update numbering
            wrappers.forEach((wrapper, index) => {
                const numSpan = wrapper.querySelector('.option-number');
                if (numSpan) numSpan.textContent = index + 1;
            });

            if (wrappers.length <= 1) {
                buttons[0].style.display = 'none';
            } else {
                buttons.forEach(btn => btn.style.display = 'block');
            }
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
