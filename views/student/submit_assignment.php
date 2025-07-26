<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\student\submit_assignment.php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

include '../../config/db_config.php';

// Get student information
$studentQuery = "SELECT s.StudentID, s.FullName, s.DepartmentID, s.SemesterID,
                        d.DepartmentName, sem.SemesterNumber
                FROM students s 
                JOIN departments d ON s.DepartmentID = d.DepartmentID 
                JOIN semesters sem ON s.SemesterID = sem.SemesterID 
                WHERE s.LoginID = ?";
$stmt = $conn->prepare($studentQuery);
$stmt->bind_param("i", $_SESSION['LoginID']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    die("Student information not found.");
}

// Mock assignment data (since we don't have assignments table yet)

// Fetch assignments uploaded by teachers from the database
$upcomingAssignments = [];
$pastDueAssignments = [];
$completedAssignments = [];

$studentID = $student['StudentID'];



// Only show assignments for student's department and semester
$assignmentQuery = $conn->prepare("
    SELECT 
        a.AssignmentID as id, 
        a.Title as title, 
        s.SubjectName as subject, 
        s.SubjectCode as subject_code, 
        t.FullName as teacher, 
        a.DueDate as due_date, 
        a.MaxPoints as points, 
        a.Description as description, 
        a.Status as status,
        a.AttachmentFileName,
        a.AttachmentPath
    FROM assignments a
    JOIN subjects s ON a.SubjectID = s.SubjectID
    JOIN teachers t ON a.TeacherID = t.TeacherID
    WHERE a.IsActive = 1 
      AND a.Status IN ('active','graded')
      AND s.DepartmentID = ? 
      AND s.SemesterID = ?
    ORDER BY a.DueDate ASC
");
$assignmentQuery->bind_param("ii", $student['DepartmentID'], $student['SemesterID']);
$assignmentQuery->execute();
$result = $assignmentQuery->get_result();


while ($row = $result->fetch_assoc()) {
    // Check if student has submitted
    $submissionQuery = $conn->prepare("SELECT Status, SubmittedAt, Grade, Feedback, FilePath, OriginalFileName FROM assignment_submissions WHERE AssignmentID = ? AND StudentID = ? LIMIT 1");
    $submissionQuery->bind_param("ii", $row['id'], $studentID);
    $submissionQuery->execute();
    $submissionRes = $submissionQuery->get_result();
    $submission = $submissionRes->fetch_assoc();
    $row['submitted'] = $submission ? true : false;
    $row['submission_status'] = $submission ? $submission['Status'] : null;
    $row['submitted_date'] = $submission ? $submission['SubmittedAt'] : null;
    $row['grade'] = $submission ? $submission['Grade'] : null;
    $row['feedback'] = $submission ? $submission['Feedback'] : null;
    $row['submission_file'] = $submission ? $submission['FilePath'] : null;
    $row['submission_file_name'] = $submission ? $submission['OriginalFileName'] : null;

    $now = new DateTime();
    $due = new DateTime($row['due_date']);
    if ($row['submitted']) {
        $row['status'] = 'completed';
        $completedAssignments[] = $row;
    } elseif ($due < $now) {
        $row['status'] = 'past_due';
        $pastDueAssignments[] = $row;
    } else {
        $row['status'] = 'upcoming';
        $upcomingAssignments[] = $row;
    }
}

// Helper functions
function getTimeRemaining($dueDate)
{
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

function getPriorityClass($dueDate)
{
    $now = new DateTime();
    $due = new DateTime($dueDate);
    $diff = $now->diff($due);

    if ($due < $now) {
        return 'priority-overdue';
    } elseif ($diff->days <= 1) {
        return 'priority-urgent';
    } elseif ($diff->days <= 3) {
        return 'priority-medium';
    } else {
        return 'priority-low';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments | Attendify+</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard_student.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_student.css">
    <link rel="stylesheet" href="../../assets/css/submit_assignment.css">

    <!-- JS Libraries -->
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/dashboard_student.js" defer></script>
    <script src="../../assets/js/submit_assignment.js" defer></script>
    <script src="../../assets/js/navbar_student.js" defer></script>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../components/sidebar_student_dashboard.php'; ?>

    <!-- Navbar -->
    <?php include '../components/navbar_student.php'; ?>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container main-content">
        <!-- Submission Modal (static, hidden by default) -->
        <div class="modal fade" id="submissionModal" tabindex="-1" aria-labelledby="submissionModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content" style="margin-top:60px;">
                    <div class="modal-header">
                        <h5 class="modal-title" id="submissionModalLabel" style="color: var(--accent-light, #1A73E8);">
                            <i data-lucide="upload"></i>
                            Submit Assignment
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="submissionForm" enctype="multipart/form-data">
                            <input type="hidden" id="assignmentId" name="assignment_id">
                            <div class="assignment-info-card">
                                <h6 id="modalAssignmentTitle"></h6>
                                <div class="d-flex gap-3 text-muted">
                                    <span id="modalSubject"></span>
                                    <span id="modalDueDate"></span>
                                    <span id="modalPoints"></span>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Assignment Description</label>
                                <div class="description-box" id="modalDescription"></div>
                            </div>
                            <div class="mb-4">
                                <label for="submissionText" class="form-label">
                                    <i data-lucide="type"></i>
                                    Your Response (Optional)
                                </label>
                                <textarea class="form-control" id="submissionText" name="submission_text" rows="4"
                                    placeholder="Add any comments or notes about your submission..."></textarea>
                            </div>
                            <div class="mb-4">
                                <label for="fileUpload" class="form-label">
                                    <i data-lucide="paperclip"></i>
                                    Upload Files
                                </label>
                                <div class="upload-zone" onclick="document.getElementById('fileUpload').click()">
                                    <input type="file" id="fileUpload" name="files[]" multiple style="display: none;"
                                        accept=".pdf,.doc,.docx,.txt,.jpg,.png,.zip,.rar">
                                    <div class="upload-content">
                                        <i data-lucide="upload-cloud" class="upload-icon"></i>
                                        <h6>Drop files here or click to browse</h6>
                                        <p class="text-muted">Accepted formats: <span id="acceptedFormats"></span></p>
                                        <p class="text-muted">Maximum size: <span id="maxFileSize"></span></p>
                                    </div>
                                </div>
                                <div id="fileList" class="file-list mt-3"></div>
                            </div>
                            <div class="submission-checklist">
                                <h6><i data-lucide="check-square"></i> Before you submit:</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="checkComplete">
                                    <label class="form-check-label" for="checkComplete">
                                        I have completed all required parts of this assignment
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="checkFiles">
                                    <label class="form-check-label" for="checkFiles">
                                        I have attached all necessary files
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="checkOriginal">
                                    <label class="form-check-label" for="checkOriginal">
                                        This work is my own original work
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary btn-submit" id="submitBtn" onclick="submitAssignment()">
                            <i data-lucide="send"></i>
                            Submit Assignment
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Header -->
        <div class="assignments-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1 class="assignments-title">
                        <i data-lucide="clipboard-list"></i>
                        Assignments
                    </h1>
                    <style>
                        .assignments-title {
                            color: var(--accent-light, #1A73E8) !important;
                        }

                        body.dark-mode .assignments-title {
                            color: var(--accent-dark, #00ffc8) !important;
                        }
                    </style>
                    <p class="assignments-subtitle">Manage your coursework and submissions</p>
                </div>
                <div class="header-actions">
                    <div class="search-container">
                        <input type="text" class="form-control search-input" placeholder="Search assignments..." id="searchInput">
                        <i data-lucide="search" class="search-icon"></i>
                    </div>
                    <div class="filter-dropdown">
                        <select class="form-select filter-select" id="subjectFilter">
                            <option value="">All subjects</option>
                            <?php
                            // Show only subjects assigned to the student
                            $subjectListQuery = $conn->prepare("
                                SELECT DISTINCT s.SubjectCode, s.SubjectName
                                FROM subjects s
                                JOIN assignments a ON a.SubjectID = s.SubjectID
                                WHERE s.DepartmentID = ? AND s.SemesterID = ?
                            ");
                            $subjectListQuery->bind_param("ii", $student['DepartmentID'], $student['SemesterID']);
                            $subjectListQuery->execute();
                            $subjectListRes = $subjectListQuery->get_result();
                            while ($subj = $subjectListRes->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($subj['SubjectCode']) . '">' . htmlspecialchars($subj['SubjectName']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assignment Tabs -->
        <div class="assignments-tabs">
            <ul class="nav nav-tabs" id="assignmentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab">
                        <i data-lucide="clock"></i>
                        Upcoming
                        <span class="badge bg-primary ms-2"><?= count($upcomingAssignments) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="past-due-tab" data-bs-toggle="tab" data-bs-target="#past-due" type="button" role="tab">
                        <i data-lucide="alert-triangle"></i>
                        Past due
                        <span class="badge bg-danger ms-2"><?= count($pastDueAssignments) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">
                        <i data-lucide="check-circle"></i>
                        Completed
                        <span class="badge bg-success ms-2"><?= count($completedAssignments) ?></span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Assignment Content -->
        <div class="tab-content assignments-content" id="assignmentTabsContent">
            <!-- Upcoming Assignments -->
            <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                <?php if (empty($upcomingAssignments)): ?>
                    <div class="empty-state">
                        <i data-lucide="clipboard-check" class="empty-icon"></i>
                        <h4>No upcoming assignments</h4>
                        <p>You're all caught up! Check back later for new assignments.</p>
                    </div>
                <?php else: ?>
                    <div class="assignments-grid">
                        <?php foreach ($upcomingAssignments as $assignment): ?>
                            <div class="assignment-card upcoming-card <?= getPriorityClass($assignment['due_date']) ?>">
                                <div class="assignment-header">
                                    <div class="assignment-meta">
                                        <span class="subject-code"><?= htmlspecialchars($assignment['subject_code']) ?></span>
                                        <span class="points"><?= $assignment['points'] ?> points</span>
                                    </div>
                                    <div class="assignment-actions">
                                        <button class="btn btn-sm btn-outline-secondary" onclick="openAssignmentDetailModal(<?= htmlspecialchars(json_encode($assignment)) ?>)">
                                            <i data-lucide="eye"></i> View Assignment
                                        </button>
                                    </div>
                                </div>

                                <div class="assignment-content">
                                    <h3 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h3>
                                    <p class="assignment-subject"><?= htmlspecialchars($assignment['subject']) ?></p>
                                    <p class="assignment-teacher">by <?= htmlspecialchars($assignment['teacher']) ?></p>

                                    <div class="assignment-description">
                                        <?= htmlspecialchars($assignment['description']) ?>
                                    </div>

                                    <div class="assignment-details">
                                        <div class="due-info">
                                            <i data-lucide="calendar"></i>
                                            <span>Due <?= date('M j, Y g:i A', strtotime($assignment['due_date'])) ?></span>
                                        </div>
                                        <div class="time-remaining <?= getPriorityClass($assignment['due_date']) ?>">
                                            <i data-lucide="clock"></i>
                                            <span><?= getTimeRemaining($assignment['due_date']) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="assignment-footer">
                                    <?php if (!empty($assignment['AttachmentPath'])): ?>
                                        <a href="/AttendifyPlus/<?= htmlspecialchars($assignment['AttachmentPath']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i data-lucide="download"></i> View/Download Attachment
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-primary btn-submit" onclick='openSubmissionModal(<?= htmlspecialchars(json_encode($assignment)) ?>)'>
                                        <i data-lucide="upload"></i>
                                        Submit Assignment
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Past Due Assignments -->
            <div class="tab-pane fade" id="past-due" role="tabpanel">
                <?php if (empty($pastDueAssignments)): ?>
                    <div class="empty-state">
                        <i data-lucide="check-circle-2" class="empty-icon text-success"></i>
                        <h4>No past due assignments</h4>
                        <p>Great job staying on top of your work!</p>
                    </div>
                <?php else: ?>
                    <div class="assignments-grid">
                        <?php foreach ($pastDueAssignments as $assignment): ?>
                            <div class="assignment-card past-due-card">
                                <div class="assignment-header">
                                    <div class="assignment-meta">
                                        <span class="subject-code"><?= htmlspecialchars($assignment['subject_code']) ?></span>
                                        <span class="points"><?= $assignment['points'] ?> points</span>
                                        <span class="badge bg-danger">Past Due</span>
                                    </div>
                                </div>

                                <div class="assignment-content">
                                    <h3 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h3>
                                    <p class="assignment-subject"><?= htmlspecialchars($assignment['subject']) ?></p>
                                    <p class="assignment-teacher">by <?= htmlspecialchars($assignment['teacher']) ?></p>

                                    <div class="assignment-description">
                                        <?= htmlspecialchars($assignment['description']) ?>
                                    </div>

                                    <div class="assignment-details">
                                        <div class="due-info overdue">
                                            <i data-lucide="alert-triangle"></i>
                                            <span>Was due <?= date('M j, Y g:i A', strtotime($assignment['due_date'])) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="assignment-footer">
                                    <button class="btn btn-warning btn-submit" onclick="openSubmissionModal(<?= htmlspecialchars(json_encode($assignment)) ?>)">
                                        <i data-lucide="upload"></i>
                                        Submit Late
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Completed Assignments -->
            <div class="tab-pane fade" id="completed" role="tabpanel">
                <?php if (empty($completedAssignments)): ?>
                    <div class="empty-state">
                        <i data-lucide="clipboard" class="empty-icon"></i>
                        <h4>No completed assignments</h4>
                        <p>Your completed assignments will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="assignments-grid">
                        <?php foreach ($completedAssignments as $assignment): ?>
                            <div class="assignment-card completed-card">
                                <div class="assignment-header">
                                    <div class="assignment-meta">
                                        <span class="subject-code"><?= htmlspecialchars($assignment['subject_code']) ?></span>
                                        <span class="grade-display">
                                            <?= $assignment['earned_points'] ?>/<?= $assignment['points'] ?>
                                            <span class="grade"><?= $assignment['grade'] ?></span>
                                        </span>
                                        <span class="badge bg-success">Completed</span>
                                    </div>
                                </div>

                                <div class="assignment-content">
                                    <h3 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h3>
                                    <p class="assignment-subject"><?= htmlspecialchars($assignment['subject']) ?></p>
                                    <p class="assignment-teacher">by <?= htmlspecialchars($assignment['teacher']) ?></p>

                                    <div class="assignment-details">
                                        <div class="submitted-info">
                                            <i data-lucide="check-circle"></i>
                                            <span>Submitted <?= date('M j, Y g:i A', strtotime($assignment['submitted_date'])) ?></span>
                                        </div>
                                    </div>

                                    <?php if (!empty($assignment['feedback'])): ?>
                                        <div class="feedback-section">
                                            <h6><i data-lucide="message-circle"></i> Teacher Feedback</h6>
                                            <p class="feedback-text"><?= htmlspecialchars($assignment['feedback']) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="assignment-footer">
                                    <?php if (!empty($assignment['AttachmentPath'])): ?>
                                        <a href="/AttendifyPlus/<?= htmlspecialchars($assignment['AttachmentPath']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i data-lucide="download"></i> View/Download Attachment
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-primary" onclick="viewSubmission(<?= $assignment['id'] ?>)">
                                        <i data-lucide="eye"></i>
                                        View Submission
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Submission Modal (created dynamically) -->
    <script>
        // Only populate and show the static modal
        function openSubmissionModal(assignment) {
            // Populate modal fields
            document.getElementById('assignmentId').value = assignment.id;
            document.getElementById('modalAssignmentTitle').textContent = assignment.title;
            document.getElementById('modalSubject').textContent = assignment.subject_code + ' - ' + assignment.subject;
            document.getElementById('modalDueDate').textContent = 'Due: ' + new Date(assignment.due_date).toLocaleString();
            document.getElementById('modalPoints').textContent = assignment.points + ' points';
            document.getElementById('modalDescription').textContent = assignment.description;
            document.getElementById('submissionText').value = '';
            document.getElementById('fileList').innerHTML = '';
            document.getElementById('acceptedFormats').textContent = '.pdf, .doc, .docx, .txt, .jpg, .png, .zip';
            document.getElementById('maxFileSize').textContent = '10MB';
            // Reset checkboxes
            var checkboxes = document.querySelectorAll('.submission-checklist input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = false);
            // Show modal using Bootstrap
            var modal = new bootstrap.Modal(document.getElementById('submissionModal'));
            modal.show();
            if (typeof lucide !== "undefined") lucide.createIcons();
        }
    </script>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/submit_assignment.js"></script>
    <script>
        // Assignment Detail Modal logic
        function openAssignmentDetailModal(assignment) {
            let fileView = '';
            if (assignment.AttachmentPath) {
                let fileName = assignment.AttachmentFileName || 'Attachment';
                let fileType = assignment.AttachmentPath.split('.').pop().toLowerCase();
                if (fileType === 'pdf') {
                    fileView = `<div class='mb-3'><strong>Attachment Preview:</strong><br><iframe src='${assignment.AttachmentPath}' style='width:100%;height:400px;border:1px solid #ccc;border-radius:8px;'></iframe></div>`;
                } else if (["jpg", "jpeg", "png", "gif", "bmp"].includes(fileType)) {
                    fileView = `<div class='mb-3'><strong>Attachment Preview:</strong><br><img src='${assignment.AttachmentPath}' alt='${fileName}' style='max-width:100%;max-height:300px;border:1px solid #ccc;border-radius:8px;' /></div>`;
                } else {
                    fileView = `<div class='mb-3'><strong>Attachment:</strong> <a href='${assignment.AttachmentPath}' target='_blank'><i data-lucide='file-text'></i> View/Download File</a></div>`;
                }
            }
            let modalHtml = `<div class='modal fade show' id='assignmentDetailModal' tabindex='-1' style='display:block;background:rgba(0,0,0,0.5);z-index:1055;'>
            <div class='modal-dialog modal-lg modal-dialog-centered'>
                <div class='modal-content' style='margin-top:60px;'>
                    <div class='modal-header'>
                        <h5 class='modal-title'><i data-lucide='clipboard-list'></i> ${assignment.title}</h5>
                        <button type='button' class='btn-close' onclick='closeAssignmentDetailModal()'></button>
                    </div>
                    <div class='modal-body'>
                        <div class='mb-2'><strong>Subject:</strong> ${assignment.subject_code} - ${assignment.subject}</div>
                        <div class='mb-2'><strong>Teacher:</strong> ${assignment.teacher}</div>
                        <div class='mb-2'><strong>Due Date:</strong> ${new Date(assignment.due_date).toLocaleString()}</div>
                        <div class='mb-2'><strong>Points:</strong> ${assignment.points}</div>
                        <div class='mb-3'><strong>Description:</strong><br>${assignment.description}</div>
                        ${fileView}
                        ${assignment.submitted ? `<div class='alert alert-success'>You have already submitted this assignment.</div>` : ''}
                    </div>
                    <div class='modal-footer'>
                        <button type='button' class='btn btn-secondary' onclick='closeAssignmentDetailModal()'>Close</button>
                        ${!assignment.submitted ? `<button type='button' class='btn btn-primary' onclick='closeAssignmentDetailModal();openSubmissionModal(${JSON.stringify(assignment)})'><i data-lucide="upload"></i> Submit Assignment</button>` : ''}
                    </div>
                </div>
            </div>
        </div>`;
            let oldModal = document.getElementById('assignmentDetailModal');
            if (oldModal) oldModal.remove();
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            lucide.createIcons();
            // Prevent closing by clicking outside or pressing Esc
            document.getElementById('assignmentDetailModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    e.stopPropagation();
                }
            });
            document.body.style.overflow = 'hidden';
        }

        function closeAssignmentDetailModal() {
            let modal = document.getElementById('assignmentDetailModal');
            if (modal) modal.remove();
            document.body.style.overflow = '';
        }
    </script>
</body>

</html>