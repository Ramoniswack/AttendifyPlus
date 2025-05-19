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
  <meta charset="UTF-8">
  <title>Admin Dashboard | Attendify+</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/teacherDashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .equal-height-card {
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    body {
      overflow-x: hidden;
    }
  </style>
</head>

<body>
  <?php include 'sidebar_admin.php'; ?>

  <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
    <div class="container-fluid">
      <button class="btn text-white me-2" id="sidebarToggle">☰</button>
      <a class="navbar-brand" href="#">Attendify+ | Admin</a>

      <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
        <span class="navbar-text text-white">Welcome, <?php echo htmlspecialchars($_SESSION['Username']); ?></span>
        <button class="btn btn-outline-light btn-sm" onclick="toggleTheme()" title="Toggle Theme">Theme</button>
        <a href="../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <h2 class="m-0">Admin Dashboard</h2>
    </div>

    <div class="row row-cols-1 row-cols-md-3 g-4 mt-5">
      <div class="col">
        <div class="card p-4 text-center equal-height-card">
          <h5>Add Student</h5>
          <p>Create new student accounts for batch and department.</p>
          <a href="add_student.php" class="btn btn-primary btn-sm mt-auto">Add Student</a>
        </div>
      </div>

      <div class="col">
        <div class="card p-4 text-center equal-height-card">
          <h5>Add Teacher</h5>
          <p>Create and manage teacher accounts and subjects.</p>
          <a href="add_teacher.php" class="btn btn-primary btn-sm mt-auto">Add Teacher</a>
        </div>
      </div>

      <div class="col">
        <div class="card p-4 text-center equal-height-card">
          <h5>Update Details</h5>
          <p>Edit information for existing users (students/teachers).</p>
          <a href="update_details.php" class="btn btn-primary btn-sm mt-auto">Update</a>
        </div>
      </div>

      <div class="col">
        <div class="card p-4 text-center equal-height-card">
          <h5>Analytics</h5>
          <p>View attendance statistics and trends.</p>
          <a href="analytics.php" class="btn btn-primary btn-sm mt-auto">View Analytics</a>
        </div>
      </div>
    </div>
  </div>

  <script src="../assets/js/login.js"></script>
  <script>
    const toggleBtn = document.getElementById("sidebarToggle");
    const sidebar = document.getElementById("sidebar");

    toggleBtn.addEventListener("click", () => {
      sidebar.classList.toggle("active");
      document.body.classList.toggle("sidebar-open");
    });
  </script>
</body>
</html>
