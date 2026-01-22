<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../backend/config.php';

$userId = $_SESSION['user_id'];
$bookingCode = isset($_GET['booking_code']) ? trim($_GET['booking_code']) : '';

$errorMessage = '';
$booking = null;

if (empty($bookingCode)) {
    $errorMessage = "Invalid request. Please select a valid ticket.";
} else {
    // Fetch booking
    if ($conn) {
        $stmt = $conn->prepare("
            SELECT b.*, 
                   e.title as event_title, 
                   e.location, 
                   e.event_date,
                   e.category,
                   u.name as user_name,
                   u.email as user_email,
                   u.phone as user_phone
            FROM bookings b
            LEFT JOIN events e ON b.event_id = e.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE b.booking_code = ? AND b.user_id = ?
            LIMIT 1
        ");
        
        if ($stmt) {
            $stmt->bind_param("si", $bookingCode, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $booking = $result->fetch_assoc();
            $stmt->close();
        }
    }

    if (!$booking) {
        $errorMessage = "Ticket not found.";
    } elseif ($booking['status'] !== 'approved') {
        $errorMessage = "Booking not approved yet. Please wait for approval.";
    } elseif ($booking['payment_status'] !== 'paid') {
        $errorMessage = "Payment not complete. Please complete payment first.";
    }
}

$eventDate = $booking ? (!empty($booking['preferred_date']) ? $booking['preferred_date'] : $booking['event_date']) : '';

// Parse addons
$addons = [];
if ($booking && !empty($booking['addons'])) {
    $addons = json_decode($booking['addons'], true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket<?= $booking ? ' - ' . htmlspecialchars($booking['booking_code']) : ''; ?> - Event Ease</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout.css?v=2">
    
    <style>
        :root {
            --primary: #5a2ca0;
            --primary-dark: #431f75;
            --bg: #f5f3ff;
        }
        
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .ticket-wrapper { padding: 0 !important; margin: 0 !important; min-height: auto !important; }
            .ticket { box-shadow: none !important; border: 2px solid #000 !important; }
            header, footer, nav, .navbar { display: none !important; }
        }
        
        body {
            background: var(--bg);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .ticket-wrapper {
            min-height: calc(100vh - 120px);
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .ticket {
            max-width: 520px;
            width: 100%;
            margin: 0 auto;
            background: white;
            border: 2px solid var(--primary);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(90, 44, 160, 0.12);
        }
        
        .ticket-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .ticket-header h2 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        .ticket-code {
            background: white;
            color: var(--primary);
            padding: 6px 16px;
            margin: 10px auto 0;
            display: inline-block;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        .ticket-body {
            padding: 20px;
        }
        
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            width: 120px;
            font-weight: 600;
            color: #666;
            font-size: 12px;
        }
        
        .info-value {
            flex: 1;
            color: #333;
            font-size: 13px;
        }
        
        .info-value.highlight {
            color: var(--primary);
            font-weight: 600;
        }
        
        .ticket-footer {
            background: #f8f9fa;
            padding: 14px 20px;
            text-align: center;
            border-top: 2px dashed var(--primary);
        }
        
        .ticket-footer .brand {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.95rem;
            margin-bottom: 2px;
        }
        
        .btn-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-custom {
            padding: 12px 28px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-download {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            box-shadow: 0 4px 15px rgba(90, 44, 160, 0.3);
        }
        
        .btn-download:hover {
            box-shadow: 0 6px 20px rgba(90, 44, 160, 0.4);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-radius: 16px;
            font-weight: 600;
            font-size: 11px;
        }
        
        .error-card {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(90, 44, 160, 0.15);
        }
        
        .error-card .error-icon {
            width: 80px;
            height: 80px;
            background: rgba(220, 53, 69, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .error-card .error-icon i {
            font-size: 36px;
            color: #dc3545;
        }
        
        .error-card h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .error-card p {
            color: #666;
            margin-bottom: 24px;
        }
        
        @media (max-width: 576px) {
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            .info-label {
                width: 100%;
            }
            .ticket-header h2 {
                font-size: 1.4rem;
            }
            .ticket-code {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="ticket-wrapper">
    <?php if ($errorMessage): ?>
        <div class="error-card">
            <div class="error-icon">
                <i class="fa-solid fa-ticket"></i>
            </div>
            <h3>Ticket Unavailable</h3>
            <p><?= htmlspecialchars($errorMessage); ?></p>
            <a href="tickets.php" class="btn-custom btn-back">
                <i class="fa-solid fa-arrow-left"></i> Back to Tickets
            </a>
        </div>
    <?php else: ?>
        <div class="ticket" id="ticket">
            <div class="ticket-header">
                <h2><?= htmlspecialchars($booking['event_title']); ?></h2>
                <div class="ticket-code">#<?= htmlspecialchars($booking['booking_code']); ?></div>
            </div>
            
            <div class="ticket-body">
                <div class="info-row">
                    <div class="info-label"><i class="fa-regular fa-calendar me-2"></i>Booking Date</div>
                    <div class="info-value highlight"><?= !empty($eventDate) ? date('d M Y', strtotime($eventDate)) : 'To Be Announced' ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label"><i class="fa-solid fa-location-dot me-2"></i>Venue</div>
                    <div class="info-value"><?= htmlspecialchars($booking['location'] ?? 'Location TBA'); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label"><i class="fa-regular fa-user me-2"></i>Guest Name</div>
                    <div class="info-value"><?= htmlspecialchars($booking['user_name']); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label"><i class="fa-regular fa-envelope me-2"></i>Email</div>
                    <div class="info-value"><?= htmlspecialchars($booking['user_email']); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label"><i class="fa-solid fa-phone me-2"></i>Phone</div>
                    <div class="info-value"><?= htmlspecialchars($booking['user_phone']); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label"><i class="fa-solid fa-users me-2"></i>Guests</div>
                    <div class="info-value"><?= $booking['guest_count'] ?? 1 ?> Person(s)</div>
                </div>
                
                <div class="info-row">
                    <div class="info-label"><i class="fa-regular fa-clock me-2"></i>Booked On</div>
                    <div class="info-value"><?= date('d M Y, h:i A', strtotime($booking['created_at'])); ?></div>
                </div>

                <?php if (!empty($addons)): ?>
                    <div class="mt-4 p-3 rounded" style="background: var(--bg); border: 1px solid rgba(90, 44, 160, 0.1);">
                        <div class="fw-bold mb-2 text-primary" style="font-size: 13px;"><i class="fa-solid fa-list-check me-2"></i>Services & Add-ons</div>
                        <div class="row g-2">
                            <?php if (!empty($addons['food_package'])): ?>
                                <div class="col-12 border-bottom pb-1 mb-1" style="font-size: 12px;">
                                    <span class="text-muted">Food Package:</span> <span class="fw-bold"><?= ucfirst($addons['food_package']); ?> Package</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($addons['pickup_service']) && $addons['pickup_service'] === 'yes'): ?>
                                <div class="col-12 border-bottom pb-1 mb-1" style="font-size: 12px;">
                                    <span class="text-muted">Pickup Service:</span> <span class="fw-bold">Yes</span>
                                    <?php if (!empty($addons['pickup_address'])): ?>
                                        <br><small class="text-muted">Address: <?= htmlspecialchars($addons['pickup_address']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($addons['decoration']) && $addons['decoration'] !== 'none'): ?>
                                <div class="col-12 pb-1" style="font-size: 12px;">
                                    <span class="text-muted">Decoration:</span> <span class="fw-bold"><?= ucfirst($addons['decoration']); ?> Theme</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="info-row mt-3 pt-3 border-top">
                    <div class="info-label"><i class="fa-solid fa-indian-rupee-sign me-2"></i>Total Amount</div>
                    <div class="info-value highlight" style="font-size: 1.1rem;">₹<?= number_format($booking['total_amount'], 2); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label"><i class="fa-solid fa-circle-check me-2"></i>Status</div>
                    <div class="info-value">
                        <span class="status-badge"><i class="fa-solid fa-check me-1"></i> APPROVED</span>
                    </div>
                </div>
            </div>
            
            <div class="ticket-footer">
                <p class="brand mb-1">
                    <i class="fa-solid fa-calendar-check me-1"></i> Event Ease
                </p>
                <small style="color: #888;">Please present this ticket at the venue for entry</small>
            </div>
        </div>
        
        <div class="btn-actions no-print">
            <button onclick="downloadTicket()" class="btn-custom btn-download">
                <i class="fa-solid fa-download"></i> Download Ticket
            </button>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="assets/js/layout.js?v=2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<?php if ($booking): ?>
<script>
function downloadTicket() {
    const ticket = document.getElementById('ticket');
    const bookingCode = '<?= htmlspecialchars($booking['booking_code']); ?>';
    
    // Show loading state
    const downloadBtn = document.querySelector('.btn-download');
    const originalText = downloadBtn.innerHTML;
    downloadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
    downloadBtn.disabled = true;
    
    html2canvas(ticket, {
        scale: 2,
        backgroundColor: '#ffffff',
        useCORS: true
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'px',
            format: [canvas.width, canvas.height]
        });
        
        pdf.addImage(imgData, 'PNG', 0, 0, canvas.width, canvas.height);
        pdf.save('Ticket_' + bookingCode + '.pdf');
        
        // Restore button
        downloadBtn.innerHTML = originalText;
        downloadBtn.disabled = false;
    }).catch(err => {
        console.error('Error generating PDF:', err);
        downloadBtn.innerHTML = originalText;
        downloadBtn.disabled = false;
        alert('Error generating PDF. Please try again.');
    });
}
</script>
<?php endif; ?>

</body>
</html>
