<?php

if (session_status() === PHP_SESSION_NONE) session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div id="sidebar" class="sidebar">
  <div class="p-3">
    <h5 class="sidebar-title mb-3">Menu</h5>
    <nav class="nav flex-column">
      <a class="nav-link <?= ($currentPage == 'dashboard_student.php') ? 'active' : '' ?>" href="dashboard_student.php">
        <i data-lucide="layout-dashboard" class="me-2"></i>Dashboard
      </a>
      <a class="nav-link <?= ($currentPage == 'submit_assignment.php') ? 'active' : '' ?>" href="submit_assignment.php">
        <i data-lucide="file-plus" class="me-2"></i>Assignments
      </a>
      <a class="nav-link <?= ($currentPage == 'scan_qr.php') ? 'active' : '' ?>" href="scan_qr.php">
        <i data-lucide="qr-code" class="me-2"></i>Scan QR
      </a>

      <a class="nav-link <?= ($currentPage == 'view_materials.php') ? 'active' : '' ?>" href="view_materials.php">
        <i data-lucide="folder-open" class="me-2"></i>View Materials
      </a>
      <a class="nav-link <?= ($currentPage == 'attendance_history.php') ? 'active' : '' ?>" href="attendance_history.php">
        <i data-lucide="calendar-check" class="me-2"></i>My Attendance
      </a>
      <a class="nav-link <?= ($currentPage == 'my_subjects_student.php') ? 'active' : '' ?>" href="my_subjects_student.php">
        <i data-lucide="book-open" class="me-2"></i>My Subjects
      </a>
      <a class="nav-link <?= ($currentPage == 'profile_student.php') ? 'active' : '' ?>" href="profile_student.php">
        <i data-lucide="user-cog" class="me-2"></i>Profile
      </a>
      <hr class="text-white-50 my-3">
      <a class="nav-link text-danger" href="../../logout.php">
        <i data-lucide="log-out" class="me-2"></i>Logout
      </a>
    </nav>
  </div>
</div>