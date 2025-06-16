<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: login.php");
    exit();
}
include '../config/db_config.php';

$successMsg = '';
$errorMsg = '';

// Handle Add Admin Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['FullName']);
    $email = trim($_POST['Email']);
    $contact = trim($_POST['Contact']);
    $address = trim($_POST['Address'] ?? '');
    $password = $_POST['Password'];
    $confirmPassword = $_POST['ConfirmPassword'];

    // Photo Upload Handling
    $photoURL = '';
    if (isset($_FILES['PhotoFile']) && $_FILES['PhotoFile']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/admins/';
        $ext = pathinfo($_FILES['PhotoFile']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('admin_', true) . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        // Create directory if not exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (move_uploaded_file($_FILES['PhotoFile']['tmp_name'], $targetPath)) {
            $photoURL = $targetPath;
        } else {
            $errorMsg = "Failed to upload photo.";
        }
    }

    if (!$errorMsg) {
        // Validation
        if ($password !== $confirmPassword) {
            $errorMsg = "Passwords do not match.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = "Invalid email format.";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT LoginID FROM login_tbl WHERE Email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errorMsg = "Email already exists.";
            } else {
                // Insert login
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmtLogin = $conn->prepare("INSERT INTO login_tbl (Email, Password, Role, Status, CreatedDate) VALUES (?, ?, 'admin', 'active', NOW())");
                $stmtLogin->bind_param("ss", $email, $hashedPassword);
                if ($stmtLogin->execute()) {
                    $loginID = $stmtLogin->insert_id;

                    // Insert admin
                    $stmtAdmin = $conn->prepare("INSERT INTO admins (FullName, Contact, Address, PhotoURL, LoginID) VALUES (?, ?, ?, ?, ?)");
                    $stmtAdmin->bind_param("ssssi", $fullName, $contact, $address, $photoURL, $loginID);
                    if ($stmtAdmin->execute()) {
                        header("Location: manage_admin.php?success=1");
                        exit();
                    } else {
                        $errorMsg = "Failed to add admin details.";
                    }
                } else {
                    $errorMsg = "Failed to create login credentials.";
                }
            }
            $stmt->close();
        }
    }
}

// Fetch admins
$admins = [];
$sql = "SELECT a.AdminID, a.FullName, a.Contact, a.Address, a.PhotoURL, l.Email
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
<h2 class="m-0"><i data-lucide="shield"></i> Manage Admins</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                <i data-lucide="user-plus" class="me-1"></i>Add Admin
            </button>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Admin added successfully.</div>
        <?php elseif ($errorMsg): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

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
                            <th>Address</th>
                            <th>Email</th>
                            <th>Photo</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?= htmlspecialchars($admin['FullName']) ?></td>
                                <td><?= htmlspecialchars($admin['Contact']) ?></td>
                                <td><?= htmlspecialchars($admin['Address']) ?></td>
                                <td><?= htmlspecialchars($admin['Email']) ?></td>
                                <td>
                                    <?php if (!empty($admin['PhotoURL']) && file_exists($admin['PhotoURL'])): ?>
                                        <img src="<?= $admin['PhotoURL'] ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;" alt="Admin Photo">
                                    <?php else: ?>
                                        <span class="text-muted">No photo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="update_admin.php?id=<?= $admin['AdminID'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i data-lucide="edit"></i> Update
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                            <label class="form-label">Address</label>
                            <input name="Address" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Upload Photo (optional)</label>
                            <input type="file" name="PhotoFile" accept="image/*" class="form-control">
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
        document.getElementById("adminSearch").addEventListener("input", function() {
            const search = this.value.toLowerCase();
            document.querySelectorAll("#adminTable tbody tr").forEach(row => {
                const name = row.children[0].textContent.toLowerCase();
                row.style.display = name.includes(search) ? "" : "none";
            });
        });
    </script>
</body>

</html>