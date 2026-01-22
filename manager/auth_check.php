<?php
// Common authentication check for all manager pages
// Include this file at the top of each manager page after session_start() and config.php

if (!isset($_SESSION['manager_id'])) {
    header("Location: login.php");
    exit();
}

$managerId = (int) $_SESSION['manager_id'];

// Verify manager exists in database
$checkManager = $conn->prepare("SELECT id, name FROM managers WHERE id = ? LIMIT 1");
if ($checkManager) {
    $checkManager->bind_param("i", $managerId);
    $checkManager->execute();
    $result = $checkManager->get_result();
    
    if ($result->num_rows === 0) {
        // Manager doesn't exist, destroy session and redirect
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    $managerData = $result->fetch_assoc();
    $managerName = $managerData['name'] ?? 'Manager';
    $checkManager->close();
} else {
    // Database error, redirect to login
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

