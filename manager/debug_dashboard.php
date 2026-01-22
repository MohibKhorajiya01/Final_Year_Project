<?php
// Debug script to check why manager dashboard shows 0 data
session_start();
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/auth_check.php';

echo "<h1>Manager Dashboard Debug Report</h1>";
echo "<hr>";
echo "<h2>Session Info:</h2>";
echo "Manager ID: <strong>" . $managerId . "</strong><br>";
echo "Manager Name: <strong>" . htmlspecialchars($managerName) . "</strong><br>";
echo "<hr>";

// Check if tables exist
echo "<h2>Table Existence Check:</h2>";
$tables = ['events', 'bookings', 'feedback', 'payments', 'users', 'managers'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $exists = $result && $result->num_rows > 0;
    echo "$table: " . ($exists ? "<span style='color:green'>EXISTS</span>" : "<span style='color:red'>NOT FOUND</span>") . "<br>";
}
echo "<hr>";

// Check manager's events
echo "<h2>Manager's Events Check:</h2>";
$result = $conn->query("SELECT COUNT(*) as count FROM events WHERE manager_id = $managerId");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total Events for Manager ID $managerId: <strong>" . $row['count'] . "</strong><br>";
    
    // Show all events
    $eventsResult = $conn->query("SELECT id, title, status, manager_id FROM events WHERE manager_id = $managerId");
    if ($eventsResult && $eventsResult->num_rows > 0) {
        echo "<h3>All Events:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Manager ID</th></tr>";
        while ($event = $eventsResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $event['id'] . "</td>";
            echo "<td>" . htmlspecialchars($event['title']) . "</td>";
            echo "<td>" . htmlspecialchars($event['status']) . "</td>";
            echo "<td>" . $event['manager_id'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red'>No events found for this manager!</p>";
    }
} else {
    echo "Error: " . $conn->error . "<br>";
}
echo "<hr>";

// Check status values in events
echo "<h2>Status Values in Events Table:</h2>";
$statusResult = $conn->query("SELECT DISTINCT status FROM events WHERE manager_id = $managerId");
if ($statusResult) {
    echo "Distinct status values for manager's events:<br>";
    while ($row = $statusResult->fetch_assoc()) {
        echo "- '" . htmlspecialchars($row['status']) . "'<br>";
    }
} else {
    echo "Error: " . $conn->error . "<br>";
}
echo "<hr>";

// Check bookings
echo "<h2>Manager's Bookings Check:</h2>";
$bookingsQuery = "
    SELECT COUNT(*) as count 
    FROM bookings b 
    JOIN events e ON b.event_id = e.id 
    WHERE e.manager_id = $managerId
";
$result = $conn->query($bookingsQuery);
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total Bookings: <strong>" . $row['count'] . "</strong><br>";
    
    // Show bookings with status
    $bookingsDetail = $conn->query("
        SELECT b.id, b.status, b.payment_status, b.total_amount, e.title 
        FROM bookings b 
        JOIN events e ON b.event_id = e.id 
        WHERE e.manager_id = $managerId 
        LIMIT 5
    ");
    if ($bookingsDetail && $bookingsDetail->num_rows > 0) {
        echo "<h3>Sample Bookings:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Booking ID</th><th>Status</th><th>Payment Status</th><th>Amount</th><th>Event</th></tr>";
        while ($booking = $bookingsDetail->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $booking['id'] . "</td>";
            echo "<td>" . htmlspecialchars($booking['status']) . "</td>";
            echo "<td>" . htmlspecialchars($booking['payment_status']) . "</td>";
            echo "<td>₹" . number_format($booking['total_amount'], 2) . "</td>";
            echo "<td>" . htmlspecialchars($booking['title']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:orange'>No bookings found!</p>";
    }
} else {
    echo "Error: " . $conn->error . "<br>";
}
echo "<hr>";

// Test the actual queries from dashboard
echo "<h2>Testing Dashboard Queries:</h2>";

// Test 1: Live Events
echo "<h3>1. Live Events Query:</h3>";
$query1 = "SELECT COUNT(*) as count FROM events WHERE manager_id = $managerId AND LOWER(status) = 'active'";
echo "Query: <code>" . htmlspecialchars($query1) . "</code><br>";
$result1 = $conn->query($query1);
if ($result1) {
    $row = $result1->fetch_assoc();
    echo "Result: <strong>" . $row['count'] . "</strong><br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Test 2: Without LOWER
echo "<h3>2. Live Events (Without LOWER):</h3>";
$query2 = "SELECT COUNT(*) as count FROM events WHERE manager_id = $managerId AND status = 'active'";
echo "Query: <code>" . htmlspecialchars($query2) . "</code><br>";
$result2 = $conn->query($query2);
if ($result2) {
    $row = $result2->fetch_assoc();
    echo "Result: <strong>" . $row['count'] . "</strong><br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Test 3: Case insensitive
echo "<h3>3. Live Events (Case Insensitive):</h3>";
$query3 = "SELECT COUNT(*) as count FROM events WHERE manager_id = $managerId AND (status = 'active' OR status = 'Active')";
echo "Query: <code>" . htmlspecialchars($query3) . "</code><br>";
$result3 = $conn->query($query3);
if ($result3) {
    $row = $result3->fetch_assoc();
    echo "Result: <strong>" . $row['count'] . "</strong><br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}
echo "<hr>";

// Check all managers
echo "<h2>All Managers in Database:</h2>";
$managersResult = $conn->query("SELECT id, name, email FROM managers");
if ($managersResult) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th></tr>";
    while ($mgr = $managersResult->fetch_assoc()) {
        $highlight = ($mgr['id'] == $managerId) ? " style='background-color:yellow;'" : "";
        echo "<tr$highlight>";
        echo "<td>" . $mgr['id'] . "</td>";
        echo "<td>" . htmlspecialchars($mgr['name']) . "</td>";
        echo "<td>" . htmlspecialchars($mgr['email']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "<hr>";

echo "<p><a href='index.php'>Back to Dashboard</a> | <a href='logout.php'>Logout</a></p>";
?>

