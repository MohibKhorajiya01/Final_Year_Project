<?php
// Common authentication check for all user pages
// Include this file at the top of each user page after session_start() and config.php

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];

// Verify user exists in database
$checkUser = $conn->prepare("SELECT id, name, status FROM users WHERE id = ? LIMIT 1");
if ($checkUser) {
    $checkUser->bind_param("i", $userId);
    $checkUser->execute();
    $result = $checkUser->get_result();
    
    if ($result->num_rows === 0) {
        // User doesn't exist, destroy session and redirect
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    $userData = $result->fetch_assoc();
    
    // Check if user is active/verified
    if (isset($userData['status']) && $userData['status'] != 1) {
        // User is not active, destroy session and redirect
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    $userName = $userData['name'] ?? 'User';
    $checkUser->close();
} else {
    // Database error, redirect to login
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

