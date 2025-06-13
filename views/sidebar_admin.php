<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div id="sidebar" class="sidebar">
  <div class="p-3">
    <h5 class="sidebar-title mb-3 ">Menu</h5>
    <nav class="nav flex-column">
      <a class="nav-link <?= ($currentPage == 'dashboard_admin.php') ? 'active' : '' ?>" href="dashboard_admin.php">
        <i data-lucide="layout-dashboard" class="me-2"></i>Dashboard
      </a>

      <a class="nav-link <?= ($currentPage == 'manage_students.php') ? 'active' : '' ?>" href="manage_student.php">
        <i data-lucide="users" class="me-2"></i>Manage Students
      </a>

      <a class="nav-link <?= ($currentPage == 'manage_teachers.php') ? 'active' : '' ?>" href="manage_teacher.php">
        <i data-lucide="user-check" class="me-2"></i>Manage Teachers
      </a>

      <a class="nav-link <?= ($currentPage == 'manage_admins.php') ? 'active' : '' ?>" href="manage_admin.php">
        <i data-lucide="shield" class="me-2"></i>Manage Admins
      </a>

      <a class="nav-link <?= ($currentPage == 'seminar_analytics.php') ? 'active' : '' ?>" href="seminar_analytics.php">
        <i data-lucide="pie-chart" class="me-2"></i>Seminar Analytics
      </a>

      <a class="nav-link <?= ($currentPage == 'full_analytics.php') ? 'active' : '' ?>" href="full_analytics.php">
        <i data-lucide="bar-chart-3" class="me-2"></i>Overall Analytics
      </a>

      <a class="nav-link <?= ($currentPage == 'profile_admin.php') ? 'active' : '' ?>" href="profile_admin.php">
        <i data-lucide="user-cog" class="me-2"></i>Profile
      </a>

      <hr class="text-white-50 my-3">
      <a class="nav-link text-danger" href="../logout.php">
        <i data-lucide="log-out" class="me-2"></i>Logout
      </a>
    </nav>
  </div>
</div>