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

$successMessage = "";
$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $price = trim($_POST['price'] ?? '0');
    $status = trim($_POST['status'] ?? 'active');
    $description = trim($_POST['description'] ?? '');

    if ($title === '' || $category === '' || $type === '') {
        $errorMessage = "Please fill all required fields.";
    } else {
        if (!tableExists($conn, 'addons')) {
            $createSql = "CREATE TABLE IF NOT EXISTS addons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(120) NOT NULL,
                category VARCHAR(80) NOT NULL,
                type VARCHAR(80) NOT NULL,
                price DECIMAL(10,2) DEFAULT 0,
                status VARCHAR(20) DEFAULT 'active',
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB";
            $conn->query($createSql);
        }

        if (tableExists($conn, 'addons')) {
            $stmt = $conn->prepare("INSERT INTO addons (title, category, type, price, status, description) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $priceVal = $price !== '' ? (float) $price : 0;
                $stmt->bind_param("ssssss", $title, $category, $type, $priceVal, $status, $description);
                if ($stmt->execute()) {
                    $successMessage = "Add-on created successfully!";
                } else {
                    $errorMessage = "Failed to save add-on. Please try again.";
                }
                $stmt->close();
            } else {
                $errorMessage = "Unable to prepare statement for insert.";
            }
        }
    }
}

if (isset($_GET['delete']) && isset($_GET['id']) && tableExists($conn, 'addons')) {
    $deleteId = (int) $_GET['id'];
    $conn->query("DELETE FROM addons WHERE id = $deleteId");
    header("Location: AddOns.php?deleted=1");
    exit();
}

$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$addons = [];
$categories = [];

if (tableExists($conn, 'addons')) {
    $categoryResult = $conn->query("SELECT DISTINCT category FROM addons WHERE category IS NOT NULL AND category != ''");
    if ($categoryResult) {
        while ($row = $categoryResult->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }

    $query = "SELECT * FROM addons WHERE 1=1";
    if ($search !== '') {
        $safe = $conn->real_escape_string($search);
        $query .= " AND (title LIKE '%$safe%' OR type LIKE '%$safe%')";
    }
    if ($categoryFilter !== '') {
        $safeCat = $conn->real_escape_string($categoryFilter);
        $query .= " AND category = '$safeCat'";
    }
    if ($statusFilter !== '') {
        $safeStatus = $conn->real_escape_string($statusFilter);
        $query .= " AND status = '$safeStatus'";
    }
    $query .= " ORDER BY created_at DESC";

    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $addons[] = $row;
        }
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
  <title>Manage Add-ons - Event Ease Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    :root {
      --primary: #5a2ca0;
      --primary-dark: #431f75;
      --bg: #f5f3ff;
    }
    body {
      background: var(--bg);
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
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
    .brand { font-size: 22px; font-weight: 700; color: var(--primary); margin-bottom: 32px; }
    .nav-link-custom {
      display: block;
      padding: 12px 14px;
      border-radius: 12px;
      color: #6c6c6c;
      text-decoration: none;
      margin-bottom: 8px;
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
    .card-box {
      background: #fff;
      border-radius: 20px;
      padding: 25px;
      box-shadow: 0 10px 25px rgba(90,44,160,0.08);
    }
    .form-label span { color: #dc3545 !important; font-weight: 700; }
    .required-star { color: #dc3545 !important; font-weight: 700; }
    .badge-status {
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      text-transform: capitalize;
    }
    /* Validation Error Styles */
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
    
    /* Hide number input arrows */
    input[type=number]::-webkit-inner-spin-button, 
    input[type=number]::-webkit-outer-spin-button { 
        -webkit-appearance: none; 
        margin: 0; 
    }
    input[type=number] {
        -moz-appearance: textfield;
    }

    @keyframes errorFadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @media (max-width: 992px) {
      .sidebar { position: relative; width: 100%; height: auto; }
      .main-content { margin-left: 0; padding: 20px; }
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
  <a class="nav-link-custom active" href="AddOns.php">Add Ons</a>
  <a class="nav-link-custom" href="gallery.php">Gallery Content</a>
  <a class="nav-link-custom" href="feedback.php">Event Ratings</a>
  <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

<main class="main-content">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-1">Manage Add-ons</h2>
      <small class="text-muted">Create and monitor all event add-on services</small>
    </div>
  </div>

  <?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      ✅ <?= htmlspecialchars($successMessage); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      ❌ <?= htmlspecialchars($errorMessage); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      ✅ Add-on deleted successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card-box mb-4">
    <h5 class="mb-3">Add New Add-on</h5>
    <form method="POST" action="" class="row g-3" novalidate>
      <div class="col-md-4">
        <label class="form-label">Title <span class="required-star">*</span></label>
        <input type="text" name="title" class="form-control" placeholder="Premium Buffet" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Category <span class="required-star">*</span></label>
        <input type="text" name="category" class="form-control" placeholder="Food / Decor / Entertainment" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Type <span class="required-star">*</span></label>
        <input type="text" name="type" class="form-control" placeholder="Catering / Floral / Music" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Price (₹) <span class="required-star">*</span></label>
        <input type="number" step="0.01" name="price" class="form-control" placeholder="55000">
      </div>
      <div class="col-md-3">
        <label class="form-label">Status <span class="required-star">*</span></label>
        <select name="status" class="form-select">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Description <span class="required-star">*</span></label>
        <textarea name="description" rows="2" class="form-control" placeholder="Short notes for managers..."></textarea>
      </div>
      <div class="col-12 text-end">
        <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none;">Save Add-on</button>
      </div>
    </form>
  </div>

  <div class="card-box mb-4">
    <h5 class="mb-3">Filter Add-ons</h5>
    <form method="GET" action="" class="row g-3">
      <div class="col-md-4">
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
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">All Status</option>
          <option value="active" <?= $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
          <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
      </div>
      <div class="col-md-1 d-flex align-items-end">
        <button type="submit" class="btn btn-outline-primary w-100">Go</button>
      </div>
    </form>
  </div>

  <div class="card-box">
    <?php if (empty($addons)): ?>
      <div class="text-center py-5">
        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">No add-ons found</h5>
        <p class="text-muted">Use the form above to create your first service.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead class="table-light">
            <tr>
              <th>Title</th>
              <th>Category</th>
              <th>Type</th>
              <th>Price</th>
              <th>Status</th>
              <th>Created</th>
              <th>Description</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($addons as $addon): ?>
              <?php
                $status = strtolower($addon['status'] ?? 'inactive');
                $statusClass = $status === 'active' ? 'bg-success text-white' : 'bg-secondary text-white';
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($addon['title']); ?></strong></td>
                <td><?= htmlspecialchars($addon['category']); ?></td>
                <td><?= htmlspecialchars($addon['type']); ?></td>
                <td>₹<?= number_format((float) ($addon['price'] ?? 0), 2); ?></td>
                <td><span class="badge-status <?= $statusClass; ?>"><?= ucfirst($status); ?></span></td>
                <td><?= isset($addon['created_at']) ? date('d M Y', strtotime($addon['created_at'])) : '-'; ?></td>
                <td>
                  <small class="text-muted"><?= htmlspecialchars($addon['description'] ?? ''); ?></small>
                </td>
                <td>
                  <a href="?delete=1&id=<?= (int) $addon['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this add-on?');">
                    <i class="fas fa-trash"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="mt-3 text-muted">
        <small>Total Services: <strong><?= count($addons); ?></strong></small>
      </div>
    <?php endif; ?>
  </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Validation Script
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form.row.g-3[novalidate]'); 
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
            */    });
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
        if (input.hasAttribute('required')) {
             if (!input.value.trim()) {
                const label = input.closest('div').querySelector('label');
                let labelText = label ? label.textContent.replace('*', '').trim() : 'This field';
                errorMessage = `${labelText} is required`;
            }
        }
        
        if (errorMessage) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
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
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';
            return true;
        }
    }
</script>
</body>
</html>
