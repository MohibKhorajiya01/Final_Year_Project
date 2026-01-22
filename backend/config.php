

<?php
$servername = "127.0.0.1";  // localhost bhi chalega
$username   = "root";
$password   = "";
$dbname     = "ee";
$port       = 3306;         // yahan my.ini wala port daalo

date_default_timezone_set('Asia/Kolkata');

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
    