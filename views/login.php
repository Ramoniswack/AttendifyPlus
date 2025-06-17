<?php
session_start();
ob_start();

require_once "../config/db_config.php";
require_once "../helpers/helpers.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = sanitize($_POST['email']);
    $password = sanitize($_POST['password']);

    // Prepare statement to get user from login_tbl where active
    $stmt = $conn->prepare("SELECT LoginID, Email, Password, Role FROM login_tbl WHERE Email = ? AND Status = 'active'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($loginID, $emailDB, $dbPassword, $role);
        $stmt->fetch();

        // For now plain-text password check (replace with password_verify for hashed passwords)
        if ($password === $dbPassword) {

            $_SESSION['LoginID'] = $loginID;
            $_SESSION['Role'] = strtolower($role);

            // Fetch user info from respective role table
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
                $profileStmt->bind_result($userID, $fullName);
                if ($profileStmt->fetch()) {
                    $_SESSION['UserID'] = $userID;
                    $_SESSION['Username'] = $fullName;

                    // Redirect based on role
                    switch (strtolower($role)) {
                        case 'admin':
                            header("Location: dashboard_admin.php");
                            break;
                        case 'teacher':
                            header("Location: dashboard_teacher.php");
                            break;
                        case 'student':
                            header("Location: dashboard_student.php");
                            break;
                    }
                    $profileStmt->close();
                    exit;
                } else {
                    $error = "User profile not found.";
                }
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
  <link rel="stylesheet" href="../assets/css/login.css" />
</head>

<body>
  <button class="btn btn-outline-secondary theme-toggle" onclick="toggleTheme()">Theme</button>

  <div class="login-card text-center">
    <div class="mb-3">
      <img src="../assets/img/logo-dark.png" alt="Attendify+ Logo" class="logo">
    </div>

    <h3 class="login-title">Login</h3>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="text" class="form-control" id="email" name="email" required />
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required />
      </div>
      <div class="mb-3 form-check d-flex justify-content-between">
        <div>
          <input type="checkbox" class="form-check-input" id="remember" onclick="togglePassword()" />
          <label class="form-check-label" for="remember">Show Password</label>
        </div>
        <a href="#">Forgot Password?</a>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>

    <div class="login-footer text-center mt-3">
      &copy; 2025 Attendify+
    </div>
  </div>

  <script>
    function togglePassword() {
      const pwd = document.getElementById('password');
      pwd.type = pwd.type === 'password' ? 'text' : 'password';
    }
  </script>
  <script src="../assets/js/login.js"></script>
</body>

</html>