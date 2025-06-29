<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\sidebar_teacher_dashboard.php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div id="sidebar" class="sidebar">
  <div class="p-3">
    <h5 class="sidebar-title mb-3">Menu</h5>
    <nav class="nav flex-column">
      <!-- Dashboard -->
      <a class="nav-link <?= ($currentPage == 'dashboard_teacher.php') ? 'active' : '' ?>" href="../teacher/dashboard_teacher.php">
        <i data-lucide="layout-dashboard" class="me-2"></i>Dashboard
      </a>

      <!-- Attendance Management -->
      <a class="nav-link <?= ($currentPage == 'attendance.php') ? 'active' : '' ?>" href="attendance.php">
        <i data-lucide="clipboard-check" class="me-2"></i>Mark Attendance
      </a>

      <!-- My Subjects -->
      <a class="nav-link <?= ($currentPage == 'my_subjects.php') ? 'active' : '' ?>" href="my_subjects.php">
        <i data-lucide="book-open" class="me-2"></i>My Subjects
      </a>

      <!-- Attendance Reports -->
      <a class="nav-link <?= ($currentPage == 'attendance_report.php') ? 'active' : '' ?>" href="attendance_report.php">
        <i data-lucide="bar-chart-3" class="me-2"></i>Attendance Reports
      </a>

      <!-- Upload Materials -->
      <a class="nav-link <?= ($currentPage == 'upload_materials.php') ? 'active' : '' ?>" href="upload_materials.php">
        <i data-lucide="upload-cloud" class="me-2"></i>Upload Materials
      </a>

      <!-- My Students -->
      <a class="nav-link <?= ($currentPage == 'students.php') ? 'active' : '' ?>" href="students.php">
        <i data-lucide="users" class="me-2"></i>My Students
      </a>

      <!-- Class Analytics -->
      <a class="nav-link <?= ($currentPage == 'class_analytics.php') ? 'active' : '' ?>" href="class_analytics.php">
        <i data-lucide="pie-chart" class="me-2"></i>Class Analytics
      </a>

      <!-- Profile Settings -->
      <a class="nav-link <?= ($currentPage == 'profile_teacher.php') ? 'active' : '' ?>" href="profile_teacher.php">
        <i data-lucide="user-cog" class="me-2"></i>Profile
      </a>

      <!-- Divider -->
      <hr class="text-white-50 my-3">

      <!-- Logout -->
      <a class="nav-link text-danger" href="../logout.php">
        <i data-lucide="log-out" class="me-2"></i>Logout
      </a>
    </nav>
  </div>
</div>