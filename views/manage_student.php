<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: login.php");
    exit();
}
include '../config/db_config.php';

// Fetch departments
$departments = [];
$sql = "SELECT DepartmentID, DepartmentName FROM departments ORDER BY DepartmentName";
$res = $conn->query($sql);
while ($d = $res->fetch_assoc()) {
    $departments[] = $d;
}

// Fetch semesters
$semesters = [];
$sem_sql = "SELECT SemesterID, SemesterNumber FROM semesters ORDER BY SemesterNumber";
$sem_result = $conn->query($sem_sql);
while ($s = $sem_result->fetch_assoc()) {
    $semesters[] = $s;
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
    <script src="../assets/js/lucide.min.js"></script>
    <script src="../assets/js/manage_student.js" defer></script>
    <style>
        .btn-close-red {
            filter: invert(41%) sepia(93%) saturate(7470%) hue-rotate(346deg) brightness(96%) contrast(120%);
        }
    </style>
</head>

<body>
    <?php include 'sidebar_admin_dashboard.php'; ?>
    <?php include 'navbar_admin.php'; ?>

    <div class="container-fluid dashboard-container pt-4" id="manageStudentsContainer">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Manage Students</h3>

            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i data-lucide="user-plus"></i> Add Student
            </button>
        </div>

        <!-- Search Input -->
        <div class="row mb-2">
            <div class="col-md-6">
                <label class="form-label">Search Name</label>

                <input id="searchName" type="text" class="form-control form-control-lg" placeholder="Full or partial name" />
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3 g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Program (Department)</label>
            <select id="filterDepartment" class="form-select">
                <option value="">All</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= htmlspecialchars($d['DepartmentID']) ?>"><?= htmlspecialchars($d['DepartmentName']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Join Year</label>
            <input id="filterYear" type="number" class="form-control" placeholder="e.g. 2022">
        </div>
        <div class="col-md-3">
            <label class="form-label">Semester</label>
            <select id="filterSemester" class="form-select">
                <option value="">All</option>
                <?php foreach ($semesters as $s): ?>
                    <option value="<?= $s['SemesterID'] ?>">Semester <?= $s['SemesterNumber'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-light" id="studentsHeader" style="display:none;">
                <tr>
                    <th>Full Name</th>
                    <th>Department</th>
                    <th>Semester</th>
                    <th>Batch</th>
                    <th>Roll No</th>
                    <th>Join Year</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="studentsBody"></tbody>
        </table>
    </div>
    </div>

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <form id="addStudentForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Student</h5>
                    <button type="button" class="btn-close btn-close-red" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label>Full Name</label>
                        <input name="FullName" class="form-control" required>
                    </div>

                    <div class="mb-2">
                        <label>Department</label>
                        <select name="DepartmentID" class="form-select" required>
                            <option value="">Select</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['DepartmentID'] ?>"><?= $d['DepartmentName'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label>Semester</label>
                        <select name="SemesterID" class="form-select" required>
                            <option value="">Select</option>
                            <?php foreach ($semesters as $s): ?>
                                <option value="<?= $s['SemesterID'] ?>">Semester <?= $s['SemesterNumber'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label>Batch ID</label>
                        <input name="BatchID" type="number" class="form-control" required>
                    </div>

                    <div class="mb-2">
                        <label>Roll No</label>
                        <input name="RollNo" class="form-control" required>
                    </div>

                    <div class="mb-2">
                        <label>Join Year</label>
                        <input name="JoinYear" type="number" class="form-control" required>
                    </div>

                    <div class="mb-2">
                        <label>Status</label>
                        <select name="Status" class="form-select">
                            <option value="active">active</option>
                            <option value="inactive">inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save"></i> Save
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