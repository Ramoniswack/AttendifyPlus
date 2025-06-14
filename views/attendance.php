<?php
session_start();
require_once(__DIR__ . '/../config/db_config.php');

if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'teacher') {
    header("Location: /attendifyplus/views/login.php");
    exit();
}

$teacherID = $_SESSION['UserID'];

$deptQuery = $conn->prepare("SELECT d.DepartmentID, d.DepartmentName FROM teachers_tbl t JOIN departments_tbl d ON t.DepartmentID = d.DepartmentID WHERE t.LoginID = ?");
$deptQuery->bind_param("i", $teacherID);
$deptQuery->execute();
$deptResult = $deptQuery->get_result();
$teacherDept = $deptResult->fetch_assoc();

if (!$teacherDept) {
    die("Error: Teacher department not found.");
}

$batches = $conn->prepare("SELECT DISTINCT b.BatchID, b.BatchName FROM batches_tbl b JOIN subjects_tbl s ON b.BatchID = s.BatchID JOIN teacher_subject_tbl ts ON ts.SubjectID = s.SubjectID WHERE ts.TeacherID = ? AND b.DepartmentID = ?");
$batches->bind_param("ii", $teacherID, $teacherDept['DepartmentID']);
$batches->execute();
$batchResult = $batches->get_result();

$selectedBatchID = $_POST['batch'] ?? null;
$selectedSubjectID = $_POST['subject'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    $date = $_POST['date'];
    if (!$selectedSubjectID) {
        die("Error: Subject is required.");
    }
    foreach ($_POST['attendance'] as $studentID => $status) {
        $stmt = $conn->prepare("INSERT INTO attendance_tbl (StudentID, TeacherID, SubjectID, Date, Status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiss", $studentID, $teacherID, $selectedSubjectID, $date, $status);
        $stmt->execute();
    }
    echo "<script>alert('Attendance submitted successfully.'); window.location.href='attendance.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Teacher Attendance | Attendify+</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/dashboard_teacher.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />

    <!-- Lucide Icons -->
    <script src="../assets/js/lucide.min.js"></script>
    
</head>
<body>
    <?php include 'sidebar_teacher_dashboard.php'; ?>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <button class="btn text-white me-2" id="sidebarToggle" title="Toggle Sidebar">
                <i data-lucide="menu"></i>
            </button>
            <a class="navbar-brand" href="#">Attendify+ | Teacher</a>
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <span class="navbar-text text-white">Welcome, <?= htmlspecialchars($_SESSION['Username']) ?></span>
                <button class="btn btn-outline-light btn-sm" onclick="toggleTheme()" title="Toggle Theme">
                    <i data-lucide="moon"></i>Theme
                </button>
                <a href="../logout.php" class="btn btn-outline-light btn-sm" title="Logout">
                    <i data-lucide="log-out"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container dashboard-container pt-5">
        <h2><i data-lucide="clipboard-list"></i> Mark Attendance</h2>
        <p><strong>Department:</strong> <?= htmlspecialchars($teacherDept['DepartmentName']) ?></p>

        <form method="POST">
            <input type="hidden" name="department" value="<?= $teacherDept['DepartmentID'] ?>">

            <label for="batchSelect">Batch:</label>
            <select id="batchSelect" name="batch" required class="form-select mb-3" onchange="this.form.submit()">
                <option value="">Select Batch</option>
                <?php while ($batch = $batchResult->fetch_assoc()): ?>
                    <option value="<?= $batch['BatchID'] ?>" <?= $selectedBatchID == $batch['BatchID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($batch['BatchName']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <?php if ($selectedBatchID):
                $subjectQuery = $conn->prepare("SELECT s.SubjectID, s.SubjectName FROM teacher_subject_tbl ts JOIN subjects_tbl s ON ts.SubjectID = s.SubjectID WHERE ts.TeacherID = ? AND s.BatchID = ?");
                $subjectQuery->bind_param("ii", $teacherID, $selectedBatchID);
                $subjectQuery->execute();
                $subjects = $subjectQuery->get_result();

                if ($subjects->num_rows === 1) {
                    $subject = $subjects->fetch_assoc();
                    echo "<input type='hidden' name='subject' value='" . htmlspecialchars($subject['SubjectID']) . "'>";
                    echo "<p><strong>Subject:</strong> " . htmlspecialchars($subject['SubjectName']) . "</p>";
                    $selectedSubjectID = $subject['SubjectID'];
                } elseif ($subjects->num_rows > 1) {
                    echo "<label for='subjectSelect'>Subject:</label>";
                    echo "<select id='subjectSelect' name='subject' class='form-select mb-3' required onchange='this.form.submit()'>";
                    echo "<option value=''>Select Subject</option>";
                    while ($sub = $subjects->fetch_assoc()) {
                        $selected = $selectedSubjectID == $sub['SubjectID'] ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($sub['SubjectID']) . "' $selected>" . htmlspecialchars($sub['SubjectName']) . "</option>";
                    }
                    echo "</select>";
                } else {
                    echo "<p class='text-danger'>No subject assigned for this batch.</p>";
                }
            endif; ?>

            <?php if ($selectedBatchID && $selectedSubjectID): ?>
                <label for="dateInput">Date:</label>
                <input type="date" id="dateInput" name="date" value="<?= date('Y-m-d') ?>" required class="form-control mb-4" />

                <div class="attendance-wrapper">
                    <div class="attendance-table">
                        <table class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Roll No</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $students = $conn->prepare("SELECT * FROM students_tbl WHERE DepartmentID = ? AND BatchID = ? AND Status = 'active'");
                                $students->bind_param("ii", $teacherDept['DepartmentID'], $selectedBatchID);
                                $students->execute();
                                $res = $students->get_result();

                                while ($row = $res->fetch_assoc()) {
                                    $studentID = htmlspecialchars($row['LoginID']);
                                    $fullName = htmlspecialchars($row['FullName']);
                                    $rollNo = htmlspecialchars($row['RollNo']);
                                    echo "<tr>
                                        <td>$fullName</td>
                                        <td>$rollNo</td>
                                        <td>
                                          <div class='btn-group' role='group'>
                                            <input type='radio' class='btn-check' name='attendance[$studentID]' id='present_$studentID' value='present' required>
                                            <label class='btn btn-outline-success btn-sm' for='present_$studentID'>Present</label>

                                            <input type='radio' class='btn-check' name='attendance[$studentID]' id='absent_$studentID' value='absent'>
                                            <label class='btn btn-outline-danger btn-sm' for='absent_$studentID'>Absent</label>

                                            <input type='radio' class='btn-check' name='attendance[$studentID]' id='late_$studentID' value='late'>
                                            <label class='btn btn-outline-warning btn-sm' for='late_$studentID'>Late</label>
                                          </div>
                                        </td>
                                      </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-center mt-4">
                        <div class="qr-static-box mb-3"><i data-lucide="qr-code"></i> QR CODE</div>
                        <button class="btn btn-outline-primary" type="button">
                            <i data-lucide="scan-line"></i> Generate QR
                        </button>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button class="btn btn-success" type="submit">
                        <i data-lucide="check-circle"></i> Submit Attendance
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
 <script src="../assets/js/manage_student.js"></script>
   
    
</body>
</html>
