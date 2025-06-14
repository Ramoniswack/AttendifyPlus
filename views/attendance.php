<?php
session_start();
require_once(__DIR__ . '/../config/db_config.php');

// Check session
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    header("Location: /attendifyplus/views/login.php");
    exit();
}

$loginID = $_SESSION['LoginID'];

// Get teacher info
$teacherStmt = $conn->prepare("SELECT TeacherID, FullName FROM teachers WHERE LoginID = ?");
if (!$teacherStmt) {
    die("Prepare failed: " . $conn->error);
}
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
$teacherRow = $teacherRes->fetch_assoc();

if (!$teacherRow) {
    echo "<script>alert('Your login is not linked with a valid teacher account. Please contact admin.'); window.location.href='../logout.php';</script>";
    exit();
}

$teacherID = $teacherRow['TeacherID'];

// Get department via subjects assigned to teacher
$deptStmt = $conn->prepare("
    SELECT DISTINCT d.DepartmentID, d.DepartmentName
    FROM departments d
    JOIN subjects s ON s.DepartmentID = d.DepartmentID
    JOIN teacher_subject_map ts ON ts.SubjectID = s.SubjectID
    WHERE ts.TeacherID = ?
    LIMIT 1
");
if (!$deptStmt) {
    die("Prepare failed: " . $conn->error);
}
$deptStmt->bind_param("i", $teacherID);
$deptStmt->execute();
$deptResult = $deptStmt->get_result();
$teacherDept = $deptResult->fetch_assoc();

if (!$teacherDept) {
    die("No department found for this teacher.");
}

// Fetch distinct semesters where teacher is assigned
$semQuery = $conn->prepare("SELECT DISTINCT sem.SemesterID, sem.SemesterNumber
                            FROM semesters sem
                            JOIN subjects s ON s.SemesterID = sem.SemesterID
                            JOIN teacher_subject_map ts ON ts.SubjectID = s.SubjectID
                            WHERE ts.TeacherID = ?");
if (!$semQuery) {
    die("Prepare failed: " . $conn->error);
}
$semQuery->bind_param("i", $teacherID);
$semQuery->execute();
$semResult = $semQuery->get_result();

$selectedSemesterID = $_POST['semester'] ?? null;
$selectedSubjectID = $_POST['subject'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    $date = $_POST['date'];

    if (!$selectedSubjectID) {
        die("Subject is required.");
    }

    foreach ($_POST['attendance'] as $studentID => $status) {
        $stmt = $conn->prepare("INSERT INTO attendance (StudentID, TeacherID, SubjectID, Date, Status)
                                VALUES (?, ?, ?, ?, ?)");
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mark Attendance | Attendify+</title>
  <link rel="stylesheet" href="../assets/css/manage_teacher.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
  <script src="../assets/js/lucide.min.js"></script>
  <script src="../assets/js/manage_teacher.js" defer></script>
=======
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
    <?php include 'sidebar_admin_dashboard.php'; ?>
    <?php include 'navbar_admin.php'; ?>

<div class="container pt-5 mt-5">
    <h2>Mark Attendance</h2>
    <p><strong>Department:</strong> <?= htmlspecialchars($teacherDept['DepartmentName']) ?></p>

    <form method="POST">
        <input type="hidden" name="department" value="<?= $teacherDept['DepartmentID'] ?>">

        <label for="semesterSelect" class="form-label">Semester</label>
        <select name="semester" id="semesterSelect" class="form-select mb-3" onchange="this.form.submit()" required>
            <option value="">Select Semester</option>
            <?php while ($sem = $semResult->fetch_assoc()): ?>
                <option value="<?= $sem['SemesterID'] ?>" <?= $selectedSemesterID == $sem['SemesterID'] ? 'selected' : '' ?>>
                    Semester <?= $sem['SemesterNumber'] ?>
                </option>
            <?php endwhile; ?>
        </select>

        <?php if ($selectedSemesterID): ?>
            <?php
            $subjectQuery = $conn->prepare("SELECT s.SubjectID, s.SubjectName
                                            FROM subjects s
                                            JOIN teacher_subject_map ts ON s.SubjectID = ts.SubjectID
                                            WHERE ts.TeacherID = ? AND s.SemesterID = ?");
            if (!$subjectQuery) {
                die("Prepare failed: " . $conn->error);
            }
            $subjectQuery->bind_param("ii", $teacherID, $selectedSemesterID);
            $subjectQuery->execute();
            $subjectResult = $subjectQuery->get_result();
            ?>

            <label for="subjectSelect" class="form-label">Subject</label>
            <select name="subject" id="subjectSelect" class="form-select mb-3" onchange="this.form.submit()" required>
                <option value="">Select Subject</option>
                <?php while ($sub = $subjectResult->fetch_assoc()): ?>
                    <option value="<?= $sub['SubjectID'] ?>" <?= $selectedSubjectID == $sub['SubjectID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sub['SubjectName']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        <?php endif; ?>

        <?php if ($selectedSemesterID && $selectedSubjectID): ?>
            <label for="dateInput" class="form-label">Date:</label>
            <input type="date" id="dateInput" name="date" value="<?= date('Y-m-d') ?>" required class="form-control mb-3" />

            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Exam Roll</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $studentsQuery = $conn->prepare("SELECT StudentID, FullName, ExamRoll
                                                 FROM students
                                                 WHERE DepartmentID = ? AND SemesterID = ?");
                if (!$studentsQuery) {
                    die("Prepare failed: " . $conn->error);
                }
                $studentsQuery->bind_param("ii", $teacherDept['DepartmentID'], $selectedSemesterID);
                $studentsQuery->execute();
                $students = $studentsQuery->get_result();

                while ($row = $students->fetch_assoc()):
                    $sid = $row['StudentID'];
                ?>
                    <tr>
                        <td><?= htmlspecialchars($row['FullName']) ?></td>
                        <td><?= htmlspecialchars($row['ExamRoll']) ?></td>
                        <td>
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="attendance[<?= $sid ?>]" id="present_<?= $sid ?>" value="present" required>
                                <label class="btn btn-outline-success btn-sm" for="present_<?= $sid ?>">Present</label>

                                <input type="radio" class="btn-check" name="attendance[<?= $sid ?>]" id="absent_<?= $sid ?>" value="absent">
                                <label class="btn btn-outline-danger btn-sm" for="absent_<?= $sid ?>">Absent</label>

                                <input type="radio" class="btn-check" name="attendance[<?= $sid ?>]" id="late_<?= $sid ?>" value="late">
                                <label class="btn btn-outline-warning btn-sm" for="late_<?= $sid ?>">Late</label>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <div class="text-end">
                <button type="submit" class="btn btn-success">
                    <i data-lucide="check-circle"></i> Submit Attendance
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>lucide.createIcons();</script>
=======
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