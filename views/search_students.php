<?php
include '../config/db_config.php';

$department = $_GET['department'] ?? '';
$joinYear = $_GET['joinYear'] ?? '';
$name = $_GET['name'] ?? '';

$sql = "SELECT s.*, d.DepartmentName FROM students_tbl s 
        JOIN departments_tbl d ON s.DepartmentID = d.DepartmentID 
        WHERE 1=1";

$params = [];

if ($department !== '') {
    $sql .= " AND s.DepartmentID = ?";
    $params[] = $department;
}

if ($joinYear !== '') {
    $sql .= " AND s.JoinYear = ?";
    $params[] = $joinYear;
}

if ($name !== '') {
    $sql .= " AND s.FullName LIKE ?";
    $params[] = "%$name%";
}

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $types = str_repeat("s", count($params));
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();
$data = [];

while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>
