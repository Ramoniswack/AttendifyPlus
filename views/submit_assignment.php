<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\submit_assignment.php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    header("Location: login.php");
    exit();
}

include '../config/db_config.php';

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
$upcomingAssignments = [
    [
        'id' => 1,
        'title' => 'Data Structures Implementation',
        'subject' => 'Data Structures',
        'subject_code' => 'BCA301',
        'teacher' => 'Prof. Smith',
        'due_date' => date('Y-m-d H:i:s', strtotime('+3 days')),
        'points' => 100,
        'description' => 'Implement Binary Search Tree with all basic operations including insertion, deletion, and traversal methods.',
        'status' => 'upcoming',
        'submitted' => false,
        'file_types' => ['.java', '.cpp', '.py', '.pdf'],
        'max_size' => '10MB'
    ],
    [
        'id' => 2,
        'title' => 'Web Development Project',
        'subject' => 'Web Technology',
        'subject_code' => 'BCA303',
        'teacher' => 'Prof. Johnson',
        'due_date' => date('Y-m-d H:i:s', strtotime('+1 week')),
        'points' => 150,
        'description' => 'Create a responsive web application using HTML5, CSS3, and JavaScript with proper form validation.',
        'status' => 'upcoming',
        'submitted' => false,
        'file_types' => ['.html', '.css', '.js', '.zip'],
        'max_size' => '25MB'
    ],
    [
        'id' => 3,
        'title' => 'OOP Concepts Report',
        'subject' => 'OOP in Java',
        'subject_code' => 'BCA302',
        'teacher' => 'Prof. Davis',
        'due_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
        'points' => 75,
        'description' => 'Write a comprehensive report on Object-Oriented Programming concepts with practical examples.',
        'status' => 'upcoming',
        'submitted' => false,
        'file_types' => ['.pdf', '.docx'],
        'max_size' => '5MB'
    ]
];

$pastDueAssignments = [
    [
        'id' => 4,
        'title' => 'Database Design Assignment',
        'subject' => 'Database Systems',
        'subject_code' => 'BCA205',
        'teacher' => 'Prof. Wilson',
        'due_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'points' => 120,
        'description' => 'Design an ER diagram for a library management system and convert it to relational schema.',
        'status' => 'past_due',
        'submitted' => false,
        'file_types' => ['.pdf', '.jpg', '.png'],
        'max_size' => '15MB'
    ]
];

$completedAssignments = [
    [
        'id' => 5,
        'title' => 'C Programming Basics',
        'subject' => 'C Programming',
        'subject_code' => 'BCA201',
        'teacher' => 'Prof. Brown',
        'due_date' => date('Y-m-d H:i:s', strtotime('-1 week')),
        'submitted_date' => date('Y-m-d H:i:s', strtotime('-1 week 2 hours')),
        'points' => 80,
        'earned_points' => 75,
        'grade' => 'A-',
        'description' => 'Write programs to demonstrate basic C programming concepts including loops, arrays, and functions.',
        'status' => 'completed',
        'submitted' => true,
        'feedback' => 'Good work! Your code structure is clean and well-commented. Minor improvements needed in error handling.'
    ],
    [
        'id' => 6,
        'title' => 'Digital Logic Circuits',
        'subject' => 'Digital Logic',
        'subject_code' => 'BCA103',
        'teacher' => 'Prof. Anderson',
        'due_date' => date('Y-m-d H:i:s', strtotime('-2 weeks')),
        'submitted_date' => date('Y-m-d H:i:s', strtotime('-2 weeks 1 day')),
        'points' => 100,
        'earned_points' => 92,
        'grade' => 'A',
        'description' => 'Design and analyze combinational logic circuits using Boolean algebra.',
        'status' => 'completed',
        'submitted' => true,
        'feedback' => 'Excellent work! All circuit designs are correct and well-documented.'
    ]
];

// Function to format time remaining
function getTimeRemaining($dueDate) {
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

function getPriorityClass($dueDate) {
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
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600;700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard_student.css">
    <link rel="stylesheet" href="../assets/css/submit_assignment.css">
</head>

<body>
    <!-- Sidebar -->
    <?php include 'sidebar_student_dashboard.php'; ?>
    
    <!-- Navbar -->
    <?php include 'navbar_student.php'; ?>
    
    <!-- Main Content -->
    <div class="container-fluid dashboard-container">
        <!-- Header -->
        <div class="assignments-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1 class="assignments-title">
                        <i data-lucide="clipboard-list"></i>
                        Assignments
                    </h1>
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
                            <option value="BCA301">Data Structures</option>
                            <option value="BCA302">OOP in Java</option>
                            <option value="BCA303">Web Technology</option>
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
                                        <button class="btn btn-sm btn-outline-secondary" onclick="viewAssignment(<?= $assignment['id'] ?>)">
                                            <i data-lucide="eye"></i>
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
                                    <button class="btn btn-primary btn-submit" onclick="openSubmissionModal(<?= htmlspecialchars(json_encode($assignment)) ?>)">
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

    <!-- Submission Modal -->
    <div class="modal fade" id="submissionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="upload"></i>
                        Submit Assignment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                    <button type="button" class="btn btn-primary" id="submitBtn" onclick="submitAssignment()">
                        <i data-lucide="send"></i>
                        Submit Assignment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/lucide.min.js"></script>
    <script src="../assets/js/submit_assignment.js"></script>
</body>
</html>