<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: login.php");
    exit();
}
include '../config/db_config.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept = $_POST['DepartmentID'];
    $sem = $_POST['SemesterID'];
    $code = $_POST['SubjectCode'];
    $name = $_POST['SubjectName'];
    $credit = $_POST['CreditHour'];
    $lecture = $_POST['LectureHour'];
    $isElective = isset($_POST['IsElective']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO subjects (SubjectName, SubjectCode, CreditHour, LectureHour, DepartmentID, SemesterID, IsElective) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiiiii", $name, $code, $credit, $lecture, $dept, $sem, $isElective);
    $stmt->execute();
    header("Location: manage_subject.php");
    exit();
}

// Fetch departments
$departments = [];
$dept_sql = "SELECT DepartmentID, DepartmentName FROM departments ORDER BY DepartmentName";
$dept_result = $conn->query($dept_sql);
while ($row = $dept_result->fetch_assoc()) {
    $departments[] = $row;
}

// Fetch subjects grouped by department
$subjectMap = [];
$sql = "SELECT s.*, d.DepartmentName, sem.SemesterNumber
        FROM subjects s
        JOIN departments d ON s.DepartmentID = d.DepartmentID
        JOIN semesters sem ON s.SemesterID = sem.SemesterID
        ORDER BY d.DepartmentName, sem.SemesterNumber, s.SubjectName";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $subjectMap[$row['DepartmentID']]['DepartmentName'] = $row['DepartmentName'];
    $subjectMap[$row['DepartmentID']]['Subjects'][] = $row;
}

// Fetch semesters
$semesters = [];
$sem_sql = "SELECT SemesterID, SemesterNumber FROM semesters ORDER BY SemesterNumber";
$sem_result = $conn->query($sem_sql);
while ($row = $sem_result->fetch_assoc()) {
    $semesters[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Subjects | Attendify+</title>
    <link rel="stylesheet" href="../assets/css/manage_teacher.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
    <style>
        .modal {
            z-index: 1055;
        }

        .modal-backdrop.show {
            z-index: 1050;
        }

        /* Fix modal overlap with navbar */
        .modal.show {
            padding-top: 80px !important;
            /* Adjust based on your navbar height */
        }

        /* Optional: add scroll if modal is tall */
        .modal-dialog {
            margin-top: 1rem;
        }
    </style>
    <script src="../assets/js/lucide.min.js"></script>
    <script src="../assets/js/dashboard_admin.js" defer></script>
</head>

<body>
    <?php include 'sidebar_admin_dashboard.php'; ?>

    <?php include 'navbar_admin.php'; ?>


    <div class="container-fluid dashboard-container pt-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <h2 class="m-0">Manage Subjects</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                <i data-lucide="plus"></i> Add Subject
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light text-center">
                    <tr>
                        <th>Department</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['DepartmentName']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#deptModal<?= $d['DepartmentID'] ?>">
                                    <i data-lucide="eye"></i> View Subjects
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php foreach ($subjectMap as $deptId => $data): ?>
        <div class="modal fade" id="deptModal<?= $deptId ?>" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Subjects - <?= htmlspecialchars($data['DepartmentName']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Semester</th>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Credit Hour</th>
                                        <th>Lecture Hour</th>
                                        <th>Elective</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['Subjects'] as $sub): ?>
                                        <tr>
                                            <td>Semester <?= htmlspecialchars($sub['SemesterNumber']) ?></td>
                                            <td><?= htmlspecialchars($sub['SubjectCode']) ?></td>
                                            <td><?= htmlspecialchars($sub['SubjectName']) ?></td>
                                            <td><?= htmlspecialchars($sub['CreditHour']) ?></td>
                                            <td><?= htmlspecialchars($sub['LectureHour']) ?></td>
                                            <td><?= $sub['IsElective'] ? 'Yes' : 'No' ?></td>
                                            <td>
                                                <a href="update_subject.php?id=<?= $sub['SubjectID'] ?>" class="btn btn-sm btn-outline-primary"><i data-lucide="edit"></i></a>
                                                <a href="delete_subject.php?id=<?= $sub['SubjectID'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this subject?')"><i data-lucide="trash"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Add Subject Modal -->
    <div class="modal fade" id="addSubjectModal" tabindex="-1"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <form class="modal-content" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Subject</h5>
                    <!-- Close button (only way to close besides Cancel) -->
                    <button type="button" class="btn-close btn-close-red" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <!-- Department Dropdown -->
                    <div class="mb-2">
                        <label class="form-label">Department</label>
                        <select name="DepartmentID" class="form-select" required>
                            <option value="">Select</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['DepartmentID'] ?>"><?= $d['DepartmentName'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Semester Dropdown -->
                    <div class="mb-2">
                        <label class="form-label">Semester</label>
                        <select name="SemesterID" class="form-select" required>
                            <option value="">Select</option>
                            <?php foreach ($semesters as $s): ?>
                                <option value="<?= $s['SemesterID'] ?>">Semester <?= $s['SemesterNumber'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Subject Code -->
                    <div class="mb-2">
                        <label class="form-label">Subject Code</label>
                        <input name="SubjectCode" class="form-control" required placeholder="e.g. CMP 221">
                    </div>

                    <!-- Subject Name -->
                    <div class="mb-2">
                        <label class="form-label">Subject Name</label>
                        <input name="SubjectName" class="form-control" required placeholder="e.g. Data Structures">
                    </div>

                    <!-- Credit Hour -->
                    <div class="mb-2">
                        <label class="form-label">Credit Hour</label>
                        <select name="CreditHour" class="form-select" required>
                            <option value="">Select</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="5">5</option>
                        </select>
                    </div>

                    <!-- Lecture Hour -->
                    <div class="mb-2">
                        <label class="form-label">Lecture Hour</label>
                        <input name="LectureHour" class="form-control" type="number" value="48" required>
                    </div>

                    <!-- Elective Checkbox -->
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="IsElective" id="IsElective">
                        <label class="form-check-label" for="IsElective">Elective Subject?</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">Save Subject</button>
                </div>
            </form>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>

</html>