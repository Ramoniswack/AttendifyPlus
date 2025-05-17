<?php
session_start();
ob_start(); // Prevents headers already sent error

require_once "../config/db_config.php";
require_once "../helpers/helpers.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = sanitize($_POST['email']);
    $password = sanitize($_POST['password']);

    $stmt = $conn->prepare("SELECT UserID, Username, Password, Role FROM login_tbl WHERE Email = ? AND Status = 'active'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($userID, $username, $dbPassword, $role);
        $stmt->fetch();

        if ($password === $dbPassword) { // Plain password match
            $_SESSION['UserID'] = $userID;
            $_SESSION['Username'] = $username;
            $_SESSION['Role'] = $role;

            $role = strtolower($role);

            if ($role === 'admin') {
                header("Location: dashboard_admin.php");
                exit;
            } elseif ($role === 'teacher') {
                header("Location: dashboard_teacher.php");
                exit;
            } elseif ($role === 'student') {
                header("Location: dashboard_student.php");
                exit;
            } else {
                $error = "Unknown user role.";
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
