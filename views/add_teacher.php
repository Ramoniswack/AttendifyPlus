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
$subjects = mysqli_query($conn, "SELECT * FROM subjects_tbl");

function isValidFormattedName($fullname) {
    $name = trim($fullname);

    if (!preg_match('/^[A-Za-z. ]+$/', $name)) return false;
    if (preg_match('/[.]{2,}|[ ]{2,}/', $name)) return false;
    if (!preg_match('/^[A-Z]/', $name)) return false;

    $segments = preg_split('/[. ]/', $name);
    foreach ($segments as $seg) {
        if ($seg === '') continue;
        if (!preg_match('/^[A-Z][a-z]*$/', $seg)) {
            return false;
        }
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel'])) {
        header("Location: admin_dashboard_view.php");
        exit();
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department = $_POST['department'] ?? '';
    $assignedSubjects = $_POST['subjects'] ?? [];

    if (empty($username) || empty($email) || empty($password) || empty($fullname) || empty($phone) || empty($department)) {
        $error = "All fields are required.";
    } elseif (!isValidFormattedName($fullname)) {
        $error = "Full name must contain only letters, spaces, or dots, and each part must start with a capital letter.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match('/^(?=.*[0-9])(?=.*[!@#\$%\^&\*\-_])[A-Za-z0-9!@#\$%\^&\*\-_]{6,}$/', $password)) {
        $error = "Password must be at least 6 characters long and include at least one number and one special character.";
    } elseif (!preg_match('/^\d{10}$/', $phone)) {
        $error = "Phone number must be exactly 10 digits.";
    } elseif (!is_numeric($department)) {
        $error = "Invalid department selected.";
    } elseif (!is_array($assignedSubjects) || count($assignedSubjects) === 0) {
        $error = "At least one subject must be assigned.";
    } else {
        $check = $conn->prepare("SELECT * FROM login_tbl WHERE Username = ? OR Email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            $error = "Username or Email already exists.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO login_tbl (Username, Password, Email, Role) VALUES (?, ?, ?, 'teacher')");
            $stmt->bind_param("sss", $username, $hashedPassword, $email);

            if ($stmt->execute()) {
                $loginID = $conn->insert_id;
                $stmt2 = $conn->prepare("INSERT INTO teachers_tbl (LoginID, FullName, Phone, DepartmentID) VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("issi", $loginID, $fullname, $phone, $department);
                if ($stmt2->execute()) {
                    $teacherID = $conn->insert_id;
                    $stmt3 = $conn->prepare("INSERT INTO teacher_subject_tbl (TeacherID, SubjectID) VALUES (?, ?)");
                    foreach ($assignedSubjects as $subID) {
                        $stmt3->bind_param("ii", $teacherID, $subID);
                        $stmt3->execute();
                    }
                    $success = "Teacher added successfully.";
                } else {
                    $error = "Error inserting into teachers_tbl: " . $stmt2->error;
                }
            } else {
                $error = "Error creating teacher: " . $stmt->error;
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Teacher | Attendify+</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
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
            <h3 class="mb-4 text-center">Add Teacher</h3>
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
                    <label>Assign Subjects</label>
                    <select name="subjects[]" class="form-select select2" multiple required>
                        <?php while ($sub = mysqli_fetch_assoc($subjects)): ?>
                            <option value="<?= $sub['SubjectID'] ?>"><?= $sub['SubjectName'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">Add Teacher</button>
                    <button type="submit" name="cancel" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/login.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            $('.select2').select2({ placeholder: "Select subjects" });
        });

        const toggleBtn = document.getElementById("sidebarToggle");
        const sidebar = document.getElementById("sidebar");
        toggleBtn?.addEventListener("click", () => {
            sidebar?.classList.toggle("active");
            document.body.classList.toggle("sidebar-open");
        });
    </script>
</body>

</html>
