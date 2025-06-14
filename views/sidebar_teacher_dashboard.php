<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div id="sidebar" class="sidebar">
  <div class="p-3">
    <h5 class="sidebar-title mb-3">Menu</h5>
    <nav class="nav flex-column">
      <a class="nav-link <?= ($currentPage == 'dashboard_teacher.php') ? 'active' : '' ?>" href="dashboard_teacher.php">
        <i data-lucide="layout-dashboard" class="me-2"></i>Dashboard
      </a>

      <a class="nav-link <?= ($currentPage == 'attendance.php') ? 'active' : '' ?>" href="attendance.php">
        <i data-lucide="check-square" class="me-2"></i>Attendance
      </a>


      <a class="nav-link <?= ($currentPage == 'my_subjects.php') ? 'active' : '' ?>" href="my_subjects.php">
        <i data-lucide="book-open" class="me-2"></i>My Subjects & Students
      </a>

      <a class="nav-link <?= ($currentPage == 'upload_slides.php') ? 'active' : '' ?>" href="upload_slides.php">
        <i data-lucide="upload-cloud" class="me-2"></i>Upload Slides
      </a>

      <a class="nav-link <?= ($currentPage == 'attendance_report.php') ? 'active' : '' ?>" href="attendance_report.php">
        <i data-lucide="clipboard-list" class="me-2"></i>Attendance Report
      </a>

      <hr class="text-white-50 my-3">
      <a class="nav-link text-danger" href="../logout.php">
        <i data-lucide="log-out" class="me-2"></i>Logout
      </a>

    </nav>
  </div>
</div>
