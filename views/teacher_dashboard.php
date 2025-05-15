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
  <title>Teacher Dashboard | Attendify+</title>
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
  <?php include 'sidebar.php'; ?>

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

    <!-- Heading and Attendance Shortcut -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <h2 class="m-0">Teacher Dashboard</h2>
      <a href="take_attendance.php" class="btn btn-sm btn-success">Quick Attendance</a>
    </div>

    <!-- Charts Section -->
    <div class="row g-4 mb-4">
      <div class="col-12 col-md-6">
        <div class="card p-4 h-100">
          <h5 class="mb-3">Weekly Attendance %</h5>
          <canvas id="attendanceChart"></canvas>
        </div>
      </div>

      <div class="col-12 col-md-6">
        <div class="card p-4 h-100">
          <h5 class="mb-3">Assignment Submission Rate</h5>
          <canvas id="assignmentChart"></canvas>
        </div>
      </div>

      <div class="col-12">
        <div class="card p-4 mx-auto" style="max-width: 600px;">
          <h5 class="mb-3">Student Engagement Overview</h5>
          <canvas id="engagementChart" style="max-height: 300px;"></canvas>
        </div>
      </div>
    </div>

    <!-- Bottom Shortcut Cards -->
    <div class="row row-cols-1 row-cols-md-3 g-4 mt-5">
      <div class="col">
        <div class="card p-4 text-center equal-height-card">
          <h5>My Subjects & Students</h5>
          <p>View all students in your faculty/semester/subject.</p>
          <a href="my_subjects_students.php" class="btn btn-primary btn-sm mt-auto">View</a>
        </div>
      </div>

      <div class="col">
        <div class="card p-4 text-center equal-height-card">
          <h5>Upload Slides</h5>
          <p>Upload your teaching materials for students.</p>
          <a href="upload_slides.php" class="btn btn-primary btn-sm mt-auto">Upload</a>
        </div>
      </div>

      <div class="col">
        <div class="card p-4 text-center equal-height-card">
          <h5>Attendance Reports</h5>
          <p>View attendance for your subject classes.</p>
          <a href="attendance_report.php" class="btn btn-primary btn-sm mt-auto">View</a>
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

    // Attendance Chart
    new Chart(document.getElementById("attendanceChart"), {
      type: "line",
      data: {
        labels: ["Mon", "Tue", "Wed", "Thu", "Fri"],
        datasets: [{
          label: "Attendance %",
          data: [85, 82, 78, 88, 90],
          borderColor: "#00ffc8",
          backgroundColor: "#00ffc8",
          tension: 0.4,
          fill: false
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: true
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100
          }
        }
      }
    });

    // Assignment Chart
    new Chart(document.getElementById("assignmentChart"), {
      type: "bar",
      data: {
        labels: ["Week 1", "Week 2", "Week 3", "Week 4"],
        datasets: [{
          label: "Submissions",
          data: [32, 45, 38, 50],
          backgroundColor: "#1A73E8"
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: true
          }
        },
        scales: {
          y: {
            beginAtZero: true
          }
        }
      }
    });

    // Engagement Chart
    new Chart(document.getElementById("engagementChart"), {
      type: "pie",
      data: {
        labels: ["Reading", "Assignments", "Attendance"],
        datasets: [{
          data: [40, 35, 25],
          backgroundColor: ["#00ffc8", "#1A73E8", "#FF6384"]
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: "bottom"
          }
        }
      }
    });
  </script>
</body>

</html>