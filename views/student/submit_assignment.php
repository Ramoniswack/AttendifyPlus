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

// Get filter parameters
$selectedSubject = $_GET['subject'] ?? '';
$selectedStatus = $_GET['status'] ?? '';

// Get student's subjects
$subjectsQuery = $conn->prepare("
    SELECT DISTINCT s.SubjectID, s.SubjectCode, s.SubjectName
    FROM subjects s
    WHERE s.DepartmentID = ? AND s.SemesterID = ?
    ORDER BY s.SubjectName
");
$subjectsQuery->bind_param("ii", $studentRow['DepartmentID'], $studentRow['SemesterID']);
$subjectsQuery->execute();
$subjectsResult = $subjectsQuery->get_result();
$subjects = [];
while ($row = $subjectsResult->fetch_assoc()) {
    $subjects[] = $row;
}

// Get assignments with filters - ONLY for student's department and semester
$whereConditions = ["a.Status IN ('active', 'graded')"];
$params = [];
$paramTypes = "";

// Add department and semester filter
$whereConditions[] = "s.DepartmentID = ? AND s.SemesterID = ?";
$params[] = $studentRow['DepartmentID'];
$params[] = $studentRow['SemesterID'];
$paramTypes .= "ii";

if ($selectedSubject) {
    $whereConditions[] = "a.SubjectID = ?";
    $params[] = $selectedSubject;
    $paramTypes .= "i";
}

if ($selectedStatus) {
    if ($selectedStatus === 'submitted') {
        $whereConditions[] = "asub.SubmissionID IS NOT NULL";
    } elseif ($selectedStatus === 'not_submitted') {
        $whereConditions[] = "asub.SubmissionID IS NULL";
    } elseif ($selectedStatus === 'graded') {
        $whereConditions[] = "asub.Status = 'graded'";
    }
}

$whereClause = implode(" AND ", $whereConditions);

$assignmentsQuery = $conn->prepare("
    SELECT 
        a.AssignmentID,
        a.Title,
        a.Description,
        a.Instructions,
        a.DueDate,
        a.MaxPoints,
        a.Status as AssignmentStatus,
        a.SubmissionType,
        a.AttachmentFileName,
        s.SubjectCode,
        s.SubjectName,
        t.FullName as TeacherName,
        asub.SubmissionID,
        asub.SubmissionText,
        asub.SubmittedAt,
        asub.Status as SubmissionStatus,
        asub.Grade,
        asub.Feedback,
        asub.IsLate
    FROM assignments a
    JOIN subjects s ON a.SubjectID = s.SubjectID
    JOIN teachers t ON a.TeacherID = t.TeacherID
    LEFT JOIN assignment_submissions asub ON a.AssignmentID = asub.AssignmentID AND asub.StudentID = ?
    WHERE $whereClause
    ORDER BY a.DueDate DESC
");

$assignmentsQuery->bind_param("i" . $paramTypes, $studentID, ...$params);
$assignmentsQuery->execute();
$assignmentsResult = $assignmentsQuery->get_result();
$assignments = [];
while ($row = $assignmentsResult->fetch_assoc()) {
    $assignments[] = $row;
}

// Calculate statistics
$totalAssignments = count($assignments);
$submittedCount = count(array_filter($assignments, fn($a) => $a['SubmissionID'] !== null));
$gradedCount = count(array_filter($assignments, fn($a) => $a['SubmissionStatus'] === 'graded'));
$lateCount = count(array_filter($assignments, fn($a) => $a['IsLate'] == 1));

$completionRate = $totalAssignments > 0 ? round(($submittedCount / $totalAssignments) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments | Attendify+</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard_student.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_student.css">
    <link rel="stylesheet" href="../../assets/css/submit_assignment.css">

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
                <i data-lucide="clipboard-list"></i>
                My Assignments
            </h2>
            <p class="text-muted mb-0">View and submit your assignments</p>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Subject</label>
                        <select name="subject" class="form-select" onchange="this.form.submit()">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['SubjectID'] ?>"
                                    <?= $selectedSubject == $subject['SubjectID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($subject['SubjectCode'] . ' - ' . $subject['SubjectName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="not_submitted" <?= $selectedStatus === 'not_submitted' ? 'selected' : '' ?>>Not Submitted</option>
                            <option value="submitted" <?= $selectedStatus === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                            <option value="graded" <?= $selectedStatus === 'graded' ? 'selected' : '' ?>>Graded</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-primary me-2" onclick="exportAssignments()">
                            <i data-lucide="download"></i>
                            Export
                        </button>
                        <a href="submit_assignment.php" class="btn btn-outline-secondary">
                            <i data-lucide="refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="clipboard-list"></i>
                    </div>
                    <div class="mini-stat-value"><?= $totalAssignments ?></div>
                    <div class="mini-stat-label">Total Assignments</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="check-circle"></i>
                    </div>
                    <div class="mini-stat-value"><?= $completionRate ?>%</div>
                    <div class="mini-stat-label">Completion Rate</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="award"></i>
                    </div>
                    <div class="mini-stat-value"><?= $gradedCount ?></div>
                    <div class="mini-stat-label">Graded</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="clock"></i>
                    </div>
                    <div class="mini-stat-value"><?= $lateCount ?></div>
                    <div class="mini-stat-label">Late Submissions</div>
                </div>
            </div>
        </div>

        <!-- Assignments List -->
        <div class="row g-4">
            <?php foreach ($assignments as $assignment): ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="card assignment-card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title mb-1"><?= htmlspecialchars($assignment['Title']) ?></h5>
                                    <p class="text-muted mb-0"><?= htmlspecialchars($assignment['SubjectCode'] . ' - ' . $assignment['SubjectName']) ?></p>
                                </div>
                                <div class="assignment-status">
                                    <?php if ($assignment['SubmissionID']): ?>
                                        <?php if ($assignment['SubmissionStatus'] === 'graded'): ?>
                                            <span class="badge bg-success">Graded</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Submitted</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Submitted</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Teacher Info -->
                            <div class="teacher-info mb-3">
                                <div class="d-flex align-items-center">
                                    <i data-lucide="user" class="me-2"></i>
                                    <span class="text-muted"><?= htmlspecialchars($assignment['TeacherName']) ?></span>
                                </div>
                            </div>

                            <!-- Assignment Details -->
                            <div class="assignment-details mb-3">
                                <?php if ($assignment['Description']): ?>
                                    <p class="assignment-description"><?= htmlspecialchars(substr($assignment['Description'], 0, 100)) ?><?= strlen($assignment['Description']) > 100 ? '...' : '' ?></p>
                                <?php endif; ?>

                                <div class="assignment-meta">
                                    <div class="meta-item">
                                        <i data-lucide="calendar"></i>
                                        <span>Due: <?= $assignment['DueDate'] ? date('M j, Y', strtotime($assignment['DueDate'])) : 'No due date' ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i data-lucide="award"></i>
                                        <span>Max Points: <?= $assignment['MaxPoints'] ?></span>
                                    </div>
                                    <?php if ($assignment['AttachmentFileName']): ?>
                                        <div class="meta-item">
                                            <i data-lucide="paperclip"></i>
                                            <span>Has Attachment</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Submission Status -->
                            <div class="submission-status mb-3">
                                <?php if ($assignment['SubmissionID']): ?>
                                    <div class="submitted-info">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-success">
                                                <i data-lucide="check-circle"></i>
                                                Submitted on <?= date('M j, Y', strtotime($assignment['SubmittedAt'])) ?>
                                            </span>
                                            <?php if ($assignment['IsLate']): ?>
                                                <span class="badge bg-danger">Late</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($assignment['Grade'] !== null): ?>
                                            <div class="grade-info mt-2">
                                                <strong>Grade: <?= $assignment['Grade'] ?>/<?= $assignment['MaxPoints'] ?></strong>
                                                <?php if ($assignment['Feedback']): ?>
                                                    <div class="feedback mt-1">
                                                        <small class="text-muted"><?= htmlspecialchars($assignment['Feedback']) ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="not-submitted-info">
                                        <span class="text-muted">
                                            <i data-lucide="clock"></i>
                                            Not submitted yet
                                        </span>
                                        <?php if ($assignment['DueDate'] && strtotime($assignment['DueDate']) < time()): ?>
                                            <span class="badge bg-danger ms-2">Overdue</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer">
                            <?php if ($assignment['SubmissionID']): ?>
                                <button class="btn btn-outline-info btn-sm" onclick="viewSubmission(<?= $assignment['AssignmentID'] ?>)">
                                    <i data-lucide="eye"></i>
                                    View Submission
                                </button>
                            <?php else: ?>
                                <button class="btn btn-primary btn-sm" onclick="submitAssignment(<?= $assignment['AssignmentID'] ?>)">
                                    <i data-lucide="upload"></i>
                                    Submit Assignment
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-outline-secondary btn-sm" onclick="viewAssignment(<?= $assignment['AssignmentID'] ?>)">
                                <i data-lucide="file-text"></i>
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($assignments)): ?>
            <!-- No Assignments Message -->
            <div class="card text-center">
                <div class="card-body py-5">
                    <i data-lucide="clipboard-x" style="width: 64px; height: 64px;" class="text-muted mb-3"></i>
                    <h4>No Assignments Found</h4>
                    <p class="text-muted">No assignments found for the selected filters.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Assignment Details Modal -->
    <div class="modal fade" id="assignmentDetailsModal" tabindex="-1" aria-labelledby="assignmentDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignmentDetailsModalLabel">
                        <i data-lucide="file-text"></i>
                        Assignment Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="assignmentDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Assignment Modal -->
    <div class="modal fade" id="submitAssignmentModal" tabindex="-1" aria-labelledby="submitAssignmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="submitAssignmentModalLabel">
                        <i data-lucide="upload"></i>
                        Submit Assignment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="submitAssignmentForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="assignmentId" name="assignment_id">

                        <div class="mb-3">
                            <label class="form-label">Assignment Title</label>
                            <input type="text" class="form-control" id="assignmentTitle" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Submission Text</label>
                            <textarea class="form-control" name="submission_text" rows="5" placeholder="Enter your submission text here..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Upload Files</label>
                            <input type="file" class="form-control" name="submission_files[]" multiple accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png">
                            <small class="text-muted">You can upload multiple files. Supported formats: PDF, DOC, DOCX, TXT, JPG, PNG</small>
                        </div>

                        <div id="filePreview" class="mb-3">
                            <!-- File previews will be shown here -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="upload"></i>
                            Submit Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.js"></script>
    <script src="../../assets/js/navbar_student.js"></script>
    <script src="../../assets/js/submit_assignment.js"></script>
</body>

</html>