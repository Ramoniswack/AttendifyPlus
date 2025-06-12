<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
  header("Location: login.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Teacher Dashboard | Attendify+</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/teacherDashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/lucide.min.js"></script>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #f8f9fa;
      overflow-x: hidden;
      transition: background-color 0.2s ease, color 0.2s ease;
    }

    body.dark-mode {
      background: #121212;
      color: #f1f1f1;
    }

    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    }

    .equal-height-card {
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .card i {
      width: 36px;
      height: 36px;
      margin-bottom: 10px;
      display: inline-block;
    }

    .card h5 {
      font-size: 1.1rem;
      margin-top: 0.5rem;
    }

    .text-success {
      color: #10b981 !important;
    }

    .text-danger {
      color: #ef4444 !important;
    }

    .doughnut-container canvas {
      max-width: 150px;
      margin: 0 auto;
    }
  </style>
</head>

<body>
  <?php include 'sidebar_teacher.php'; ?>

  <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
    <div class="container-fluid">
      <button class="btn text-white me-2" id="sidebarToggle">☰</button>
      <a class="navbar-brand" href="#">Attendify+ | Teacher</a>
      <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
        <span class="navbar-text text-white">Welcome, <?php echo htmlspecialchars($_SESSION['Username']); ?></span>
        <button class="btn btn-outline-light btn-sm" onclick="toggleTheme()" title="Toggle Theme">Theme</button>
        <a href="../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <h2 class="m-0">Teacher Dashboard</h2>
      <a href="attendance.php" class="btn btn-success btn-sm">Quick Attendance</a>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-4">
      <div class="col-md-3">
        <div class="card text-center p-4">
          <i data-lucide="users"></i>
          <h6>Total Students</h6>
          <h3>156</h3>
          <small class="text-success">+12% this week</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center p-4">
          <i data-lucide="calendar"></i>
          <h6>Avg Attendance</h6>
          <h3>84.6%</h3>
          <small class="text-success">+2.1%</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center p-4">
          <i data-lucide="file-text"></i>
          <h6>Assignments Due</h6>
          <h3>8</h3>
          <small class="text-danger">-3 from last week</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center p-4">
          <i data-lucide="book-open"></i>
          <h6>Course Progress</h6>
          <h3>67%</h3>
          <small class="text-success">+5%</small>
        </div>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div class="card p-4 h-100">
          <h5 class="mb-3">Weekly Attendance %</h5>
          <canvas id="attendanceChart"></canvas>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card p-4 h-100">
          <h5 class="mb-3">Assignment Submission Rate</h5>
          <canvas id="assignmentChart"></canvas>
        </div>
      </div>
      <div class="col-12">
        <div class="card p-4">
          <h5 class="mb-4">Student Engagement Overview</h5>
          <div class="row text-center">
            <div class="col-md-4 mb-4 doughnut-container">
              <h6>Reading</h6>
              <canvas id="readingChart"></canvas>
            </div>
            <div class="col-md-4 mb-4 doughnut-container">
              <h6>Assignment Completion</h6>
              <canvas id="completionChart"></canvas>
            </div>
            <div class="col-md-4 mb-4 doughnut-container">
              <h6>Class Attendance</h6>
              <canvas id="classChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row row-cols-1 row-cols-md-3 g-4 mt-5">
      <div class="col">
        <div class="card text-center p-4 equal-height-card">
          <i data-lucide="graduation-cap"></i>
          <h5>My Subjects & Students</h5>
          <p class="mb-3">View all students in your faculty/semester/subject.</p>
          <a href="my_subjects_students.php" class="btn btn-outline-primary btn-sm mt-auto">View</a>
        </div>
      </div>
      <div class="col">
        <div class="card text-center p-4 equal-height-card">
          <i data-lucide="upload"></i>
          <h5>Upload Slides</h5>
          <p class="mb-3">Upload your teaching materials for students.</p>
          <a href="upload_slides.php" class="btn btn-outline-primary btn-sm mt-auto">Upload</a>
        </div>
      </div>
      <div class="col">
        <div class="card text-center p-4 equal-height-card">
          <i data-lucide="clipboard-list"></i>
          <h5>Attendance Reports</h5>
          <p class="mb-3">View attendance for your subject classes.</p>
          <a href="attendance_report.php" class="btn btn-outline-primary btn-sm mt-auto">View</a>
        </div>
      </div>
    </div>
  </div>

  <script>
    lucide.createIcons();

    function toggleTheme() {
      document.body.classList.toggle('dark-mode');
      localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
    }

    window.onload = () => {
      requestAnimationFrame(() => {
        if (localStorage.getItem('theme') === 'dark') {
          document.body.classList.add('dark-mode');
        }
      });
    };

    document.getElementById("sidebarToggle").addEventListener("click", function () {
      const sidebar = document.getElementById("sidebar");
      if (sidebar) {
        sidebar.classList.toggle("active");
        document.body.classList.toggle("sidebar-open");
      }
    });

    new Chart(document.getElementById("attendanceChart"), {
      type: "line",
      data: {
        labels: ["Mon", "Tue", "Wed", "Thu", "Fri"],
        datasets: [{
          label: "Attendance %",
          data: [85, 82, 78, 88, 90],
          borderColor: "#3b82f6",
          backgroundColor: "rgba(59,130,246,0.2)",
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true, max: 100 } }
      }
    });

    new Chart(document.getElementById("assignmentChart"), {
      type: "bar",
      data: {
        labels: ["Week 1", "Week 2", "Week 3", "Week 4"],
        datasets: [{
          label: "Submissions",
          data: [32, 45, 38, 50],
          backgroundColor: "#8b5cf6"
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true } }
      }
    });

    new Chart(document.getElementById("readingChart"), {
      type: "doughnut",
      data: {
        labels: ["Read", "Unread"],
        datasets: [{
          data: [65, 35],
          backgroundColor: ["#10b981", "#e5e7eb"]
        }]
      },
      options: { responsive: true, plugins: { legend: { position: "bottom" } } }
    });

    new Chart(document.getElementById("completionChart"), {
      type: "doughnut",
      data: {
        labels: ["Completed", "Pending"],
        datasets: [{
          data: [72, 28],
          backgroundColor: ["#3b82f6", "#e5e7eb"]
        }]
      },
      options: { responsive: true, plugins: { legend: { position: "bottom" } } }
    });

    new Chart(document.getElementById("classChart"), {
      type: "doughnut",
      data: {
        labels: ["Present", "Absent"],
        datasets: [{
          data: [80, 20],
          backgroundColor: ["#f59e0b", "#e5e7eb"]
        }]
      },
      options: { responsive: true, plugins: { legend: { position: "bottom" } } }
    });
  </script>
</body>

</html>
