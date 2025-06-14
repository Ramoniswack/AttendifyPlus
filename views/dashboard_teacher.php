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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Teacher Dashboard | Attendify+</title>

    <!-- External CSS -->
    <link rel="stylesheet" href="../assets/css/dashboard_teacher.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

    <!-- External JS Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/lucide.min.js"></script>
    <script src="../assets/js/dashboard_teacher.js" defer></script>
</head>


<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar_teacher_dashboard.php'; ?>

    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <button class="btn text-white me-2" id="sidebarToggle">
                <span style="font-size: 24px;">☰</span>
            </button>
            <a class="navbar-brand" href="#">Attendify+ | Teacher</a>
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
            <h2 class="m-0">Teacher Dashboard</h2>
            <a href="attendance.php" class="btn btn-success btn-sm">
                <i data-lucide="plus" class="me-1"></i>Quick Attendance
            </a>
        </div>

        <!-- Stats Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card text-center p-4">
                    <i data-lucide="users" class="text-primary"></i>
                    <h6 class="mt-2">Total Students</h6>
                    <h3 class="mb-1">156</h3>
                    <small class="text-success">+12% this week</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center p-4">
                    <i data-lucide="calendar" class="text-primary"></i>
                    <h6 class="mt-2">Avg Attendance</h6>
                    <h3 class="mb-1">84.6%</h3>
                    <small class="text-success">+2.1%</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center p-4">
                    <i data-lucide="file-text" class="text-primary"></i>
                    <h6 class="mt-2">Assignments Due</h6>
                    <h3 class="mb-1">8</h3>
                    <small class="text-danger">-3 from last week</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center p-4">
                    <i data-lucide="book-open" class="text-primary"></i>
                    <h6 class="mt-2">Course Progress</h6>
                    <h3 class="mb-1">67%</h3>
                    <small class="text-success">+5%</small>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card p-4" style="height: 400px;">
                    <h5 class="mb-3">Weekly Attendance %</h5>
                    <div style="position: relative; height: 300px;">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-4" style="height: 400px;">
                    <h5 class="mb-3">Assignment Submission Rate</h5>
                    <div style="position: relative; height: 300px;">
                        <canvas id="assignmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Engagement Overview -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card p-4">
                    <h5 class="mb-4">Student Engagement Overview</h5>
                    <div class="row text-center">
                        <div class="col-md-4 mb-4">
                            <h6>Reading</h6>
                            <div class="doughnut-container" style="position: relative; height: 150px;">
                                <canvas id="readingChart"></canvas>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-primary">75%</span>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <h6>Assignment Completion</h6>
                            <div class="doughnut-container" style="position: relative; height: 150px;">
                                <canvas id="completionChart"></canvas>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-success">82%</span>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <h6>Class Attendance</h6>
                            <div class="doughnut-container" style="position: relative; height: 150px;">
                                <canvas id="classChart"></canvas>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-warning">89%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <div class="col">
                <div class="card text-center p-4 equal-height-card">
                    <i data-lucide="graduation-cap" class="text-primary"></i>
                    <h5 class="mt-2">My Subjects & Students</h5>
                    <p class="mb-3">View all students in your faculty/semester/subject.</p>
                    <a href="my_subjects_students.php" class="btn btn-outline-primary btn-sm mt-auto">
                        <i data-lucide="eye" class="me-1"></i>View
                    </a>
                </div>
            </div>
            <div class="col">
                <div class="card text-center p-4 equal-height-card">
                    <i data-lucide="upload" class="text-primary"></i>
                    <h5 class="mt-2">Upload Slides</h5>
                    <p class="mb-3">Upload your teaching materials for students.</p>
                    <a href="upload_slides.php" class="btn btn-outline-primary btn-sm mt-auto">
                        <i data-lucide="upload" class="me-1"></i>Upload
                    </a>
                </div>
            </div>
            <div class="col">
                <div class="card text-center p-4 equal-height-card">
                    <i data-lucide="clipboard-list" class="text-primary"></i>
                    <h5 class="mt-2">Attendance Reports</h5>
                    <p class="mb-3">View attendance for your subject classes.</p>
                    <a href="attendance_report.php" class="btn btn-outline-primary btn-sm mt-auto">
                        <i data-lucide="bar-chart" class="me-1"></i>View
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="sidebar-overlay"></div>

</body>

</html>