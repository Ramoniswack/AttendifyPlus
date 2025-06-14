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

  <!-- Add Teacher Modal -->
  <div id="exampleModal" class="modal show d-none position-static modal-below-navbar" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <<div class="modal-dialog modal-lg">
>
      <div class="modal-content p-4">
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="exampleModalLabel">Add Teacher</h1>
          <button type="button" class="btn-close" onclick="document.getElementById('exampleModal').classList.add('d-none')" aria-label="Close"></button>
        </div>
          <form id="addTeacherForm" method="POST" action="add_teacher_process.php">
  <div class="mb-3">
    <label for="teacherName" class="form-label">Full Name</label>
    <input type="text" class="form-control" id="teacherName" name="teacherName" required>
  </div>

  <div class="mb-3">
    <label for="teacherID" class="form-label">Teacher ID</label>
    <input type="text" class="form-control" id="teacherID" name="teacherID" required>
  </div>

  <div class="mb-3">
    <label for="email" class="form-label">Email</label>
    <input type="email" class="form-control" id="email" name="email" required>
  </div>

  <div class="mb-3">
    <label for="phone" class="form-label">Phone Number</label>
    <input type="text" class="form-control" id="phone" name="phone">
  </div>

  <div class="mb-3">
    <label for="department" class="form-label">Department</label>
    <select class="form-select" id="department" name="department" required>
      <option value="">Select Department</option>
      <option value="Computer">Computer</option>
      <option value="Management">Management</option>
      <option value="Science">Science</option>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Gender</label><br>
    <div class="form-check form-check-inline">
      <input class="form-check-input" type="radio" name="gender" id="genderMale" value="Male" required>
      <label class="form-check-label" for="genderMale">Male</label>
    </div>
    <div class="form-check form-check-inline">
      <input class="form-check-input" type="radio" name="gender" id="genderFemale" value="Female">
      <label class="form-check-label" for="genderFemale">Female</label>
    </div>
    <div class="form-check form-check-inline">
      <input class="form-check-input" type="radio" name="gender" id="genderOther" value="Other">
      <label class="form-check-label" for="genderOther">Other</label>
    </div>
  </div>

  <div class="mb-3">
    <label for="address" class="form-label">Address</label>
    <textarea class="form-control" id="address" name="address" rows="2"></textarea>
  </div>

  <div class="mb-3">
    <label for="password" class="form-label">Password</label>
    <input type="password" class="form-control" id="password" name="password" required>
  </div>

  <div class="mb-3">
    <label for="confirmPassword" class="form-label">Confirm Password</label>
    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" onclick="document.getElementById('exampleModal').classList.add('d-none')">Close</button>
    <button type="submit" class="btn btn-primary">Save changes</button>
  </div>
</form>

      </div>
    </div>
  </div>

  <div class="container mt-4">
    <div class="row g-3">
      <!-- View Teachers -->
      <div class="col-md-12">
        <div class="card text-center p-4 equal-height-card h-100">
          <i data-lucide="eye" class="text-primary" style="height: 40px;"></i>
          <h5 class="mt-3">Manage Teachers</h5>
          <p class="mb-3">All teacher records in the system.</p>

          <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
            <input type="text" id="searchInput" class="form-control form-control-sm w-auto" placeholder="Search by ID or Name">
            <button onclick="searchTable()" class="btn btn-outline-secondary btn-sm">Search</button>
            <button type="button" class="btn btn-primary btn-add" onclick="document.getElementById('exampleModal').classList.remove('d-none')">
              Add
            </button>
            <button onclick="editTable()" class="btn btn-outline-secondary btn-sm">Edit</button>
            <button onclick="deleteTable()" class="btn btn-outline-secondary btn-sm">Delete</button>
          </div>

        </div>
      </div>
    </div>
  </div>


 
  
          <!-- Table -->
          <div class="table-responsive mt-2">
   
            <table class="table table-bordered table-striped" id="teacherTable">
              <thead class="table-light">
                <tr>
                  <th>LoginID</th>
                  <th>FullName</th>
                   <th>Phone</th>
                  <th>DepartmentID</th>
                  <th>Status</th>
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

 
</body>

</html>
