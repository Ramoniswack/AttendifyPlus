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
  <link rel="stylesheet" href="../assets/css/manage_teacher.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

  <!-- JS Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="../assets/js/lucide.min.js"></script>
  <script src="../assets/js/manage_teacher.js" defer></script>
</head>

<body>
  <!-- Sidebar -->
  <?php include 'sidebar_teacher.php'; ?>

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

  <div class="container mt-4">
    <div class="row g-3">

      <!-- View Teachers -->
      <div class="col-md-12">
        <div class="card text-center p-4 equal-height-card h-100">
          <i data-lucide="eye" class="text-primary" style="height: 40px;"></i>
          <h5 class="mt-3">Manage Teachers</h5>
          <p class="mb-3">All teacher records in the system.</p>

          <!-- Buttons and search input in a row -->
          <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">

            <!-- Search input and button -->
            <input type="text" id="searchInput" class="form-control form-control-sm w-auto" placeholder="Search by ID or Name">
            <button onclick="searchTable()" class="btn btn-outline-secondary btn-sm">Search</button>
            <button onclick="addTable()" class="btn btn-outline-secondary btn-sm">Add</button>
            <button onclick="editTable()" class="btn btn-outline-secondary btn-sm">Edit</button>
            <button onclick="deleteTable()" class="btn btn-outline-secondary btn-sm">Delete</button>
          </div>

          <!-- Table -->
          <div class="table-responsive mt-2">
            <table class="table table-bordered table-striped" id="teacherTable">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Department</th>
                  <th>Email</th>
                </tr>
              </thead>
              <tbody>
                <!-- Example Data (replace with PHP or dynamic data) -->
                <tr>
                  <td>101</td>
                  <td>John Doe</td>
                  <td>Computer Science</td>
                  <td>john@example.com</td>
                </tr>
                <tr>
                  <td>102</td>
                  <td>Jane Smith</td>
                  <td>Mathematics</td>
                  <td>jane@example.com</td>
                </tr>
                <!-- More rows... -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add/Edit Teacher Modal -->
<div class="modal fade" id="teacherModal" tabindex="-1" aria-labelledby="teacherModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="teacherForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="teacherModalLabel">Add/Edit Teacher</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="teacherId" />
          <div class="mb-3">
            <label>Name</label>
            <input type="text" name="name" id="teacherName" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>Department</label>
            <input type="text" name="department" id="teacherDepartment" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" id="teacherEmail" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>
