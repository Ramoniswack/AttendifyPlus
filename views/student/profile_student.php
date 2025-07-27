<?php
session_start();
require_once(__DIR__ . '/../../config/db_config.php');

// Check session
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$loginID = $_SESSION['LoginID'];

// Get student info
$studentStmt = $conn->prepare("
    SELECT s.*, d.DepartmentName, sem.SemesterNumber 
    FROM students s
    JOIN departments d ON s.DepartmentID = d.DepartmentID
    JOIN semesters sem ON s.SemesterID = sem.SemesterID
    WHERE s.LoginID = ?
");
$studentStmt->bind_param("i", $loginID);
$studentStmt->execute();
$studentRes = $studentStmt->get_result();
$studentRow = $studentRes->fetch_assoc();

if (!$studentRow) {
    header("Location: ../logout.php");
    exit();
}

$studentID = $studentRow['StudentID'];

// Get login info
$loginStmt = $conn->prepare("SELECT Email FROM login_tbl WHERE LoginID = ?");
$loginStmt->bind_param("i", $loginID);
$loginStmt->execute();
$loginRes = $loginStmt->get_result();
$loginRow = $loginRes->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Attendify+</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard_student.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_student.css">
    <link rel="stylesheet" href="../../assets/css/profile_student.css">

    <!-- JS Libraries -->
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/sidebar_student.js" defer></script>
    <script src="../../assets/js/navbar_student.js" defer></script>
</head>

<body>
    <!-- Include sidebar and navbar -->
    <?php include '../components/sidebar_student_dashboard.php'; ?>
    <?php include '../components/navbar_student.php'; ?>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">
                <i data-lucide="user"></i>
                My Profile
            </h2>
            <p class="text-muted mb-0">Manage your account information and settings</p>
        </div>

        <div class="row g-4">
            <!-- Profile Card -->
            <div class="col-lg-4 col-md-6">
                <div class="card profile-card">
                    <div class="card-body text-center">
                        <div class="profile-photo mb-3">
                            <?php if ($studentRow['PhotoURL']): ?>
                                <img src="<?= htmlspecialchars($studentRow['PhotoURL']) ?>"
                                    alt="Profile Photo" class="rounded-circle" width="120" height="120">
                            <?php else: ?>
                                <div class="profile-placeholder rounded-circle">
                                    <i data-lucide="user" style="width: 60px; height: 60px;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h4 class="mb-1"><?= htmlspecialchars($studentRow['FullName']) ?></h4>
                        <p class="text-muted mb-2"><?= htmlspecialchars($studentRow['ProgramCode']) ?></p>
                        <div class="student-info">
                            <div class="info-item">
                                <i data-lucide="graduation-cap"></i>
                                <span><?= htmlspecialchars($studentRow['DepartmentName']) ?></span>
                            </div>
                            <div class="info-item">
                                <i data-lucide="calendar"></i>
                                <span>Semester <?= $studentRow['SemesterNumber'] ?></span>
                            </div>
                            <div class="info-item">
                                <i data-lucide="calendar-days"></i>
                                <span>Joined <?= $studentRow['JoinYear'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Details -->
            <div class="col-lg-8 col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i data-lucide="edit-3"></i>
                            Profile Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($studentRow['FullName']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?= htmlspecialchars($loginRow['Email']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($studentRow['Contact']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Program Code</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($studentRow['ProgramCode']) ?>" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($studentRow['Address']) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($studentRow['DepartmentName']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Current Semester</label>
                                    <input type="text" class="form-control" value="Semester <?= $studentRow['SemesterNumber'] ?>" readonly>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row g-4 mt-4">
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="calendar-check"></i>
                    </div>
                    <div class="mini-stat-value">85%</div>
                    <div class="mini-stat-label">Attendance Rate</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="book-open"></i>
                    </div>
                    <div class="mini-stat-value">6</div>
                    <div class="mini-stat-label">Active Subjects</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="file-text"></i>
                    </div>
                    <div class="mini-stat-value">12</div>
                    <div class="mini-stat-label">Assignments</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="award"></i>
                    </div>
                    <div class="mini-stat-value">B+</div>
                    <div class="mini-stat-label">Average Grade</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.js"></script>
    <script src="../../assets/js/navbar_student.js"></script>
</body>

</html>