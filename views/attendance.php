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
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
$teacherRow = $teacherRes->fetch_assoc();

if (!$teacherRow) {
    header("Location: ../logout.php");
    exit();
}

$teacherID = $teacherRow['TeacherID'];

// Get department
$deptStmt = $conn->prepare("
    SELECT DISTINCT d.DepartmentID, d.DepartmentName
    FROM departments d
    JOIN subjects s ON s.DepartmentID = d.DepartmentID
    JOIN teacher_subject_map ts ON ts.SubjectID = s.SubjectID
    WHERE ts.TeacherID = ?
    LIMIT 1
");
$deptStmt->bind_param("i", $teacherID);
$deptStmt->execute();
$teacherDept = $deptStmt->get_result()->fetch_assoc();

if (!$teacherDept) {
    die("No department found for this teacher.");
}

// Get semesters
$semQuery = $conn->prepare("
    SELECT DISTINCT sem.SemesterID, sem.SemesterNumber
    FROM semesters sem
    JOIN subjects s ON s.SemesterID = sem.SemesterID
    JOIN teacher_subject_map ts ON ts.SubjectID = s.SubjectID
    WHERE ts.TeacherID = ?
");
$semQuery->bind_param("i", $teacherID);
$semQuery->execute();
$semResult = $semQuery->get_result();

$selectedSemesterID = $_POST['semester'] ?? null;
$selectedSubjectID = $_POST['subject'] ?? null;
$date = $_POST['date'] ?? date('Y-m-d');
$successMsg = isset($_GET['success']) ? "Attendance submitted successfully." : "";
$errorMsg = $_GET['error'] ?? "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    if (!$selectedSubjectID || !$selectedSemesterID || !$date) {
        header("Location: attendance.php?error=Missing required fields.");
        exit();
    }

    // Check if attendance already exists
    $checkStmt = $conn->prepare("SELECT 1 FROM attendance_records WHERE SubjectID = ? AND DateTime = ? AND TeacherID = ?");
    $checkStmt->bind_param("isi", $selectedSubjectID, $date, $teacherID);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        header("Location: attendance.php?error=Attendance already marked for this subject and date.");
        exit();
    }

    // Insert attendance
    foreach ($_POST['attendance'] as $studentID => $status) {
        $insertStmt = $conn->prepare("INSERT INTO attendance_records (StudentID, TeacherID, SubjectID, DateTime, Status) VALUES (?, ?, ?, ?, ?)");
        $insertStmt->bind_param("iiiss", $studentID, $teacherID, $selectedSubjectID, $date, $status);
        $insertStmt->execute();
    }

    header("Location: attendance.php?success=1");
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
</head>

<body>
    <?php include 'sidebar_teacher_dashboard.php'; ?>
    <?php include 'navbar_admin.php'; ?>

    <div class="container pt-5 mt-5">
        <h2>Mark Attendance</h2>

        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
        <?php elseif ($errorMsg): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <p><strong>Department:</strong> <?= htmlspecialchars($teacherDept['DepartmentName']) ?></p>

        <form method="POST">
            <input type="hidden" name="department" value="<?= $teacherDept['DepartmentID'] ?>">

            <!-- Semester Dropdown -->
            <label class="form-label">Semester</label>
            <select name="semester" class="form-select mb-3" onchange="this.form.submit()" required>
                <option value="">Select Semester</option>
                <?php while ($sem = $semResult->fetch_assoc()): ?>
                    <option value="<?= $sem['SemesterID'] ?>" <?= $selectedSemesterID == $sem['SemesterID'] ? 'selected' : '' ?>>
                        Semester <?= $sem['SemesterNumber'] ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Subject Dropdown -->
            <?php if ($selectedSemesterID): ?>
                <?php
                $subjectQuery = $conn->prepare("
                SELECT s.SubjectID, s.SubjectName
                FROM subjects s
                JOIN teacher_subject_map ts ON ts.SubjectID = s.SubjectID
                WHERE ts.TeacherID = ? AND s.SemesterID = ?
            ");
                $subjectQuery->bind_param("ii", $teacherID, $selectedSemesterID);
                $subjectQuery->execute();
                $subjectResult = $subjectQuery->get_result();
                ?>
                <label class="form-label">Subject</label>
                <select name="subject" class="form-select mb-3" onchange="this.form.submit()" required>
                    <option value="">Select Subject</option>
                    <?php while ($sub = $subjectResult->fetch_assoc()): ?>
                        <option value="<?= $sub['SubjectID'] ?>" <?= $selectedSubjectID == $sub['SubjectID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sub['SubjectName']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            <?php endif; ?>

            <!-- Attendance Table -->
            <?php if ($selectedSemesterID && $selectedSubjectID): ?>
                <?php
                // Check if attendance already exists
                $checkStmt = $conn->prepare("SELECT 1 FROM attendance_records WHERE SubjectID = ? AND DateTime = ? AND TeacherID = ?");
                $checkStmt->bind_param("isi", $selectedSubjectID, $date, $teacherID);
                $checkStmt->execute();
                $checkStmt->store_result();
                $attendanceExists = $checkStmt->num_rows > 0;
                ?>

                <label for="dateInput" class="form-label">Date:</label>
                <input type="date" id="dateInput" name="date" value="<?= $date ?>" required class="form-control mb-3" />

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $studentsQuery = $conn->prepare("SELECT StudentID, FullName FROM students WHERE DepartmentID = ? AND SemesterID = ?");
                        $studentsQuery->bind_param("ii", $teacherDept['DepartmentID'], $selectedSemesterID);
                        $studentsQuery->execute();
                        $students = $studentsQuery->get_result();

                        while ($row = $students->fetch_assoc()):
                            $sid = $row['StudentID'];
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['FullName']) ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <input type="radio" class="btn-check" name="attendance[<?= $sid ?>]" id="present_<?= $sid ?>" value="present" <?= $attendanceExists ? 'disabled' : '' ?> required>
                                        <label class="btn btn-outline-success btn-sm" for="present_<?= $sid ?>">Present</label>

                                        <input type="radio" class="btn-check" name="attendance[<?= $sid ?>]" id="absent_<?= $sid ?>" value="absent" <?= $attendanceExists ? 'disabled' : '' ?>>
                                        <label class="btn btn-outline-danger btn-sm" for="absent_<?= $sid ?>">Absent</label>

                                        <input type="radio" class="btn-check" name="attendance[<?= $sid ?>]" id="late_<?= $sid ?>" value="late" <?= $attendanceExists ? 'disabled' : '' ?>>
                                        <label class="btn btn-outline-warning btn-sm" for="late_<?= $sid ?>">Late</label>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <?php if (!$attendanceExists): ?>
                    <div class="text-end">
                        <button type="submit" class="btn btn-success">
                            <i data-lucide="check-circle"></i> Submit Attendance
                        </button>
                    </div>
                <?php else: ?>
                    <div class="text-muted text-center">Attendance already submitted for this subject and date.</div>
                <?php endif; ?>
            <?php endif; ?>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>

</html>