<?php
session_start();

if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Teacher Dashboard | Attendify+</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/theme.css">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f1f5f9;
      margin: 0;
      padding: 0;
      transition: background-color 0.3s, color 0.3s;
    }

    .dark-mode {
      background-color: #121212;
      color: #f1f1f1;
    }

    .dashboard-container {
      padding: 40px;
    }

    .card {
      border-radius: 15px;
      box-shadow: 0 0 10px rgba(0,0,0,0.05);
      transition: transform 0.2s ease, background-color 0.3s, color 0.3s;
      background-color: #ffffff;
    }

    .card:hover {
      transform: translateY(-5px);
    }

    .dark-mode .card {
      background-color: #1e1e1e;
      color: #e0e0e0;
    }
  </style>
</head>
<body>

<!-- navbar -->
   <nav class="navbar navbar-expand-lg navbar-dark bg-primary">

  <!-- <nav class="navbar navbar-expand-lg"> -->

    <div class="container-fluid">
      <a class="navbar-brand" href="#">Attendify+ | Teacher</a>
      <div class="d-flex align-items-center gap-2">
        <span class="navbar-text text-white">Welcome, <?php echo htmlspecialchars($_SESSION['Username']); ?></span>
        <button class="btn btn-outline-light btn-sm" onclick="toggleTheme()" title="Toggle Theme">Theme</button>
        <a href="../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
      </div>
    </div>
  </nav>

  <!--  -->
  <div class="container dashboard-container">
    <h2 class="mb-4">Dashboard Overview</h2>
    <div class="row g-4">

      <div class="col-md-4">
        <div class="card p-4 text-center">
          <h5>My Seminars</h5>
          <p>View and manage seminar entries and attendance.</p>
          <a href="seminar_list.php" class="btn btn-primary btn-sm">Go</a>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card p-4 text-center">
          <h5>Student Self-Study Logs</h5>
          <p>Review and evaluate student study logs.</p>
          <a href="study_logger.php" class="btn btn-primary btn-sm">Go</a>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card p-4 text-center">
          <h5>Scan Attendance</h5>
          <p>Start QR code scanning for seminar attendance.</p>
          <a href="scan.php" class="btn btn-primary btn-sm">Start</a>
        </div>
      </div>

    </div>
  </div>

  <!-- -->
  <script src="../assets/js/login.js"></script>
</body>
</html>
