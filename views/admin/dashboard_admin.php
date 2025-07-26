<?php

session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Include database configuration
include '../../config/db_config.php';

// Fetch real-time statistics
$stats = [];

// Total Students
$studentQuery = "SELECT COUNT(*) as count FROM students s JOIN login_tbl l ON s.LoginID = l.LoginID WHERE l.Status = 'active'";
$result = $conn->query($studentQuery);
$stats['total_students'] = $result->fetch_assoc()['count'];

// Total Teachers
$teacherQuery = "SELECT COUNT(*) as count FROM teachers t JOIN login_tbl l ON t.LoginID = l.LoginID WHERE l.Status = 'active'";
$result = $conn->query($teacherQuery);
$stats['total_teachers'] = $result->fetch_assoc()['count'];

// Total Admins
$adminQuery = "SELECT COUNT(*) as count FROM admins a JOIN login_tbl l ON a.LoginID = l.LoginID WHERE l.Status = 'active'";
$result = $conn->query($adminQuery);
$stats['total_admins'] = $result->fetch_assoc()['count'];

// Recent Students (this month)
$recentStudentsQuery = "SELECT COUNT(*) as count FROM students s JOIN login_tbl l ON s.LoginID = l.LoginID WHERE MONTH(l.CreatedDate) = MONTH(CURRENT_DATE()) AND YEAR(l.CreatedDate) = YEAR(CURRENT_DATE())";
$result = $conn->query($recentStudentsQuery);
$stats['recent_students'] = $result->fetch_assoc()['count'];

// Department-wise student count for charts
$deptStudentsQuery = "SELECT d.DepartmentName, COUNT(s.StudentID) as student_count 
                      FROM departments d 
                      LEFT JOIN students s ON d.DepartmentID = s.DepartmentID 
                      GROUP BY d.DepartmentID, d.DepartmentName 
                      ORDER BY student_count DESC";
$result = $conn->query($deptStudentsQuery);
$departmentData = [];
while ($row = $result->fetch_assoc()) {
    $departmentData[] = $row;
}

// Semester-wise student distribution
$semesterQuery = "SELECT sem.SemesterNumber, COUNT(s.StudentID) as student_count 
                  FROM semesters sem 
                  LEFT JOIN students s ON sem.SemesterID = s.SemesterID 
                  GROUP BY sem.SemesterID, sem.SemesterNumber 
                  ORDER BY sem.SemesterNumber";
$result = $conn->query($semesterQuery);
$semesterData = [];
while ($row = $result->fetch_assoc()) {
    $semesterData[] = $row;
}

// Monthly registration trends (last 6 months)
$monthlyQuery = "SELECT 
                    DATE_FORMAT(l.CreatedDate, '%M %Y') as month_year,
                    COUNT(*) as registrations
                 FROM login_tbl l 
                 WHERE l.Role = 'student' 
                 AND l.CreatedDate >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
                 GROUP BY YEAR(l.CreatedDate), MONTH(l.CreatedDate)
                 ORDER BY l.CreatedDate";
$result = $conn->query($monthlyQuery);
$monthlyData = [];
while ($row = $result->fetch_assoc()) {
    $monthlyData[] = $row;
}

// Recent activities (last 10 registrations)
$recentActivitiesQuery = "SELECT 
                            CASE 
                                WHEN l.Role = 'student' THEN CONCAT('Student: ', s.FullName)
                                WHEN l.Role = 'teacher' THEN CONCAT('Teacher: ', t.FullName)
                                WHEN l.Role = 'admin' THEN CONCAT('Admin: ', a.FullName)
                            END as name,
                            l.Role,
                            l.CreatedDate,
                            l.Status
                          FROM login_tbl l
                          LEFT JOIN students s ON l.LoginID = s.LoginID
                          LEFT JOIN teachers t ON l.LoginID = t.LoginID  
                          LEFT JOIN admins a ON l.LoginID = a.LoginID
                          ORDER BY l.CreatedDate DESC 
                          LIMIT 10";
$result = $conn->query($recentActivitiesQuery);
$recentActivities = [];
while ($row = $result->fetch_assoc()) {
    $recentActivities[] = $row;
}

// Convert data to JSON for JavaScript
$departmentDataJSON = json_encode($departmentData);
$semesterDataJSON = json_encode($semesterData);
$monthlyDataJSON = json_encode($monthlyData);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard | Attendify+</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/dashboard_admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

    <!-- JS Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/dashboard_admin.js" defer></script>
    <script src="../../assets/js/navbar_admin.js" defer></script>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../components/sidebar_admin_dashboard.php'; ?>

    <!-- Navbar -->
    <?php include '../components/navbar_admin.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 class="page-title">
                    <i data-lucide="layout-dashboard"></i>
                    Admin Dashboard
                </h2>
                <p class="text-muted mb-0">Manage users and monitor system performance</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="manage_student.php" class="btn btn-primary">
                    <i data-lucide="users"></i> Student Management
                </a>
                <a href="manage_teacher.php" class="btn btn-outline-primary">
                    <i data-lucide="user-check"></i> Teacher Management
                </a>
            </div>
        </div>

        <!-- Statistics Cards - Updated to Match Teacher/Student Theme -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                            <div>Total Students</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i>
                                    +<?php echo $stats['recent_students']; ?> this month
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="users"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card teachers text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['total_teachers']; ?></div>
                            <div>Total Teachers</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i>
                                    Active faculty
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="user-check"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card admins text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['total_admins']; ?></div>
                            <div>Active Admins</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="shield" style="width: 14px; height: 14px;"></i>
                                    System managers
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="shield"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card activities text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo count($departmentData); ?></div>
                            <div>Departments</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="building" style="width: 14px; height: 14px;"></i>
                                    Academic units
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="building"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <h5 class="chart-title">
                        <i data-lucide="bar-chart"></i>
                        Student Registration Trends
                    </h5>
                    <div class="chart-container">
                        <canvas id="registrationTrendsChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="chart-card">
                    <h5 class="chart-title">
                        <i data-lucide="pie-chart"></i>
                        Department Distribution
                    </h5>
                    <div class="chart-container">
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <h5 class="chart-title">
                        <i data-lucide="layers"></i>
                        Semester-wise Student Distribution
                    </h5>
                    <div class="chart-container">
                        <canvas id="semesterChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="recent-activity-card">
                    <h5 class="chart-title">
                        <i data-lucide="activity"></i>
                        Recent Activities
                    </h5>
                    <div class="activity-list">
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-avatar <?php echo $activity['Role']; ?>">
                                    <?php echo strtoupper(substr($activity['Role'], 0, 1)); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="mb-1 fw-semibold" style="font-size: 0.9rem;"><?php echo htmlspecialchars($activity['name']); ?></p>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($activity['CreatedDate'])); ?>
                                        <span class="badge bg-<?php echo $activity['Status'] === 'active' ? 'success' : 'secondary'; ?> ms-1">
                                            <?php echo ucfirst($activity['Status']); ?>
                                        </span>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="quick-action-card">
                    <div class="quick-action-icon">
                        <i data-lucide="users" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5 class="mb-3">Manage Students</h5>
                    <p class="text-muted mb-4 flex-grow-1">View, add, update, or manage student records and their academic information.</p>
                    <a href="manage_student.php" class="btn btn-gradient">
                        <i data-lucide="settings" class="me-1"></i>Manage Students
                    </a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="quick-action-card">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                        <i data-lucide="user-check" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5 class="mb-3">Manage Teachers</h5>
                    <p class="text-muted mb-4 flex-grow-1">Add new teachers, update profiles, and manage teaching assignments.</p>
                    <a href="manage_teacher.php" class="btn btn-gradient" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                        <i data-lucide="settings" class="me-1"></i>Manage Teachers
                    </a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="quick-action-card">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                        <i data-lucide="shield" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5 class="mb-3">System Settings</h5>
                    <p class="text-muted mb-4 flex-grow-1">Configure system settings, manage admin access, and monitor platform health.</p>
                    <a href="system_settings.php" class="btn btn-gradient" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                        <i data-lucide="settings" class="me-1"></i>Settings
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Chart data from PHP
        const departmentData = <?php echo $departmentDataJSON; ?>;
        const semesterData = <?php echo $semesterDataJSON; ?>;
        const monthlyData = <?php echo $monthlyDataJSON; ?>;

        // Chart configurations
        Chart.defaults.font.family = 'Poppins';
        Chart.defaults.color = '#6c757d';

        // Registration Trends Chart
        const registrationCtx = document.getElementById('registrationTrendsChart').getContext('2d');
        new Chart(registrationCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month_year),
                datasets: [{
                    label: 'New Registrations',
                    data: monthlyData.map(item => item.registrations),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#007bff',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Department Distribution Chart
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'doughnut',
            data: {
                labels: departmentData.map(item => item.DepartmentName),
                datasets: [{
                    data: departmentData.map(item => item.student_count),
                    backgroundColor: [
                        '#007bff',
                        '#28a745',
                        '#ffc107',
                        '#17a2b8',
                        '#dc3545',
                        '#6f42c1'
                    ],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });

        // Semester Distribution Chart
        const semesterCtx = document.getElementById('semesterChart').getContext('2d');
        new Chart(semesterCtx, {
            type: 'bar',
            data: {
                labels: semesterData.map(item => `Sem ${item.SemesterNumber}`),
                datasets: [{
                    label: 'Students',
                    data: semesterData.map(item => item.student_count),
                    backgroundColor: 'rgba(0, 123, 255, 0.8)',
                    borderColor: '#007bff',
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>