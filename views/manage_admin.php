<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
  header("Location: login.php");
  exit();
}
include '../config/db_config.php';


=======
    header("Location: login.php");
    exit();
}
include '../config/db_config.php';

// Fetch all admins with login info
$admins = [];
$sql = "SELECT a.AdminID, a.FullName, a.Contact, l.Email
        FROM admins a
        JOIN login_tbl l ON a.LoginID = l.LoginID
        WHERE l.Status = 'active' AND l.Role = 'admin'";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $admins[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Teachers | Attendify+</title>
  <link rel="stylesheet" href="../assets/css/manage_teacher.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
  <script src="../assets/js/lucide.min.js"></script>
  <script src="../assets/js/manage_teacher.js" defer></script>
</head>

<body>
  <?php include 'sidebar_admin_dashboard.php'; ?>

  <!-- Navbar -->
  <?php include 'navbar_admin.php'; ?>
  <?php include 'navbar_admin.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage admin</title>
</head>
<body>
    

</body>
=======
    <meta charset="UTF-8" />
    <title>Manage Admins | Attendify+</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../assets/css/manage_teacher.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
    <script src="../assets/js/lucide.min.js"></script>
    <script src="../assets/js/manage_teacher.js" defer></script>
</head>

<body>
    <?php include 'sidebar_admin_dashboard.php'; ?>
    <?php include 'navbar_admin.php'; ?>

    <div class="container-fluid dashboard-container">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="m-0">Manage Admins</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                <i data-lucide="user-plus" class="me-1"></i>Add Admin
            </button>
        </div>

        <div class="mb-3">
            <input type="text" class="form-control" id="adminSearch" placeholder="Search by name...">
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-bordered table-hover text-center" id="adminTable">
                    <thead class="table-light">
                        <tr>
                            <th>Full Name</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?= htmlspecialchars($admin['FullName']) ?></td>
                                <td><?= htmlspecialchars($admin['Contact']) ?></td>
                                <td><?= htmlspecialchars($admin['Email']) ?></td>
                                <td>
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="update_admin.php?id=<?= $admin['AdminID'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i data-lucide="edit"></i> Update
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" method="POST" action="add_admin_process.php">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input name="FullName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="Email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact</label>
                            <input name="Contact" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="Password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="ConfirmPassword" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">Save Admin</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        lucide.createIcons();

        // Search logic
        document.getElementById("adminSearch").addEventListener("input", function() {
            const search = this.value.toLowerCase();
            const rows = document.querySelectorAll("#adminTable tbody tr");

            rows.forEach(row => {
                const name = row.children[0].textContent.toLowerCase();
                row.style.display = name.includes(search) ? "" : "none";
            });
        });
    </script>
</body>

</html>