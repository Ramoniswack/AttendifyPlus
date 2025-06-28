<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\login.php
session_start();
ob_start();

require_once "../../config/db_config.php";
require_once "../../helpers/helpers.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = sanitize($_POST['email']);
  $password = sanitize($_POST['password']);

  // Prepare statement to get user from login_tbl where active
  $stmt = $conn->prepare("SELECT LoginID, Email, Password, Role FROM login_tbl WHERE Email = ? AND Status = 'active'");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $loginID = $row['LoginID'];
    $emailDB = $row['Email'];
    $dbPassword = $row['Password'];
    $role = $row['Role'];

    // Check if password is hashed (starts with $2y$ for bcrypt) or plain text
    $passwordMatch = false;
    if (str_starts_with($dbPassword, '$2y$')) {
      // Hashed password - use password_verify
      $passwordMatch = password_verify($password, $dbPassword);
    } else {
      // Plain-text password - direct comparison
      $passwordMatch = ($password === $dbPassword);
    }

    if ($passwordMatch) {
      $_SESSION['LoginID'] = $loginID;
      $_SESSION['Role'] = strtolower($role);

      // Fetch user info from respective role table
      $query = "";
      switch (strtolower($role)) {
        case 'admin':
          $query = "SELECT AdminID, FullName FROM admins WHERE LoginID = ?";
          break;
        case 'teacher':
          $query = "SELECT TeacherID, FullName FROM teachers WHERE LoginID = ?";
          break;
        case 'student':
          $query = "SELECT StudentID, FullName FROM students WHERE LoginID = ?";
          break;
        default:
          $error = "Invalid user role.";
          break;
      }

      if (empty($error)) {
        $profileStmt = $conn->prepare($query);
        $profileStmt->bind_param("i", $loginID);
        $profileStmt->execute();
        $profileResult = $profileStmt->get_result();

        if ($profileResult->num_rows === 1) {
          $profileRow = $profileResult->fetch_assoc();

          // Set session variables based on role
          switch (strtolower($role)) {
            case 'admin':
              $_SESSION['UserID'] = $profileRow['AdminID'];
              break;
            case 'teacher':
              $_SESSION['UserID'] = $profileRow['TeacherID'];
              break;
            case 'student':
              $_SESSION['UserID'] = $profileRow['StudentID'];
              break;
          }

          $_SESSION['Username'] = $profileRow['FullName'];

          // Redirect based on role
          switch (strtolower($role)) {
            case 'admin':
              header("Location: ../admin/dashboard_admin.php");
              break;
            case 'teacher':
              header("Location: ../teacher/dashboard_teacher.php");
              break;
            case 'student':
              header("Location: ../student/dashboard_student.php");
              break;
          }
          $profileStmt->close();
          exit();
        } else {
          $error = "User profile not found in " . $role . " table.";
        }
        $profileStmt->close();
      }
    } else {
      $error = "Incorrect password.";
    }
  } else {
    $error = "Invalid email or inactive account.";
  }

  $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | Attendify+</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/login.css" />
</head>

<body>
  <button class="btn btn-outline-secondary theme-toggle" onclick="toggleTheme()">Theme</button>

  <div class="login-card text-center">
    <div class="mb-3">
      <img src="../../assets/img/logo-dark.png" alt="Attendify+ Logo" class="logo">
    </div>

    <h3 class="login-title">Login</h3>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email"
          value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required />
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required />
      </div>
      <div class="mb-3 form-check d-flex justify-content-between">
        <div>
          <input type="checkbox" class="form-check-input" id="showPassword" onchange="togglePassword()" />
          <label class="form-check-label" for="showPassword">Show Password</label>
        </div>
        <a href="#" class="text-decoration-none">Forgot Password?</a>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>

    <div class="login-footer text-center mt-3">
      &copy; 2025 Attendify+
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function togglePassword() {
      const pwd = document.getElementById('password');
      pwd.type = pwd.type === 'password' ? 'text' : 'password';
    }

    function toggleTheme() {
      document.body.classList.toggle('dark-theme');
      localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light');
    }

    // Load saved theme
    document.addEventListener('DOMContentLoaded', function() {
      const savedTheme = localStorage.getItem('theme');
      if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
      }
    });
  </script>
  <script src="../../assets/js/login.js"></script>
</body>

</html>