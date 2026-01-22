<?php
// Common authentication check for all admin pages
// Include this file at the top of each admin page after session_start() and config.php

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$adminId = (int) $_SESSION['admin_id'];

// Verify admin exists in database
$checkAdmin = $conn->prepare("SELECT id, name FROM admins WHERE id = ? LIMIT 1");
if ($checkAdmin) {
    $checkAdmin->bind_param("i", $adminId);
    $checkAdmin->execute();
    $result = $checkAdmin->get_result();
    
    if ($result->num_rows === 0) {
        // Admin doesn't exist, destroy session and redirect
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    $adminData = $result->fetch_assoc();
    $adminName = $adminData['name'] ?? 'Admin';
    $checkAdmin->close();
} else {
    // Database error, redirect to login
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

