<?php

session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
  header("Location: login.php");
  exit();
}

include '../config/db_config.php';

$successMsg = '';
$errorMsg = '';

// Handle form submission for adding teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_teacher') {
  // Collect form data
  $FullName = trim($_POST['FullName'] ?? '');
  $Email = trim($_POST['Email'] ?? '');
  $Contact = trim($_POST['Contact'] ?? '');
  $Type = $_POST['Type'] ?? '';
  $Password = $_POST['Password'] ?? '';
  $ConfirmPassword = $_POST['ConfirmPassword'] ?? '';
  $DepartmentID = $_POST['DepartmentID'] ?? '';
  $SemesterID = $_POST['SemesterID'] ?? '';
  $SubjectID = $_POST['SubjectID'] ?? '';
  $Address = trim($_POST['Address'] ?? '');
  $PhotoURL = '';

  // Basic validation
  if (empty($FullName) || empty($Email) || empty($Password)) {
    $errorMsg = "Please fill in all required fields.";
  } elseif ($Password !== $ConfirmPassword) {
    $errorMsg = "Passwords do not match.";
  } elseif (strlen($Password) < 6) {
    $errorMsg = "Password must be at least 6 characters long.";
  }

  // Photo upload
  if (isset($_FILES['PhotoFile']) && $_FILES['PhotoFile']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/teachers/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileType = $_FILES['PhotoFile']['type'];

    if (!in_array($fileType, $allowedTypes)) {
      $errorMsg = "Only JPEG, PNG, and GIF images are allowed.";
    } elseif ($_FILES['PhotoFile']['size'] > 5 * 1024 * 1024) {
      $errorMsg = "Image size must be less than 5MB.";
    } else {
      $ext = pathinfo($_FILES['PhotoFile']['name'], PATHINFO_EXTENSION);
      $filename = uniqid('teacher_', true) . '.' . $ext;
      $targetPath = $uploadDir . $filename;

      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }

      if (move_uploaded_file($_FILES['PhotoFile']['tmp_name'], $targetPath)) {
        $PhotoURL = $targetPath;
      } else {
        $errorMsg = "Failed to upload photo.";
      }
    }
  }

  // Only proceed if no error so far
  if (empty($errorMsg)) {
    // Check if email already exists
    $emailCheck = $conn->prepare("SELECT LoginID FROM login_tbl WHERE Email = ?");
    $emailCheck->bind_param("s", $Email);
    $emailCheck->execute();
    $emailCheck->store_result();

    if ($emailCheck->num_rows > 0) {
      $errorMsg = "This email is already registered.";
    } else {
      // Begin transaction
      $conn->begin_transaction();

      try {
        // Insert login
        $stmt1 = $conn->prepare("INSERT INTO login_tbl (Email, Password, Role, Status, CreatedDate) VALUES (?, ?, 'teacher', 'active', NOW())");
        $hashedPass = password_hash($Password, PASSWORD_BCRYPT);
        $stmt1->bind_param("ss", $Email, $hashedPass);
        $stmt1->execute();
        $loginID = $conn->insert_id;

        // Insert teacher
        $stmt2 = $conn->prepare("INSERT INTO teachers (LoginID, FullName, Contact, Address, PhotoURL) VALUES (?, ?, ?, ?, ?)");
        $stmt2->bind_param("issss", $loginID, $FullName, $Contact, $Address, $PhotoURL);
        $stmt2->execute();
        $teacherID = $conn->insert_id;

        // Insert subject mapping if provided
        if (!empty($SubjectID)) {
          $stmt3 = $conn->prepare("INSERT INTO teacher_subject_map (TeacherID, SubjectID) VALUES (?, ?)");
          $stmt3->bind_param("ii", $teacherID, $SubjectID);
          $stmt3->execute();
          $stmt3->close();
        }

        $conn->commit();
        $_SESSION['success_message'] = "Teacher added successfully.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
      } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = "Error adding teacher: " . $e->getMessage();
      }

      if (isset($stmt1)) $stmt1->close();
      if (isset($stmt2)) $stmt2->close();
    }
    $emailCheck->close();
  }
}

// Handle teacher status update (instead of deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
  $teacherID = $_POST['teacher_id'] ?? '';
  $newStatus = $_POST['new_status'] ?? '';

  if (!empty($teacherID) && !empty($newStatus)) {
    // Update status in login_tbl
    $updateStmt = $conn->prepare("UPDATE login_tbl l 
                                 JOIN teachers t ON l.LoginID = t.LoginID 
                                 SET l.Status = ? 
                                 WHERE t.TeacherID = ?");
    $updateStmt->bind_param("si", $newStatus, $teacherID);

    if ($updateStmt->execute()) {
      $_SESSION['success_message'] = "Teacher status updated successfully.";
    } else {
      $_SESSION['error_message'] = "Failed to update teacher status.";
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

// Fetch teachers with enhanced data (including inactive ones)
$teachers = [];
$sql = "SELECT t.TeacherID, t.FullName, t.Contact, t.Address, l.Email, l.CreatedDate, l.Status,
               t.PhotoURL, COUNT(ts.SubjectID) as SubjectCount
        FROM teachers t
        JOIN login_tbl l ON t.LoginID = l.LoginID
        LEFT JOIN teacher_subject_map ts ON t.TeacherID = ts.TeacherID
        GROUP BY t.TeacherID, t.FullName, t.Contact, t.Address, l.Email, l.CreatedDate, l.Status, t.PhotoURL
        ORDER BY l.Status ASC, t.FullName";
$res = $conn->query($sql);

while ($row = $res->fetch_assoc()) {
  $teachers[] = $row;
}

// Fetch teacher subjects for detail view
$teacherSubjects = [];
$subjectSql = "SELECT ts.TeacherID, s.SubjectName, s.SubjectCode, d.DepartmentName, sem.SemesterNumber
               FROM teacher_subject_map ts
               JOIN subjects s ON ts.SubjectID = s.SubjectID
               JOIN departments d ON s.DepartmentID = d.DepartmentID
               JOIN semesters sem ON s.SemesterID = sem.SemesterID
               ORDER BY ts.TeacherID, s.SubjectName";
$subjectRes = $conn->query($subjectSql);

while ($row = $subjectRes->fetch_assoc()) {
  $teacherSubjects[$row['TeacherID']][] = $row;
}

// Fetch reference data
$departments = [];
$deptRes = $conn->query("SELECT DepartmentID, DepartmentName FROM departments ORDER BY DepartmentName");
while ($row = $deptRes->fetch_assoc()) {
  $departments[] = $row;
}

$semesters = [];
$semRes = $conn->query("SELECT SemesterID, SemesterNumber FROM semesters ORDER BY SemesterNumber");
while ($row = $semRes->fetch_assoc()) {
  $semesters[] = $row;
}

$subjects = [];
$subRes = $conn->query("SELECT SubjectID, SubjectName, SubjectCode, DepartmentID, SemesterID FROM subjects ORDER BY SubjectName");
while ($row = $subRes->fetch_assoc()) {
  $subjects[] = $row;
}

// Get statistics - back to original
$stats = [];
$statsQueries = [
  'total_teachers' => "SELECT COUNT(*) as count FROM teachers t JOIN login_tbl l ON t.LoginID = l.LoginID WHERE l.Status = 'active'",
  'active_mappings' => "SELECT COUNT(*) as count FROM teacher_subject_map",
  'recent_teachers' => "SELECT COUNT(*) as count FROM teachers t JOIN login_tbl l ON t.LoginID = l.LoginID WHERE l.Status = 'active' AND DATE(l.CreatedDate) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
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
  <title>Manage Teachers | Attendify+</title>
  <link rel="stylesheet" href="../assets/css/manage_teacher.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <script src="../assets/js/lucide.min.js"></script>
  <script src="../assets/js/manage_teacher.js" defer></script>
  <style>
  

  </style>
</head>

<body>
  <!-- Include sidebar and navbar -->
  <?php include 'sidebar_admin_dashboard.php'; ?>
  <?php include 'navbar_admin.php'; ?>

  <!-- Main content -->
  <div class="container-fluid dashboard-container pt-4">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="mb-1">
          <i data-lucide="users" class="me-2"></i>
          Manage Teachers
        </h2>
        <p class="text-muted mb-0">Add, view, and manage teacher accounts</p>
      </div>
      <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
        <i data-lucide="user-plus" class="me-2"></i>
        Add New Teacher
      </button>
    </div>

    <!-- Statistics Cards - Back to Original -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="stats-card text-center">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stats-number"><?= $stats['total_teachers'] ?></div>
              <div>Total Teachers</div>
            </div>
            <div class="stats-icon">
              <i data-lucide="users"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stats-card assignments-card text-center">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stats-number"><?= $stats['active_mappings'] ?></div>
              <div>Subject Assignments</div>
            </div>
            <div class="stats-icon">
              <i data-lucide="book-open"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stats-card recent-card text-center">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stats-number"><?= $stats['recent_teachers'] ?></div>
              <div>New This Month</div>
            </div>
            <div class="stats-icon">
              <i data-lucide="user-plus"></i>
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

    <!-- Search and Filter Section - Fixed Department Search -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">
              <i data-lucide="search" class="me-1"></i>
              Search Teachers
            </label>
            <input id="teacherSearch" type="text" class="form-control" placeholder="Search by name, email, or contact..." />
          </div>
          <div class="col-md-3">
            <label class="form-label">
              <i data-lucide="layers" class="me-1"></i>
              Status
            </label>
            <select id="filterStatus" class="form-select">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">
              <i data-lucide="calendar" class="me-1"></i>
              Subject Count
            </label>
            <select id="filterSubjectCount" class="form-select">
              <option value="">All Teachers</option>
              <option value="0">No Subjects Assigned</option>
              <option value="1">1 Subject</option>
              <option value="2+">2+ Subjects</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">&nbsp;</label>
            <button id="clearFilters" class="btn btn-outline-secondary d-block w-100">
              <i data-lucide="x" class="me-1"></i>
              Clear Filters
            </button>
          </div>
        </div>
        <div class="mt-3">
          <small id="resultsCount" class="text-muted"></small>
        </div>
      </div>
    </div>

    <!-- Teachers Table -->
    <div class="card shadow-sm">
      <div class="table-responsive">
        <table id="teachersTable" class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Photo</th>
              <th>Teacher</th>
              <th>Contact Info</th>
              <th>Subjects</th>
              <th>Joined</th>
              <th>Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody id="teachersTableBody">
            <?php foreach ($teachers as $teacher): ?>
              <tr class="teacher-row"
                data-name="<?= strtolower(htmlspecialchars($teacher['FullName'])) ?>"
                data-email="<?= strtolower(htmlspecialchars($teacher['Email'])) ?>"
                data-contact="<?= htmlspecialchars($teacher['Contact']) ?>"
                data-status="<?= htmlspecialchars($teacher['Status']) ?>"
                data-subject-count="<?= $teacher['SubjectCount'] ?>">
                <td>
                  <?php if (!empty($teacher['PhotoURL']) && file_exists($teacher['PhotoURL'])): ?>
                    <img src="<?= htmlspecialchars($teacher['PhotoURL']) ?>"
                      alt="<?= htmlspecialchars($teacher['FullName']) ?>"
                      class="teacher-photo">
                  <?php else: ?>
                    <div class="teacher-placeholder">
                      <i data-lucide="user"></i>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <div>
                    <div class="fw-semibold"><?= htmlspecialchars($teacher['FullName']) ?></div>
                    <small class="text-muted">ID: <?= $teacher['TeacherID'] ?></small>
                  </div>
                </td>
                <td>
                  <div>
                    <div class="mb-1">
                      <i data-lucide="mail" class="me-1 text-muted" style="width: 14px; height: 14px;"></i>
                      <small><?= htmlspecialchars($teacher['Email']) ?></small>
                    </div>
                    <?php if (!empty($teacher['Contact'])): ?>
                      <div>
                        <i data-lucide="phone" class="me-1 text-muted" style="width: 14px; height: 14px;"></i>
                        <small><?= htmlspecialchars($teacher['Contact']) ?></small>
                      </div>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="d-flex align-items-center">
                    <span class="badge bg-primary me-2"><?= $teacher['SubjectCount'] ?></span>
                    <small class="text-muted">
                      <?= $teacher['SubjectCount'] == 1 ? 'Subject' : 'Subjects' ?>
                    </small>
                  </div>
                </td>
                <td>
                  <small class="text-muted">
                    <?= date('M j, Y', strtotime($teacher['CreatedDate'])) ?>
                  </small>
                </td>
                <td>
                  <span class="badge <?= $teacher['Status'] === 'active' ? 'bg-success' : 'bg-danger' ?> status-badge">
                    <?= ucfirst($teacher['Status']) ?>
                  </span>
                </td>
                <td class="text-center">
                  <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-outline-info"
                      data-bs-toggle="modal"
                      data-bs-target="#viewTeacherModal<?= $teacher['TeacherID'] ?>"
                      title="View Details">
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
                            <input type="hidden" name="teacher_id" value="<?= $teacher['TeacherID'] ?>">
                            <input type="hidden" name="new_status" value="<?= $teacher['Status'] === 'active' ? 'inactive' : 'active' ?>">
                            <button type="submit" class="dropdown-item" onclick="return confirm('Are you sure you want to change the status?')">
                              <i data-lucide="<?= $teacher['Status'] === 'active' ? 'user-x' : 'user-check' ?>"></i>
                              <?= $teacher['Status'] === 'active' ? 'Deactivate' : 'Activate' ?>
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

    <!-- View Teacher Modals -->
    <?php foreach ($teachers as $teacher): ?>
      <div class="modal fade" id="viewTeacherModal<?= $teacher['TeacherID'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i data-lucide="user" class="me-2"></i>
                Teacher Details - <?= htmlspecialchars($teacher['FullName']) ?>
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="row">
                <div class="col-md-4 text-center">
                  <?php if (!empty($teacher['PhotoURL']) && file_exists($teacher['PhotoURL'])): ?>
                    <img src="<?= htmlspecialchars($teacher['PhotoURL']) ?>"
                      alt="<?= htmlspecialchars($teacher['FullName']) ?>"
                      class="teacher-photo-large mb-3">
                  <?php else: ?>
                    <div class="teacher-placeholder-large mb-3">
                      <i data-lucide="user"></i>
                    </div>
                  <?php endif; ?>
                  <h5><?= htmlspecialchars($teacher['FullName']) ?></h5>
                  <span class="badge <?= $teacher['Status'] === 'active' ? 'bg-success' : 'bg-danger' ?> mb-2">
                    <?= ucfirst($teacher['Status']) ?>
                  </span>
                </div>
                <div class="col-md-8">
                  <table class="table table-borderless">
                    <tr>
                      <th width="40%">Teacher ID:</th>
                      <td><?= $teacher['TeacherID'] ?></td>
                    </tr>
                    <tr>
                      <th>Email:</th>
                      <td><?= htmlspecialchars($teacher['Email']) ?></td>
                    </tr>
                    <tr>
                      <th>Contact:</th>
                      <td><?= htmlspecialchars($teacher['Contact'] ?: 'Not provided') ?></td>
                    </tr>
                    <tr>
                      <th>Address:</th>
                      <td><?= htmlspecialchars($teacher['Address'] ?: 'Not provided') ?></td>
                    </tr>
                    <tr>
                      <th>Subjects Assigned:</th>
                      <td><?= $teacher['SubjectCount'] ?> Subject<?= $teacher['SubjectCount'] != 1 ? 's' : '' ?></td>
                    </tr>
                    <tr>
                      <th>Joined Date:</th>
                      <td><?= date('F j, Y', strtotime($teacher['CreatedDate'])) ?></td>
                    </tr>
                  </table>

                  <?php if (isset($teacherSubjects[$teacher['TeacherID']])): ?>
                    <div class="mt-3">
                      <h6>Assigned Subjects:</h6>
                      <div class="row g-2">
                        <?php foreach ($teacherSubjects[$teacher['TeacherID']] as $subject): ?>
                          <div class="col-md-6">
                            <div class="subject-card">
                              <div class="subject-code"><?= htmlspecialchars($subject['SubjectCode']) ?></div>
                              <div class="subject-name"><?= htmlspecialchars($subject['SubjectName']) ?></div>
                              <small class="text-muted">
                                <?= htmlspecialchars($subject['DepartmentName']) ?> -
                                Semester <?= $subject['SemesterNumber'] ?>
                              </small>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="alert alert-info mt-3">
                      <i data-lucide="info" class="me-2"></i>
                      No subjects assigned yet.
                    </div>
                  <?php endif; ?>
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

    <!-- Add Teacher Modal -->
    <div class="modal fade" id="addTeacherModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_teacher">
          <div class="modal-header">
            <h5 class="modal-title">
              <i data-lucide="user-plus" class="me-2"></i>
              Add New Teacher
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
                <input name="FullName" type="text" class="form-control" required placeholder="Enter full name" />
              </div>
              <div class="col-md-6">
                <label class="form-label">
                  Email Address <span class="required-field">*</span>
                </label>
                <input name="Email" type="email" class="form-control" required placeholder="teacher@example.com" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact Number</label>
                <input name="Contact" type="tel" class="form-control" placeholder="98xxxxxxxx" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Employment Type</label>
                <select name="Type" class="form-select">
                  <option value="full-time">Full-Time</option>
                  <option value="part-time">Part-Time</option>
                  <option value="contract">Contract</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Address</label>
                <textarea name="Address" class="form-control" rows="2" placeholder="Enter full address"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">
                  Profile Photo
                  <small class="text-muted">(Optional, max 5MB)</small>
                </label>
                <input name="PhotoFile" type="file" class="form-control" accept="image/*" />
                <small class="form-text text-muted">JPG, PNG, GIF (Max 5MB)</small>
              </div>

              <!-- Academic Information -->
              <div class="col-12 mt-4">
                <h6 class="text-primary border-bottom pb-2"><i data-lucide="graduation-cap"></i> Academic Information</h6>
              </div>
              <div class="col-md-6">
                <label class="form-label">Department</label>
                <select name="DepartmentID" id="departmentSelect" class="form-select">
                  <option value="">Select Department</option>
                  <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['DepartmentID'] ?>"><?= htmlspecialchars($d['DepartmentName']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Semester</label>
                <select name="SemesterID" id="semesterSelect" class="form-select">
                  <option value="">Select Semester</option>
                  <?php foreach ($semesters as $s): ?>
                    <option value="<?= $s['SemesterID'] ?>">Semester <?= $s['SemesterNumber'] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Subject Assignment</label>
                <select name="SubjectID" id="subjectSelect" class="form-select">
                  <option value="">Select Department & Semester First</option>
                </select>
                <small class="form-text text-muted">You can assign additional subjects later</small>
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
              </div>
              <div class="col-md-6">
                <label class="form-label">
                  Confirm Password <span class="required-field">*</span>
                </label>
                <input name="ConfirmPassword" type="password" class="form-control" required placeholder="Re-enter password" />
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i data-lucide="x" class="me-1"></i>
              Cancel
            </button>
            <button type="submit" class="btn btn-primary">
              <i data-lucide="save" class="me-1"></i>
              Add Teacher
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

    // Subject filtering logic for add teacher modal
    const subjectData = <?= json_encode($subjects) ?>;
    const departmentSelect = document.getElementById("departmentSelect");
    const semesterSelect = document.getElementById("semesterSelect");
    const subjectSelect = document.getElementById("subjectSelect");

    function filterSubjects() {
      const dept = departmentSelect.value;
      const sem = semesterSelect.value;
      subjectSelect.innerHTML = '<option value="">Select Subject</option>';

      if (dept && sem) {
        const filteredSubjects = subjectData.filter(sub =>
          parseInt(sub.DepartmentID) === parseInt(dept) &&
          parseInt(sub.SemesterID) === parseInt(sem)
        );

        filteredSubjects.forEach(sub => {
          subjectSelect.innerHTML +=
            `<option value="${sub.SubjectID}">${sub.SubjectCode} - ${sub.SubjectName}</option>`;
        });

        if (filteredSubjects.length === 0) {
          subjectSelect.innerHTML = '<option value="">No subjects available</option>';
        }
      } else {
        subjectSelect.innerHTML = '<option value="">Select Department & Semester First</option>';
      }
    }

    departmentSelect.addEventListener("change", filterSubjects);
    semesterSelect.addEventListener("change", filterSubjects);

    // Search and filter functionality (without department filter)
    const searchInput = document.getElementById('teacherSearch');
    const statusFilter = document.getElementById('filterStatus');
    const subjectCountFilter = document.getElementById('filterSubjectCount');
    const clearFiltersBtn = document.getElementById('clearFilters');
    const resultsCount = document.getElementById('resultsCount');
    const teacherRows = document.querySelectorAll('.teacher-row');

    function updateResultsCount() {
      const visibleRows = document.querySelectorAll('.teacher-row:not([style*="display: none"])').length;
      const totalRows = teacherRows.length;
      resultsCount.textContent = `Showing ${visibleRows} of ${totalRows} teachers`;
    }

    function filterTeachers() {
      const searchTerm = searchInput.value.toLowerCase();
      const statusFilterValue = statusFilter.value.toLowerCase();
      const subjectCountFilterValue = subjectCountFilter.value;

      teacherRows.forEach(row => {
        const name = row.dataset.name || '';
        const email = row.dataset.email || '';
        const contact = row.dataset.contact || '';
        const status = row.dataset.status || '';
        const subjectCount = parseInt(row.dataset.subjectCount) || 0;

        let showRow = true;

        // Search filter
        if (searchTerm) {
          const searchMatch = name.includes(searchTerm) ||
            email.includes(searchTerm) ||
            contact.includes(searchTerm);
          if (!searchMatch) showRow = false;
        }

        // Status filter
        if (statusFilterValue && status !== statusFilterValue) {
          showRow = false;
        }

        // Subject count filter
        if (subjectCountFilterValue) {
          if (subjectCountFilterValue === '0' && subjectCount !== 0) showRow = false;
          if (subjectCountFilterValue === '1' && subjectCount !== 1) showRow = false;
          if (subjectCountFilterValue === '2+' && subjectCount < 2) showRow = false;
        }

        row.style.display = showRow ? '' : 'none';
      });

      updateResultsCount();
    }

    // Event listeners for filters
    searchInput.addEventListener('input', filterTeachers);
    statusFilter.addEventListener('change', filterTeachers);
    subjectCountFilter.addEventListener('change', filterTeachers);

    // Clear filters
    clearFiltersBtn.addEventListener('click', () => {
      searchInput.value = '';
      statusFilter.value = '';
      subjectCountFilter.value = '';
      filterTeachers();
    });

    // Reset add teacher modal on close
    const addTeacherModal = document.getElementById('addTeacherModal');
    addTeacherModal.addEventListener('hidden.bs.modal', () => {
      addTeacherModal.querySelector('form').reset();
      subjectSelect.innerHTML = '<option value="">Select Department & Semester First</option>';
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