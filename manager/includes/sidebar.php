<?php
// Common sidebar for all manager pages
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="brand">Event Ease Manager</div>
    <a class="nav-link-custom <?= $currentPage == 'index.php' ? 'active' : '' ?>" href="index.php">Dashboard</a>
    <a class="nav-link-custom <?= $currentPage == 'pending_approval.php' ? 'active' : '' ?>" href="pending_approval.php">Pending Approvals</a>
    <a class="nav-link-custom <?= $currentPage == 'manage_events.php' ? 'active' : '' ?>" href="manage_events.php">Manage Events</a>
    <a class="nav-link-custom <?= $currentPage == 'manage_booking.php' ? 'active' : '' ?>" href="manage_booking.php">Booking Hub</a>
    <a class="nav-link-custom <?= $currentPage == 'manage_addOns.php' ? 'active' : '' ?>" href="manage_addOns.php">Add-on & Pickup</a>
    <a class="nav-link-custom <?= $currentPage == 'manage_gallary.php' ? 'active' : '' ?>" href="manage_gallary.php">Gallery Uploads</a>
    <a class="nav-link-custom <?= $currentPage == 'manage_payments.php' ? 'active' : '' ?>" href="manage_payments.php">Payment History</a>
    <a class="nav-link-custom <?= $currentPage == 'manage_feedback.php' ? 'active' : '' ?>" href="manage_feedback.php">Event Ratings</a>
    <a class="nav-link-custom" href="logout.php">Logout</a>
</aside>

