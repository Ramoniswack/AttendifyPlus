<?php
session_start();

if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    header("Location: login.php");
    exit();
}

$weeklyAttendance = [85, 82, 78, 88, 90];
$assignmentSubmissions = [32, 45, 38, 50];
$engagement = ['Reading' => 40, 'Assignments' => 35, 'Attendance' => 25];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Teacher Dashboard | Attendify+</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/styles.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      padding-top: 70px;
    }
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      width: 220px;
      background-color: var(--primary-dark);
      padding-top: 70px;
      z-index: 1000;
      transition: transform 0.3s ease;
    }
    .sidebar ul {
      list-style: none;
      padding: 0;
    }
    .sidebar ul li a {
      display: block;
      padding: 0.75rem 1.5rem;
      color: var(--text-muted);
      text-decoration: none;
      transition: background 0.3s, color 0.3s;
    }
    .sidebar ul li a:hover {
      background-color: var(--highlight);
      color: white;
    }
    .sidebar-open .sidebar {
      transform: translateX(0);
    }
    .sidebar.collapsed {
      transform: translateX(-100%);
    }
    .dashboard-container {
      margin-left: 240px;
      padding: 2rem;
      transition: margin-left 0.3s ease;
    }
    .dashboard-container.expanded {
      margin-left: 0;
    }
    .equal-height-card {
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      border: none;
      border-radius: 1rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    .navbar {
      background-color: var(--primary-dark);
    }
    .navbar-brand {
      color: var(--highlight-light);
    }
  </style>
</head>
<body>

<?php include 'sidebar_teacher.php'; ?>

<nav class="navbar navbar-expand-lg fixed-top shadow">
  <div class="container-fluid">
    <button class="btn text-white me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <a class="navbar-brand" href="#">Attendify+ | Teacher</a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="navbar-text text-white">Welcome, <?php echo htmlspecialchars($_SESSION['Username']); ?></span>
      <button class="btn btn-outline-light btn-sm" onclick="toggleTheme()" title="Toggle Theme">Theme</button>
      <a href="../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="container-fluid dashboard-container" id="mainContent">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="fw-bold">Teacher Dashboard</h2>
    <a href="attendance.php" class="btn btn-success">Quick Attendance</a>
  </div>

  <div class="row g-4">
    <div class="col-md-6">
      <div class="card p-4 equal-height-card">
        <h5 class="mb-3">Weekly Attendance</h5>
        <canvas id="attendanceChart"></canvas>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card p-4 equal-height-card">
        <h5 class="mb-3">Assignment Submissions</h5>
        <canvas id="assignmentChart"></canvas>
      </div>
    </div>
    <div class="col-12">
      <div class="card p-4 equal-height-card mx-auto" style="max-width: 600px;">
        <h5 class="mb-3">Engagement Overview</h5>
        <canvas id="engagementChart" style="max-height: 300px;"></canvas>
      </div>
    </div>
  </div>

  <div class="row row-cols-1 row-cols-md-3 g-4 mt-5">
    <div class="col">
      <div class="card p-4 text-center equal-height-card">
        <i class="bi bi-book fs-1 mb-2 text-primary"></i>
        <h6 class="fw-semibold">Subjects & Students</h6>
        <a href="my_subjects_students.php" class="btn btn-primary btn-sm mt-auto">View</a>
      </div>
    </div>
    <div class="col">
      <div class="card p-4 text-center equal-height-card">
        <i class="bi bi-upload fs-1 mb-2 text-primary"></i>
        <h6 class="fw-semibold">Upload Slides</h6>
        <a href="upload_slides.php" class="btn btn-primary btn-sm mt-auto">Upload</a>
      </div>
    </div>
    <div class="col">
      <div class="card p-4 text-center equal-height-card">
        <i class="bi bi-file-earmark-check fs-1 mb-2 text-primary"></i>
        <h6 class="fw-semibold">Attendance Reports</h6>
        <a href="attendance_report.php" class="btn btn-primary btn-sm mt-auto">View</a>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/login.js"></script>
<script>
  const toggleBtn = document.getElementById("sidebarToggle");
  const sidebar = document.getElementById("sidebar");
  const content = document.getElementById("mainContent");

  toggleBtn.addEventListener("click", () => {
    sidebar.classList.toggle("collapsed");
    content.classList.toggle("expanded");
  });

  const attendanceData = <?php echo json_encode($weeklyAttendance); ?>;
  new Chart(document.getElementById("attendanceChart"), {
    type: "line",
    data: {
      labels: ["Mon", "Tue", "Wed", "Thu", "Fri"],
      datasets: [{
        label: "Attendance %",
        data: attendanceData,
        borderColor: "#1cc88a",
        backgroundColor: "rgba(28,200,138,0.2)",
        tension: 0.4,
        fill: true
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, max: 100 } }
    }
  });

  const submissionData = <?php echo json_encode($assignmentSubmissions); ?>;
  new Chart(document.getElementById("assignmentChart"), {
    type: "bar",
    data: {
      labels: ["Week 1", "Week 2", "Week 3", "Week 4"],
      datasets: [{
        label: "Submissions",
        data: submissionData,
        backgroundColor: "#f6c23e"
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });

  const engagementData = <?php echo json_encode(array_values($engagement)); ?>;
  new Chart(document.getElementById("engagementChart"), {
    type: "doughnut",
    data: {
      labels: <?php echo json_encode(array_keys($engagement)); ?>,
      datasets: [{
        data: engagementData,
        backgroundColor: ["#00ffc8", "#1A73E8", "#FF6384"]
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: "bottom" } }
    }
  });
</script>
</body>
</html>