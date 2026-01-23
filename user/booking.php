<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../backend/config.php';

// Get logged-in user's email
$userEmail = '';
if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    $emailQuery = $conn->prepare("SELECT email FROM users WHERE id = ?");
    if ($emailQuery) {
        $emailQuery->bind_param("i", $userId);
        $emailQuery->execute();
        $emailResult = $emailQuery->get_result();
        if ($emailRow = $emailResult->fetch_assoc()) {
            $userEmail = $emailRow['email'];
        }
        $emailQuery->close(); 
    }
}

function tableExists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $check = $conn->query("SHOW TABLES LIKE '$safe'");
    return $check && $check->num_rows > 0;
}

function ensureBookingsTable(mysqli $conn): void {
    if (!tableExists($conn, 'bookings')) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS bookings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_code VARCHAR(20) NOT NULL,
                user_id INT NOT NULL,
                event_id INT NOT NULL,
                preferred_date DATE NULL,
                guest_count INT DEFAULT 0,
                addons TEXT NULL,
                notes TEXT NULL,
                total_amount DECIMAL(12,2) DEFAULT 0,
                status VARCHAR(20) DEFAULT 'pending',
                payment_status VARCHAR(20) DEFAULT 'unpaid',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}

ensureBookingsTable($conn);

$eventLoadedFromDb = false;



function resolveEventByKey(mysqli $conn, string $key, bool &$loadedFromDb): ?array {
    $loadedFromDb = false;
    if ($key !== '' && ctype_digit($key) && (int) $key > 0 && tableExists($conn, 'events')) {
        $numericId = (int) $key;
        $stmt = $conn->prepare("SELECT id, title, description, event_date, location, available_locations, price, image_path FROM events WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $numericId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $event = $result->fetch_assoc();
                if ($event) {
                    $loadedFromDb = true;
                    $stmt->close();
                    return $event;
                }
            }
            $stmt->close();
        }
    }



    return null;
}

$eventKey = trim($_GET['event_id'] ?? $_GET['id'] ?? '');
$successMessage = "";
$errorMessage = "";

$event = resolveEventByKey($conn, $eventKey, $eventLoadedFromDb);

// Check if user has already booked this event (on page load)
$alreadyBooked = false;
if ($event && $eventLoadedFromDb && isset($_SESSION['user_id'])) {
    $eventIdCheck = (int)$eventKey;
    if ($eventIdCheck > 0 && tableExists($conn, 'bookings')) {
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE event_id = ? AND user_id = ? AND payment_status = 'paid' AND status != 'cancelled'");
        if ($checkStmt) {
            $checkStmt->bind_param("ii", $eventIdCheck, $_SESSION['user_id']);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            $checkStmt->close();
            if ($row['count'] > 0) {
                $alreadyBooked = true;
                // $errorMessage assignment removed to prevent duplicate error display
            }
        }
    }
}



$packagePricing = [
    'simple' => 3000,
    'premium' => 5000
];

$pickupPricing = [
    'no' => 0,
    'yes' => 4000  // Fixed price for pickup service
];

$decorationPricing = [
    'none' => 0,
    'low' => 5000,
    'medium' => 10000,
    'high' => 15000
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedKey = trim($_POST['event_id'] ?? '');
    if ($submittedKey !== '') {
        $eventKey = $submittedKey;
    }
    $event = resolveEventByKey($conn, $eventKey, $eventLoadedFromDb);
    if (!$event) {
        $errorMessage = "Invalid event selection. Go back and pick an event again.";
    } else {
        // If event is a sample (not loaded from DB) do not allow booking
        if (!$eventLoadedFromDb) {
            $errorMessage = "Selected event is not bookable. Please choose a real event from the events list.";
        } else {
            $foodPackage = $_POST['food_package'] ?? 'simple';
            $pickupService = $_POST['pickup_service'] ?? 'no';
            $pickupAddress = trim($_POST['event_address'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $decoration = $_POST['decoration'] ?? 'none';
            $selected_location = $_POST['selected_location'] ?? '';
            $preferredDate = $_POST['preferred_date'] ?? null;

            // Validate pickup service - if yes selected, address is required
            if ($pickupService === 'yes' && $pickupAddress === '') {
                $errorMessage = "Please enter pickup address when pickup service is selected.";
            } elseif ($fullName === '' || $email === '') {
                $errorMessage = "Please enter your name and email address.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
                $errorMessage = "Please enter a valid email address.";
            } elseif ($selected_location === '') {
                $errorMessage = "Please select a location for the event.";
            } elseif (empty($preferredDate)) {
                $errorMessage = "Please select a preferred date for the event.";
            } else {
                // Check if the selected date is already booked for this event
                $eventIdForCheck = (int)$eventKey;
                $dateCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE event_id = ? AND preferred_date = ? AND payment_status = 'paid' AND status != 'cancelled'");
                if ($dateCheckStmt) {
                    $dateCheckStmt->bind_param("is", $eventIdForCheck, $preferredDate);
                    $dateCheckStmt->execute();
                    $dateCheckResult = $dateCheckStmt->get_result();
                    $dateCheckRow = $dateCheckResult->fetch_assoc();
                    $dateCheckStmt->close();
                    
                    if ($dateCheckRow['count'] > 0) {
                        $errorMessage = "Sorry! This event is already booked for the selected date (" . date('d M Y', strtotime($preferredDate)) . "). Please choose a different date.";
                    }
                }
                
                if ($errorMessage === '') {
                $packageCost = $packagePricing[$foodPackage] ?? 0;
                $pickupCost = $pickupPricing[$pickupService] ?? 0;
                $decorationCost = $decorationPricing[$decoration] ?? 0;
                $basePrice = (float) ($event['price'] ?? 0);
                $totalAmount = $basePrice + $packageCost + $pickupCost + $decorationCost;

                $bookingCode = 'EV' . strtoupper(substr(uniqid(), -6));
                $metaPayload = [
                    'food_package' => $foodPackage,
                    'pickup_service' => $pickupService,
                    'pickup_address' => $pickupAddress,
                    'decoration' => $decoration,
                    'decoration_cost' => $decorationCost,
                    'selected_location' => $selected_location,
                    'guest_notes' => $fullName . ' | ' . $email
                ];

                // Validate event ID first (only DB-loaded numeric IDs are bookable)
                if (empty($eventKey) || !ctype_digit($eventKey)) {
                    $errorMessage = "Event ID missing or invalid!";
                } else {
                    $eventIdForInsert = (int)$eventKey;
                    if ($eventIdForInsert <= 0) {
                        $errorMessage = "Event ID invalid!";
                    } else {
                        // Check if this user has already booked this specific event
                        $checkBookingStmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE event_id = ? AND user_id = ? AND payment_status = 'paid' AND status != 'cancelled'");
                        if ($checkBookingStmt) {
                            $checkBookingStmt->bind_param("ii", $eventIdForInsert, $_SESSION['user_id']);
                            $checkBookingStmt->execute();
                            $bookingCheckResult = $checkBookingStmt->get_result();
                            $bookingCheckRow = $bookingCheckResult->fetch_assoc();
                            $checkBookingStmt->close();
                            
                            if ($bookingCheckRow['count'] > 0) {
                                $errorMessage = "You have already booked this event! You can book other events, but cannot book the same event twice.";
                            } else {
                                $stmt = $conn->prepare("INSERT INTO bookings 
                                    (booking_code, user_id, event_id, selected_location, preferred_date, guest_count, addons, notes, total_amount) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                                if ($stmt) {
                                    $guestCount = 100;
                                    $addonsJson = json_encode($metaPayload);

                                    $decorationLabel = ucfirst($decoration);
                                    $notes = "Decoration Package: {$decorationLabel} (₹" . number_format($decorationCost, 2) . ")";

                                    // Bind parameters
                                    $stmt->bind_param(
                                        "siississd",
                                        $bookingCode,
                                        $_SESSION['user_id'],
                                        $eventIdForInsert,
                                        $selected_location,
                                        $preferredDate,
                                        $guestCount,
                                        $addonsJson,
                                        $notes,
                                        $totalAmount
                                    );

                                    if ($stmt->execute()) {
                                        $_SESSION['pending_payment'] = [
                                            'booking_code' => $bookingCode,
                                            'amount' => $totalAmount
                                        ];

                                        header("Location: peyment.php?booking=" . urlencode($bookingCode) . "&amount=" . urlencode(number_format($totalAmount, 2, '.', '')));
                                        exit();
                                    } else {
                                        $errorMessage = "Unable to save registration. Please try again.";
                                    }

                                    $stmt->close();
                                } else {
                                    $errorMessage = "Booking system error. Please try later.";
                                }
                            }
                        }
                    }
                }
                }
            }
        }
    }
}

$imagePath = $event && !empty($event['image_path'])
    ? "../" . ltrim($event['image_path'], './')
    : "https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=60";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration - Event Ease</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout.css?v=2">
    <style>
        :root {
            --primary: #5a2ca0;
            --primary-dark: #431f75;
            --bg: #f5f3ff;
        }
        * {
            box-sizing: border-box;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: var(--bg);
            margin: 0;
        }
        .registration-wrapper {
            min-height: calc(100vh - 120px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            margin-top: 20px;
        }
        .registration-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 28px;
            padding: 30px;
            box-shadow: 0 20px 45px rgba(90,44,160,0.12);
            border: 1px solid rgba(90,44,160,0.08);
        }
        .registration-card h2 {
            margin-bottom: 20px;
            color: var(--primary-dark);
        }
        .form-control,
        .form-select {
            border-radius: 14px;
            border: 1px solid rgba(90,44,160,0.2);
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(90,44,160,0.15);
        }
        .action-buttons {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 25px;
        }
        .btn-cancel {
            flex: 1;
            border-radius: 14px;
            border: 1px solid rgba(90,44,160,0.2);
            background: transparent;
            color: var(--primary-dark);
            padding: 12px;
        }
        .btn-confirm {
            flex: 1;
            border-radius: 14px;
            border: none;
            background: var(--primary);
            color: #fff;
            padding: 12px;
            font-weight: 600;
        }
        .event-mini {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 16px;
            background: rgba(90,44,160,0.05);
            margin-bottom: 20px;
        }
        .event-mini img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 14px;
        }
        .event-mini span {
            display: block;
            font-size: 13px;
            color: #6f6f6f;
        }


    </style>
</head>
<body>

  <?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="registration-wrapper">
    <div class="registration-card">
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$event): ?>
            <div class="text-center">
                <h4 class="text-muted">Select an event to register</h4>
                <p class="text-muted">Go back to the events list and choose "Register".</p>
                <a href="events.php" class="btn btn-confirm text-center">Browse Events</a>
            </div>
        <?php elseif ($alreadyBooked): ?>
            <div class="text-center">
                <div class="alert alert-warning" role="alert">
                    <i class="fa-solid fa-exclamation-triangle fa-2x mb-3"></i>
                    <h4>Already Booked</h4>
                    <p>You have already booked this event! You can book other events, but cannot book the same event twice.</p>
                </div>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="events.php" class="btn btn-confirm">Browse Other Events</a>
                    <a href="mybooking.php" class="btn btn-cancel">View My Bookings</a>
                </div>
            </div>
        <?php else: ?>
            <div class="event-mini">
                <img src="<?= htmlspecialchars($imagePath); ?>" alt="<?= htmlspecialchars($event['title']); ?>">
                <div>
                    <strong><?= htmlspecialchars($event['title']); ?></strong>
                    <span><?= !empty($event['event_date']) ? date('d M Y', strtotime($event['event_date'])) : 'Date flexible'; ?></span>
                </div>
            </div>

            <h2>Registration</h2>
            <form method="POST" novalidate>
                <input type="hidden" name="event_id" value="<?= htmlspecialchars($eventKey); ?>">
                
                <div class="mb-3">
                    <label class="form-label">Select Event Location <span class="required-star">*</span></label>
                    <select name="selected_location" class="form-select" required>
                        <option value="" disabled <?= !isset($_POST['selected_location']) ? 'selected' : ''; ?>>Choose a location...</option>
                        <?php
                        $rawLocations = $event['available_locations'] ?? '';
                        $locationOptions = array_filter(array_map('trim', explode('|', $rawLocations)));

                        foreach ($locationOptions as $option):
                            if (empty($option)) continue;
                            $isSelected = ($_POST['selected_location'] ?? '') === $option;
                        ?>
                            <option value="<?= htmlspecialchars($option); ?>" <?= $isSelected ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Select Your Date <span class="required-star">*</span></label>
                    <input type="date" name="preferred_date" class="form-control" required 
                           min="<?= date('Y-m-d'); ?>" 
                           max="<?= htmlspecialchars($event['event_date']); ?>"
                           value="<?= htmlspecialchars($_POST['preferred_date'] ?? ''); ?>">
                    <small class="text-muted">Available until <?= date('d M Y', strtotime($event['event_date'])); ?></small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Food Package</label>
                    <select name="food_package" class="form-select">
                        <option value="simple" <?= ($_POST['food_package'] ?? '') === 'simple' ? 'selected' : ''; ?>>Simple Food (+₹3,000)</option>
                        <option value="premium" <?= ($_POST['food_package'] ?? '') === 'premium' ? 'selected' : ''; ?>>Premium Food (+₹5,000)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Pickup Service</label>
                    <select name="pickup_service" id="pickup_service" class="form-select">
                        <option value="no" <?= ($_POST['pickup_service'] ?? '') === 'no' ? 'selected' : ''; ?>>No</option>
                        <option value="yes" <?= ($_POST['pickup_service'] ?? '') === 'yes' ? 'selected' : ''; ?>>Yes (+₹4,000)</option>
                    </select>
                </div>
                <div class="mb-3" id="pickup_address_container" style="display: <?= (($_POST['pickup_service'] ?? 'no') === 'yes') ? 'block' : 'none'; ?>;">
                    <label class="form-label">Pickup Address <span class="required-star">*</span></label>
                    <textarea name="event_address" id="event_address" rows="2" class="form-control" placeholder="Enter your pickup address" <?= (($_POST['pickup_service'] ?? 'no') === 'yes') ? 'required' : ''; ?>><?= htmlspecialchars($_POST['event_address'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Enter your name</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($_POST['full_name'] ?? ''); ?>" placeholder="Your full name">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email ID <span class="required-star">*</span></label>
                    <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($userEmail); ?>" placeholder="you@example.com" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}" required readonly style="background-color: #f0f0f0; cursor: not-allowed;">
                    <small class="text-muted">This is your registered email address</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Decoration Package</label>
                    <select name="decoration" class="form-select">
                        <option value="none" <?= ($_POST['decoration'] ?? '') === 'none' ? 'selected' : ''; ?>>No decoration (included)</option>
                        <option value="low" <?= ($_POST['decoration'] ?? '') === 'low' ? 'selected' : ''; ?>>Low (+₹5,000)</option>
                        <option value="medium" <?= ($_POST['decoration'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium (+₹10,000)</option>
                        <option value="high" <?= ($_POST['decoration'] ?? '') === 'high' ? 'selected' : ''; ?>>High (+₹15,000)</option>
                    </select>
                </div>
                <div class="action-buttons">
                    <a href="events.php" class="btn btn-cancel text-center">Cancel</a>
                    <button type="submit" class="btn btn-confirm">Confirm</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Show/hide pickup address field based on pickup service selection
    document.addEventListener('DOMContentLoaded', function() {
        const pickupService = document.getElementById('pickup_service');
        const pickupAddressContainer = document.getElementById('pickup_address_container');
        const eventAddress = document.getElementById('event_address');
        
        function togglePickupAddress() {
            if (pickupService.value === 'yes') {
                pickupAddressContainer.style.display = 'block';
                eventAddress.setAttribute('required', 'required');
            } else {
                pickupAddressContainer.style.display = 'none';
                eventAddress.removeAttribute('required');
                eventAddress.value = '';
            }
        }
        
        // Check on page load
        togglePickupAddress();
        
        // Check on change
        pickupService.addEventListener('change', togglePickupAddress);
        


        // Enhanced email validation
        const emailInput = document.getElementById('email');
        // Enhanced email validation - Logic removed as per request to rely on browser validation
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        }
    });
</script>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <script src="assets/js/layout.js?v=2"></script>
  <script>
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
    });

    function validateField(input) {
        let errorContainer = input.parentNode.querySelector('.validation-error');
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.className = 'validation-error';
            // CSS for validation-error is in layout.css or needs to be inline if not
            errorContainer.style.color = '#dc3545';
            errorContainer.style.fontSize = '13px';
            errorContainer.style.marginTop = '6px';
            input.parentNode.appendChild(errorContainer);
        }
        
        let errorMessage = '';
        if (input.hasAttribute('required')) {
             if (!input.value.trim()) {
                let labelText = 'This field';
                const label = input.closest('div').querySelector('label');
                if (label) {
                    labelText = label.textContent.replace('*', '').trim();
                }
                errorMessage = `${labelText} is required`;
            } else if (input.type === 'email') {
                const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                if (!emailPattern.test(input.value.trim())) {
                    errorMessage = 'Please enter a valid email address';
                }
            }
        }
        
        if (errorMessage) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            
            // Special handling for location grid
            if (input.id === 'selected_location_input') {
                const grid = document.getElementById('locationOptionsGrid');
                if (grid) grid.style.borderColor = '#dc3545';
                const locErr = document.getElementById('location-error');
                if (locErr) {
                    locErr.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${errorMessage}`;
                    locErr.style.display = 'block';
                    return false;
                }
            }
            
            errorContainer.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${errorMessage}`;
            errorContainer.style.display = 'block';

            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                input.classList.remove('is-invalid');
                if (input.value.trim()) {
                    input.classList.add('is-valid');
                }
                errorContainer.style.display = 'none';
                errorContainer.innerHTML = '';
            }, 3000);

            return false;
        } else {
            input.classList.remove('is-invalid');
            if (input.value.trim()) {
                input.classList.add('is-valid');
            }
            
            // Special handling for location grid
            if (input.id === 'selected_location_input') {
                const grid = document.getElementById('locationOptionsGrid');
                if (grid) grid.style.borderColor = 'rgba(90,44,160,0.1)';
                const locErr = document.getElementById('location-error');
                if (locErr) {
                    locErr.style.display = 'none';
                    locErr.innerHTML = '';
                }
            }

            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';
            return true;
        }
    }
  </script>
</body>
</html>
