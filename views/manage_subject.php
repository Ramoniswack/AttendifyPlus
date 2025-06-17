<?php

session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: login.php");
    exit();
}
include '../config/db_config.php';

$successMsg = '';
$errorMsg = '';

// Handle form submission for adding subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_subject') {
    // Collect form data
    $SubjectCode = trim($_POST['SubjectCode'] ?? '');
    $SubjectName = trim($_POST['SubjectName'] ?? '');
    $CreditHour = intval($_POST['CreditHour'] ?? 0);
    $LectureHour = intval($_POST['LectureHour'] ?? 48);
    $IsElective = isset($_POST['IsElective']) ? 1 : 0;
    $DepartmentID = $_POST['DepartmentID'] ?? '';
    $SemesterID = $_POST['SemesterID'] ?? '';

    // Basic validation
    if (empty($SubjectCode) || empty($SubjectName) || empty($DepartmentID) || empty($SemesterID) || $CreditHour <= 0) {
        $errorMsg = "Please fill in all required fields with valid values.";
    } else {
        // Check if subject code already exists
        $codeCheck = $conn->prepare("SELECT SubjectID FROM subjects WHERE SubjectCode = ?");
        $codeCheck->bind_param("s", $SubjectCode);
        $codeCheck->execute();
        $codeCheck->store_result();

        if ($codeCheck->num_rows > 0) {
            $errorMsg = "This subject code is already in use.";
        } else {
            // Insert subject
            $stmt = $conn->prepare("INSERT INTO subjects (SubjectCode, SubjectName, CreditHour, LectureHour, IsElective, DepartmentID, SemesterID) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiiiii", $SubjectCode, $SubjectName, $CreditHour, $LectureHour, $IsElective, $DepartmentID, $SemesterID);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Subject added successfully.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $errorMsg = "Failed to add subject. Please try again.";
            }
            $stmt->close();
        }
        $codeCheck->close();
    }
}

// Handle subject update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_subject') {
    $SubjectID = $_POST['SubjectID'] ?? '';
    $SubjectCode = trim($_POST['SubjectCode'] ?? '');
    $SubjectName = trim($_POST['SubjectName'] ?? '');
    $CreditHour = intval($_POST['CreditHour'] ?? 0);
    $LectureHour = intval($_POST['LectureHour'] ?? 48);
    $IsElective = isset($_POST['IsElective']) ? 1 : 0;
    $DepartmentID = $_POST['DepartmentID'] ?? '';
    $SemesterID = $_POST['SemesterID'] ?? '';

    if (!empty($SubjectID) && !empty($SubjectCode) && !empty($SubjectName) && !empty($DepartmentID) && !empty($SemesterID) && $CreditHour > 0) {
        // Check if subject code exists for other subjects
        $codeCheck = $conn->prepare("SELECT SubjectID FROM subjects WHERE SubjectCode = ? AND SubjectID != ?");
        $codeCheck->bind_param("si", $SubjectCode, $SubjectID);
        $codeCheck->execute();
        $codeCheck->store_result();

        if ($codeCheck->num_rows > 0) {
            $_SESSION['error_message'] = "This subject code is already in use by another subject.";
        } else {
            $updateStmt = $conn->prepare("UPDATE subjects SET SubjectCode = ?, SubjectName = ?, CreditHour = ?, LectureHour = ?, IsElective = ?, DepartmentID = ?, SemesterID = ? WHERE SubjectID = ?");
            $updateStmt->bind_param("ssiiiiii", $SubjectCode, $SubjectName, $CreditHour, $LectureHour, $IsElective, $DepartmentID, $SemesterID, $SubjectID);

            if ($updateStmt->execute()) {
                $_SESSION['success_message'] = "Subject updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update subject.";
            }
            $updateStmt->close();
        }
        $codeCheck->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle subject deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_subject') {
    $SubjectID = $_POST['SubjectID'] ?? '';

    if (!empty($SubjectID)) {
        // Check if subject is assigned to any teacher
        $checkAssignment = $conn->prepare("SELECT COUNT(*) as count FROM teacher_subject_map WHERE SubjectID = ?");
        $checkAssignment->bind_param("i", $SubjectID);
        $checkAssignment->execute();
        $result = $checkAssignment->get_result();
        $count = $result->fetch_assoc()['count'];

        if ($count > 0) {
            $_SESSION['error_message'] = "Cannot delete subject. It is assigned to one or more teachers.";
        } else {
            $deleteStmt = $conn->prepare("DELETE FROM subjects WHERE SubjectID = ?");
            $deleteStmt->bind_param("i", $SubjectID);

            if ($deleteStmt->execute()) {
                $_SESSION['success_message'] = "Subject deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete subject.";
            }
            $deleteStmt->close();
        }
        $checkAssignment->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Check for messages from session (after redirect)
if (isset($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $errorMsg = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Fetch all subjects with their details
$subjects = [];
$sql = "SELECT s.SubjectID, s.SubjectCode, s.SubjectName, s.CreditHour, s.LectureHour, s.IsElective,
               d.DepartmentName, d.DepartmentID,
               sem.SemesterNumber, sem.SemesterID,
               COUNT(tsm.MapID) as TeacherCount
        FROM subjects s
        JOIN departments d ON s.DepartmentID = d.DepartmentID
        JOIN semesters sem ON s.SemesterID = sem.SemesterID
        LEFT JOIN teacher_subject_map tsm ON s.SubjectID = tsm.SubjectID
        GROUP BY s.SubjectID
        ORDER BY d.DepartmentName, sem.SemesterNumber, s.SubjectName";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
}

// Fetch departments
$departments = [];
$deptSql = "SELECT DepartmentID, DepartmentName FROM departments ORDER BY DepartmentName";
$deptResult = $conn->query($deptSql);
while ($d = $deptResult->fetch_assoc()) {
    $departments[] = $d;
}

// Fetch semesters
$semesters = [];
$semSql = "SELECT SemesterID, SemesterNumber FROM semesters ORDER BY SemesterNumber";
$semResult = $conn->query($semSql);
while ($s = $semResult->fetch_assoc()) {
    $semesters[] = $s;
}

// Get statistics
$stats = [];
$statsQueries = [
    'total' => "SELECT COUNT(*) as count FROM subjects",
    'core' => "SELECT COUNT(*) as count FROM subjects WHERE IsElective = 0",
    'elective' => "SELECT COUNT(*) as count FROM subjects WHERE IsElective = 1",
    'assigned' => "SELECT COUNT(DISTINCT s.SubjectID) as count FROM subjects s JOIN teacher_subject_map tsm ON s.SubjectID = tsm.SubjectID"
];

foreach ($statsQueries as $key => $query) {
    $result = $conn->query($query);
    $stats[$key] = $result->fetch_assoc()['count'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Manage Subjects | Attendify+</title>
    <link rel="stylesheet" href="../assets/css/manage_subject.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
    <script src="../assets/js/lucide.min.js"></script>
    <script src="../assets/js/manage_teacher.js" defer></script>
    <style>
        /* Additional responsive styles */
    </style>
</head>

<body>
    <?php include 'sidebar_admin_dashboard.php'; ?>
    <?php include 'navbar_admin.php'; ?>

    <div class="container-fluid dashboard-container pt-4" id="manageSubjectsContainer">
        <!-- Header and Add Button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i data-lucide="book-open"></i> Manage Subjects</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                <i data-lucide="plus"></i> Add Subject
            </button>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($successMsg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i data-lucide="check-circle"></i> <?= htmlspecialchars($successMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i data-lucide="alert-circle"></i> <?= htmlspecialchars($errorMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

       
        <!-- Statistics Cards - Updated to Match Teacher/Student Theme -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?= $stats['total'] ?></div>
                            <div>Total Subjects</div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="book-open"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card core-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?= $stats['core'] ?></div>
                            <div>Core Subjects</div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="book"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card elective-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?= $stats['elective'] ?></div>
                            <div>Elective Subjects</div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="bookmark"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card assigned-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?= $stats['assigned'] ?></div>
                            <div>Assigned Subjects</div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="user-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label"><i data-lucide="search"></i> Search Subject</label>
                        <input id="searchSubject" type="text" class="form-control" placeholder="Enter subject name or code..." />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><i data-lucide="building"></i> Department</label>
                        <select id="filterDepartment" class="form-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= htmlspecialchars($d['DepartmentID']) ?>"><?= htmlspecialchars($d['DepartmentName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><i data-lucide="layers"></i> Semester</label>
                        <select id="filterSemester" class="form-select">
                            <option value="">All Semesters</option>
                            <?php foreach ($semesters as $s): ?>
                                <option value="<?= $s['SemesterID'] ?>">Semester <?= $s['SemesterNumber'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><i data-lucide="tag"></i> Type</label>
                        <select id="filterType" class="form-select">
                            <option value="">All Types</option>
                            <option value="0">Core Subjects</option>
                            <option value="1">Elective Subjects</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subjects Table -->
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="subjectsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Department</th>
                            <th>Semester</th>
                            <th>Credit Hours</th>
                            <th>Lecture Hours</th>
                            <th>Type</th>
                            <th>Teachers</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $subject): ?>
                            <tr data-department-id="<?= $subject['DepartmentID'] ?>" data-semester-id="<?= $subject['SemesterID'] ?>">
                                <td>
                                    <span class="subject-code"><?= htmlspecialchars($subject['SubjectCode']) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($subject['SubjectName']) ?></strong>
                                    <br><small class="text-muted">ID: <?= $subject['SubjectID'] ?></small>
                                </td>
                                <td><?= htmlspecialchars($subject['DepartmentName']) ?></td>
                                <td>Semester <?= htmlspecialchars($subject['SemesterNumber']) ?></td>
                                <td>
                                    <span class="credit-hour-badge"><?= $subject['CreditHour'] ?> Credits</span>
                                </td>
                                <td>
                                    <span class="lecture-hour-badge"><?= $subject['LectureHour'] ?> Hours</span>
                                </td>
                                <td>
                                    <span class="badge <?= $subject['IsElective'] ? 'bg-info' : 'bg-success' ?> subject-badge">
                                        <?= $subject['IsElective'] ? 'Elective' : 'Core' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= $subject['TeacherCount'] ?> Teacher(s)</span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewModal<?= $subject['SubjectID'] ?>" title="View Details">
                                            <i data-lucide="eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $subject['SubjectID'] ?>" title="Edit Subject">
                                            <i data-lucide="edit"></i>
                                        </button>
                                        <?php if ($subject['TeacherCount'] == 0): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteSubject(<?= $subject['SubjectID'] ?>, '<?= htmlspecialchars($subject['SubjectName']) ?>')" title="Delete Subject">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Subject Modal -->
    <div class="modal fade" id="addSubjectModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="add_subject">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="plus"></i> Add New Subject
                    </h5>
                    <button type="button" class="btn-close btn-close-red" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Basic Information -->
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2"><i data-lucide="book-open"></i> Subject Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subject Code <span class="required-field">*</span></label>
                            <input name="SubjectCode" class="form-control" required placeholder="e.g., CS101" style="text-transform: uppercase;">
                            <small class="form-text text-muted">Unique identifier for the subject</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subject Name <span class="required-field">*</span></label>
                            <input name="SubjectName" class="form-control" required placeholder="e.g., Introduction to Programming">
                        </div>

                        <!-- Academic Details -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2"><i data-lucide="graduation-cap"></i> Academic Details</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department <span class="required-field">*</span></label>
                            <select name="DepartmentID" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['DepartmentID'] ?>"><?= htmlspecialchars($d['DepartmentName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Semester <span class="required-field">*</span></label>
                            <select name="SemesterID" class="form-select" required>
                                <option value="">Select Semester</option>
                                <?php foreach ($semesters as $s): ?>
                                    <option value="<?= $s['SemesterID'] ?>">Semester <?= $s['SemesterNumber'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Credit Hours <span class="required-field">*</span></label>
                            <input name="CreditHour" type="number" class="form-control" required min="1" max="10" value="3" placeholder="3">
                            <small class="form-text text-muted">Usually between 1-6 credits</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lecture Hours</label>
                            <input name="LectureHour" type="number" class="form-control" min="1" max="200" value="48" placeholder="48">
                            <small class="form-text text-muted">Total lecture hours per semester</small>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="IsElective" id="isElective">
                                <label class="form-check-label" for="isElective">
                                    This is an elective subject
                                </label>
                                <small class="form-text text-muted d-block">Check if this subject is optional for students</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i data-lucide="x"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save"></i> Add Subject
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Subject Details Modals -->
    <?php foreach ($subjects as $subject): ?>
        <div class="modal fade" id="viewModal<?= $subject['SubjectID'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i data-lucide="book-open"></i> Subject Details - <?= htmlspecialchars($subject['SubjectName']) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Subject ID:</th>
                                        <td><?= $subject['SubjectID'] ?></td>
                                    </tr>
                                    <tr>
                                        <th>Subject Code:</th>
                                        <td><span class="subject-code"><?= htmlspecialchars($subject['SubjectCode']) ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>Subject Name:</th>
                                        <td><?= htmlspecialchars($subject['SubjectName']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Department:</th>
                                        <td><?= htmlspecialchars($subject['DepartmentName']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Semester:</th>
                                        <td>Semester <?= htmlspecialchars($subject['SemesterNumber']) ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Credit Hours:</th>
                                        <td><span class="credit-hour-badge"><?= $subject['CreditHour'] ?> Credits</span></td>
                                    </tr>
                                    <tr>
                                        <th>Lecture Hours:</th>
                                        <td><span class="lecture-hour-badge"><?= $subject['LectureHour'] ?> Hours</span></td>
                                    </tr>
                                    <tr>
                                        <th>Subject Type:</th>
                                        <td>
                                            <span class="badge <?= $subject['IsElective'] ? 'bg-info' : 'bg-success' ?> subject-badge">
                                                <?= $subject['IsElective'] ? 'Elective' : 'Core' ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Assigned Teachers:</th>
                                        <td><span class="badge bg-secondary"><?= $subject['TeacherCount'] ?> Teacher(s)</span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Edit Subject Modals -->
    <?php foreach ($subjects as $subject): ?>
        <div class="modal fade" id="editModal<?= $subject['SubjectID'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <form method="POST" class="modal-content">
                    <input type="hidden" name="action" value="update_subject">
                    <input type="hidden" name="SubjectID" value="<?= $subject['SubjectID'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i data-lucide="edit"></i> Edit Subject - <?= htmlspecialchars($subject['SubjectName']) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Subject Code <span class="required-field">*</span></label>
                                <input name="SubjectCode" class="form-control" required value="<?= htmlspecialchars($subject['SubjectCode']) ?>" style="text-transform: uppercase;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subject Name <span class="required-field">*</span></label>
                                <input name="SubjectName" class="form-control" required value="<?= htmlspecialchars($subject['SubjectName']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department <span class="required-field">*</span></label>
                                <select name="DepartmentID" class="form-select" required>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= $d['DepartmentID'] ?>" <?= $d['DepartmentID'] == $subject['DepartmentID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($d['DepartmentName']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Semester <span class="required-field">*</span></label>
                                <select name="SemesterID" class="form-select" required>
                                    <?php foreach ($semesters as $s): ?>
                                        <option value="<?= $s['SemesterID'] ?>" <?= $s['SemesterID'] == $subject['SemesterID'] ? 'selected' : '' ?>>
                                            Semester <?= $s['SemesterNumber'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Credit Hours <span class="required-field">*</span></label>
                                <input name="CreditHour" type="number" class="form-control" required min="1" max="10" value="<?= $subject['CreditHour'] ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Lecture Hours</label>
                                <input name="LectureHour" type="number" class="form-control" min="1" max="200" value="<?= $subject['LectureHour'] ?>">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="IsElective" id="isElective<?= $subject['SubjectID'] ?>" <?= $subject['IsElective'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="isElective<?= $subject['SubjectID'] ?>">
                                        This is an elective subject
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i data-lucide="x"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save"></i> Update Subject
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php endforeach; ?>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Make the search and filtering work
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchSubject');
            const departmentFilter = document.getElementById('filterDepartment');
            const semesterFilter = document.getElementById('filterSemester');
            const typeFilter = document.getElementById('filterType');
            const table = document.getElementById('subjectsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            function filterTable() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const selectedDepartment = departmentFilter.value;
                const selectedSemester = semesterFilter.value;
                const selectedType = typeFilter.value;

                let visibleCount = 0;

                rows.forEach(row => {
                    const subjectCode = row.cells[0].textContent.toLowerCase();
                    const subjectName = row.cells[1].textContent.toLowerCase();
                    const departmentName = row.cells[2].textContent.toLowerCase();
                    const departmentId = row.getAttribute('data-department-id');
                    const semesterId = row.getAttribute('data-semester-id');
                    const type = row.cells[6].textContent.toLowerCase();
                    const isElective = type.includes('elective') ? '1' : '0';

                    let visible = true;

                    // Search filter - search in subject code, name, and department
                    if (searchTerm) {
                        const matchesSearch = subjectCode.includes(searchTerm) ||
                            subjectName.includes(searchTerm) ||
                            departmentName.includes(searchTerm);
                        if (!matchesSearch) {
                            visible = false;
                        }
                    }

                    // Department filter
                    if (selectedDepartment && departmentId !== selectedDepartment) {
                        visible = false;
                    }

                    // Semester filter
                    if (selectedSemester && semesterId !== selectedSemester) {
                        visible = false;
                    }

                    // Type filter
                    if (selectedType && isElective !== selectedType) {
                        visible = false;
                    }

                    row.style.display = visible ? '' : 'none';
                    if (visible) visibleCount++;
                });

                updateVisibleCount(visibleCount);
            }

            function updateVisibleCount(count) {
                let countElement = document.getElementById('visibleCount');
                if (!countElement) {
                    countElement = document.createElement('div');
                    countElement.id = 'visibleCount';
                    countElement.className = 'text-muted small mt-2';
                    countElement.style.padding = '8px 0';
                    table.parentNode.appendChild(countElement);
                }
                countElement.innerHTML = `<i data-lucide="info"></i> Showing <strong>${count}</strong> of <strong>${rows.length}</strong> subjects`;
                lucide.createIcons();
            }

            // Event listeners
            searchInput.addEventListener('input', filterTable);
            departmentFilter.addEventListener('change', filterTable);
            semesterFilter.addEventListener('change', filterTable);
            typeFilter.addEventListener('change', filterTable);

            // Initialize
            updateVisibleCount(rows.length);

            // Clear filters function
            window.clearFilters = function() {
                searchInput.value = '';
                departmentFilter.value = '';
                semesterFilter.value = '';
                typeFilter.value = '';
                filterTable();
            };
        });

        // Delete subject function
        function deleteSubject(subjectId, subjectName) {
            if (confirm(`Are you sure you want to delete "${subjectName}"?\n\nThis action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                <input type="hidden" name="action" value="delete_subject">
                <input type="hidden" name="SubjectID" value="${subjectId}">
            `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        lucide.createIcons();
    </script>
</body>

</html>