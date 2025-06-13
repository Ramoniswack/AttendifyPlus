<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
  header("Location: login.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard | Attendify+</title>

  <!-- CSS -->
  <link rel="stylesheet" href="../assets/css/dashboard_admin.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

  <!-- JS Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="../assets/js/lucide.min.js"></script>
  <script src="../assets/js/dashboard_admin.js" defer></script>
</head>

<body>
  <!-- Sidebar -->
  <?php include 'sidebar_admin.php'; ?>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--accent-light);">
    <div class="container-fluid">
      <button class="btn text-white me-2" id="sidebarToggle">
        <span style="font-size: 24px;">☰</span>
      </button>
      <a class="navbar-brand d-flex align-items-center gap-2" href="#">
        <span>Attendify+ | Admin</span>
      </a>
      <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
        <span class="navbar-text text-white">Welcome, <?php echo htmlspecialchars($_SESSION['Username']); ?></span>
        <button class="btn btn-outline-light btn-sm" onclick="toggleTheme()" title="Toggle Theme">
          <i data-lucide="moon" class="me-1"></i>Theme
        </button>
        <a href="../logout.php" class="btn btn-outline-light btn-sm">
          <i data-lucide="log-out" class="me-1"></i>Logout
        </a>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container-fluid dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <h2 class="m-0">Admin Dashboard</h2>
      <div class="d-flex gap-2">
        <a href="manage_student.php" class="btn btn-success btn-sm">
          <i data-lucide="user-plus" class="me-1"></i>Manage Student
        </a>
        <a href="full_analytics.php" class="btn btn-outline-info btn-sm">
          <i data-lucide="bar-chart" class="me-1"></i>View Analytics
        </a>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-4 mb-4">
      <div class="col-md-3">
        <div class="card text-center p-4">
          <i data-lucide="users" class="text-primary"></i>
          <h6 class="mt-2">Total Students</h6>
          <h3 class="mb-1">350</h3>
          <small class="text-success">+18 this month</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center p-4">
          <i data-lucide="user-check" class="text-primary"></i>
          <h6 class="mt-2">Total Teachers</h6>
          <h3 class="mb-1">25</h3>
          <small class="text-success">+3 hired</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center p-4">
          <i data-lucide="shield" class="text-primary"></i>
          <h6 class="mt-2">Active Admins</h6>
          <h3 class="mb-1">3</h3>
          <small class="text-muted">1 new added</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center p-4">
          <i data-lucide="calendar" class="text-primary"></i>
          <h6 class="mt-2">Seminar Events</h6>
          <h3 class="mb-1">7</h3>
          <small class="text-info">+2 this semester</small>
        </div>
      </div>
    </div>

    <!-- Chart Section -->
    <div class="row g-4 mb-4">
      <div class="col-md-12">
        <div class="card p-4" style="height: 400px;">
          <h5 class="mb-3">Administrative Overview</h5>
          <div style="position: relative; height: 300px;">
            <canvas id="adminAnalyticsChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Links -->
    <div class="row row-cols-1 row-cols-md-3 g-4">
      <div class="col">
        <div class="card text-center p-4 equal-height-card">
          <i data-lucide="users" class="text-primary"></i>
          <h5 class="mt-2">Manage Students</h5>
          <p class="mb-3">View, update or delete student records.</p>
          <a href="manage_students.php" class="btn btn-outline-primary btn-sm mt-auto">
            <i data-lucide="settings" class="me-1"></i>Manage
          </a>
        </div>
      </div>
      <div class="col">
        <div class="card text-center p-4 equal-height-card">
          <i data-lucide="user-check" class="text-primary"></i>
          <h5 class="mt-2">Manage Teachers</h5>
          <p class="mb-3">Add, update or remove teachers from the system.</p>
          <a href="manage_teachers.php" class="btn btn-outline-primary btn-sm mt-auto">
            <i data-lucide="settings" class="me-1"></i>Manage
          </a>
        </div>
      </div>
      <div class="col">
        <div class="card text-center p-4 equal-height-card">
          <i data-lucide="shield" class="text-primary"></i>
          <h5 class="mt-2">Manage Admins</h5>
          <p class="mb-3">Assign and monitor admin-level access.</p>
          <a href="manage_admins.php" class="btn btn-outline-primary btn-sm mt-auto">
            <i data-lucide="settings" class="me-1"></i>Manage
          </a>
        </div>
      </div>
    </div>

  </div>
  </div>

  <!-- Mobile Blur Overlay -->
  <div class="sidebar-overlay"></div>
</body>

</html>