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

// Mock assignment data (since we don't have assignments table yet)
$activeAssignments = [
    [
        'id' => 1,
        'title' => 'Data Structures Implementation Project',
        'subject' => 'Data Structures',
        'subject_code' => 'BCA301',
        'description' => 'Students will implement various data structures including Binary Search Tree, Hash Table, and Graph algorithms with complete documentation.',
        'created_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
        'due_date' => date('Y-m-d H:i:s', strtotime('+4 days')),
        'points' => 100,
        'submissions' => 15,
        'total_students' => 25,
        'status' => 'active'
    ],
    [
        'id' => 2,
        'title' => 'Web Development Portfolio',
        'subject' => 'Web Technology',
        'subject_code' => 'BCA303',
        'description' => 'Create a responsive portfolio website showcasing HTML5, CSS3, and JavaScript skills with proper form validation.',
        'created_date' => date('Y-m-d H:i:s', strtotime('-1 week')),
        'due_date' => date('Y-m-d H:i:s', strtotime('+1 week')),
        'points' => 150,
        'submissions' => 8,
        'total_students' => 30,
        'status' => 'active'
    ],
    [
        'id' => 3,
        'title' => 'OOP Concepts Report',
        'subject' => 'Object Oriented Programming',
        'subject_code' => 'BCA302',
        'description' => 'Comprehensive report on OOP principles with practical Java examples demonstrating inheritance, polymorphism, and encapsulation.',
        'created_date' => date('Y-m-d H:i:s', strtotime('-5 days')),
        'due_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
        'points' => 75,
        'submissions' => 22,
        'total_students' => 25,
        'status' => 'active'
    ]
];

$draftAssignments = [
    [
        'id' => 4,
        'title' => 'Database Normalization Exercise',
        'subject' => 'Database Management',
        'subject_code' => 'BCA305',
        'description' => 'Practice database normalization techniques from 1NF to BCNF with real-world examples.',
        'created_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'due_date' => null,
        'points' => 50,
        'submissions' => 0,
        'total_students' => 28,
        'status' => 'draft'
    ]
];

$gradedAssignments = [
    [
        'id' => 5,
        'title' => 'C Programming Fundamentals',
        'subject' => 'Programming in C',
        'subject_code' => 'BCA201',
        'description' => 'Basic C programming exercises covering loops, arrays, functions, and pointers.',
        'created_date' => date('Y-m-d H:i:s', strtotime('-3 weeks')),
        'due_date' => date('Y-m-d H:i:s', strtotime('-2 weeks')),
        'points' => 80,
        'submissions' => 24,
        'total_students' => 25,
        'status' => 'graded',
        'avg_score' => 68.5
    ],
    [
        'id' => 6,
        'title' => 'Digital Logic Circuit Design',
        'subject' => 'Digital Electronics',
        'subject_code' => 'BCA103',
        'description' => 'Design and analyze combinational and sequential logic circuits using Boolean algebra.',
        'created_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
        'due_date' => date('Y-m-d H:i:s', strtotime('-3 weeks')),
        'points' => 100,
        'submissions' => 22,
        'total_students' => 24,
        'status' => 'graded',
        'avg_score' => 82.3
    ]
];

// Function to get time remaining
function getTimeRemaining($dueDate) {
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

function getSubmissionProgress($submissions, $total) {
    if ($total == 0) return 0;
    return round(($submissions / $total) * 100);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments | Attendify+</title>

    <!-- CSS Files in Correct Order -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600;700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard_teacher.css">
    <link rel="stylesheet" href="../../assets/css/teacher_assignments.css">
</head>

<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include '../components/sidebar_teacher_dashboard.php'; ?>

    <!-- Navbar -->
    <?php include '../components/navbar_teacher.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container">
        <!-- Header -->
        <div class="assignments-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1 class="assignments-title">
                        <i data-lucide="clipboard-list"></i>
                        Assignment Management
                    </h1>
                    <p class="assignments-subtitle">Create, manage, and grade student assignments</p>
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
                            $subjectsResult->data_seek(0);
                            while ($subject = $subjectsResult->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($subject['SubjectCode']) ?>">
                                    <?= htmlspecialchars($subject['SubjectCode']) ?> - <?= htmlspecialchars($subject['SubjectName']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button class="btn create-assignment-btn" onclick="createAssignment()">
                        <i data-lucide="plus"></i>
                        Create Assignment
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-card">
                <div class="stat-number"><?= count($activeAssignments) ?></div>
                <p class="stat-label">Active Assignments</p>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($draftAssignments) ?></div>
                <p class="stat-label">Draft Assignments</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $totalSubmissions = array_sum(array_column($activeAssignments, 'submissions'));
                    echo $totalSubmissions;
                    ?>
                </div>
                <p class="stat-label">Total Submissions</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $gradedCount = count($gradedAssignments);
                    if ($gradedCount > 0) {
                        $avgScore = array_sum(array_column($gradedAssignments, 'avg_score')) / $gradedCount;
                        echo round($avgScore, 1) . '%';
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </div>
                <p class="stat-label">Average Score</p>
            </div>
        </div>

        <!-- Assignment Tabs -->
        <div class="assignments-tabs">
            <ul class="nav nav-tabs" id="assignmentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                        <i data-lucide="play-circle"></i>
                        Active
                        <span class="badge bg-success ms-2"><?= count($activeAssignments) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="draft-tab" data-bs-toggle="tab" data-bs-target="#draft" type="button" role="tab">
                        <i data-lucide="edit"></i>
                        Drafts
                        <span class="badge bg-warning ms-2"><?= count($draftAssignments) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="graded-tab" data-bs-toggle="tab" data-bs-target="#graded" type="button" role="tab">
                        <i data-lucide="check-circle"></i>
                        Graded
                        <span class="badge bg-primary ms-2"><?= count($gradedAssignments) ?></span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Assignment Content -->
        <div class="tab-content assignments-content" id="assignmentTabsContent">
            <!-- Active Assignments -->
            <div class="tab-pane fade show active" id="active" role="tabpanel">
                <?php if (empty($activeAssignments)): ?>
                    <div class="empty-state">
                        <i data-lucide="clipboard" class="empty-icon"></i>
                        <h4>No active assignments</h4>
                        <p>Create your first assignment to get started.</p>
                        <button class="btn create-assignment-btn mt-3" onclick="createAssignment()">
                            <i data-lucide="plus"></i>
                            Create Assignment
                        </button>
                    </div>
                <?php else: ?>
                    <div class="assignments-grid">
                        <?php foreach ($activeAssignments as $assignment): ?>
                            <div class="assignment-card active-card">
                                <div class="assignment-header">
                                    <div class="assignment-meta">
                                        <span class="subject-code"><?= htmlspecialchars($assignment['subject_code']) ?></span>
                                        <span class="points"><?= $assignment['points'] ?> points</span>
                                        <span class="status-badge status-active">Active</span>
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

                                    <div class="assignment-description">
                                        <?= htmlspecialchars($assignment['description']) ?>
                                    </div>

                                    <div class="assignment-details">
                                        <div class="due-info">
                                            <i data-lucide="calendar"></i>
                                            <span>Due <?= date('M j, Y g:i A', strtotime($assignment['due_date'])) ?></span>
                                        </div>
                                        <div class="created-info">
                                            <i data-lucide="clock"></i>
                                            <span>Created <?= date('M j, Y', strtotime($assignment['created_date'])) ?></span>
                                        </div>
                                        <div class="submissions-info">
                                            <i data-lucide="users"></i>
                                            <span><?= $assignment['submissions'] ?>/<?= $assignment['total_students'] ?> submissions (<?= getSubmissionProgress($assignment['submissions'], $assignment['total_students']) ?>%)</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="assignment-footer">
                                    <button class="btn btn-primary btn-action" onclick="viewSubmissions(<?= $assignment['id'] ?>)">
                                        <i data-lucide="file-text"></i>
                                        View Submissions
                                    </button>
                                    <button class="btn btn-outline-secondary btn-action" onclick="editAssignment(<?= $assignment['id'] ?>)">
                                        <i data-lucide="edit"></i>
                                        Edit
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Draft Assignments -->
            <div class="tab-pane fade" id="draft" role="tabpanel">
                <?php if (empty($draftAssignments)): ?>
                    <div class="empty-state">
                        <i data-lucide="edit-3" class="empty-icon"></i>
                        <h4>No draft assignments</h4>
                        <p>Your saved drafts will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="assignments-grid">
                        <?php foreach ($draftAssignments as $assignment): ?>
                            <div class="assignment-card draft-card">
                                <div class="assignment-header">
                                    <div class="assignment-meta">
                                        <span class="subject-code"><?= htmlspecialchars($assignment['subject_code']) ?></span>
                                        <span class="points"><?= $assignment['points'] ?> points</span>
                                        <span class="status-badge status-draft">Draft</span>
                                    </div>
                                </div>

                                <div class="assignment-content">
                                    <h3 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h3>
                                    <p class="assignment-subject"><?= htmlspecialchars($assignment['subject']) ?></p>

                                    <div class="assignment-description">
                                        <?= htmlspecialchars($assignment['description']) ?>
                                    </div>

                                    <div class="assignment-details">
                                        <div class="created-info">
                                            <i data-lucide="edit"></i>
                                            <span>Last edited <?= date('M j, Y', strtotime($assignment['created_date'])) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="assignment-footer">
                                    <button class="btn btn-primary btn-action" onclick="publishAssignment(<?= $assignment['id'] ?>)">
                                        <i data-lucide="send"></i>
                                        Publish
                                    </button>
                                    <button class="btn btn-outline-secondary btn-action" onclick="editAssignment(<?= $assignment['id'] ?>)">
                                        <i data-lucide="edit"></i>
                                        Continue Editing
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Graded Assignments -->
            <div class="tab-pane fade" id="graded" role="tabpanel">
                <?php if (empty($gradedAssignments)): ?>
                    <div class="empty-state">
                        <i data-lucide="award" class="empty-icon"></i>
                        <h4>No graded assignments</h4>
                        <p>Completed assignments will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="assignments-grid">
                        <?php foreach ($gradedAssignments as $assignment): ?>
                            <div class="assignment-card graded-card">
                                <div class="assignment-header">
                                    <div class="assignment-meta">
                                        <span class="subject-code"><?= htmlspecialchars($assignment['subject_code']) ?></span>
                                        <span class="points">Avg: <?= $assignment['avg_score'] ?>%</span>
                                        <span class="status-badge status-graded">Graded</span>
                                    </div>
                                </div>

                                <div class="assignment-content">
                                    <h3 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h3>
                                    <p class="assignment-subject"><?= htmlspecialchars($assignment['subject']) ?></p>

                                    <div class="assignment-description">
                                        <?= htmlspecialchars($assignment['description']) ?>
                                    </div>

                                    <div class="assignment-details">
                                        <div class="due-info">
                                            <i data-lucide="calendar-check"></i>
                                            <span>Completed <?= date('M j, Y', strtotime($assignment['due_date'])) ?></span>
                                        </div>
                                        <div class="submissions-info">
                                            <i data-lucide="users"></i>
                                            <span><?= $assignment['submissions'] ?>/<?= $assignment['total_students'] ?> submissions graded</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="assignment-footer">
                                    <button class="btn btn-primary btn-action" onclick="viewGrades(<?= $assignment['id'] ?>)">
                                        <i data-lucide="bar-chart"></i>
                                        View Grades
                                    </button>
                                    <button class="btn btn-outline-secondary btn-action" onclick="exportGrades(<?= $assignment['id'] ?>)">
                                        <i data-lucide="download"></i>
                                        Export
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/dashboard_teacher.js"></script>
    <script src="../../assets/js/teacher_assignments.js"></script>

    <script>
        // Initialize everything after DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Initialize sidebar toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('sidebar-open');
                    if (sidebarOverlay) {
                        sidebarOverlay.classList.toggle('show');
                    }
                });
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('sidebar-open');
                    sidebarOverlay.classList.remove('show');
                });
            }

            // Initialize theme from localStorage
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('dark-mode');
            }

            // Theme toggle function
            window.toggleTheme = function() {
                const isDark = document.body.classList.toggle('dark-mode');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                
                // Re-initialize icons after theme change
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            };

            // Re-initialize icons after a short delay
            setTimeout(() => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 100);
        });
    </script>
</body>

</html>