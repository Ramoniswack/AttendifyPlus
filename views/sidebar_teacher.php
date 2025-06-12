<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div id="sidebar" class="sidebar">
  <div class="p-3">
    <h5 class="text-white mb-3">Menu</h5>
    <nav class="nav flex-column">
      <a class="nav-link <?= ($currentPage == 'dashboard_teacher.php') ? 'active' : '' ?>" href="dashboard_teacher.php">
        <i data-lucide="home" class="me-2"></i>Dashboard
      </a>
      <a class="nav-link <?= ($currentPage == 'my_subjects_students.php') ? 'active' : '' ?>" href="my_subjects_students.php">
        <i data-lucide="graduation-cap" class="me-2"></i>Subjects & Students
      </a>
      <a class="nav-link <?= ($currentPage == 'attendance.php') ? 'active' : '' ?>" href="attendance.php">
        <i data-lucide="calendar-check" class="me-2"></i>Take Attendance
      </a>
      <a class="nav-link <?= ($currentPage == 'attendance_report.php') ? 'active' : '' ?>" href="attendance_report.php">
        <i data-lucide="clipboard-list" class="me-2"></i>Attendance Reports
      </a>
      <a class="nav-link <?= ($currentPage == 'upload_slides.php') ? 'active' : '' ?>" href="upload_slides.php">
        <i data-lucide="upload" class="me-2"></i>Upload Slides
      </a>
      <a class="nav-link <?= ($currentPage == 'view_assignments.php') ? 'active' : '' ?>" href="view_assignments.php">
        <i data-lucide="file-text" class="me-2"></i>Assignments
      </a>
      <a class="nav-link <?= ($currentPage == 'analytics.php') ? 'active' : '' ?>" href="analytics.php">
        <i data-lucide="bar-chart" class="me-2"></i>Analytics
      </a>
      <a class="nav-link <?= ($currentPage == 'profile_teacher.php') ? 'active' : '' ?>" href="profile_teacher.php">
        <i data-lucide="user" class="me-2"></i>Profile
      </a>
      <hr class="text-white-50 my-3">
      <a class="nav-link text-danger" href="../logout.php">
        <i data-lucide="log-out" class="me-2"></i>Logout
      </a>
    </nav>
  </div>
</div>