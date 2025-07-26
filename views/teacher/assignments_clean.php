<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\teacher\assignments.php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    header("Location: ../auth/login.php");
    exit();
}

include '../../config/db_config.php';

// Get teacher information
$loginID = $_SESSION['LoginID'];
$teacherStmt = $conn->prepare("SELECT TeacherID, FullName FROM teachers WHERE LoginID = ?");
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
$teacherRow = $teacherRes->fetch_assoc();

if (!$teacherRow) {
    header("Location: ../../logout.php");
    exit();
}

$teacherID = $teacherRow['TeacherID'];
$teacherName = $teacherRow['FullName'];

// Get teacher's subjects for filtering
$subjectsQuery = $conn->prepare("
    SELECT s.SubjectID, s.SubjectCode, s.SubjectName
    FROM subjects s
    JOIN teacher_subject_map tsm ON s.SubjectID = tsm.SubjectID
    WHERE tsm.TeacherID = ?
    ORDER BY s.SubjectName
");
$subjectsQuery->bind_param("i", $teacherID);
$subjectsQuery->execute();
$subjectsResult = $subjectsQuery->get_result();

// Get assignments from database (if table exists)
$activeAssignments = [];
$draftAssignments = [];
$gradedAssignments = [];

// Check if assignments table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'assignments'");
if ($tableCheck->num_rows > 0) {
    // Get active assignments
    $activeQuery = $conn->prepare("
        SELECT a.*, s.SubjectCode, s.SubjectName,
               COUNT(sub.SubmissionID) as submissions,
               COUNT(DISTINCT st.StudentID) as total_students
        FROM assignments a
        JOIN subjects s ON a.SubjectID = s.SubjectID
        LEFT JOIN assignment_submissions sub ON a.AssignmentID = sub.AssignmentID
        LEFT JOIN students st ON s.SemesterID = st.SemesterID AND s.DepartmentID = st.DepartmentID
        WHERE a.TeacherID = ? AND a.Status = 'active' AND a.IsActive = 1
        GROUP BY a.AssignmentID, s.SubjectCode, s.SubjectName
        ORDER BY a.DueDate ASC
    ");
    $activeQuery->bind_param("i", $teacherID);
    $activeQuery->execute();
    $activeResult = $activeQuery->get_result();
    while ($row = $activeResult->fetch_assoc()) {
        $activeAssignments[] = $row;
    }

    // Get draft assignments
    $draftQuery = $conn->prepare("
        SELECT a.*, s.SubjectCode, s.SubjectName,
               COUNT(sub.SubmissionID) as submissions,
               COUNT(DISTINCT st.StudentID) as total_students
        FROM assignments a
        JOIN subjects s ON a.SubjectID = s.SubjectID
        LEFT JOIN assignment_submissions sub ON a.AssignmentID = sub.AssignmentID
        LEFT JOIN students st ON s.SemesterID = st.SemesterID AND s.DepartmentID = st.DepartmentID
        WHERE a.TeacherID = ? AND a.Status = 'draft' AND a.IsActive = 1
        GROUP BY a.AssignmentID, s.SubjectCode, s.SubjectName
        ORDER BY a.CreatedAt DESC
    ");
    $draftQuery->bind_param("i", $teacherID);
    $draftQuery->execute();
    $draftResult = $draftQuery->get_result();
    while ($row = $draftResult->fetch_assoc()) {
        $draftAssignments[] = $row;
    }

    // Get graded assignments
    $gradedQuery = $conn->prepare("
        SELECT a.*, s.SubjectCode, s.SubjectName,
               COUNT(sub.SubmissionID) as submissions,
               COUNT(DISTINCT st.StudentID) as total_students,
               AVG(sub.Grade) as avg_score
        FROM assignments a
        JOIN subjects s ON a.SubjectID = s.SubjectID
        LEFT JOIN assignment_submissions sub ON a.AssignmentID = sub.AssignmentID AND sub.Status = 'graded'
        LEFT JOIN students st ON s.SemesterID = st.SemesterID AND s.DepartmentID = st.DepartmentID
        WHERE a.TeacherID = ? AND a.Status = 'graded' AND a.IsActive = 1
        GROUP BY a.AssignmentID, s.SubjectCode, s.SubjectName
        ORDER BY a.UpdatedAt DESC
    ");
    $gradedQuery->bind_param("i", $teacherID);
    $gradedQuery->execute();
    $gradedResult = $gradedQuery->get_result();
    while ($row = $gradedResult->fetch_assoc()) {
        $gradedAssignments[] = $row;
    }
} else {
    // Fallback to mock data if table doesn't exist
    $activeAssignments = [];
    $draftAssignments = [];
    $gradedAssignments = [];
}

// Helper functions
function getTimeRemaining($dueDate)
{
    if (!$dueDate) return 'No due date';

    $now = new DateTime();
    $due = new DateTime($dueDate);
    $diff = $now->diff($due);

    if ($due < $now) {
        return 'Past due';
    }

    if ($diff->days > 0) {
        return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' remaining';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' remaining';
    } else {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' remaining';
    }
}

function getSubmissionProgress($submissions, $total)
{
    if ($total == 0) return 0;
    return round(($submissions / $total) * 100);
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments | Attendify+</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/assignments.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_teacher.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

    <!-- JS Libraries -->
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/assignments.js" defer></script>
    <script src="../../assets/js/navbar_teacher.js" defer></script>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../components/sidebar_teacher_dashboard.php'; ?>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Navbar -->
    <?php include '../components/navbar_teacher.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 class="page-title">
                    <i data-lucide="clipboard-list"></i>
                    Assignments
                </h2>
                <p class="text-muted mb-0">Manage and track your student assignments</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-outline-primary" onclick="refreshData()" title="Refresh">
                    <i data-lucide="refresh-cw"></i>
                </button>
                <button class="btn btn-primary" onclick="createAssignment()">
                    <i data-lucide="plus"></i>
                    Create Assignment
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= count($activeAssignments) ?></div>
                            <div>Active</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i>
                                    Accepting submissions
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="clipboard-check"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card teachers text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= count($draftAssignments) ?></div>
                            <div>Drafts</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                                    Being prepared
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="edit-3"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card admins text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number">
                                <?php
                                $totalSubmissions = array_sum(array_column($activeAssignments, 'submissions'));
                                echo $totalSubmissions;
                                ?>
                            </div>
                            <div>Submissions</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="file-text" style="width: 14px; height: 14px;"></i>
                                    Total received
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="file-text"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card activities text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= count($gradedAssignments) ?></div>
                            <div>Graded</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                    Completed
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assignment Tabs -->
        <div class="assignments-tabs mb-4">
            <ul class="nav nav-tabs" id="assignmentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-assignments"
                        type="button" role="tab" aria-controls="active-assignments" aria-selected="true">
                        <i data-lucide="clipboard-check"></i>
                        Active
                        <span class="badge bg-primary ms-2"><?= count($activeAssignments) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="draft-tab" data-bs-toggle="tab" data-bs-target="#draft-assignments"
                        type="button" role="tab" aria-controls="draft-assignments" aria-selected="false">
                        <i data-lucide="edit-3"></i>
                        Drafts
                        <span class="badge bg-warning ms-2"><?= count($draftAssignments) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="graded-tab" data-bs-toggle="tab" data-bs-target="#graded-assignments"
                        type="button" role="tab" aria-controls="graded-assignments" aria-selected="false">
                        <i data-lucide="check-circle"></i>
                        Graded
                        <span class="badge bg-success ms-2"><?= count($gradedAssignments) ?></span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="assignmentTabContent">
            <!-- Active Assignments Tab -->
            <div class="tab-pane fade show active" id="active-assignments" role="tabpanel" aria-labelledby="active-tab">
                <?php if (empty($activeAssignments)): ?>
                    <div class="empty-state">
                        <div class="empty-state-content">
                            <i data-lucide="clipboard-check" class="empty-state-icon"></i>
                            <h3>No Active Assignments</h3>
                            <p>Create your first assignment to get started with managing student work.</p>
                            <button class="btn btn-primary" onclick="createAssignment()">
                                <i data-lucide="plus"></i>
                                Create Assignment
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="assignments-grid">
                        <?php foreach ($activeAssignments as $assignment): ?>
                            <div class="assignment-card">
                                <div class="assignment-header">
                                    <span class="subject-code"><?= $assignment['SubjectCode'] ?></span>
                                    <span class="points"><?= $assignment['MaxPoints'] ?> pts</span>
                                </div>
                                <h5 class="assignment-title"><?= htmlspecialchars($assignment['Title']) ?></h5>
                                <p class="assignment-description"><?= htmlspecialchars(substr($assignment['Description'], 0, 100)) ?>...</p>
                                <div class="assignment-footer">
                                    <small class="text-muted">Due: <?= date('M j, Y', strtotime($assignment['DueDate'])) ?></small>
                                    <div class="assignment-actions">
                                        <button class="btn btn-sm btn-primary">View</button>
                                        <button class="btn btn-sm btn-outline-secondary">Grade</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Draft Assignments Tab -->
            <div class="tab-pane fade" id="draft-assignments" role="tabpanel" aria-labelledby="draft-tab">
                <?php if (empty($draftAssignments)): ?>
                    <div class="empty-state">
                        <div class="empty-state-content">
                            <i data-lucide="edit-3" class="empty-state-icon"></i>
                            <h3>No Draft Assignments</h3>
                            <p>Your draft assignments will appear here when you create them.</p>
                            <button class="btn btn-primary" onclick="createAssignment()">
                                <i data-lucide="plus"></i>
                                Create Assignment
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="assignments-grid">
                        <?php foreach ($draftAssignments as $assignment): ?>
                            <div class="assignment-card draft-card">
                                <div class="assignment-header">
                                    <span class="subject-code"><?= $assignment['SubjectCode'] ?></span>
                                    <span class="draft-badge">Draft</span>
                                </div>
                                <h5 class="assignment-title"><?= htmlspecialchars($assignment['Title']) ?></h5>
                                <p class="assignment-description"><?= htmlspecialchars(substr($assignment['Description'], 0, 100)) ?>...</p>
                                <div class="assignment-footer">
                                    <small class="text-muted">Created: <?= date('M j, Y', strtotime($assignment['CreatedAt'])) ?></small>
                                    <div class="assignment-actions">
                                        <button class="btn btn-sm btn-primary">Edit</button>
                                        <button class="btn btn-sm btn-success">Publish</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Graded Assignments Tab -->
            <div class="tab-pane fade" id="graded-assignments" role="tabpanel" aria-labelledby="graded-tab">
                <?php if (empty($gradedAssignments)): ?>
                    <div class="empty-state">
                        <div class="empty-state-content">
                            <i data-lucide="check-circle" class="empty-state-icon"></i>
                            <h3>No Graded Assignments</h3>
                            <p>Completed assignments will appear here after grading.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="assignments-grid">
                        <?php foreach ($gradedAssignments as $assignment): ?>
                            <div class="assignment-card graded-card">
                                <div class="assignment-header">
                                    <span class="subject-code"><?= $assignment['SubjectCode'] ?></span>
                                    <span class="graded-badge">Graded</span>
                                </div>
                                <h5 class="assignment-title"><?= htmlspecialchars($assignment['Title']) ?></h5>
                                <p class="assignment-description"><?= htmlspecialchars(substr($assignment['Description'], 0, 100)) ?>...</p>
                                <div class="assignment-footer">
                                    <small class="text-muted">Avg Score: <?= isset($assignment['avg_score']) ? round($assignment['avg_score'], 1) : '0' ?>%</small>
                                    <div class="assignment-actions">
                                        <button class="btn btn-sm btn-primary">View Grades</button>
                                        <button class="btn btn-sm btn-outline-secondary">Export</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        // Placeholder functions for assignment management
        function createAssignment() {
            alert('Create Assignment functionality will be implemented in the next phase.');
        }

        function refreshData() {
            location.reload();
        }
    </script>
</body>

</html>