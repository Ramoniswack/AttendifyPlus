<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
  header("Location: login.php");
  exit();
}
include '../config/db_config.php';

$successMsg = '';
$errorMsg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Collect form data
  $FullName = $_POST['FullName'] ?? '';
  $Email = $_POST['Email'] ?? '';
  $Contact = $_POST['Contact'] ?? '';
  $Type = $_POST['Type'] ?? '';
  $Password = $_POST['Password'] ?? '';
  $ConfirmPassword = $_POST['ConfirmPassword'] ?? '';
  $DepartmentID = $_POST['DepartmentID'] ?? '';
  $SemesterID = $_POST['SemesterID'] ?? '';
  $SubjectID = $_POST['SubjectID'] ?? '';
  $Address = $_POST['Address'] ?? '';
  $PhotoURL = '';

  if ($Password !== $ConfirmPassword) {
    $errorMsg = "Passwords do not match.";
  }

  // Photo upload
  if (isset($_FILES['PhotoFile']) && $_FILES['PhotoFile']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/teachers/';
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
      // Insert login
      $stmt1 = $conn->prepare("INSERT INTO login_tbl (Email, Password, Role, Status, CreatedDate) VALUES (?, ?, 'teacher', 'active', NOW())");
      if ($stmt1) {
        $hashedPass = password_hash($Password, PASSWORD_BCRYPT);
        $stmt1->bind_param("ss", $Email, $hashedPass);

        if ($stmt1->execute()) {
          $loginID = $conn->insert_id;

          // Insert teacher
          $stmt2 = $conn->prepare("INSERT INTO teachers (LoginID, FullName, Contact, Address, PhotoURL) VALUES (?, ?, ?, ?, ?)");
          if ($stmt2) {
            $stmt2->bind_param("issss", $loginID, $FullName, $Contact, $Address, $PhotoURL);
            if ($stmt2->execute()) {
              // SUCCESS: Store success message in session and redirect
              $_SESSION['success_message'] = "Teacher added successfully.";
              header("Location: " . $_SERVER['PHP_SELF']);
              exit();
            } else {
              $errorMsg = "Failed to insert into teachers.";
            }
            $stmt2->close();
          } else {
            $errorMsg = "Error preparing teacher insert.";
          }
        } else {
          $errorMsg = "Failed to insert into login_tbl.";
        }
        $stmt1->close();
      } else {
        $errorMsg = "Error preparing login insert.";
      }
    }
    $emailCheck->close();
  }
}

// Check for success message from session (after redirect)
if (isset($_SESSION['success_message'])) {
  $successMsg = $_SESSION['success_message'];
  unset($_SESSION['success_message']); // Clear it after displaying
}

// Fetch teachers with subjects
$teachers = [];
$sql = "SELECT t.TeacherID, t.FullName, t.Contact, l.Email,
               s.SubjectName, d.DepartmentName, sem.SemesterNumber,
               ts.MapID, t.PhotoURL
        FROM teachers t
        JOIN login_tbl l ON t.LoginID = l.LoginID
        LEFT JOIN teacher_subject_map ts ON t.TeacherID = ts.TeacherID
        LEFT JOIN subjects s ON ts.SubjectID = s.SubjectID
        LEFT JOIN departments d ON s.DepartmentID = d.DepartmentID
        LEFT JOIN semesters sem ON s.SemesterID = sem.SemesterID
        WHERE l.Status = 'active'
        ORDER BY t.FullName";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
  $id = $row['TeacherID'];
  if (!isset($teachers[$id])) {
    $teachers[$id] = [
      'FullName' => $row['FullName'],
      'Email'       => $row['Email'],
      'Contact'     => $row['Contact'],
      'PhotoURL'    => $row['PhotoURL'],
      'Subjects'    => []
    ];
  }
  if ($row['SubjectName']) {
    $teachers[$id]['Subjects'][] = [
      'MapID'      => $row['MapID'],
      'Department' => $row['DepartmentName'],
      'Semester'   => $row['SemesterNumber'],
      'Subject'    => $row['SubjectName']
    ];
  }
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
$subRes = $conn->query("SELECT SubjectID, SubjectName, DepartmentID, SemesterID FROM subjects ORDER BY SubjectName");
while ($row = $subRes->fetch_assoc()) {
  $subjects[] = $row;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Manage Teachers | Attendify+</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="../assets/css/manage_teacher.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
  <script src="../assets/js/lucide.min.js"></script>
  <script src="../assets/js/manage_teacher.js" defer></script>

</head>

<body>
  <?php include 'sidebar_admin_dashboard.php'; ?>
  <?php include 'navbar_admin.php'; ?>
  <div class="container-fluid dashboard-container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
<h2><i data-lucide="users"></i> Manage Teachers</h2>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
        <i data-lucide="user-plus"></i> Add Teacher
      </button>
    </div>
    <?php if ($successMsg): ?>
      <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="mb-4">
      <input id="teacherSearch" class="form-control w-100 w-md-50 mx-auto" placeholder="Search by name..." />
    </div>

    <div class="card shadow-sm">
      <div class="table-responsive">
        <table id="teacherTable" class="table table-bordered table-hover align-middle text-center">
          <thead class="table-light">
            <tr>
              <th>Full Name</th>
              <th>Contact</th>
              <th>Email</th>
              <th>Photo</th>
              <th style="width:150px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($teachers as $id => $teacher): ?>
              <tr>
                <td><?= htmlspecialchars($teacher['FullName']) ?></td>
                <td><?= htmlspecialchars($teacher['Contact']) ?></td>
                <td><?= htmlspecialchars($teacher['Email']) ?></td>
                <td>
                  <?php if (!empty($teacher['PhotoURL']) && file_exists($teacher['PhotoURL'])): ?>
                    <img src="<?= htmlspecialchars($teacher['PhotoURL']) ?>" alt="Photo" class="rounded-circle" style="width:50px;height:50px;object-fit:cover;">
                  <?php else: ?>
                    <span class="text-muted">No photo</span>
                  <?php endif; ?>
                </td>
                <td>
                  <button class="btn btn-sm btn-outline-info me-1" data-bs-toggle="modal" data-bs-target="#viewModal<?= $id ?>">
                    <i data-lucide="eye"></i>
                  </button>
                  <a href="update_teacher.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
                    <i data-lucide="edit"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- View Subject Modal -->
    <?php foreach ($teachers as $id => $teacher): ?>
      <div class="modal fade" id="viewModal<?= $id ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><?= htmlspecialchars($teacher['FullName']) ?> — Assigned Subjects</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <?php if ($teacher['Subjects']): ?>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered text-center">
                    <thead class="table-light">
                      <tr>
                        <th>Department</th>
                        <th>Semester</th>
                        <th>Subject</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($teacher['Subjects'] as $s): ?>
                        <tr>
                          <td><?= htmlspecialchars($s['Department']) ?></td>
                          <td>Semester <?= htmlspecialchars($s['Semester']) ?></td>
                          <td><?= htmlspecialchars($s['Subject']) ?></td>
                          <td>
                            <a href="update_assignment.php?mapid=<?= $s['MapID'] ?>" class="btn btn-sm btn-warning me-1">
                              <i data-lucide="edit-3"></i>
                            </a>
                            <a href="delete_assignment.php?mapid=<?= $s['MapID'] ?>" class="btn btn-sm btn-danger"
                              onclick="return confirm('Are you sure?')">
                              <i data-lucide="trash"></i>
                            </a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="text-muted">No subjects assigned.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- Add Teacher Modal -->
    <div class="modal fade" id="addTeacherModal" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" method="POST" enctype="multipart/form-data">
          <div class="modal-header">
            <h5 class="modal-title">Add New Teacher</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input name="FullName" class="form-control" required />
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input name="Email" type="email" class="form-control" required />
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact</label>
                <input name="Contact" class="form-control" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Type</label>
                <select name="Type" class="form-select" required>
                  <option value="full-time">Full-Time</option>
                  <option value="part-time">Part-Time</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Password</label>
                <input name="Password" type="password" class="form-control" required />
              </div>
              <div class="col-md-6">
                <label class="form-label">Confirm Password</label>
                <input name="ConfirmPassword" type="password" class="form-control" required />
              </div>
              <div class="col-md-6">
                <label class="form-label">Department</label>
                <select name="DepartmentID" id="departmentSelect" class="form-select" required>
                  <option value="">Select</option>
                  <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['DepartmentID'] ?>"><?= htmlspecialchars($d['DepartmentName']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Semester</label>
                <select name="SemesterID" id="semesterSelect" class="form-select" required>
                  <option value="">Select</option>
                  <?php foreach ($semesters as $s): ?>
                    <option value="<?= $s['SemesterID'] ?>">Semester <?= htmlspecialchars($s['SemesterNumber']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Subject</label>
                <select name="SubjectID" id="subjectSelect" class="form-select" required style="text-overflow:ellipsis;overflow:hidden;">
                  <option value="">Select Department & Semester First</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Choose Photo <small class="form-text text-muted">(optional)</small></label>
                <input name="PhotoFile" type="file" class="form-control" accept="image/*" />
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Teacher</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    lucide.createIcons();

    // Search filter for teacher table
    document.getElementById("teacherSearch").addEventListener("input", e => {
      const term = e.target.value.toLowerCase();
      for (const row of document.querySelectorAll("#teacherTable tbody tr")) {
        const name = row.cells[0].textContent.toLowerCase();
        row.style.display = name.includes(term) ? "" : "none";
      }
    });

    // Subject filtering logic
    const subjectData = <?= json_encode($subjects) ?>;
    const departmentSelect = document.getElementById("departmentSelect");
    const semesterSelect = document.getElementById("semesterSelect");
    const subjectSelect = document.getElementById("subjectSelect");

    departmentSelect.addEventListener("change", filterSubjects);
    semesterSelect.addEventListener("change", filterSubjects);

    function filterSubjects() {
      const dept = departmentSelect.value;
      const sem = semesterSelect.value;
      subjectSelect.innerHTML = '<option value="">Select</option>';

      subjectData.forEach(sub => {
        if (+sub.DepartmentID === +dept && +sub.SemesterID === +sem) {
          subjectSelect.innerHTML += `<option value="${sub.SubjectID}">${sub.SubjectName}</option>`;
        }
      });
    }

    // Reset modal form on close
    const addTeacherModal = document.getElementById('addTeacherModal');
    addTeacherModal.addEventListener('hidden.bs.modal', () => {
      addTeacherModal.querySelector('form').reset();
      subjectSelect.innerHTML = '<option value="">Select Department & Semester First</option>';
    });
  </script>

</body>

</html>