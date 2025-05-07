<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "attendifyplus_db";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("❌ DB connection failed: " . $conn->connect_error);
}
?>
