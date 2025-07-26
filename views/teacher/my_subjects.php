<?php

session_start();
require_once(__DIR__ . '/../../config/db_config.php');

// Check session
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
  header("Location: ../auth/login.php");
  exit();
}

$loginID = $_SESSION['LoginID'];

// Get teacher info
$teacherStmt = $conn->prepare("SELECT TeacherID, FullName FROM teachers WHERE LoginID = ?");
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
$teacherRow = $teacherRes->fetch_assoc();

if (!$teacherRow) {
  header("Location: ../logout.php");
  exit();
}

$teacherID = $teacherRow['TeacherID'];
$teacherName = $teacherRow['FullName'];

// Get teacher's subjects with detailed information
$subjectsQuery = $conn->prepare("
    SELECT 
        s.SubjectID,
        s.SubjectCode,
        s.SubjectName,
        s.CreditHour,
        s.LectureHour,
        s.IsElective,
        d.DepartmentName,
        d.DepartmentCode,
        sem.SemesterNumber,
        COUNT(st.StudentID) as TotalStudents,
        COUNT(CASE WHEN ar.Status = 'present' THEN 1 END) as PresentToday,
        COUNT(CASE WHEN ar.Status = 'absent' THEN 1 END) as AbsentToday,
        COUNT(CASE WHEN ar.Status = 'late' THEN 1 END) as LateToday,
        MAX(ar.DateTime) as LastAttendance
    FROM subjects s
    JOIN teacher_subject_map tsm ON s.SubjectID = tsm.SubjectID
    JOIN departments d ON s.DepartmentID = d.DepartmentID
    JOIN semesters sem ON s.SemesterID = sem.SemesterID
    LEFT JOIN students st ON st.DepartmentID = s.DepartmentID AND st.SemesterID = s.SemesterID
    LEFT JOIN attendance_records ar ON ar.SubjectID = s.SubjectID AND ar.StudentID = st.StudentID AND DATE(ar.DateTime) = CURDATE()
    WHERE tsm.TeacherID = ?
    GROUP BY s.SubjectID, s.SubjectCode, s.SubjectName, s.CreditHour, s.LectureHour, s.IsElective, d.DepartmentName, d.DepartmentCode, sem.SemesterNumber
    ORDER BY sem.SemesterNumber, s.SubjectName
");
$subjectsQuery->bind_param("i", $teacherID);
$subjectsQuery->execute();
$subjectsResult = $subjectsQuery->get_result();

// Get attendance statistics for each subject
$attendanceStats = [];
while ($subject = $subjectsResult->fetch_assoc()) {
  $subjectID = $subject['SubjectID'];

  // Get overall attendance statistics for this subject
  $statsQuery = $conn->prepare("
        SELECT 
            COUNT(DISTINCT ar.StudentID) as StudentsWithRecords,
            COUNT(CASE WHEN ar.Status = 'present' THEN 1 END) as TotalPresent,
            COUNT(CASE WHEN ar.Status = 'absent' THEN 1 END) as TotalAbsent,
            COUNT(CASE WHEN ar.Status = 'late' THEN 1 END) as TotalLate,
            COUNT(DISTINCT DATE(ar.DateTime)) as TotalClasses
        FROM attendance_records ar
        WHERE ar.SubjectID = ? AND ar.TeacherID = ?
    ");
  $statsQuery->bind_param("ii", $subjectID, $teacherID);
  $statsQuery->execute();
  $stats = $statsQuery->get_result()->fetch_assoc();

  $attendanceStats[$subjectID] = $stats;
}

// Reset the result pointer
$subjectsResult->data_seek(0);

$successMsg = $_GET['success'] ?? '';
$errorMsg = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Subjects | Attendify+</title>

  <!-- CSS -->
  <link rel="stylesheet" href="../../assets/css/dashboard_teacher.css">
  <link rel="stylesheet" href="../../assets/css/sidebar_teacher.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Custom Styles with proper theming -->
  <style>
    /* CSS Variables - EXACT MATCH FROM MANAGE_ADMIN.CSS */
    :root {
      --primary-color: #1a73e8;
      --text-primary: #2d3748;
      --text-secondary: #718096;
      --border-light: #e2e8f0;
      --bg-subtle: #f8fafc;
      --accent-light: #1A73E8;
      --accent-dark: #00ffc8;
      --card-light: #ffffff;
      --card-dark: #1f1f1f;
      --text-light: #333;
      --text-dark: #eee;
      --text-muted-light: #6c757d;
      --text-muted-dark: #a0a0a0;
      --shadow-light: rgba(0, 0, 0, 0.1);
      --shadow-dark: rgba(0, 0, 0, 0.3);
      --input-bg-light: #ffffff;
      --input-bg-dark: #2a2a2a;
      --hover-light: rgba(0, 0, 0, 0.05);
      --hover-dark: rgba(255, 255, 255, 0.1);
    }

    body.dark-mode {
      --primary-color: #00ffc8;
      --text-primary: #e2e8f0;
      --text-secondary: #a0aec0;
      --border-light: #2d3748;
      --bg-subtle: #1a202c;
    }

    /* Subject Cards */
    .subject-card {
      border: 1px solid var(--border-light);
      transition: all 0.2s ease;
      background: white;
      display: flex;
      flex-direction: column;
    }

    body.dark-mode .subject-card {
      background: var(--card-dark);
      border-color: var(--border-light);
    }

    .subject-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    body.dark-mode .subject-card:hover {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .card-body {
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .card-content {
      flex: 1;
    }

    .card-footer-btn {
      margin-top: auto;
      padding-top: 1rem;
    }

    .minimal-badge {
      background: var(--bg-subtle);
      color: var(--text-primary);
      border: 1px solid var(--border-light);
      font-weight: 500;
    }

    .status-indicator {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      display: inline-block;
    }

    .status-completed {
      background: #10b981;
    }

    .status-pending {
      background: #f59e0b;
    }

    .stats-text {
      color: var(--text-secondary);
      font-size: 0.875rem;
    }

    .dropdown-toggle::after {
      display: none;
    }

    .subject-header {
      border-bottom: 1px solid var(--border-light);
      padding-bottom: 1rem;
      margin-bottom: 1rem;
    }

    .minimal-progress {
      height: 4px;
      background: var(--bg-subtle);
      border-radius: 2px;
      overflow: hidden;
    }

    .minimal-progress .progress-bar {
      background: var(--primary-color);
      border-radius: 2px;
    }

    /* Attendance Buttons */
    .attendance-btn {
      background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
      border: none;
      color: white;
      font-weight: 600;
      padding: 0.75rem 1rem;
      border-radius: 8px;
      transition: all 0.3s ease;
      text-decoration: none;
      display: block;
      text-align: center;
    }

    .attendance-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(17, 153, 142, 0.3);
      color: white;
    }

    body.dark-mode .attendance-btn {
      background: linear-gradient(135deg, #065f46 0%, #047857 100%);
      color: white;
    }

    body.dark-mode .attendance-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(6, 95, 70, 0.4);
      color: white;
    }

    .attendance-btn.completed {
      background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
      color: white;
    }

    .attendance-btn.completed:hover {
      background: linear-gradient(135deg, #0f8a7e 0%, #32d970 100%);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(17, 153, 142, 0.3);
      color: white;
    }

    body.dark-mode .attendance-btn.completed {
      background: linear-gradient(135deg, #065f46 0%, #047857 100%);
      color: white;
    }

    body.dark-mode .attendance-btn.completed:hover {
      background: linear-gradient(135deg, #064e39 0%, #036649 100%);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(6, 95, 70, 0.4);
      color: white;
    }

    /* ===== MODALS - EXACT MATCH FROM MANAGE_ADMIN.CSS ===== */
    .modal-content {
      background: var(--card-light);
      color: var(--text-light);
      border-radius: 12px;
      border: none;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    body.dark-mode .modal-content {
      background: var(--card-dark);
      color: var(--text-dark);
      box-shadow: 0 10px 30px rgba(255, 255, 255, 0.1);
    }

    .modal-header {
      background-color: #f8f9fa;
      border-bottom: 2px solid #dee2e6;
      border-radius: 12px 12px 0 0;
    }

    body.dark-mode .modal-header {
      background-color: var(--hover-dark);
      border-bottom-color: var(--border-light);
    }

    .modal-title {
      font-weight: 600;
      color: var(--accent-light);
    }

    body.dark-mode .modal-title {
      color: var(--accent-dark);
    }

    .modal-body {
      padding: 2rem;
    }

    .modal-footer {
      padding: 1.5rem;
      border-top: 1px solid var(--border-light);
    }

    body.dark-mode .modal-footer {
      border-color: var(--border-light);
    }

    .btn-close {
      filter: none;
    }

    body.dark-mode .btn-close {
      filter: invert(1);
    }

    /* Modal Section Containers - EXACT MATCH */
    .modal-section-container {
      background: var(--bg-subtle);
      border: 1px solid var(--border-light);
      border-radius: 8px;
      padding: 1rem;
      margin-bottom: 1rem;
    }

    body.dark-mode .modal-section-container {
      background: #2a2a2a;
      border-color: var(--border-light);
    }

    .modal-section-title {
      color: var(--accent-light);
      font-weight: 600;
      margin-bottom: 0.75rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    body.dark-mode .modal-section-title {
      color: var(--accent-dark);
    }

    /* Modal Table Styling - EXACT MATCH */
    .modal .table {
      background-color: transparent;
      color: inherit;
      border-radius: 0;
      overflow: hidden;
      margin-bottom: 0;
    }

    .modal .table td {
      background-color: transparent;
      color: inherit;
      padding: 0.5rem 0.25rem;
      vertical-align: middle;
      border-bottom: none;
    }

    .modal-table-text {
      color: var(--text-light);
      font-weight: 600;
    }

    body.dark-mode .modal-table-text {
      color: var(--text-dark);
    }

    .modal-table-label {
      color: var(--text-muted-light);
      font-weight: 500;
    }

    body.dark-mode .modal-table-label {
      color: var(--text-muted-dark);
    }

    /* Modal HR separator */
    .modal-body hr {
      border-color: var(--border-light);
      margin: 1.5rem 0;
    }

    body.dark-mode .modal-body hr {
      border-color: var(--border-light);
    }

    /* Modal Buttons - EXACT MATCH FROM MANAGE_ADMIN.CSS */
    .modal .btn-primary {
      background: linear-gradient(135deg, var(--accent-light) 0%, #0056b3 100%);
      border: none;
      color: white;
      font-weight: 600;
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      transition: all 0.3s ease;
    }

    body.dark-mode .modal .btn-primary {
      background: linear-gradient(135deg, var(--accent-dark) 0%, #00d4aa 100%);
      color: black;
    }

    .modal .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(26, 115, 232, 0.3);
      color: white;
    }

    body.dark-mode .modal .btn-primary:hover {
      box-shadow: 0 5px 15px rgba(0, 255, 200, 0.3);
      color: black;
    }

    .modal .btn-outline-primary {
      color: var(--accent-light);
      border-color: var(--accent-light);
      border-width: 2px;
      font-weight: 600;
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      transition: all 0.3s ease;
    }

    body.dark-mode .modal .btn-outline-primary {
      color: var(--accent-dark);
      border-color: var(--accent-dark);
    }

    .modal .btn-outline-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(26, 115, 232, 0.2);
      background-color: var(--accent-light);
      border-color: var(--accent-light);
      color: white;
    }

    body.dark-mode .modal .btn-outline-primary:hover {
      box-shadow: 0 5px 15px rgba(0, 255, 200, 0.2);
      background-color: var(--accent-dark);
      border-color: var(--accent-dark);
      color: black;
    }

    /* Modal Z-Index - EXACT MATCH */
    .modal {
      z-index: 1070 !important;
    }

    .modal-backdrop {
      z-index: 1060 !important;
    }

    /* Responsive Design */
    @media (max-width: 576px) {
      .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100vw - 1rem);
      }

      .modal-body {
        padding: 1rem;
      }

      .modal-section-container {
        padding: 0.75rem;
      }

      .modal-body table {
        font-size: 0.875rem;
      }

      .modal .btn {
        padding: 0.625rem 1rem;
        font-size: 0.875rem;
      }
    }

    @media (max-width: 768px) {
      .modal-dialog {
        margin: 1rem;
      }

      .modal-section-container {
        margin-bottom: 0.75rem;
      }
    }

    .modal-dialog {
      max-height: calc(100vh - 2rem);
      overflow-y: auto;
    }

    @media (max-width: 576px) {
      .modal-dialog {
        max-height: calc(100vh - 1rem);
      }
    }
  </style>

  <!-- JS Libraries -->
  <script src="../../assets/js/lucide.min.js"></script>
  <script src="../../assets/js/sidebar_teacher.js" defer></script>
  <script src="../../assets/js/navbar_teacher.js" defer></script>
</head>

<body>
  <!-- Sidebar Overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Sidebar -->
  <?php include '../components/sidebar_teacher_dashboard.php'; ?>

  <!-- Navbar -->
  <?php include '../components/navbar_teacher.php'; ?>

  <!-- Main Content -->
  <div class="container-fluid dashboard-container main-content">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="page-title mb-1">My Subjects</h2>
        <p class="stats-text mb-0"><?= $subjectsResult->num_rows ?> subjects assigned</p>
      </div>
      <div class="stats-text">
        <?= date('M j, Y') ?> • <?= date('g:i A') ?>
      </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($successMsg): ?>
      <div class="alert alert-success alert-dismissible fade show border-0" role="alert">
        <?= htmlspecialchars($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
      <div class="alert alert-danger alert-dismissible fade show border-0" role="alert">
        <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Subjects Grid -->
    <?php if ($subjectsResult->num_rows > 0): ?>
      <div class="row g-3">
        <?php while ($subject = $subjectsResult->fetch_assoc()):
          $subjectID = $subject['SubjectID'];
          $stats = $attendanceStats[$subjectID] ?? [];
          $totalStudents = $subject['TotalStudents'] ?? 0;
          $todayPresent = $subject['PresentToday'] ?? 0;
          $todayAbsent = $subject['AbsentToday'] ?? 0;
          $todayLate = $subject['LateToday'] ?? 0;
          $lastAttendance = $subject['LastAttendance'];

          // Calculate attendance percentage
          $totalClasses = $stats['TotalClasses'] ?? 0;
          $totalPresent = $stats['TotalPresent'] ?? 0;
          $totalRecords = ($stats['TotalPresent'] ?? 0) + ($stats['TotalAbsent'] ?? 0) + ($stats['TotalLate'] ?? 0);
          $attendancePercentage = $totalRecords > 0 ? round(($totalPresent / $totalRecords) * 100, 1) : 0;

          // Today's attendance status
          $todayTotal = $todayPresent + $todayAbsent + $todayLate;
          $todayAttendanceTaken = $todayTotal > 0;
        ?>
          <div class="col-lg-6 col-xl-4">
            <div class="card subject-card border-0 h-100">
              <div class="card-body p-4">
                <div class="card-content">
                  <!-- Subject Header -->
                  <div class="subject-header">
                    <div class="d-flex justify-content-between align-items-start">
                      <div class="flex-grow-1">
                        <h5 class="mb-1 fw-semibold"><?= htmlspecialchars($subject['SubjectCode']) ?></h5>
                        <p class="stats-text mb-2"><?= htmlspecialchars($subject['SubjectName']) ?></p>
                        <div class="d-flex gap-2 flex-wrap">
                          <span class="minimal-badge badge rounded-pill px-2 py-1">
                            <?= htmlspecialchars($subject['DepartmentCode']) ?>
                          </span>
                          <span class="minimal-badge badge rounded-pill px-2 py-1">
                            Sem <?= $subject['SemesterNumber'] ?>
                          </span>
                        </div>
                      </div>
                      <div class="dropdown">
                        <button class="btn btn-link text-muted p-1" type="button" data-bs-toggle="dropdown">
                          <i data-lucide="more-horizontal" style="width: 18px; height: 18px;"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm">
                          <li>
                            <a class="dropdown-item py-2" href="attendance_report.php?subject=<?= $subjectID ?>">
                              <i data-lucide="bar-chart-3" class="me-2" style="width: 16px; height: 16px;"></i>
                              View Reports
                            </a>
                          </li>
                          <li>
                            <hr class="dropdown-divider my-1">
                          </li>
                          <li>
                            <a class="dropdown-item py-2" href="#" onclick="viewSubjectDetails(<?= $subjectID ?>, '<?= htmlspecialchars($subject['SubjectName']) ?>', '<?= htmlspecialchars($subject['SubjectCode']) ?>', '<?= htmlspecialchars($subject['DepartmentName']) ?>', <?= $subject['SemesterNumber'] ?>, <?= $subject['CreditHour'] ?>, <?= $subject['LectureHour'] ?>, <?= $subject['IsElective'] ? 'true' : 'false' ?>)">
                              <i data-lucide="info" class="me-2" style="width: 16px; height: 16px;"></i>
                              Details
                            </a>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <!-- Today's Status -->
                  <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <div class="d-flex align-items-center gap-2">
                        <span class="status-indicator <?= $todayAttendanceTaken ? 'status-completed' : 'status-pending' ?>"></span>
                        <span class="stats-text">Today's Attendance</span>
                      </div>
                      <span class="stats-text">
                        <?= $todayAttendanceTaken ? 'Completed' : 'Pending' ?>
                      </span>
                    </div>

                    <?php if ($todayAttendanceTaken): ?>
                      <div class="d-flex justify-content-between stats-text">
                        <span>Present: <?= $todayPresent ?></span>
                        <span>Absent: <?= $todayAbsent ?></span>
                        <span>Late: <?= $todayLate ?></span>
                      </div>
                    <?php endif; ?>
                  </div>

                  <!-- Overall Stats -->
                  <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span class="stats-text">Overall Attendance</span>
                      <span class="fw-medium"><?= $attendancePercentage ?>%</span>
                    </div>
                    <div class="minimal-progress">
                      <div class="progress-bar" style="width: <?= $attendancePercentage ?>%"></div>
                    </div>
                  </div>

                  <!-- Quick Stats -->
                  <div class="row g-3 text-center mb-3">
                    <div class="col-4">
                      <div class="fw-medium"><?= $subject['CreditHour'] ?></div>
                      <div class="stats-text">Credits</div>
                    </div>
                    <div class="col-4">
                      <div class="fw-medium"><?= $totalStudents ?></div>
                      <div class="stats-text">Students</div>
                    </div>
                    <div class="col-4">
                      <div class="fw-medium"><?= $totalClasses ?></div>
                      <div class="stats-text">Classes</div>
                    </div>
                  </div>
                </div>

                <!-- Attendance Button at Bottom -->
                <div class="card-footer-btn">
                  <?php if (!$todayAttendanceTaken): ?>
                    <a href="attendance.php?semester=<?= $subject['SemesterNumber'] ?>&subject=<?= $subjectID ?>&date=<?= date('Y-m-d') ?>"
                      class="attendance-btn">
                      <i data-lucide="clipboard-check" class="me-2" style="width: 16px; height: 16px;"></i>
                      Mark Today's Attendance
                    </a>
                  <?php else: ?>
                    <a href="attendance.php?semester=<?= $subject['SemesterNumber'] ?>&subject=<?= $subjectID ?>&date=<?= date('Y-m-d') ?>"
                      class="attendance-btn completed">
                      <i data-lucide="edit-3" class="me-2" style="width: 16px; height: 16px;"></i>
                      Update Today's Attendance
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <!-- Empty State -->
      <div class="text-center py-5">
        <div class="text-muted mb-3">
          <i data-lucide="book-x" style="width: 48px; height: 48px;"></i>
        </div>
        <h4 class="mb-2">No Subjects Assigned</h4>
        <p class="stats-text mb-3">Contact your administrator to get subjects assigned.</p>
        <a href="dashboard_teacher.php" class="btn btn-outline-primary">
          Back to Dashboard
        </a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Subject Details Modal - EXACT MATCH STYLE FROM MANAGE_ADMIN -->
  <div class="modal fade" id="subjectDetailsModal" tabindex="-1" aria-labelledby="subjectDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow">
        <div class="modal-header border-0 pb-2">
          <h5 class="modal-title fw-semibold" id="subjectDetailsModalLabel">
            <i data-lucide="book" class="me-2" style="width: 20px; height: 20px;"></i>
            Subject Details
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-2" id="subjectDetailsContent">
          <!-- Content will be loaded here -->
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Modal function with proper theming - EXACT MATCH FROM MANAGE_ADMIN
    function viewSubjectDetails(subjectId, subjectName, subjectCode, departmentName, semesterNumber, creditHour, lectureHour, isElective) {
      const modalContent = document.getElementById('subjectDetailsContent');
      const electiveText = isElective === 'true' ? 'Yes' : 'No';

      modalContent.innerHTML = `
            <div class="container-fluid">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="modal-section-container">
                            <h6 class="modal-section-title">
                                <i data-lucide="info" class="me-2" style="width: 16px; height: 16px;"></i>
                                Basic Information
                            </h6>
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td class="modal-table-label fw-medium" style="width: 45%;">Subject Code:</td>
                                    <td class="modal-table-text fw-semibold">${subjectCode}</td>
                                </tr>
                                <tr>
                                    <td class="modal-table-label fw-medium">Subject Name:</td>
                                    <td class="modal-table-text fw-semibold">${subjectName}</td>
                                </tr>
                                <tr>
                                    <td class="modal-table-label fw-medium">Department:</td>
                                    <td class="modal-table-text fw-semibold">${departmentName}</td>
                                </tr>
                                <tr>
                                    <td class="modal-table-label fw-medium">Semester:</td>
                                    <td class="modal-table-text fw-semibold">${semesterNumber}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-section-container">
                            <h6 class="modal-section-title">
                                <i data-lucide="clock" class="me-2" style="width: 16px; height: 16px;"></i>
                                Academic Details
                            </h6>
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td class="modal-table-label fw-medium" style="width: 45%;">Credit Hours:</td>
                                    <td class="modal-table-text fw-semibold">${creditHour}</td>
                                </tr>
                                <tr>
                                    <td class="modal-table-label fw-medium">Lecture Hours:</td>
                                    <td class="modal-table-text fw-semibold">${lectureHour}</td>
                                </tr>
                                <tr>
                                    <td class="modal-table-label fw-medium">Elective:</td>
                                    <td class="modal-table-text fw-semibold">${electiveText}</td>
                                </tr>
                                <tr>
                                    <td class="modal-table-label fw-medium">Academic Year:</td>
                                    <td class="modal-table-text fw-semibold">${new Date().getFullYear()}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="row g-2">
                    <div class="col-md-6">
                        <a href="attendance.php?semester=${semesterNumber}&subject=${subjectId}&date=${new Date().toISOString().split('T')[0]}" 
                           class="btn btn-primary w-100">
                            <i data-lucide="clipboard-check" class="me-1" style="width: 16px; height: 16px;"></i>
                            Mark Attendance
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="attendance_report.php?subject=${subjectId}" 
                           class="btn btn-outline-primary w-100">
                            <i data-lucide="bar-chart-3" class="me-1" style="width: 16px; height: 16px;"></i>
                            View Reports
                        </a>
                    </div>
                </div>
            </div>
        `;

      // Re-initialize Lucide icons
      lucide.createIcons();

      // Show modal
      const modalInstance = new bootstrap.Modal(document.getElementById('subjectDetailsModal'));
      modalInstance.show();
    }

    // DOM Content Loaded
    document.addEventListener('DOMContentLoaded', function() {
      // Card hover effects
      const subjectCards = document.querySelectorAll('.subject-card');
      subjectCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
          this.style.transform = 'translateY(-2px)';
        });

        card.addEventListener('mouseleave', function() {
          this.style.transform = 'translateY(0)';
        });
      });

      // Modal cleanup
      const modal = document.getElementById('subjectDetailsModal');
      modal.addEventListener('hidden.bs.modal', function() {
        document.body.classList.remove('modal-open');
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
          backdrop.remove();
        }
      });

      // Final icon refresh
      setTimeout(() => {
        lucide.createIcons();
      }, 100);
    });

    // Responsive behavior
    window.addEventListener('resize', function() {
      const modal = document.querySelector('.modal.show');
      if (modal) {
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) {
          bsModal.handleUpdate();
        }
      }
    });
  </script>
</body>

</html>