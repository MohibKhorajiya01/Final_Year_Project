<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Fetch approved bookings for ticket download
$bookings = [];
if ($conn && $isLoggedIn) {
    $stmt = $conn->prepare("
        SELECT b.*, 
               e.title as event_title, 
               e.location, 
               e.event_date as original_event_date,
               e.category,
               u.name as user_name,
               u.email as user_email,
               u.phone as user_phone
        FROM bookings b
        LEFT JOIN events e ON b.event_id = e.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.user_id = ? AND b.status = 'approved' AND b.payment_status = 'paid'
        ORDER BY b.created_at DESC
    ");
    
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
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
    <title>Download Tickets - Event Ease</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout.css?v=2">
    <style>
        body {
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .page-container {
            flex: 1 0 auto;
            padding: 20px;
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }
        .page-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ddd;
        }
        .page-header h4 {
            color: #333;
            margin-bottom: 5px;
            font-size: 1.6rem;
            font-weight: 600;
        }
        .ticket-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .ticket-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .ticket-header h5 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 1.4rem;
            font-weight: 600;
        }
        .ticket-code {
            color: #666;
            font-family: monospace;
            font-size: 0.95rem;
        }
        .ticket-info {
            margin-bottom: 15px;
        }
        .info-item {
            padding: 8px 0;
            display: flex;
            border-bottom: 1px solid #f5f5f5;
        }
        .info-item strong {
            width: 130px;
            color: #666;
            font-weight: 600;
        }
        .info-item span {
            color: #333;
        }
        .ticket-footer {
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .approved-badge {
            background: #28a745;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .btn-download {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .btn-download:hover {
            background: #0056b3;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .empty-state i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 15px;
        }
        .site-footer {
            flex-shrink: 0;
            margin-top: auto;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="page-container">
    <div class="page-header">
        <h4><i class="fa-solid fa-ticket"></i> My Tickets</h4>
        <p class="text-muted small">Download your event tickets for approved bookings</p>
    </div>

    <?php if (!$isLoggedIn): ?>
        <div class="empty-state">
            <i class="fa-solid fa-lock"></i>
            <h5>Login Required</h5>
            <p class="small text-muted mb-3">Please login to view and download your event tickets.</p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="login.php" class="btn btn-sm btn-primary" style="background: var(--primary); border: none;">Login</a>
                <a href="register.php" class="btn btn-sm btn-outline-primary">Register</a>
            </div>
        </div>
    <?php elseif (empty($bookings)): ?>
        <div class="empty-state">
            <i class="fa-regular fa-ticket"></i>
            <h5>No Tickets Available</h5>
            <p class="text-muted mb-3">You don't have any approved bookings yet.</p>
            <p class="text-muted small">Tickets will appear here once your bookings are approved by the admin.</p>
            <a href="events.php" class="btn btn-primary mt-3" style="background: var(--primary); border: none;">Browse Events</a>
        </div>
    <?php else: ?>
        <?php foreach ($bookings as $booking): 
            $imagePath = !empty($booking['image_path']) ? "../" . ltrim($booking['image_path'], './') : '';
            $eventDate = !empty($booking['preferred_date']) ? $booking['preferred_date'] : $booking['original_event_date'];
            $addons = !empty($booking['addons']) ? json_decode($booking['addons'], true) : [];
        ?>
            <div class="ticket-card">
                <div class="ticket-header">
                    <div>
                        <h5><?= htmlspecialchars($booking['event_title'] ?? 'Event'); ?></h5>
                        <div class="ticket-code">#<?= htmlspecialchars($booking['booking_code']); ?></div>
                    </div>
                </div>
                
                <div class="ticket-info">
                    <div class="info-item">
                        <strong>Booking Date:</strong>
                        <span class="fw-bold text-primary"><?= !empty($eventDate) ? date('d M Y', strtotime($eventDate)) : 'TBA' ?></span>
                    </div>
                    
                    <div class="info-item">
                        <strong>Venue:</strong>
                        <span><?= htmlspecialchars($booking['location'] ?? 'Location TBA'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <strong>Guest Name:</strong>
                        <span><?= htmlspecialchars($booking['user_name'] ?? 'Guest'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <strong>Total Paid:</strong>
                        <span>₹<?= number_format($booking['total_amount'], 2); ?></span>
                    </div>
                </div>
                
                <div class="ticket-footer">
                    <span class="approved-badge">Approved</span>
                    <button onclick='viewTicket(<?= json_encode($booking, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' 
                       class="btn-download">
                        View & Download Ticket
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Ticket Modal -->
<div class="modal fade" id="ticketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="ticket-wrapper" id="ticketContent">
                    <div class="ticket" id="ticket">
                        <div class="ticket-header" style="background: linear-gradient(135deg, #5a2ca0 0%, #431f75 100%); color: white; padding: 20px; text-align: center;">
                            <h2 id="modalEventTitle" style="margin: 0; font-size: 1.3rem; font-weight: 700;"></h2>
                            <div id="modalBookingCode" style="background: white; color: #5a2ca0; padding: 6px 16px; margin: 10px auto 0; display: inline-block; border-radius: 6px; font-family: monospace; font-size: 1rem; font-weight: bold;"></div>
                        </div>
                        
                        <div class="ticket-body" style="padding: 20px;">
                            <div class="info-row" style="display: flex; padding: 10px 0; border-bottom: 1px solid #f0f0f0; align-items: center;">
                                <div class="info-label" style="width: 120px; font-weight: 600; color: #666; font-size: 12px;"><i class="fa-regular fa-calendar me-2"></i>Date</div>
                                <div class="info-value" id="modalEventDate" style="flex: 1; color: #5a2ca0; font-weight: 600;"></div>
                            </div>
                            
                            <div class="info-row" style="display: flex; padding: 10px 0; border-bottom: 1px solid #f0f0f0; align-items: center;">
                                <div class="info-label" style="width: 120px; font-weight: 600; color: #666; font-size: 12px;"><i class="fa-solid fa-location-dot me-2"></i>Venue</div>
                                <div class="info-value" id="modalLocation"></div>
                            </div>
                            
                            <div class="info-row" style="display: flex; padding: 10px 0; border-bottom: 1px solid #f0f0f0; align-items: center;">
                                <div class="info-label" style="width: 120px; font-weight: 600; color: #666; font-size: 12px;"><i class="fa-regular fa-user me-2"></i>Guest</div>
                                <div class="info-value" id="modalUserName"></div>
                            </div>

                            <div class="info-row" style="display: flex; padding: 10px 0; border-bottom: 1px solid #f0f0f0; align-items: center;">
                                <div class="info-label" style="width: 120px; font-weight: 600; color: #666; font-size: 12px;"><i class="fa-solid fa-users me-2"></i>Guests</div>
                                <div class="info-value" id="modalGuestCount"></div>
                            </div>
                            
                            <div class="info-row mt-3 pt-3 border-top" style="display: flex; padding: 10px 0; align-items: center;">
                                <div class="info-label" style="width: 120px; font-weight: 600; color: #666; font-size: 12px;"><i class="fa-solid fa-indian-rupee-sign me-2"></i>Total</div>
                                <div class="info-value" id="modalTotalAmount" style="font-size: 1.1rem; font-weight: 700; color: #333;"></div>
                            </div>

                            <div id="modalAddons" class="mt-3 p-3 rounded" style="background: #f5f3ff; border: 1px solid rgba(90, 44, 160, 0.1); display: none;">
                                <div class="fw-bold mb-2 text-primary" style="font-size: 13px;"><i class="fa-solid fa-list-check me-2"></i>Included Services</div>
                                <div id="modalAddonsContent"></div>
                            </div>
                        </div>
                        
                        <div class="ticket-footer" style="background: #f8f9fa; padding: 14px 20px; text-align: center; border-top: 2px dashed #5a2ca0;">
                            <p class="mb-1" style="font-weight: 700; color: #5a2ca0; font-size: 0.95rem;">
                                <i class="fa-solid fa-calendar-check me-1"></i> Event Ease
                            </p>
                            <small style="color: #888;">Present this ticket at the venue</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-center border-0 pb-4">
                <button type="button" onclick="downloadPDF()" class="btn btn-primary px-4" id="btnDownload">
                    <i class="fa-solid fa-download me-2"></i> Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/layout.js?v=2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
let currentBookingCode = '';

function viewTicket(booking) {
    currentBookingCode = booking.booking_code;
    
    // Populate Modal
    document.getElementById('modalEventTitle').textContent = booking.event_title || 'Event';
    document.getElementById('modalBookingCode').textContent = '#' + booking.booking_code;
    
    const dateStr = booking.preferred_date || booking.original_event_date;
    const dateObj = new Date(dateStr);
    document.getElementById('modalEventDate').textContent = !isNaN(dateObj) ? dateObj.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : 'TBA';
    
    document.getElementById('modalLocation').textContent = booking.location || 'TBA';
    document.getElementById('modalUserName').textContent = booking.user_name || 'Guest';
    document.getElementById('modalGuestCount').textContent = (booking.guest_count || 1) + ' Person(s)';
    document.getElementById('modalTotalAmount').textContent = '₹' + parseFloat(booking.total_amount).toLocaleString('en-IN', {minimumFractionDigits: 2});

    // Handle Addons
    const addonsContainer = document.getElementById('modalAddons');
    const addonsContent = document.getElementById('modalAddonsContent');
    addonsContent.innerHTML = '';
    
    let hasAddons = false;
    let addons = {};
    
    try {
        if (booking.addons && typeof booking.addons === 'string') {
            addons = JSON.parse(booking.addons);
        } else if (booking.addons && typeof booking.addons === 'object') {
            addons = booking.addons;
        }
    } catch (e) { console.warn('Error parsing addons', e); }

    if (addons.food_package) {
        addonsContent.innerHTML += `<div class="mb-1" style="font-size: 12px;"><span class="text-muted">Food:</span> <strong>${addons.food_package}</strong></div>`;
        hasAddons = true;
    }
    if (addons.pickup_service === 'yes') {
        addonsContent.innerHTML += `<div class="mb-1" style="font-size: 12px;"><span class="text-muted">Pickup:</span> <strong>Yes</strong></div>`;
        if (addons.pickup_address) {
            addonsContent.innerHTML += `<div class="mb-1 ps-2" style="font-size: 12px;"><small class="text-muted">Address: ${addons.pickup_address}</small></div>`;
        }
        hasAddons = true;
    }
    if (addons.decoration && addons.decoration !== 'none') {
        addonsContent.innerHTML += `<div class="mb-1" style="font-size: 12px;"><span class="text-muted">Deco:</span> <strong>${addons.decoration}</strong></div>`;
        hasAddons = true;
    }

    addonsContainer.style.display = hasAddons ? 'block' : 'none';

    // Show Modal
    const modal = new bootstrap.Modal(document.getElementById('ticketModal'));
    modal.show();
}

function downloadPDF() {
    const element = document.getElementById('ticket');
    const btn = document.getElementById('btnDownload');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Generating...';
    btn.disabled = true;

    html2canvas(element, { scale: 2, useCORS: true, backgroundColor: '#ffffff' }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'px',
            format: [canvas.width, canvas.height]
        });

        pdf.addImage(imgData, 'PNG', 0, 0, canvas.width, canvas.height);
        pdf.save('Ticket_' + currentBookingCode + '.pdf');

        btn.innerHTML = originalText;
        btn.disabled = false;
    }).catch(err => {
        console.error(err);
        alert('Failed to generate PDF');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
</script>
</body>
</html>
