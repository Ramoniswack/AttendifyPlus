<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: login.php");
    exit();
}
include '../config/db_config.php';

// Fetch departments
$departments = [];
$sql = "SELECT DepartmentID, DepartmentName FROM departments_tbl ORDER BY DepartmentName";
$res = $conn->query($sql);
while ($d = $res->fetch_assoc()) {
    $departments[] = $d;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Manage Students | Attendify+</title>
  <link rel="stylesheet" href="../assets/css/manage_student.css"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <script src="../assets/js/lucide.min.js"></script>
  <script src="../assets/js/dashboard_admin.js" defer></script>
  <script src="../assets/js/manage_students.js" defer></script>
</head>
<body>
  <?php include 'sidebar_student.php'; ?>

  <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--accent-light);">
    <div class="container-fluid">
      <button class="btn text-white me-2" id="sidebarToggle"><span style="font-size:24px;">☰</span></button>
      <a class="navbar-brand" href="#">Attendify+ | Admin</a>
      <div class="d-flex ms-auto align-items-center gap-2">
        <span class="text-white">Welcome, <?=htmlspecialchars($_SESSION['Username'])?></span>
        <button class="btn btn-outline-light btn-sm" onclick="toggleTheme()">
          <i data-lucide="moon" me-2></i>Theme
        </button>
        <a href="../logout.php" class="btn btn-outline-light btn-sm">
          <i data-lucide="log-out"></i>
        </a>
      </div>
    </div>
  </nav>

  <div class="container-fluid dashboard-container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>Manage Students</h2>
      <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStudentModal">
        <i data-lucide="user-plus"></i> Add Student
      </button>
    </div>

    <div class="row mb-3 g-3 align-items-center">
      <div class="col-md-3">
        <label>Program</label>
        <select id="filterDepartment" class="form-select">
          <option value="">All</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?=htmlspecialchars($d['DepartmentID'])?>"><?=htmlspecialchars($d['DepartmentName'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label>Join Year</label>
        <input id="filterYear" type="number" class="form-control" placeholder="e.g. 2022">
      </div>
      <div class="col-md-4">
        <label>Search Name</label>
        <div class="input-group">
          <span class="input-group-text bg-white"><i data-lucide="search"></i></span>
          <input id="searchName" type="text" class="form-control" placeholder="Full or partial name" />
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped table-bordered align-middle">
        <thead class="table-light" id="studentsHeader" style="display:none;">
          <tr>
            <th>Full Name</th>
            <th>Department</th>
            <th>Batch</th>
            <th>Roll No</th>
            <th>Join Year</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="studentsBody"></tbody>
      </table>
    </div>
  </div>

  <div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog">
      <form id="addStudentForm" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Student</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2"><label>Name</label><input name="FullName" class="form-control" required></div>
          <div class="mb-2"><label>Program</label>
            <select name="DepartmentID" class="form-select" required>
              <?php foreach ($departments as $d): ?>
                <option value="<?=htmlspecialchars($d['DepartmentID'])?>"><?=htmlspecialchars($d['DepartmentName'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2"><label>Batch ID</label><input name="BatchID" type="number" class="form-control" required></div>
          <div class="mb-2"><label>Roll No</label><input name="RollNo" class="form-control" required></div>
          <div class="mb-2"><label>Join Year</label><input name="JoinYear" type="number" class="form-control" required></div>
          <div class="mb-2"><label>Status</label>
            <select name="Status" class="form-select"><option>active</option><option>inactive</option></select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">
            <i data-lucide="save"></i> Save
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="sidebar-overlay"></div>
</body>
</html>
