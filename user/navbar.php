<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
.nav-links a.active {
    color: #ffc107 !important;
    font-weight: 600;
    position: relative;
}
.nav-links a.active::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    right: 0;
    height: 2px;
    background: #ffc107;
}
</style>
<nav class="site-nav">
    <a href="<?= $_SERVER['PHP_SELF']; ?>" class="logo-container" style="text-decoration: none; color: inherit; cursor: pointer;">
        <img src="assets/1.logo.png" alt="EventEase - Event Booking System" class="logo-img">
        <span class="logo-text">EventEase</span>
    </a>

    <div class="right-section">
        <div class="nav-links">
            <a href="index.php" class="<?= ($current_page == 'index.php') ? 'active' : '' ?>">Home</a>
            <a href="events.php" class="<?= ($current_page == 'events.php') ? 'active' : '' ?>">Events</a>
            <a href="gallery.php" class="<?= ($current_page == 'gallery.php') ? 'active' : '' ?>">Gallery</a>
            <a href="contact.php" class="<?= ($current_page == 'contact.php') ? 'active' : '' ?>">Contact</a>
        </div>

        <div class="profile-container" onclick="toggleDropdown()">
            <div class="profile-pill">
                <img src="assets/1.logo.png" alt="Event Ease" class="avatar-circle">
                <div class="profile-meta">
                    <span class="label"><?= isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['user_name'] ?? 'My Desk') : 'Guest'; ?></span>
                    <small class="sub-label"><?= isset($_SESSION['user_id']) ? 'Access everything' : 'Login or Register'; ?></small>
                </div>
                <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="dropdown" id="profileDropdown">
                <div class="dropdown-header">
                    <img src="assets/1.logo.png" alt="Event Ease" class="avatar-circle large">
                    <div>
                        <strong><?= isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['user_name'] ?? 'Event Ease User') : 'Event Ease Guest'; ?></strong>
                        <p><?= isset($_SESSION['user_id']) ? 'Plan, pay & track' : 'Join us to get started'; ?></p>
                    </div>
                </div>
                <div class="dropdown-links">
                    <a href="mybooking.php"><i class="fa-solid fa-calendar-check"></i> My Bookings</a>
                    <a href="tickets.php"><i class="fa-solid fa-ticket"></i> Download Ticket</a>
                    <a href="support.php"><i class="fa-solid fa-headset"></i> Concierge Support</a>
                    <a href="feedback.php"><i class="fa-solid fa-comment-dots"></i> Share Feedback</a>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
                        <a href="register.php"><i class="fa-solid fa-user-plus"></i> Register</a>
                    <?php endif; ?>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown-footer">
                        <a href="#" onclick="confirmLogout(event)"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <i class="fa-solid fa-bars hamburger" onclick="toggleMobileNav()"></i>
    </div>
</nav>

<div class="mobile-nav" id="mobileNav">
    <a href="index.php">Home</a>
    <a href="events.php">Events</a>
    <a href="gallery.php">Gallery</a>
    <a href="contact.php">Contact</a>
</div>

