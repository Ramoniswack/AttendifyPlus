<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: login.php");
    exit();
}
include '../config/db_config.php';

$successMsg = '';
$errorMsg = '';
$errors = [];

// Handle form submission for adding student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    // Collect form data
    $FullName = trim($_POST['FullName'] ?? '');
    $Email = trim($_POST['Email'] ?? '');
    $Contact = trim($_POST['Contact'] ?? '');
    $Address = trim($_POST['Address'] ?? '');
    $DepartmentID = $_POST['DepartmentID'] ?? '';
    $SemesterID = $_POST['SemesterID'] ?? '';
    $JoinYear = $_POST['JoinYear'] ?? '';
    $Status = $_POST['Status'] ?? 'active';
    $Password = $_POST['Password'] ?? '';
    $ConfirmPassword = $_POST['ConfirmPassword'] ?? '';
    $PhotoURL = '';



    function isValidFormattedName($Fullname) {
        $Fullname = trim($Fullname);
        if (!preg_match('/^[A-Za-z. ]+$/', $Fullname)) return false;
        if (preg_match('/[.]{2,}|[ ]{2,}/', $Fullname)) return false;
        if (!preg_match('/^[A-Z]/', $Fullname)) return false;

        $words = explode(' ', $Fullname);
        foreach ($words as $word) {
            if ($word === '') continue;
            $parts = explode('.', $word);
            foreach ($parts as $part) {
                if ($part === '') continue;
                if (!preg_match('/^[A-Z][a-z]*$/', $part)) return false;
            }
        }
        return true;
    }

    function validateEmail($Email) {
      $Email = trim($Email);
      if (!preg_match('/^[a-zA-Z0-9._%+-]+@lagrandee\.com$/', $Email)) return false;

      return true;
    }

    //Rikita

    if (empty($FullName)) {
        $errors['FullName'] = "Full name is required.";
    } elseif (!isValidFormattedName($FullName)) {
        $errors['FullName'] = "Only letters, spaces, and dots allowed. Each part must start with a capital letter.";
    }

    if (empty($Email)) {
        $errors['Email'] = "Email is required.";
    } elseif (!validateEmail($Email)) {
        $errors['Email'] = "Invalid email format. Example: example1@lagrandee.com";
    }

    if (empty($Contact)) {
        $errors['Contact'] = "Contact number is required.";
    } elseif (!preg_match('/^\d{10}$/', $Contact)) {
        $errors['Contact'] = "Contact number must be exactly 10 digits.";
    }

    if (empty($DepartmentID)) {
        $errors['DepartmentID'] = "Please select a department.";
    }

    if (empty($SemesterID)) {
        $errors['SemesterID'] = "Please select a semester.";
    }

    if (empty($Address)) {
        $errors['Address'] = "Address is required.";
    }

    if (empty($Password)) {
        $errors['Password'] = "Password is required.";
    } elseif (!preg_match('/^(?=.*[0-9])(?=.*[!@#\$%\^&\*\-_])[A-Za-z0-9!@#\$%\^&\*\-_]{6,}$/', $Password)) {
        $errors['Password'] = "Password must be at least 6 characters long, with a number and a special character.";
    }

    if (empty($ConfirmPassword)) {
        $errors['ConfirmPassword'] = "Please confirm your password.";
    } elseif ($Password !== $ConfirmPassword) {
        $errors['ConfirmPassword'] = "Passwords do not match.";
    }

    if (isset($_FILES['PhotoFile']) && $_FILES['PhotoFile']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/students/';

        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = $_FILES['PhotoFile']['type'];

        if (in_array($fileType, $allowedTypes)) {
            if ($_FILES['PhotoFile']['size'] <= 5 * 1024 * 1024) { // 5MB limit
                $ext = pathinfo($_FILES['PhotoFile']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('student_', true) . '.' . $ext;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['PhotoFile']['tmp_name'], $targetPath)) {
                    $PhotoURL = $targetPath;
                } else {
                    $errorMsg = "Failed to upload photo.";
                }
            } else {
                $errorMsg = "Image size must be less than 5MB.";
            }
        } else {
            $errorMsg = "Only JPEG, PNG, and GIF images are allowed.";
        }
    }

    if ((empty($errors)) && (empty($errorMsg))){
        // Check if email already exists
        $emailCheck = $conn->prepare("SELECT LoginID FROM login_tbl WHERE Email = ?");
        $emailCheck->bind_param("s", $Email);
        $emailCheck->execute();
        $emailCheck->store_result();

        if ($emailCheck->num_rows > 0) {
            $errorMsg = "This email is already registered.";
        } else {
            // Generate ProgramCode
            $deptQuery = $conn->prepare("SELECT DepartmentName FROM departments WHERE DepartmentID = ?");
            $deptQuery->bind_param("i", $DepartmentID);
            $deptQuery->execute();
            $deptResult = $deptQuery->get_result();
            $deptRow = $deptResult->fetch_assoc();
            $ProgramCode = strtoupper($deptRow['DepartmentName']) . '-' . $JoinYear;

            // Start transaction
            $conn->begin_transaction();

            try {
                // Insert into login_tbl
                $stmt1 = $conn->prepare("INSERT INTO login_tbl (Email, Password, Role, Status, CreatedDate) VALUES (?, ?, 'student', ?, NOW())");
                $hashedPass = password_hash($Password, PASSWORD_BCRYPT);
                $stmt1->bind_param("sss", $Email, $hashedPass, $Status);

                if (!$stmt1->execute()) {
                    throw new Exception("Failed to create login account.");
                }

                $loginID = $conn->insert_id;

                // Insert into students table
                $stmt2 = $conn->prepare("INSERT INTO students (FullName, Contact, Address, PhotoURL, DepartmentID, SemesterID, JoinYear, ProgramCode, LoginID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt2->bind_param("ssssiissi", $FullName, $Contact, $Address, $PhotoURL, $DepartmentID, $SemesterID, $JoinYear, $ProgramCode, $loginID);

                if (!$stmt2->execute()) {
                    throw new Exception("Failed to create student record.");
                }

                // Commit transaction
                $conn->commit();

                // Success: Store success message in session and redirect
                $_SESSION['success_message'] = "Student added successfully.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } catch (Exception $e) {
                // Rollback transaction
                $conn->rollback();
                $errorMsg = $e->getMessage();
            }

            if (isset($stmt1)) $stmt1->close();
            if (isset($stmt2)) $stmt2->close();
        }
        $emailCheck->close();
    }
}

// Handle student status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $studentID = $_POST['student_id'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';

    if (!empty($studentID) && !empty($newStatus)) {
        $updateStmt = $conn->prepare("UPDATE login_tbl l 
                                     JOIN students s ON l.LoginID = s.LoginID 
                                     SET l.Status = ? 
                                     WHERE s.StudentID = ?");
        $updateStmt->bind_param("si", $newStatus, $studentID);

        if ($updateStmt->execute()) {
            $_SESSION['success_message'] = "Student status updated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to update student status.";
        }
        $updateStmt->close();
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

// Fetch all students with their details
$students = [];
$sql = "SELECT s.StudentID, s.FullName, s.Contact, s.Address, s.PhotoURL, 
               s.JoinYear, s.ProgramCode,
               d.DepartmentName, d.DepartmentID,
               sem.SemesterNumber, sem.SemesterID,
               l.Email, l.Status, l.CreatedDate
        FROM students s
        JOIN departments d ON s.DepartmentID = d.DepartmentID
        JOIN semesters sem ON s.SemesterID = sem.SemesterID
        JOIN login_tbl l ON s.LoginID = l.LoginID
        ORDER BY l.Status ASC, s.FullName";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
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

// Get statistics - updated to match manage_admin structure
$stats = [];
$statsQueries = [
    'total' => "SELECT COUNT(*) as count FROM students s JOIN login_tbl l ON s.LoginID = l.LoginID",
    'active' => "SELECT COUNT(*) as count FROM students s JOIN login_tbl l ON s.LoginID = l.LoginID WHERE l.Status = 'active'",
    'inactive' => "SELECT COUNT(*) as count FROM students s JOIN login_tbl l ON s.LoginID = l.LoginID WHERE l.Status = 'inactive'"
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Students | Attendify+</title>
    <link rel="stylesheet" href="../assets/css/manage_student.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <script src="../assets/js/lucide.min.js"></script>
    <script src="../assets/js/manage_student.js" defer></script>
</head>

<body>
    <!-- Include sidebar and navbar -->
    <?php include 'sidebar_admin_dashboard.php'; ?>
    <?php include 'navbar_admin.php'; ?>

    <!-- Main content -->
    <div class="container-fluid dashboard-container">
        <!-- Page Header - Updated to Match manage_admin.php -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 class="page-title">
                    <i data-lucide="graduation-cap"></i>
                    Student Management
                </h2>
                <p class="text-muted mb-0">Manage student accounts and academic records</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i data-lucide="user-plus"></i> Add Student
                </button>
                <a href="manage_teacher.php" class="btn btn-outline-primary">
                    <i data-lucide="users"></i> Teacher Management
                </a>
            </div>
        </div>

        <!-- Statistics Cards - Updated to Match manage_admin structure -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= $stats['total'] ?></div>
                            <div class="stat-label">Total Students</div>
                            <div class="stat-change">
                                <i data-lucide="graduation-cap"></i>
                                <span>Enrolled students</span>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="graduation-cap"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card teachers text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= $stats['active'] ?></div>
                            <div class="stat-label">Active Students</div>
                            <div class="stat-change">
                                <i data-lucide="user-check"></i>
                                <span>Currently studying</span>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="user-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card admins text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= $stats['inactive'] ?></div>
                            <div class="stat-label">Inactive Students</div>
                            <div class="stat-change">
                                <i data-lucide="user-x"></i>
                                <span>Suspended accounts</span>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="user-x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($successMsg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i data-lucide="check-circle" class="me-2"></i>
                <?= htmlspecialchars($successMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i data-lucide="alert-circle" class="me-2"></i>
                <?= htmlspecialchars($errorMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Section - Updated to Match manage_admin -->
        <div class="card mb-4">
            <div class="card-body">
                <h6 class="card-title">
                    <i data-lucide="filter"></i>
                    Search & Filter Students
                </h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">
                            <i data-lucide="search"></i>
                            Search Students
                        </label>
                        <input id="studentSearch" type="text" class="form-control" placeholder="Search by name, email, or contact..." />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">
                            <i data-lucide="building"></i>
                            Department
                        </label>
                        <select id="filterDepartment" class="form-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= htmlspecialchars($d['DepartmentID']) ?>"><?= htmlspecialchars($d['DepartmentName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">
                            <i data-lucide="calendar"></i>
                            Join Year
                        </label>
                        <input id="filterYear" type="number" class="form-control" placeholder="e.g. 2022" min="2000" max="<?= date('Y') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button id="clearFilters" class="btn btn-outline-secondary d-block w-100">
                            <i data-lucide="x"></i>
                            Clear Filters
                        </button>
                    </div>
                </div>
                <div class="mt-3">
                    <small id="resultsCount" class="text-muted"></small>
                </div>
            </div>
        </div>

        <!-- Students Table - Updated to Match manage_admin structure -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i data-lucide="graduation-cap"></i>
                    Student Directory
                </h6>
            </div>
            <div class="table-responsive">
                <table id="studentsTable" class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Profile</th>
                            <th>Student</th>
                            <th>Contact Information</th>
                            <th>Academic Info</th>
                            <th>Join Year</th>
                            <th>Account Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                        <?php foreach ($students as $student): ?>
                            <tr class="student-row"
                                data-name="<?= strtolower(htmlspecialchars($student['FullName'])) ?>"
                                data-email="<?= strtolower(htmlspecialchars($student['Email'])) ?>"
                                data-contact="<?= htmlspecialchars($student['Contact']) ?>"
                                data-department="<?= htmlspecialchars($student['DepartmentID']) ?>"
                                data-year="<?= htmlspecialchars($student['JoinYear']) ?>"
                                data-status="<?= htmlspecialchars($student['Status']) ?>">
                                <td>
                                    <?php if (!empty($student['PhotoURL']) && file_exists($student['PhotoURL'])): ?>
                                        <img src="<?= htmlspecialchars($student['PhotoURL']) ?>"
                                            alt="<?= htmlspecialchars($student['FullName']) ?>"
                                            class="student-photo">
                                    <?php else: ?>
                                        <div class="student-placeholder">
                                            <i data-lucide="user"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($student['FullName']) ?></div>
                                        <small class="text-muted">ID: <?= $student['StudentID'] ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div class="mb-1">
                                            <i data-lucide="mail" class="me-1 text-muted" style="width: 14px; height: 14px;"></i>
                                            <small><?= htmlspecialchars($student['Email']) ?></small>
                                        </div>
                                        <?php if (!empty($student['Contact'])): ?>
                                            <div>
                                                <i data-lucide="phone" class="me-1 text-muted" style="width: 14px; height: 14px;"></i>
                                                <small><?= htmlspecialchars($student['Contact']) ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($student['DepartmentName']) ?></div>
                                        <small class="text-muted">Semester <?= htmlspecialchars($student['SemesterNumber']) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted"><?= htmlspecialchars($student['JoinYear']) ?></small>
                                </td>
                                <td>
                                    <span class="badge <?= $student['Status'] === 'active' ? 'bg-success' : 'bg-danger' ?> status-badge">
                                        <?= ucfirst($student['Status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-info"
                                            data-bs-toggle="modal"
                                            data-bs-target="#viewStudentModal<?= $student['StudentID'] ?>"
                                            title="View Details">
                                            <i data-lucide="eye"></i>
                                        </button>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                data-bs-toggle="dropdown"
                                                title="Change Status">
                                                <i data-lucide="settings"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="student_id" value="<?= $student['StudentID'] ?>">
                                                        <input type="hidden" name="new_status" value="<?= $student['Status'] === 'active' ? 'inactive' : 'active' ?>">
                                                        <button type="submit" class="dropdown-item" onclick="return confirm('Are you sure you want to change the status?')">
                                                            <i data-lucide="<?= $student['Status'] === 'active' ? 'user-x' : 'user-check' ?>"></i>
                                                            <?= $student['Status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- View Student Modals -->
        <?php foreach ($students as $student): ?>
            <div class="modal fade" id="viewStudentModal<?= $student['StudentID'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i data-lucide="user"></i>
                                Student Profile - <?= htmlspecialchars($student['FullName']) ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <?php if (!empty($student['PhotoURL']) && file_exists($student['PhotoURL'])): ?>
                                        <img src="<?= htmlspecialchars($student['PhotoURL']) ?>"
                                            alt="<?= htmlspecialchars($student['FullName']) ?>"
                                            class="student-photo-large mb-3">
                                    <?php else: ?>
                                        <div class="student-placeholder-large mb-3">
                                            <i data-lucide="user"></i>
                                        </div>
                                    <?php endif; ?>
                                    <h5><?= htmlspecialchars($student['FullName']) ?></h5>
                                    <span class="badge <?= $student['Status'] === 'active' ? 'bg-success' : 'bg-danger' ?> mb-2">
                                        <?= ucfirst($student['Status']) ?>
                                    </span>
                                </div>
                                <div class="col-md-8">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="40%">Student ID:</th>
                                            <td><?= $student['StudentID'] ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email:</th>
                                            <td><?= htmlspecialchars($student['Email']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Contact:</th>
                                            <td><?= htmlspecialchars($student['Contact'] ?: 'Not provided') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Address:</th>
                                            <td><?= htmlspecialchars($student['Address'] ?: 'Not provided') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Department:</th>
                                            <td><?= htmlspecialchars($student['DepartmentName']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Semester:</th>
                                            <td>Semester <?= htmlspecialchars($student['SemesterNumber']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Join Year:</th>
                                            <td><?= htmlspecialchars($student['JoinYear']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Program Code:</th>
                                            <td><code><?= htmlspecialchars($student['ProgramCode']) ?></code></td>
                                        </tr>
                                        <tr>
                                            <th>Registration Date:</th>
                                            <td><?= date('F j, Y', strtotime($student['CreatedDate'])) ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i data-lucide="x" class="me-1"></i>
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Add Student Modal -->
        <div class="modal fade" id="addStudentModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <form class="modal-content" id='studentform' method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_student">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i data-lucide="user-plus"></i>
                            Add New Student
                        </h5>
                        <button type="button" class="btn-close btn-close-red" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Personal Information -->
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2"><i data-lucide="user"></i> Personal Information</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Full Name <span class="required-field">*</span>
                                </label>
                                <input name="FullName" type="text" class="form-control" required placeholder="Enter full name" 
                                    value="<?php echo htmlspecialchars($_POST['FullName'] ?? ''); ?>" />
                                <span class="error text-danger"><?php echo $errors['FullName'] ?? ''; ?></span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Email Address <span class="required-field">*</span>
                                </label>
                                <input name="Email" type="email" class="form-control" required placeholder="student@example.com" 
                                    value="<?php echo htmlspecialchars($_POST['Email'] ?? ''); ?>" />
                                <span class="error text-danger"><?php echo $errors['Email'] ?? ''; ?></span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input name="Contact" type="tel" class="form-control" placeholder="98xxxxxxxx" 
                                    value="<?php echo htmlspecialchars($_POST['Contact'] ?? ''); ?>" />
                                <span class="error text-danger"><?php echo $errors['Contact'] ?? ''; ?></span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address</label>
                                <input name="Address" type="text" class="form-control" placeholder="City, District" 
                                    value="<?php echo htmlspecialchars($_POST['Address'] ?? ''); ?>" />
                                <span class="error text-danger"><?php echo $errors['Address'] ?? ''; ?></span>
                            </div>
                            <div class="col-12">
                                <label class="form-label">
                                    Profile Photo
                                    <small class="text-muted">(Optional, max 5MB)</small>
                                </label>
                                <input name="PhotoFile" type="file" class="form-control" accept="image/*" />
                                <small class="form-text text-muted">JPG, PNG, GIF (Max 5MB)</small>
                                <span class="error text-danger"><?php echo $errors['Photo'] ?? ''; ?></span>
                            </div>

                            <!-- Academic Information -->
                            <div class="col-12 mt-4">
                                <h6 class="text-primary border-bottom pb-2"><i data-lucide="graduation-cap"></i> Academic Information</h6>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">
                                    Department <span class="required-field">*</span>
                                </label>
                                <select name="DepartmentID" class="form-select" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= $d['DepartmentID'] ?>"><?= htmlspecialchars($d['DepartmentName']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="error text-danger"><?php echo $errors['DepartmentID'] ?? ''; ?></span>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">
                                    Semester <span class="required-field">*</span>
                                </label>
                                <select name="SemesterID" class="form-select" required>
                                    <option value="">Select Semester</option>
                                    <?php foreach ($semesters as $s): ?>
                                        <option value="<?= $s['SemesterID'] ?>">Semester <?= $s['SemesterNumber'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="error text-danger"><?php echo $errors['SemesterID'] ?? ''; ?></span>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">
                                    Join Year <span class="required-field">*</span>
                                </label>
                                <input name="JoinYear" type="number" class="form-control" required
                                    min="2000" max="<?= date('Y') ?>" value="<?= date('Y') ?>" placeholder="<?= date('Y') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="Status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <!-- Account Information -->
                            <div class="col-12 mt-4">
                                <h6 class="text-primary border-bottom pb-2"><i data-lucide="lock"></i> Account Information</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Password <span class="required-field">*</span>
                                </label>
                                <input name="Password" type="password" class="form-control" required minlength="6" placeholder="Minimum 6 characters" />
                                <span class="error text-danger"><?php echo $errors['Password'] ?? ''; ?></span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Confirm Password <span class="required-field">*</span>
                                </label>
                                <input name="ConfirmPassword" type="password" class="form-control" required placeholder="Re-enter password" />
                                <span class="error text-danger"><?php echo $errors['ConfirmPassword'] ?? ''; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i data-lucide="x" class="me-1"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save"></i>
                            Create Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Search and filter functionality
        const searchInput = document.getElementById('studentSearch');
        const departmentFilter = document.getElementById('filterDepartment');
        const yearFilter = document.getElementById('filterYear');
        const clearFiltersBtn = document.getElementById('clearFilters');
        const resultsCount = document.getElementById('resultsCount');
        const studentRows = document.querySelectorAll('.student-row');

        function updateResultsCount() {
            const visibleRows = document.querySelectorAll('.student-row:not([style*="display: none"])').length;
            const totalRows = studentRows.length;
            resultsCount.textContent = `Showing ${visibleRows} of ${totalRows} students`;
        }

        function filterStudents() {
            const searchTerm = searchInput.value.toLowerCase();
            const departmentFilterValue = departmentFilter.value;
            const yearFilterValue = yearFilter.value;

            studentRows.forEach(row => {
                const name = row.dataset.name || '';
                const email = row.dataset.email || '';
                const contact = row.dataset.contact || '';
                const department = row.dataset.department || '';
                const year = row.dataset.year || '';

                let showRow = true;

                // Search filter
                if (searchTerm) {
                    const searchMatch = name.includes(searchTerm) ||
                        email.includes(searchTerm) ||
                        contact.includes(searchTerm);
                    if (!searchMatch) showRow = false;
                }

                // Department filter
                if (departmentFilterValue && department !== departmentFilterValue) {
                    showRow = false;
                }

                // Year filter
                if (yearFilterValue && year !== yearFilterValue) {
                    showRow = false;
                }

                row.style.display = showRow ? '' : 'none';
            });

            updateResultsCount();
        }

        // Event listeners for filters
        searchInput.addEventListener('input', filterStudents);
        departmentFilter.addEventListener('change', filterStudents);
        yearFilter.addEventListener('change', filterStudents);

        // Clear filters
        clearFiltersBtn.addEventListener('click', () => {
            searchInput.value = '';
            departmentFilter.value = '';
            yearFilter.value = '';
            filterStudents();
        });

        // Reset add student modal on close      
        const addStudentModal = document.getElementById('addStudentModal');
        addStudentModal.addEventListener('hidden.bs.modal', () => {
        console.log('Here');
        document.getElementById('studentform').reset();
        });


        // Password confirmation validation
        const passwordInput = document.querySelector('input[name="Password"]');
        const confirmPasswordInput = document.querySelector('input[name="ConfirmPassword"]');

        function validatePasswordMatch() {
            if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        }

        passwordInput.addEventListener('input', validatePasswordMatch);
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);

        // Initialize results count
        updateResultsCount();

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>

</html>