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
    die("Error: Teacher department not found. Please ensure the logged-in user exists in teachers_tbl.");
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
        die("Error: Subject is required to submit attendance.");
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
    <meta charset="UTF-8">
    <title>Teacher Attendance | Attendify+</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/teacherDashboard.css">
    <style>
        .equal-height-card {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .qr-static-box {
            width: 130px;
            height: 130px;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #bbb;
            font-size: 14px;
            color: #666;
        }

        .btn-group .btn {
            margin-right: 5px;
        }

        .attendance-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 30px;
        }

        .attendance-table {
            flex: 1;
        }

        body {
            overflow-x: hidden;
        }
    </style>
</head>

<body>
    <?php include 'sidebar_teacher.php'; ?>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <button class="btn text-white me-2" id="sidebarToggle">☰</button>
            <a class="navbar-brand" href="#">Attendify+ | Teacher</a>
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <span class="navbar-text text-white">Welcome, <?php echo htmlspecialchars($_SESSION['Username']); ?></span>
                <button class="btn btn-outline-light btn-sm" onclick="toggleTheme()" title="Toggle Theme">Theme</button>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-container pt-5">
        <h2>Mark Attendance</h2>
        <p><strong>Department:</strong> <?= htmlspecialchars($teacherDept['DepartmentName']) ?></p>

        <form method="POST">
            <input type="hidden" name="department" value="<?= $teacherDept['DepartmentID'] ?>">

            <label>Batch:</label>
            <select name="batch" required class="form-select mb-3" onchange="this.form.submit()">
                <option value="">Select Batch</option>
                <?php while ($batch = $batchResult->fetch_assoc()): ?>
                    <option value="<?= $batch['BatchID'] ?>" <?= $selectedBatchID == $batch['BatchID'] ? 'selected' : '' ?>>
                        <?= $batch['BatchName'] ?>
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
                    echo "<input type='hidden' name='subject' value='{$subject['SubjectID']}'>";
                    echo "<p><strong>Subject:</strong> {$subject['SubjectName']}</p>";
                    $selectedSubjectID = $subject['SubjectID'];
                } elseif ($subjects->num_rows > 1) {
                    echo "<label>Subject:</label><select name='subject' class='form-select mb-3' required onchange='this.form.submit()'>";
                    echo "<option value=''>Select Subject</option>";
                    while ($sub = $subjects->fetch_assoc()) {
                        $selected = $selectedSubjectID == $sub['SubjectID'] ? 'selected' : '';
                        echo "<option value='{$sub['SubjectID']}' $selected>{$sub['SubjectName']}</option>";
                    }
                    echo "</select>";
                } else {
                    echo "<p class='text-danger'>No subject assigned for this batch.</p>";
                }
            endif; ?>

            <?php if ($selectedBatchID && $selectedSubjectID): ?>
                <label>Date:</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" required class="form-control mb-4">

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
                                    echo "<tr>
                    <td>{$row['FullName']}</td>
                    <td>{$row['RollNo']}</td>
                    <td>
                      <div class='btn-group' role='group'>
                        <input type='radio' class='btn-check' name='attendance[{$row['LoginID']}]' id='present_{$row['LoginID']}' value='present' required>
                        <label class='btn btn-outline-success btn-sm' for='present_{$row['LoginID']}'>Present</label>

                        <input type='radio' class='btn-check' name='attendance[{$row['LoginID']}]' id='absent_{$row['LoginID']}' value='absent'>
                        <label class='btn btn-outline-danger btn-sm' for='absent_{$row['LoginID']}'>Absent</label>

                        <input type='radio' class='btn-check' name='attendance[{$row['LoginID']}]' id='late_{$row['LoginID']}' value='late'>
                        <label class='btn btn-outline-warning btn-sm' for='late_{$row['LoginID']}'>Late</label>
                      </div>
                    </td>
                  </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-center">
                        <div class="qr-static-box">QR CODE</div>
                        <button class="btn btn-outline-primary mt-3" type="button">Generate QR</button>
                    </div>
                </div>

                <div class="mt-4">
                    <button class="btn btn-success" type="submit">Submit Attendance</button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <script src="../assets/js/login.js"></script>
    <script>
        const toggleBtn = document.getElementById("sidebarToggle");
        const sidebar = document.getElementById("sidebar");
        toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("active");
            document.body.classList.toggle("sidebar-open");
        });
    </script>
</body>

</html>