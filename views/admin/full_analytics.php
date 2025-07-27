<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Include database configuration and helpers
include '../../config/db_config.php';
include '../../helpers/notification_helpers.php';

// Fetch all departments and semesters for filters
$departmentsQuery = "SELECT DepartmentID, DepartmentName FROM departments ORDER BY DepartmentName";
$departments = $conn->query($departmentsQuery)->fetch_all(MYSQLI_ASSOC);

$semestersQuery = "SELECT SemesterID, SemesterNumber FROM semesters ORDER BY SemesterNumber";
$semesters = $conn->query($semestersQuery)->fetch_all(MYSQLI_ASSOC);

// Fetch all teachers for individual analytics
$teachersQuery = "SELECT t.TeacherID, t.FullName, t.Contact, d.DepartmentName 
                  FROM teachers t 
                  LEFT JOIN teacher_department_map tdm ON t.TeacherID = tdm.TeacherID 
                  LEFT JOIN departments d ON tdm.DepartmentID = d.DepartmentID 
                  JOIN login_tbl l ON t.LoginID = l.LoginID 
                  WHERE l.Status = 'active' 
                  ORDER BY t.FullName";
$teachers = $conn->query($teachersQuery)->fetch_all(MYSQLI_ASSOC);

// Fetch all students for individual analytics
$studentsQuery = "SELECT s.StudentID, s.FullName, s.Contact, s.JoinYear, s.ProgramCode,
                         d.DepartmentName, sem.SemesterNumber, s.DeviceRegistered
                  FROM students s 
                  JOIN departments d ON s.DepartmentID = d.DepartmentID 
                  JOIN semesters sem ON s.SemesterID = sem.SemesterID 
                  JOIN login_tbl l ON s.LoginID = l.LoginID 
                  WHERE l.Status = 'active' 
                  ORDER BY s.FullName";
$students = $conn->query($studentsQuery)->fetch_all(MYSQLI_ASSOC);

// Overall statistics
$stats = [];

// Total counts
$stats['total_students'] = $conn->query("SELECT COUNT(*) as count FROM students s JOIN login_tbl l ON s.LoginID = l.LoginID WHERE l.Status = 'active'")->fetch_assoc()['count'];
$stats['total_teachers'] = $conn->query("SELECT COUNT(*) as count FROM teachers t JOIN login_tbl l ON t.LoginID = l.LoginID WHERE l.Status = 'active'")->fetch_assoc()['count'];
$stats['total_admins'] = $conn->query("SELECT COUNT(*) as count FROM admins a JOIN login_tbl l ON a.LoginID = l.LoginID WHERE l.Status = 'active'")->fetch_assoc()['count'];

// Attendance statistics
$stats['total_attendance'] = $conn->query("SELECT COUNT(*) as count FROM attendance_records")->fetch_assoc()['count'];
$stats['qr_attendance'] = $conn->query("SELECT COUNT(*) as count FROM attendance_records WHERE Method = 'qr'")->fetch_assoc()['count'];
$stats['manual_attendance'] = $conn->query("SELECT COUNT(*) as count FROM attendance_records WHERE Method = 'manual'")->fetch_assoc()['count'];

// Assignment statistics
$stats['total_assignments'] = $conn->query("SELECT COUNT(*) as count FROM assignments WHERE IsActive = 1")->fetch_assoc()['count'];
$stats['total_submissions'] = $conn->query("SELECT COUNT(*) as count FROM assignment_submissions")->fetch_assoc()['count'];
$stats['graded_submissions'] = $conn->query("SELECT COUNT(*) as count FROM assignment_submissions WHERE Status = 'graded'")->fetch_assoc()['count'];

// Material statistics
$stats['total_materials'] = $conn->query("SELECT COUNT(*) as count FROM materials WHERE IsActive = 1")->fetch_assoc()['count'];
$stats['total_downloads'] = $conn->query("SELECT SUM(DownloadCount) as total FROM materials")->fetch_assoc()['total'] ?? 0;

// Device registration statistics
$stats['registered_devices'] = $conn->query("SELECT COUNT(*) as count FROM students WHERE DeviceRegistered = 1")->fetch_assoc()['count'];
$stats['pending_tokens'] = $conn->query("SELECT COUNT(*) as count FROM device_registration_tokens WHERE Used = 0 AND ExpiresAt > NOW()")->fetch_assoc()['count'];

// Department-wise statistics
$deptStatsQuery = "SELECT d.DepartmentName, 
                          COUNT(s.StudentID) as student_count,
                          COUNT(DISTINCT t.TeacherID) as teacher_count
                   FROM departments d 
                   LEFT JOIN students s ON d.DepartmentID = s.DepartmentID AND s.LoginID IN (SELECT LoginID FROM login_tbl WHERE Status = 'active')
                   LEFT JOIN teacher_department_map tdm ON d.DepartmentID = tdm.DepartmentID
                   LEFT JOIN teachers t ON tdm.TeacherID = t.TeacherID AND t.LoginID IN (SELECT LoginID FROM login_tbl WHERE Status = 'active')
                   GROUP BY d.DepartmentID, d.DepartmentName 
                   ORDER BY student_count DESC";
$deptStats = $conn->query($deptStatsQuery)->fetch_all(MYSQLI_ASSOC);

// Recent activities (last 20)
$recentActivitiesQuery = "SELECT 
                            'attendance' as type,
                            CONCAT(s.FullName, ' marked attendance for ', sub.SubjectName) as description,
                            ar.DateTime as activity_date,
                            s.FullName as user_name,
                            'student' as user_type
                          FROM attendance_records ar
                          JOIN students s ON ar.StudentID = s.StudentID
                          JOIN subjects sub ON ar.SubjectID = sub.SubjectID
                          UNION ALL
                          SELECT 
                            'assignment' as type,
                            CONCAT('Assignment: ', a.Title, ' by ', t.FullName) as description,
                            a.CreatedAt as activity_date,
                            t.FullName as user_name,
                            'teacher' as user_type
                          FROM assignments a
                          JOIN teachers t ON a.TeacherID = t.TeacherID
                          WHERE a.IsActive = 1
                          UNION ALL
                          SELECT 
                            'submission' as type,
                            CONCAT(s.FullName, ' submitted assignment') as description,
                            ass.SubmittedAt as activity_date,
                            s.FullName as user_name,
                            'student' as user_type
                          FROM assignment_submissions ass
                          JOIN students s ON ass.StudentID = s.StudentID
                          ORDER BY activity_date DESC 
                          LIMIT 20";
$recentActivities = $conn->query($recentActivitiesQuery)->fetch_all(MYSQLI_ASSOC);

// Convert data to JSON for JavaScript
$deptStatsJSON = json_encode($deptStats);
$recentActivitiesJSON = json_encode($recentActivities);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Full Analytics - Attendify+</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/full_analytics.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_admin.css">
    <link rel="stylesheet" href="../../assets/css/dashboard_student.css">
    <link rel="stylesheet" href="../../assets/css/manage_admin.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <?php include '../components/sidebar_admin_dashboard.php'; ?>
    <?php include '../components/navbar_admin.php'; ?>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-container">
            <!-- Z-index fix for modals -->
            <style>
                .modal {
                    z-index: 1070 !important;
                }

                .modal-backdrop {
                    z-index: 1060 !important;
                }

                .navbar {
                    z-index: 1060 !important;
                }

                .sidebar {
                    z-index: 1050 !important;
                }
            </style>

            <!-- Page Header -->
            <div class="page-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="page-title">
                        <i data-lucide="bar-chart-3"></i>
                        Full Analytics Dashboard
                    </h2>
                    <p class="text-muted mb-0">Comprehensive analytics and insights for the entire system</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="exportAllAnalytics()">
                        <i data-lucide="download"></i>
                        Export All
                    </button>
                    <button class="btn btn-primary" onclick="refreshAnalytics()">
                        <i data-lucide="refresh-cw"></i>
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search Users</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i data-lucide="search"></i>
                                </span>
                                <input type="text" id="searchInput" class="form-control" placeholder="Search by name, email, or contact...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filter by Department</label>
                            <select id="departmentFilter" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['DepartmentName']) ?>"><?= htmlspecialchars($dept['DepartmentName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filter by Semester</label>
                            <select id="semesterFilter" class="form-select">
                                <option value="">All Semesters</option>
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?= $sem['SemesterNumber'] ?>">Semester <?= $sem['SemesterNumber'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">User Type</label>
                            <select id="userTypeFilter" class="form-select">
                                <option value="">All Users</option>
                                <option value="student">Students</option>
                                <option value="teacher">Teachers</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overall Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-12 col-md-3">
                    <div class="mini-stat-card h-100 d-flex flex-column justify-content-center align-items-start p-4">
                        <div class="mini-stat-icon"><i data-lucide="users"></i></div>
                        <div class="mini-stat-value"><?= number_format($stats['total_students']) ?></div>
                        <div class="mini-stat-label">Total Students</div>
                        <div class="mini-stat-desc text-muted mt-1"><i data-lucide="trending-up" style="width: 14px; height: 14px;"></i> Active students</div>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="mini-stat-card h-100 d-flex flex-column justify-content-center align-items-start p-4">
                        <div class="mini-stat-icon"><i data-lucide="graduation-cap"></i></div>
                        <div class="mini-stat-value"><?= number_format($stats['total_teachers']) ?></div>
                        <div class="mini-stat-label">Total Teachers</div>
                        <div class="mini-stat-desc text-muted mt-1"><i data-lucide="user-check" style="width: 14px; height: 14px;"></i> Active faculty</div>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="mini-stat-card h-100 d-flex flex-column justify-content-center align-items-start p-4">
                        <div class="mini-stat-icon"><i data-lucide="shield"></i></div>
                        <div class="mini-stat-value"><?= number_format($stats['total_admins']) ?></div>
                        <div class="mini-stat-label">Total Admins</div>
                        <div class="mini-stat-desc text-muted mt-1"><i data-lucide="shield-check" style="width: 14px; height: 14px;"></i> System managers</div>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="mini-stat-card h-100 d-flex flex-column justify-content-center align-items-start p-4">
                        <div class="mini-stat-icon"><i data-lucide="check-circle"></i></div>
                        <div class="mini-stat-value"><?= number_format($stats['total_attendance']) ?></div>
                        <div class="mini-stat-label">Total Attendance</div>
                        <div class="mini-stat-desc text-muted mt-1"><i data-lucide="calendar-check" style="width: 14px; height: 14px;"></i> Records tracked</div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-lg-8 mb-3">
                    <div class="chart-card">
                        <h5 class="chart-title">
                            <i data-lucide="trending-up"></i>
                            Department-wise Distribution
                        </h5>
                        <div class="chart-container">
                            <canvas id="departmentChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-3">
                    <div class="chart-card">
                        <h5 class="chart-title">
                            <i data-lucide="pie-chart"></i>
                            Attendance Methods
                        </h5>
                        <div class="chart-container">
                            <canvas id="attendanceMethodChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Individual Analytics Tabs -->
            <div class="card mb-4">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="analyticsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="teachers-tab" data-bs-toggle="tab" data-bs-target="#teachers" type="button" role="tab">
                                <i data-lucide="graduation-cap"></i>
                                Teachers Analytics
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab">
                                <i data-lucide="users"></i>
                                Students Analytics
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="activities-tab" data-bs-toggle="tab" data-bs-target="#activities" type="button" role="tab">
                                <i data-lucide="activity"></i>
                                Recent Activities
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="analyticsTabsContent">
                        <!-- Teachers Analytics Tab -->
                        <div class="tab-pane fade show active" id="teachers" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Individual Teacher Analytics</h6>
                                <button class="btn btn-sm btn-outline-primary" onclick="exportTeachersAnalytics()">
                                    <i data-lucide="download"></i>
                                    Export Teachers Data
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover" id="teachersTable">
                                    <thead>
                                        <tr>
                                            <th>Teacher Name</th>
                                            <th>Department</th>
                                            <th>Contact</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <tr class="teacher-row"
                                                data-name="<?= htmlspecialchars($teacher['FullName']) ?>"
                                                data-department="<?= htmlspecialchars($teacher['DepartmentName'] ?? '') ?>"
                                                data-contact="<?= htmlspecialchars($teacher['Contact'] ?? '') ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-placeholder me-2">
                                                            <?= strtoupper(substr($teacher['FullName'], 0, 1)) ?>
                                                        </div>
                                                        <?= htmlspecialchars($teacher['FullName']) ?>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($teacher['DepartmentName'] ?? 'Not Assigned') ?></td>
                                                <td><?= htmlspecialchars($teacher['Contact'] ?? 'N/A') ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewTeacherAnalytics(<?= $teacher['TeacherID'] ?>)">
                                                            <i data-lucide="eye"></i>
                                                            View
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-success" onclick="downloadTeacherReport(<?= $teacher['TeacherID'] ?>)">
                                                            <i data-lucide="download"></i>
                                                            Report
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Students Analytics Tab -->
                        <div class="tab-pane fade" id="students" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Individual Student Analytics</h6>
                                <button class="btn btn-sm btn-outline-primary" onclick="exportStudentsAnalytics()">
                                    <i data-lucide="download"></i>
                                    Export Students Data
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover" id="studentsTable">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Department</th>
                                            <th>Semester</th>
                                            <th>Join Year</th>
                                            <th>Device Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr class="student-row"
                                                data-name="<?= htmlspecialchars($student['FullName']) ?>"
                                                data-department="<?= htmlspecialchars($student['DepartmentName']) ?>"
                                                data-semester="<?= $student['SemesterNumber'] ?>"
                                                data-year="<?= $student['JoinYear'] ?>"
                                                data-contact="<?= htmlspecialchars($student['Contact'] ?? '') ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-placeholder me-2">
                                                            <?= strtoupper(substr($student['FullName'], 0, 1)) ?>
                                                        </div>
                                                        <?= htmlspecialchars($student['FullName']) ?>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($student['DepartmentName']) ?></td>
                                                <td>Semester <?= $student['SemesterNumber'] ?></td>
                                                <td><?= $student['JoinYear'] ?></td>
                                                <td>
                                                    <?php if ($student['DeviceRegistered']): ?>
                                                        <span class="badge bg-success">Registered</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Not Registered</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewStudentAnalytics(<?= $student['StudentID'] ?>)">
                                                            <i data-lucide="eye"></i>
                                                            View
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-success" onclick="downloadStudentReport(<?= $student['StudentID'] ?>)">
                                                            <i data-lucide="download"></i>
                                                            Report
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Recent Activities Tab -->
                        <div class="tab-pane fade" id="activities" role="tabpanel">
                            <div class="activity-list">
                                <?php foreach ($recentActivities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?= $activity['type'] ?>">
                                            <i data-lucide="<?= getActivityIcon($activity['type']) ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title"><?= htmlspecialchars($activity['description']) ?></div>
                                            <div class="activity-meta">
                                                <span class="status-badge <?= $activity['type'] ?>"><?= ucfirst($activity['type']) ?></span>
                                                <span class="activity-time"><?= date('M j, Y g:i A', strtotime($activity['activity_date'])) ?></span>
                                            </div>
                                            <div class="activity-user">by <?= htmlspecialchars($activity['user_name']) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Individual Analytics Modal -->
    <div class="modal fade" id="individualAnalyticsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="analyticsModalTitle">
                        <i data-lucide="bar-chart-3"></i>
                        Individual Analytics
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="analyticsModalBody">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="downloadIndividualReport">
                        <i data-lucide="download"></i>
                        Download Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JS -->
    <script src="../../assets/js/full_analytics.js"></script>
    <script src="../../assets/js/navbar_admin.js"></script>

    <script>
        // Pass PHP data to JavaScript
        const deptStats = <?= $deptStatsJSON ?>;
        const recentActivities = <?= $recentActivitiesJSON ?>;
        const attendanceStats = {
            qr: <?= $stats['qr_attendance'] ?>,
            manual: <?= $stats['manual_attendance'] ?>
        };
    </script>
</body>

</html>

<?php
function getActivityIcon($type)
{
    switch ($type) {
        case 'attendance':
            return 'check-circle';
        case 'assignment':
            return 'file-text';
        case 'submission':
            return 'upload';
        default:
            return 'activity';
    }
}
?>