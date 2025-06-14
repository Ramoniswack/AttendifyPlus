<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
  header("Location: login.php");
  exit();
}
include '../config/db_config.php';

// Fetch teacher info with subject mappings
$teachers = [];
$sql = "SELECT t.TeacherID, t.FullName, t.Contact, l.Email,
               s.SubjectName, d.DepartmentName, sem.SemesterNumber, ts.MapID
        FROM teachers t
        JOIN login_tbl l ON t.LoginID = l.LoginID
        LEFT JOIN teacher_subject_map ts ON t.TeacherID = ts.TeacherID
        LEFT JOIN subjects s ON ts.SubjectID = s.SubjectID
        LEFT JOIN departments d ON s.DepartmentID = d.DepartmentID
        LEFT JOIN semesters sem ON s.SemesterID = sem.SemesterID
        WHERE l.Status = 'active'";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
  $id = $row['TeacherID'];
  if (!isset($teachers[$id])) {
    $teachers[$id] = [
      'FullName' => $row['FullName'],
      'Email' => $row['Email'],
      'Contact' => $row['Contact'],
      'Subjects' => []
    ];
  }
  if ($row['SubjectName']) {
    $teachers[$id]['Subjects'][] = [
      'MapID' => $row['MapID'],
      'Department' => $row['DepartmentName'],
      'Semester' => $row['SemesterNumber'],
      'Subject' => $row['SubjectName']
    ];
  }
}

// Fetch departments
$departments = [];
$dept_sql = "SELECT DepartmentID, DepartmentName FROM departments ORDER BY DepartmentName";
$dept_result = $conn->query($dept_sql);
while ($row = $dept_result->fetch_assoc()) {
  $departments[] = $row;
}

// Fetch semesters
$semesters = [];
$sem_sql = "SELECT SemesterID, SemesterNumber FROM semesters ORDER BY SemesterNumber";
$sem_result = $conn->query($sem_sql);
while ($row = $sem_result->fetch_assoc()) {
  $semesters[] = $row;
}

// Fetch subjects (used later in JS for filtering)
$subjects = [];
$sub_sql = "SELECT SubjectID, SubjectName, DepartmentID, SemesterID FROM subjects ORDER BY SubjectName";
$sub_result = $conn->query($sub_sql);
while ($row = $sub_result->fetch_assoc()) {
  $subjects[] = $row;
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
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
  <script src="../assets/js/lucide.min.js"></script>
  <script src="../assets/js/manage_teacher.js" defer></script>
</head>

<body>
  <?php include 'sidebar_admin_dashboard.php'; ?>

  <!-- Navbar -->
  <?php include 'navbar_admin.php'; ?>


  <!-- Dashboard Content -->
  <div class="container-fluid dashboard-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <h2 class="m-0">Manage Teachers</h2>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
        <i data-lucide="user-plus" class="me-1"></i>Add Teacher
      </button>
    </div>

    <div class="mb-4">
      <input type="text" class="form-control form-control-md w-100 w-md-50 mx-auto" placeholder="Search by name..." id="teacherSearch" />
    </div>


    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle mb-0" id="teacherTable">
            <thead class="table-light text-center">
              <tr>
                <th>Full Name</th>
                <th>Contact</th>
                <th>Email</th>
                <th style="width: 150px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($teachers as $id => $teacher): ?>
                <tr>
                  <td><?= htmlspecialchars($teacher['FullName']) ?></td>
                  <td><?= htmlspecialchars($teacher['Contact']) ?></td>
                  <td><?= htmlspecialchars($teacher['Email']) ?></td>
                  <td class="text-center">
                    <div class="d-flex justify-content-center gap-2 flex-nowrap">
                      <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewModal<?= $id ?>">
                        <i data-lucide="eye" class="me-1"></i>View
                      </button>
                      <a href="update_teacher.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
                        <i data-lucide="edit" class="me-1"></i>Update
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- View Modals -->
    <?php foreach ($teachers as $id => $teacher): ?>
      <div class="modal fade" id="viewModal<?= $id ?>" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">

        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><?= htmlspecialchars($teacher['FullName']) ?> — Assigned Subjects</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <?php if (!empty($teacher['Subjects'])): ?>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered">
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
                            <div class="d-flex gap-2 flex-nowrap">
                              <a href="update_assignment.php?mapid=<?= $s['MapID'] ?>" class="btn btn-sm btn-warning">
                                <i data-lucide="edit-3" class="me-1"></i>Update
                              </a>
                              <a href="delete_assignment.php?mapid=<?= $s['MapID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                <i data-lucide="trash" class="me-1"></i>Delete
                              </a>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="text-muted">No subject assigned.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- Add Teacher Modal -->
    <div class="modal fade" id="addTeacherModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" method="POST" action="add_teacher_process.php">
          <div class="modal-header">
            <h5 class="modal-title">Add New Teacher</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input name="FullName" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input name="Email" type="email" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact</label>
                <input name="Contact" class="form-control">
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
                <input type="password" name="Password" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="ConfirmPassword" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Department</label>
                <select name="DepartmentID" id="departmentSelect" class="form-select" required>
                  <option value="">Select</option>
                  <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['DepartmentID'] ?>"><?= $d['DepartmentName'] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Semester</label>
                <select name="SemesterID" id="semesterSelect" class="form-select" required>
                  <option value="">Select</option>
                  <?php foreach ($semesters as $s): ?>
                    <option value="<?= $s['SemesterID'] ?>">Semester <?= $s['SemesterNumber'] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Subject</label>
                <select name="SubjectID" id="subjectSelect" class="form-select" required>
                  <option value="">Select Department & Semester First</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" type="submit">Save Teacher</button>
          </div>
        </form>
      </div>
    </div>


  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    lucide.createIcons();

    // Search filter logic
    document.getElementById("teacherSearch").addEventListener("input", function() {
      const searchValue = this.value.toLowerCase();
      const rows = document.querySelectorAll("#teacherTable tbody tr");

      rows.forEach(row => {
        const fullName = row.children[0].textContent.toLowerCase();
        row.style.display = fullName.includes(searchValue) ? "" : "none";
      });
    });

    document.getElementById("departmentSelect").addEventListener("change", filterSubjects);
    document.getElementById("semesterSelect").addEventListener("change", filterSubjects);

    function filterSubjects() {
      const dept = document.getElementById("departmentSelect").value;
      const sem = document.getElementById("semesterSelect").value;
      const subjectSelect = document.getElementById("subjectSelect");

      subjectSelect.innerHTML = '<option value="">Select</option>';

      subjectData.forEach((sub) => {
        if (sub.DepartmentID === dept && sub.SemesterID === sem) {
          const opt = document.createElement("option");
          opt.value = sub.SubjectID;
          opt.textContent = sub.SubjectName;
          subjectSelect.appendChild(opt);
        }
      });
    }
  </script>
  <script>
    const subjectData = <?= json_encode($subjects) ?>;
  </script>

</body>

</html>