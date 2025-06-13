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
  <title>Manage Admin | Attendify+</title>

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
      <h2 class="m-0">Manage Admin</h2>
      <div class="d-flex gap-2">
        <a href="" class="btn btn-success btn-sm">
          <i data-lucide="user-plus" class="me-1"></i>Create Admin
        </a>
      </div>
    </div>
</div>


<div class="container mt-5">

    <div class="input-group mb-3">
    <input type="text" id="search" class="form-control" placeholder="Search">
    <button class="btn btn-primary">Search</button>
    </div>

    <table class="table table-bordered table-hover">
      <thead class="table-dark">
        <tr>
          <th>UserId</th>
          <th>UserName</th>
          <th>Email</th>
          <th>Password</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>1</td>
          <td>Admin</td>
          <td>admin@mail.com</td>
          <td>Admin</td>
          <td>
            <button class="btn btn-sm btn-primary">Edit</button>
            <button class="btn btn-sm btn-danger">Delete</button>
          </td>
        </tr>
        <tr>
          <td>2</td>
           <td>Admin</td>
          <td>admin@mail.com</td>
          <td>Admin</td>
          <td>
            <button class="btn btn-sm btn-primary">Edit</button>
            <button class="btn btn-sm btn-danger">Delete</button>
          </td>
        </tr>
        <tr>
          <td>3</td>
          <td>Admin</td>
          <td>admin@mail.com</td>
          <td>Admin</td>
          <td>
            <button class="btn btn-sm btn-primary">Edit</button>
            <button class="btn btn-sm btn-danger">Delete</button>
          </td>
        </tr>
        <tr>
          <td>4</td>
          <td>Admin</td>
          <td>admin@mail.com</td>
          <td>Admin</td>
          <td>
            <button class="btn btn-sm btn-primary">Edit</button>
            <button class="btn btn-sm btn-danger">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</body>

</html>