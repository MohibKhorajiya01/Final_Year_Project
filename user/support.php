<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once __DIR__ . '/../backend/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support & Help - Event Ease</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout.css?v=2">
    <style>
        :root {
            --primary: #5a2ca0;
            --primary-dark: #431f75;
            --bg: #f8f9fa;
        }
        body {
            background: var(--bg);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
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
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ddd;
        }
        .page-header h4 {
            color: #333;
            margin-bottom: 5px;
            font-size: 1.6rem;
            font-weight: 600;
        }
        .support-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .support-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border: none;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .accordion-button {
            font-weight: 600;
            color: var(--primary-dark);
            background-color: white;
            padding: 20px;
            font-size: 1.1rem;
        }
        .accordion-button:not(.collapsed) {
            color: var(--primary);
            background-color: #f5f3ff;
            box-shadow: inset 0 -1px 0 rgba(0,0,0,.125);
        }
        .accordion-button:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(90, 44, 160, 0.25);
        }
        .accordion-body {
            padding: 25px;
            background: #fff;
            color: #555;
            line-height: 1.6;
        }
        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 10px;
            transition: background 0.2s;
        }
        .contact-item:hover {
            background: #f8f9fa;
        }
        .contact-icon {
            width: 40px;
            height: 40px;
            background: #f5f3ff;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        .service-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .service-list li {
            padding: 10px 0;
            border-bottom: 1px dashed #eee;
            display: flex;
            align-items: center;
        }
        .service-list li:last-child {
            border-bottom: none;
        }
        .service-list li i {
            color: var(--primary);
            margin-right: 10px;
            width: 20px;
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
        <h4><i class="fa-solid fa-headset"></i> Concierge Support</h4>
        <p class="text-muted small">Find answers, contact info, and services below</p>
    </div>

    <div class="support-header">
        <h1 class="fw-bold mb-2" style="font-size: 2rem;">How can we help you?</h1>
        <p class="mb-0 opacity-75">We're here to assist you with all your event needs</p>
    </div>

    <div class="pb-5">
        
        <!-- Accordion Section -->
        <div class="accordion support-card" id="supportAccordion">
            
            <!-- 1. Contact Information -->
            <div class="accordion-item border-0">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#contactInfo">
                        <i class="fa-solid fa-headset me-3"></i> Contact Support
                    </button>
                </h2>
                <div id="contactInfo" class="accordion-collapse collapse show" data-bs-parent="#supportAccordion">
                    <div class="accordion-body">
                        <div class="contact-item">
                            <div class="contact-icon"><i class="fa-solid fa-user"></i></div>
                            <div>
                                <small class="text-muted d-block">Support Manager</small>
                                <strong>Event Ease Team</strong>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon"><i class="fa-solid fa-phone"></i></div>
                            <div>
                                <small class="text-muted d-block">Phone Number</small>
                                <strong>+91 98765 43210</strong>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon"><i class="fa-solid fa-envelope"></i></div>
                            <div>
                                <small class="text-muted d-block">Email Address</small>
                                <strong>eventease99@gmail.com</strong>
                            </div>
                        </div>
                        <div class="mt-3 text-center">
                            <a href="mailto:eventease99@gmail.com" class="btn btn-primary px-4 rounded-pill">
                                <i class="fa-regular fa-paper-plane me-2"></i> Send Email Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Our Services -->
            <div class="accordion-item border-0 border-top">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#servicesInfo">
                        <i class="fa-solid fa-wand-magic-sparkles me-3"></i> Our Services
                    </button>
                </h2>
                <div id="servicesInfo" class="accordion-collapse collapse" data-bs-parent="#supportAccordion">
                    <div class="accordion-body">
                        <p class="mb-3">We provide end-to-end event management services tailored to your needs:</p>
                        <ul class="service-list">
                            <li><i class="fa-solid fa-check-circle"></i> Wedding Planning & Coordination</li>
                            <li><i class="fa-solid fa-check-circle"></i> Corporate Events & Conferences</li>
                            <li><i class="fa-solid fa-check-circle"></i> Birthday Parties & Celebrations</li>
                            <li><i class="fa-solid fa-check-circle"></i> Catering & Food Services</li>
                            <li><i class="fa-solid fa-check-circle"></i> Venue Decoration & Styling</li>
                            <li><i class="fa-solid fa-check-circle"></i> Photography & Videography</li>
                            <li><i class="fa-solid fa-check-circle"></i> Sound & Lighting Setup</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 3. Office Location -->
            <div class="accordion-item border-0 border-top">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#locationInfo">
                        <i class="fa-solid fa-location-dot me-3"></i> Office Location
                    </button>
                </h2>
                <div id="locationInfo" class="accordion-collapse collapse" data-bs-parent="#supportAccordion">
                    <div class="accordion-body">
                        <div class="d-flex">
                            <div class="contact-icon"><i class="fa-solid fa-map-location-dot"></i></div>
                            <div>
                                <h5 class="mb-1">Event Ease HQ</h5>
                                <p class="mb-0 text-muted">
                                    123, Celebration Square,<br>
                                    Near City Center Mall,<br>
                                    Ahmedabad, Gujarat - 380001
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. FAQ -->
            <div class="accordion-item border-0 border-top">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqInfo">
                        <i class="fa-solid fa-circle-question me-3"></i> Frequently Asked Questions
                    </button>
                </h2>
                <div id="faqInfo" class="accordion-collapse collapse" data-bs-parent="#supportAccordion">
                    <div class="accordion-body">
                        <div class="mb-3">
                            <strong>How do I book an event?</strong>
                            <p class="small text-muted">Simply navigate to the 'Events' page, select your desired event type, and click 'Book Now'.</p>
                        </div>
                        <div class="mb-3">
                            <strong>Can I cancel my booking?</strong>
                            <p class="small text-muted">Yes, you can cancel from your 'My Bookings' page, subject to our cancellation policy.</p>
                        </div>
                        <div>
                            <strong>How do I contact a manager?</strong>
                            <p class="small text-muted">Once your booking is approved, you will be assigned a manager who will contact you directly.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/layout.js?v=2"></script>
</body>
</html>
