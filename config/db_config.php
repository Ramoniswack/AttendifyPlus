<?php

date_default_timezone_set('Asia/Kathmandu');

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "attendifyplus_fainal";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(" DB connection failed: " . $conn->connect_error);
}


// Set MySQL timezone to Nepal time
$conn->query("SET time_zone = '+05:45'");

// Set charset
$conn->set_charset("utf8");
?>