<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

// Check admin login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$success = "";
$error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $category = $conn->real_escape_string($_POST['category']);
    $event_date = $conn->real_escape_string($_POST['event_date']); // This is the End Date
    $location_options = $_POST['location_options'] ?? [];
    $available_locations = implode('|', array_filter(array_map('trim', $location_options)));
    $location = !empty($location_options[0]) ? $conn->real_escape_string($location_options[0]) : '';
    $price = $conn->real_escape_string($_POST['price']);
    $manager_id = $conn->real_escape_string($_POST['manager_id']);
    
    // Handle image upload
    $image_path = "";
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === 0) {
        $target_dir = "../assets/images/uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
                $image_path = "assets/images/uploads/" . $new_filename;
            }
        }
    }
    
    // Check if image was successfully uploaded
    if (empty($image_path)) {
        $error = "Event image is required. Please upload a valid image (jpg, jpeg, png, gif).";
    } else {
        // Insert into database
        $sql = "INSERT INTO events (title, description, category, event_date, location, available_locations, price, manager_id, image_path, created_by) 
                VALUES ('$title', '$description', '$category', '$event_date', '$location', '$available_locations', '$price', '$manager_id', '$image_path', '{$_SESSION['admin_id']}')";
        
        if ($conn->query($sql)) {
            $success = "Event successfully added!";
        } else {
            $error = "Error adding event: " . $conn->error;
        }
    }
}

// Fetch managers for dropdown
$managers = [];
$managers_result = $conn->query("SELECT id, name FROM managers WHERE status='active'");
if ($managers_result) {
    while ($row = $managers_result->fetch_assoc()) {
        $managers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Event - Admin Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Shared Styles */
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
        .form-container {
            background: #fff;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(90,44,160,0.08);
            border: 1px solid rgba(90,44,160,0.08);
            max-width: 800px;
        }
        .form-header {
            border-bottom: 2px solid var(--primary);
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 12px 30px;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(90,44,160,0.25);
        }
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 10px;
            display: none;
        }
        
        /* Validation Styles */
        .required-star {
            color: #dc3545;
            font-weight: 700;
            font-size: 14px;
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
        
        @keyframes errorFadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        input.is-invalid, select.is-invalid, textarea.is-invalid {
            border-color: #dc3545 !important;
            background-color: #fff8f8 !important;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.15) !important;
        }
        
        input.is-valid, select.is-valid, textarea.is-valid {
            border-color: #28a745 !important;
        }
        /* Hide number input arrows */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            margin: 0; 
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
</head>
<body>

<!-- ... existing HTML ... -->

<aside class="sidebar">
    <div class="brand">Event Ease Admin</div>
    <a class="nav-link-custom" href="index.php">Dashboard</a>
    <a class="nav-link-custom" href="pending_approval.php">Pending Approvals</a>
    <a class="nav-link-custom active" href="add_event.php">Add Event</a>
    <a class="nav-link-custom" href="manage_events.php">Manage Events</a>
    <a class="nav-link-custom" href="payment.php">Payment Detail</a>
    <a class="nav-link-custom" href="AddOns.php">Add Ons</a>
    <a class="nav-link-custom" href="gallery.php">Gallery Content</a>
    <a class="nav-link-custom" href="feedback.php">Event Ratings</a>
    <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Add New Event</h2>
            <p class="text-muted mb-0">Create exciting events for users to book</p>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ✅ <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            ❌ <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" action="" enctype="multipart/form-data" id="addEventForm" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="title" class="form-label">Event Title <span class="required-star">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" required 
                           placeholder="Enter event title">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="category" class="form-label">Category <span class="required-star">*</span></label>
                    <select class="form-control" id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="wedding">Wedding</option>
                        <option value="corporate">Corporate Event</option>
                        <option value="birthday">Birthday Party</option>
                        <option value="conference">Conference</option>
                        <option value="concert">Concert</option>
                        <option value="festival">Festival</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Event Description <span class="required-star">*</span></label>
                <textarea class="form-control" id="description" name="description" rows="4" 
                          placeholder="Describe the event details..." required></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="event_date" class="form-label">Event End Date <span class="required-star">*</span></label>
                    <input type="date" class="form-control" id="event_date" name="event_date" required 
                           min="<?= date('Y-m-d'); ?>">
                    <small class="text-muted">Event will be hidden after this date.</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="price" class="form-label">Price (₹) <span class="required-star">*</span></label>
                    <input type="number" class="form-control" id="price" name="price" required 
                           placeholder="Enter price" min="0" step="0.01">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>Event Locations (Cities/Venues) <span class="required-star">*</span></span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addLocationBtn">
                        <i class="fas fa-plus"></i> Add Location
                    </button>
                </label>
                <div id="locationOptionsContainer">
                    <div class="location-option-wrapper mb-3">
                        <div class="d-flex gap-2">
                            <span class="option-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; flex-shrink: 0; font-size: 12px;">1</span>
                            <div class="flex-grow-1">
                                <input type="text" name="location_options[]" class="form-control" required placeholder="Enter city or venue name">
                            </div>
                            <button type="button" class="btn btn-outline-danger remove-location" style="display:none;"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>
                <small class="text-muted">Add one or more locations. These will be selectable by users during registration.</small>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="manager_id" class="form-label">Assign Manager <span class="required-star">*</span></label>
                    <select class="form-control" id="manager_id" name="manager_id" required>
                        <option value="">Select Manager</option>
                        <?php foreach ($managers as $manager): ?>
                            <option value="<?= $manager['id'] ?>"><?= htmlspecialchars($manager['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="event_image" class="form-label">Event Image <span class="required-star">*</span></label>
                    <input type="file" class="form-control" id="event_image" name="event_image" 
                           accept="image/*" required>
                    <div class="mt-2">
                        <img id="imagePreview" class="image-preview" src="#" alt="Preview">
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="reset" class="btn btn-secondary me-md-2">Reset Form</button>
                <button type="submit" class="btn btn-primary">Add Event</button>
            </div>
        </form>
    </div>
</main>

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

    // Image preview functionality
    document.getElementById('event_image').addEventListener('change', function(e) {
        const preview = document.getElementById('imagePreview');
        const file = e.target.files[0];
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
        
        // Trigger validation
        validateField(e.target);
    });

    // Form Validation Logic
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('addEventForm');
        
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
                // Focus on first invalid field
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

        // Auto-dismiss alerts after 3.5 seconds
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
        const errorContainer = getErrorContainer(input);
        let errorMessage = '';
        
        // Required validation
        if (input.hasAttribute('required')) {
            if (input.type === 'file') {
                if (input.files.length === 0) {
                    errorMessage = 'Please upload an event image';
                }
            } else if (!input.value.trim()) {
                const label = document.querySelector(`label[for="${input.id}"]`);
                const fieldName = label ? label.textContent.replace('*', '').trim() : 'This field';
                errorMessage = `${fieldName} is required`;
            }
        }
        
        if (input.type === 'number' && input.value && parseFloat(input.value) < 0) {
            errorMessage = 'Price cannot be negative';
        }

        // Show/hide error
        if (errorMessage) {
            showError(input, errorContainer, errorMessage);
            return false;
        } else {
            hideError(input, errorContainer);
            return true;
        }
    }

    function getErrorContainer(input) {
        let errorContainer = input.parentNode.querySelector('.validation-error');
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.className = 'validation-error';
            input.parentNode.appendChild(errorContainer);
        }
        return errorContainer;
    }

    function showError(input, container, message) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        container.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${message}`;
        container.style.display = 'block';

        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            hideError(input, container);
        }, 3000);
    }

    function hideError(input, container) {
        input.classList.remove('is-invalid');
        if (input.value.trim() || (input.type === 'file' && input.files.length > 0)) {
            input.classList.add('is-valid');
        }
        container.style.display = 'none';
        container.innerHTML = '';
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>