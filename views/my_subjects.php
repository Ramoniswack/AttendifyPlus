<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Subjects | Attendify+</title>

  <!-- CSS -->
  <link rel="stylesheet" href="../assets/css/dashboard_teacher.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

  <!-- JS Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="../assets/js/lucide.min.js"></script>
  <script src="../assets/js/dashboard_teacher.js" defer></script>
</head>

<body>
  <!-- Sidebar -->
  <?php include 'sidebar_teacher_dashboard.php'; ?>

  <!-- Navbar -->
  <?php include 'navbar_teacher.php'; ?>

  <!-- Main Content -->
  <div class="container-fluid dashboard-container">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap mb-4">
      <div>
        <h2 class="page-title"><i data-lucide="book-open"></i> My Subjects</h2>
        <p class="text-muted">Browse through your assigned subjects and their details</p>
      </div>
    </div>

    <!-- Subject Cards -->
    <div class="row g-4">
      <!-- Subject 1 -->
      <div class="col-md-6">
        <div class="card shadow-sm p-4 h-100">
          <h5><i data-lucide="book"></i> Computer Architecture</h5>
          <p class="text-muted mb-1"><strong>Department:</strong> Computer Science</p>
          <p class="text-muted mb-3"><strong>Semester:</strong> 5th Semester</p>
          <p><strong>Description:</strong> This subject covers digital logic, memory systems, processor architecture, pipelining, and control units.</p>
          <p><strong>Syllabus Highlights:</strong></p>
          <ul>
            <li>Combinational & Sequential Circuits</li>
            <li>ALU and Register Transfer</li>
            <li>Pipelining and RISC Architecture</li>
            <li>Memory Hierarchy and Caches</li>
          </ul>
          <a href="upload_materials.php?subject_id=1" class="btn btn-primary btn-sm mt-2">
            <i data-lucide="upload"></i> Upload Materials
          </a>
        </div>
      </div>

      <!-- Subject 2 -->
      <div class="col-md-6">
        <div class="card shadow-sm p-4 h-100">
          <h5><i data-lucide="book"></i> Calculus II</h5>
          <p class="text-muted mb-1"><strong>Department:</strong> Mathematics</p>
          <p class="text-muted mb-3"><strong>Semester:</strong> 3rd Semester</p>
          <p><strong>Description:</strong> Topics include integration techniques, polar coordinates, infinite series, and applications of calculus in engineering.</p>
          <p><strong>Syllabus Highlights:</strong></p>
          <ul>
            <li>Methods of Integration</li>
            <li>Series and Convergence</li>
            <li>Parametric Equations & Polar Coordinates</li>
            <li>Applications in Physics</li>
          </ul>
          <a href="upload_materials.php?subject_id=2" class="btn btn-primary btn-sm mt-2">
            <i data-lucide="upload"></i> Upload Materials
          </a>
        </div>
      </div>

      <!-- Add more static subjects below as needed -->
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    lucide.createIcons();
  </script>
</body>

</html>
