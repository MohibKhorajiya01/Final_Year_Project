<?php
session_start();
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/auth_check.php';

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
    // Event not found or not assigned to this manager
    header("Location: manage_events.php");
    exit();
}

$event = $result->fetch_assoc();
$stmt->close();

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
        $updateStmt = $conn->prepare("UPDATE events SET title=?, description=?, category=?, event_date=?, location=?, available_locations=?, price=?, image_path=? WHERE id=?");
        $updateStmt->bind_param("ssssssdsi", $title, $description, $category, $event_date, $location, $available_locations, $price, $image_path, $eventId);
        
        if ($updateStmt->execute()) {
            $success = "Event updated successfully!";
            // Refresh event data
            $event['title'] = $title;
            $event['description'] = $description;
            $event['category'] = $category;
            $event['event_date'] = $event_date;
            $event['location'] = $location;
            $event['available_locations'] = $available_locations;
            $event['price'] = $price;
            $event['image_path'] = $image_path;
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
    <title>Edit Event - Manager Panel</title>
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
            max-width: 800px;
            margin: 0 auto;
        }
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
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
        /* Professional Validation Styles */
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
        input.is-invalid, select.is-invalid, textarea.is-invalid {
            border-color: #dc3545 !important;
            background-color: #fff8f8 !important;
        }
        input.is-valid, select.is-valid, textarea.is-valid {
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1" style="color: var(--primary);">Edit Event</h2>
            <small class="text-muted">Update event details | Signed in as <strong><?= htmlspecialchars($managerName); ?></strong></small>
        </div>
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
        <form method="POST" enctype="multipart/form-data" novalidate id="editEventForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Event Title <span class="required-star">*</span></label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($event['title']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Category <span class="required-star">*</span></label>
                    <select name="category" class="form-select" required>
                        <option value="Wedding" <?= $event['category'] == 'Wedding' ? 'selected' : ''; ?>>Wedding</option>
                        <option value="Corporate" <?= $event['category'] == 'Corporate' ? 'selected' : ''; ?>>Corporate Event</option>
                        <option value="Birthday" <?= $event['category'] == 'Birthday' ? 'selected' : ''; ?>>Birthday Party</option>
                        <option value="Conference" <?= $event['category'] == 'Conference' ? 'selected' : ''; ?>>Conference</option>
                        <option value="Concert" <?= $event['category'] == 'Concert' ? 'selected' : ''; ?>>Concert</option>
                        <option value="Festival" <?= $event['category'] == 'Festival' ? 'selected' : ''; ?>>Festival</option>
                        <option value="Sports" <?= $event['category'] == 'Sports' ? 'selected' : ''; ?>>Sports</option>
                        <option value="Shows" <?= $event['category'] == 'Shows' ? 'selected' : ''; ?>>Shows</option>
                        <option value="Exhibition" <?= $event['category'] == 'Exhibition' ? 'selected' : ''; ?>>Exhibition</option>
                        <option value="Seminar" <?= $event['category'] == 'Seminar' ? 'selected' : ''; ?>>Seminar</option>
                        <option value="Anniversary" <?= $event['category'] == 'Anniversary' ? 'selected' : ''; ?>>Anniversary</option>
                        <option value="Engagement" <?= $event['category'] == 'Engagement' ? 'selected' : ''; ?>>Engagement</option>
                        <option value="Religious" <?= $event['category'] == 'Religious' ? 'selected' : ''; ?>>Religious</option>
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
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) firstInvalid.focus();
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
