<?php

session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
  header("Location: login.php");
  exit();
}

include '../config/db_config.php';

$successMsg = '';
$errorMsg = '';
$errors = [];             //declare array


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


    // Assigned subjects should be treated as an array
    $assignedSubjects = is_array($SubjectID) ? $SubjectID : [$SubjectID];

    // VALIDATION
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

    if (empty($Type)) {
        $errors['Type'] = "Please select employment type.";
    }

    if (empty($DepartmentID)) {
        $errors['DepartmentID'] = "Please select a department.";
    }

    if (empty($SemesterID)) {
        $errors['SemesterID'] = "Please select a semester.";
    }

    if (empty($assignedSubjects) || !is_array($assignedSubjects) || empty($assignedSubjects[0])) {
        $errors['SubjectID'] = "Please assign at least one subject.";
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

// Photo upload
  if (isset($_FILES['PhotoFile']) && $_FILES['PhotoFile']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/teachers/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileType = $_FILES['PhotoFile']['type'];

    if (!in_array($fileType, $allowedTypes)) {
      $errors['Photo'] = "Only JPEG, PNG, and GIF images are allowed.";
    } elseif ($_FILES['PhotoFile']['size'] > 5 * 1024 * 1024) {
      $errors['Photo'] = "Image size must be less than 5MB.";
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
  if (empty($errors)) {
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
  }else{

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

// Get statistics - updated to match manage_admin structure
$stats = [];
$statsQueries = [
  'total_teachers' => "SELECT COUNT(*) as count FROM teachers t JOIN login_tbl l ON t.LoginID = l.LoginID",
  'active_teachers' => "SELECT COUNT(*) as count FROM teachers t JOIN login_tbl l ON t.LoginID = l.LoginID WHERE l.Status = 'active'",
  'inactive_teachers' => "SELECT COUNT(*) as count FROM teachers t JOIN login_tbl l ON t.LoginID = l.LoginID WHERE l.Status = 'inactive'"
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
</head>

<body>
  <!-- Include sidebar and navbar -->
  <?php include 'sidebar_admin_dashboard.php'; ?>
  <?php include 'navbar_admin.php'; ?>

  <!-- Main content -->
  <div class="container-fluid dashboard-container">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
      <div>
        <h2 class="page-title">
          <i data-lucide="users"></i>
          Teacher Management
        </h2>
        <p class="text-muted mb-0">Manage teacher accounts and subject assignments</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
          <i data-lucide="user-plus"></i> Add Teacher
        </button>
        <a href="manage_admin.php" class="btn btn-outline-primary">
          <i data-lucide="shield"></i> Admin Management
        </a>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
      <div class="col-md-4">
        <div class="stat-card text-center">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stat-number"><?= $stats['total_teachers'] ?></div>
              <div class="stat-label">Total Teachers</div>
              <div class="stat-change">
                <i data-lucide="users"></i>
                <span>Faculty members</span>
              </div>
            </div>
            <div class="stats-icon">
              <i data-lucide="users"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card teachers text-center">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stat-number"><?= $stats['active_teachers'] ?></div>
              <div class="stat-label">Active Teachers</div>
              <div class="stat-change">
                <i data-lucide="user-check"></i>
                <span>Currently teaching</span>
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
              <div class="stat-number"><?= $stats['inactive_teachers'] ?></div>
              <div class="stat-label">Inactive Teachers</div>
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

    <!-- Search and Filter Section -->
    <div class="card mb-4">
      <div class="card-body">
        <h6 class="card-title">
          <i data-lucide="filter"></i>
          Search & Filter Teachers
        </h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">
              <i data-lucide="search"></i>
              Search Teachers
            </label>
            <input id="teacherSearch" type="text" class="form-control" placeholder="Search by name, email, or contact..." />
          </div>
          <div class="col-md-3">
            <label class="form-label">
              <i data-lucide="layers"></i>
              Account Status
            </label>
            <select id="filterStatus" class="form-select">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">
              <i data-lucide="book-open"></i>
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

    <!-- Teachers Table -->
    <div class="card shadow-sm">
      <div class="card-header">
        <h6 class="card-title mb-0">
          <i data-lucide="users"></i>
          Teacher Directory
        </h6>
      </div>
      <div class="table-responsive">
        <table id="teachersTable" class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Profile</th>
              <th>Teacher</th>
              <th>Contact Information</th>
              <th>Subjects</th>
              <th>Date Joined</th>
              <th>Account Status</th>
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
                <i data-lucide="user"></i>
                Teacher Profile - <?= htmlspecialchars($teacher['FullName']) ?>
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
        <form class="modal-content" id="teacherform" method="POST"  enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_teacher">
          <div class="modal-header">
            <h5 class="modal-title">
              <i data-lucide="user-plus"></i>
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
                  <input name="FullName" type="text" class="form-control" required placeholder="Enter full name"
                          value="<?php echo htmlspecialchars($_POST['FullName'] ?? ''); ?>" />
                  <span class="error text-danger"><?php echo $errors['FullName'] ?? ''; ?></span>
              </div>

              <div class="col-md-6">
                <label class="form-label">
                  Email Address <span class="required-field">*</span>
                </label>
                  <input name="Email" type="email" class="form-control" required placeholder="teacher@example.com"
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
                <label class="form-label">Employment Type</label>
                <select name="Type" class="form-select">
                  <option value="full-time">Full-Time</option>
                  <option value="part-time">Part-Time</option>
                  <option value="contract">Contract</option>
                </select>
                <span class="error text-danger"><?php echo $errors['Type'] ?? ''; ?></span>
              </div>

              <div class="col-12">
                <label class="form-label">Address</label>
                <textarea name="Address" class="form-control" rows="2" placeholder="Enter full address" ></textarea>
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
                <span class="error text-danger"><?php echo $errors['DepartmentID'] ?? ''; ?></span>
              </div>
              <div class="col-md-6">
                <label class="form-label">Semester</label>
                <select name="SemesterID" id="semesterSelect" class="form-select">
                  <option value="">Select Semester</option>
                  <?php foreach ($semesters as $s): ?>
                    <option value="<?= $s['SemesterID'] ?>">Semester <?= $s['SemesterNumber'] ?></option>
                  <?php endforeach; ?>
                </select>
                <span class="error text-danger"><?php echo $errors['SemesterID'] ?? ''; ?></span>
              </div>
              <div class="col-12">
                <label class="form-label">Subject Assignment</label>
                <select name="SubjectID" id="subjectSelect" class="form-select">
                  <option value="">Select Department & Semester First</option>
                </select>
                <span class="error text-danger"><?php echo $errors['SubjectID'] ?? ''; ?></span>
                <small class="form-text text-muted"><br>
                You can assign additional subjects later</small>
              </div>

              <!-- Account Information -->
              <div class="col-12 mt-4">
                <h6 class="text-primary border-bottom pb-2"><i data-lucide="lock"></i> Account Information</h6>
              </div>
              <div class="col-md-6">
                <label class="form-label">
                  Password <span class="required-field">*</span>
                </label>
                  <input name="Password" type="password" class="form-control" required />
                  <span class="error text-danger"><?php echo $errors['Password'] ?? ''; ?></span>
              </div>
              <div class="col-md-6">
                <label class="form-label">
                  Confirm Password <span class="required-field">*</span>
                </label>
                  <input name="ConfirmPassword" type="password" class="form-control" required />
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
              Create Teacher
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

    // Search and filter functionality
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
      console.log('Here');
      document.getElementById('teacherform').reset();
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

<?php if (!empty($errors)): ?>
<script>
  var myModal = new bootstrap.Modal(document.getElementById('addTeacherModal'));
  window.addEventListener('load', () => {
    myModal.show();
  });
</script>
<?php endif; ?>