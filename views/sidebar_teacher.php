<?php
// sidebar_teacher.php (updated to prevent session_start() error)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<div id="sidebar" class="sidebar">
  <ul class="nav flex-column">
    <li><a href="dashboard_teacher.php">Dashboard</a></li>
    <li><a href="my_subjects_students.php">Subjects & Students</a></li>
    <li><a href="upload_slides.php">Upload Slides</a></li>
    <li><a href="attendance.php">Attendance</a></li>
    <li><a href="view_assignments.php">Assignments</a></li>
    <li><a href="analytics.php">Analytics</a></li>
    <li><a href="../logout.php">Logout</a></li>
  </ul>
</div>
