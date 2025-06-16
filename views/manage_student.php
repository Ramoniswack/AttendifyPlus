<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: login.php");
    exit();
}
include '../config/db_config.php';

$successMsg = '';
$errorMsg = '';

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

    // Basic validation
    if (empty($FullName) || empty($Email) || empty($DepartmentID) || empty($SemesterID) || empty($JoinYear)) {
        $errorMsg = "Please fill in all required fields.";
    } elseif ($Password !== $ConfirmPassword) {
        $errorMsg = "Passwords do not match.";
    } elseif (strlen($Password) < 6) {
        $errorMsg = "Password must be at least 6 characters long.";
    } else {
        // Handle photo upload
        if (isset($_FILES['PhotoFile']) && $_FILES['PhotoFile']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/students/';

            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = $_FILES['PhotoFile']['type'];

            if (in_array($fileType, $allowedTypes)) {
                $ext = pathinfo($_FILES['PhotoFile']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('student_', true) . '.' . $ext;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['PhotoFile']['tmp_name'], $targetPath)) {
                    $PhotoURL = $targetPath;
                } else {
                    $errorMsg = "Failed to upload photo.";
                }
            } else {
                $errorMsg = "Invalid file type. Please upload JPG, PNG, or GIF files only.";
            }
        }

        if (empty($errorMsg)) {
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

                    // Insert into students table (simplified - only essential fields)
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
            }
            $emailCheck->close();
        }
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
        ORDER BY s.FullName";

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

// Get statistics
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
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Manage Students | Attendify+</title>
    <link rel="stylesheet" href="../assets/css/manage_student.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
    <script src="../assets/js/lucide.min.js"></script>
    <script src="../assets/js/manage_student.js" defer></script>
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
        }

        .student-photo {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
        }

        .status-badge {
            font-size: 0.875rem;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .btn-close-red {
            filter: invert(41%) sepia(93%) saturate(7470%) hue-rotate(346deg) brightness(96%) contrast(120%);
        }

        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
        }

        .required-field {
            color: #dc3545;
        }
    </style>
</head>

<body>
    <?php include 'sidebar_admin_dashboard.php'; ?>
    <?php include 'navbar_admin.php'; ?>

    <div class="container-fluid dashboard-container pt-4" id="manageStudentsContainer">
        <!-- Header and Add Button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i data-lucide="users"></i> Manage Students</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i data-lucide="user-plus"></i> Add Student
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

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card text-center">
                    <div class="stats-number"><?= $stats['total'] ?></div>
                    <div>Total Students</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card text-center" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    <div class="stats-number"><?= $stats['active'] ?></div>
                    <div>Active Students</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card text-center" style="background: linear-gradient(135deg, #fc466b 0%, #3f5efb 100%);">
                    <div class="stats-number"><?= $stats['inactive'] ?></div>
                    <div>Inactive Students</div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label"><i data-lucide="search"></i> Search Name</label>
                        <input id="searchName" type="text" class="form-control" placeholder="Enter student name..." />
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
                        <label class="form-label"><i data-lucide="calendar"></i> Join Year</label>
                        <input id="filterYear" type="number" class="form-control" placeholder="e.g. 2022" min="2000" max="<?= date('Y') ?>">
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
                </div>
            </div>
        </div>

        <!-- Students Table -->
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="studentsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Photo</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Department</th>
                            <th>Semester</th>
                            <th>Join Year</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($student['PhotoURL']) && file_exists($student['PhotoURL'])): ?>
                                        <img src="<?= htmlspecialchars($student['PhotoURL']) ?>" alt="Photo" class="student-photo">
                                    <?php else: ?>
                                        <div class="student-photo bg-secondary d-flex align-items-center justify-content-center text-white">
                                            <i data-lucide="user"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($student['FullName']) ?></strong>
                                    <br><small class="text-muted">ID: <?= $student['StudentID'] ?></small>
                                </td>
                                <td><?= htmlspecialchars($student['Email']) ?></td>
                                <td><?= htmlspecialchars($student['Contact'] ?: 'Not provided') ?></td>
                                <td><?= htmlspecialchars($student['DepartmentName']) ?></td>
                                <td>Semester <?= htmlspecialchars($student['SemesterNumber']) ?></td>
                                <td><?= htmlspecialchars($student['JoinYear']) ?></td>
                                <td>
                                    <span class="badge <?= $student['Status'] === 'active' ? 'bg-success' : 'bg-danger' ?> status-badge">
                                        <?= ucfirst($student['Status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewModal<?= $student['StudentID'] ?>" title="View Details">
                                            <i data-lucide="eye"></i>
                                        </button>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="Change Status">
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
    </div>

    <!-- View Student Details Modals -->
    <?php foreach ($students as $student): ?>
        <div class="modal fade" id="viewModal<?= $student['StudentID'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i data-lucide="user"></i> Student Details - <?= htmlspecialchars($student['FullName']) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <?php if (!empty($student['PhotoURL']) && file_exists($student['PhotoURL'])): ?>
                                    <img src="<?= htmlspecialchars($student['PhotoURL']) ?>" alt="Photo" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white mb-3 mx-auto" style="width: 150px; height: 150px; font-size: 3rem;">
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
                                        <td><?= date('M d, Y', strtotime($student['CreatedDate'])) ?></td>
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

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="POST" enctype="multipart/form-data" class="modal-content">
                <input type="hidden" name="action" value="add_student">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="user-plus"></i> Add New Student
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
                            <label class="form-label">Full Name <span class="required-field">*</span></label>
                            <input name="FullName" class="form-control" required placeholder="Enter full name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address <span class="required-field">*</span></label>
                            <input name="Email" type="email" class="form-control" required placeholder="student@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input name="Contact" type="tel" class="form-control" placeholder="98xxxxxxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input name="Address" class="form-control" placeholder="City, District">
                        </div>

                        <!-- Academic Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2"><i data-lucide="graduation-cap"></i> Academic Information</h6>
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
                            <label class="form-label">Join Year <span class="required-field">*</span></label>
                            <input name="JoinYear" type="number" class="form-control" required
                                min="2000" max="<?= date('Y') ?>" value="<?= date('Y') ?>" placeholder="<?= date('Y') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Profile Photo</label>
                            <input name="PhotoFile" type="file" class="form-control" accept="image/*">
                            <small class="form-text text-muted">Optional - JPG, PNG, GIF (Max 5MB)</small>
                        </div>

                        <!-- Account Information -->
                        <div class="col-12 mt-4">
                            <h6 class="text-primary border-bottom pb-2"><i data-lucide="lock"></i> Account Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="required-field">*</span></label>
                            <input name="Password" type="password" class="form-control" required minlength="6" placeholder="Minimum 6 characters">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password <span class="required-field">*</span></label>
                            <input name="ConfirmPassword" type="password" class="form-control" required minlength="6" placeholder="Re-enter password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="Status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i data-lucide="x"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save"></i> Add Student
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="sidebar-overlay"></div>
    <script>
        lucide.createIcons();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>