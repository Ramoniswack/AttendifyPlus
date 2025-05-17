<?php
session_start();
require_once(__DIR__ . '/../config/db_config.php');

if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: /attendifyplus/views/login.php");
    exit();
}

$success = "";
$error = "";
$departments = mysqli_query($conn, "SELECT * FROM departments_tbl");
$batches = mysqli_query($conn, "SELECT * FROM batches_tbl");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel'])) {
        header("Location: admin_dashboard_view.php");
        exit();
    }

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $department = $_POST['department'];
    $batch = $_POST['batch'];
    $roll = $_POST['rollno'];
    $joinYear = $_POST['join_year'];

    if (!is_numeric($department) || !is_numeric($batch)) {
        $error = "Invalid department or batch selected.";
    } else {
        $check = $conn->prepare("SELECT * FROM login_tbl WHERE Username = ? OR Email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            $error = "Username or Email already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO login_tbl (Username, Password, Email, Role) VALUES (?, ?, ?, 'student')");
            $stmt->bind_param("sss", $username, $password, $email);

            if ($stmt->execute()) {
                $loginID = $conn->insert_id;
                $stmt2 = $conn->prepare("INSERT INTO students_tbl (LoginID, FullName, DepartmentID, BatchID, RollNo, JoinYear) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt2->bind_param("isiisi", $loginID, $fullname, $department, $batch, $roll, $joinYear);
                $stmt2->execute();
                $success = "Student added successfully.";
            } else {
                $error = "Error creating student: " . $stmt->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Student | Attendify+</title>
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

        .form-wrapper {
            max-width: 600px;
            margin: auto;
        }

        .dashboard-container {
            padding-top: 90px;
        }

        body {
            overflow-x: hidden;
        }
    </style>
</head>

<body class="bg-light">
    <?php include 'sidebar_admin.php'; ?>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <button class="btn text-white me-2" id="sidebarToggle">☰</button>
            <a class="navbar-brand" href="#">Attendify+ | Admin</a>
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <span class="navbar-text text-white">Welcome, <?php echo htmlspecialchars($_SESSION['Username']); ?></span>
                <button class="btn btn-outline-light btn-sm" onclick="toggleTheme()" title="Toggle Theme">Theme</button>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-container">
        <div class="form-wrapper p-4 shadow rounded">
            <h3 class="mb-4 text-center">Add Student</h3>
            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <form method="POST">
                <div class="mb-3"><label>Username</label><input type="text" name="username" class="form-control" required></div>
                <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-3"><label>Password</label><input type="text" name="password" class="form-control" required></div>
                <div class="mb-3"><label>Full Name</label><input type="text" name="fullname" class="form-control" required></div>
                <div class="mb-3"><label>Phone</label><input type="text" name="phone" class="form-control" required></div>
                <div class="mb-3">
                    <label>Department</label>
                    <select name="department" class="form-select" required>
                        <option value="">Select Department</option>
                        <?php mysqli_data_seek($departments, 0);
                        while ($dept = mysqli_fetch_assoc($departments)): ?>
                            <option value="<?= $dept['DepartmentID'] ?>"><?= $dept['DepartmentName'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Batch</label>
                    <select name="batch" class="form-select" required>
                        <option value="">Select Batch</option>
                        <?php mysqli_data_seek($batches, 0);
                        while ($batch = mysqli_fetch_assoc($batches)): ?>
                            <option value="<?= $batch['BatchID'] ?>"><?= $batch['BatchName'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3"><label>Roll Number</label><input type="text" name="rollno" class="form-control" required></div>
                <div class="mb-3"><label>Join Year</label><input type="number" name="join_year" class="form-control" required></div>
                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">Add Student</button>
                    <button type="submit" name="cancel" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>

    </div>

    <script src="../assets/js/login.js"></script>
    <script>
        const toggleBtn = document.getElementById("sidebarToggle");
        const sidebar = document.getElementById("sidebar");
        toggleBtn?.addEventListener("click", () => {
            sidebar?.classList.toggle("active");
            document.body.classList.toggle("sidebar-open");
        });
    </script>
</body>

</html>
