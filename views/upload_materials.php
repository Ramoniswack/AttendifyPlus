<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Upload Materials | Attendify+</title>

  <!-- CSS -->
  <link rel="stylesheet" href="../assets/css/dashboard_teacher.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

  <!-- JS Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="../assets/js/lucide.min.js"></script>
  <script src="../assets/js/dashboard_teacher.js" defer></script>
</head>

<body>
  <!-- Sidebar -->
  <?php include '../views/sidebar_teacher_dashboard.php'; ?>

  <!-- Navbar -->
  <?php include '../views/navbar_teacher.php'; ?>

  <!-- Main Content -->
  <div class="container-fluid dashboard-container">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
      <div>
        <h2><i data-lucide="upload-cloud"></i> Upload Slides</h2>
        <p class="text-muted mb-0">Upload and manage your teaching materials</p>
      </div>
    </div>

    <!-- Upload Form -->
    <div class="card p-4 mb-4">
      <form>
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label for="subject_id" class="form-label">Select Subject</label>
            <select name="subject_id" id="subject_id" class="form-select" required>
              <option value="">-- Select --</option>
              <option value="1">Mathematics</option>
              <option value="2">Physics</option>
              <option value="3">Computer Science</option>
            </select>
          </div>
          <div class="col-md-5">
            <label for="slide_file" class="form-label">Select File</label>
            <input type="file" name="slide_file" id="slide_file" class="form-control" required>
          </div>
          <div class="col-md-3">
            <button type="submit" class="btn btn-success w-100">
              <i data-lucide="upload"></i> Upload Slide
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Uploaded Slides Table -->
    <div class="card p-4">
      <h5><i data-lucide="file-text"></i> Uploaded Slides</h5>
      <div class="table-responsive mt-3">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>Slide Name</th>
              <th>Subject</th>
              <th>Open</th>
              <th>% Watched</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Week1_Intro.pdf</td>
              <td>Mathematics</td>
              <td>
                <a href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                  <i data-lucide="eye"></i> Open
                </a>
              </td>
              <td>65%</td>
            </tr>
            <tr>
              <td>Week2_Algebra.pptx</td>
              <td>Mathematics</td>
              <td>
                <a href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                  <i data-lucide="eye"></i> Open
                </a>
              </td>
              <td>78%</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Theme toggle
    const themeToggle = document.querySelector("#theme-toggle");
    const body = document.body;
    if (localStorage.getItem("theme") === "dark") {
      body.classList.add("dark-theme");
    }
    themeToggle?.addEventListener("click", () => {
      body.classList.toggle("dark-theme");
      localStorage.setItem("theme", body.classList.contains("dark-theme") ? "dark" : "light");
    });

    // Sidebar toggle
    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(btn => {
      btn.addEventListener("click", () => {
        const target = document.querySelector(btn.getAttribute("data-bs-target"));
        if (target) {
          target.classList.toggle("show");
        }
      });
    });
  </script>
</body>

</html>
