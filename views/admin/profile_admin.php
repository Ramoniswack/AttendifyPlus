<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\admin\profile_admin.php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['LoginID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once "../../config/db_config.php";
require_once "../../helpers/helpers.php";

$loginID = $_SESSION['LoginID'];
$adminID = $_SESSION['UserID'];
$success = "";
$error = "";

// Fetch current admin data
$stmt = $conn->prepare("
    SELECT a.AdminID, a.FullName, a.Contact, a.Address, a.PhotoURL, l.Email 
    FROM admins a 
    JOIN login_tbl l ON a.LoginID = l.LoginID 
    WHERE a.AdminID = ?
");
$stmt->bind_param("i", $adminID);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if (!$admin) {
    header("Location: ../login.php");
    exit();
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_profile':
            $fullName = sanitize($_POST['fullName']);
            $contact = sanitize($_POST['contact']);
            $address = sanitize($_POST['address']);

            // Handle photo upload
            $photoURL = $admin['PhotoURL'];
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = "../../assets/uploads/profiles/";
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($fileExtension, $allowedExtensions)) {
                    $fileName = "admin_" . $adminID . "_" . time() . "." . $fileExtension;
                    $filePath = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $filePath)) {
                        // Delete old photo if exists
                        if ($photoURL && file_exists("../../" . $photoURL)) {
                            unlink("../../" . $photoURL);
                        }
                        $photoURL = "assets/uploads/profiles/" . $fileName;
                    }
                }
            }

            $updateStmt = $conn->prepare("UPDATE admins SET FullName = ?, Contact = ?, Address = ?, PhotoURL = ? WHERE AdminID = ?");
            $updateStmt->bind_param("ssssi", $fullName, $contact, $address, $photoURL, $adminID);

            if ($updateStmt->execute()) {
                $success = "Profile updated successfully!";
                $admin['FullName'] = $fullName;
                $admin['Contact'] = $contact;
                $admin['Address'] = $address;
                $admin['PhotoURL'] = $photoURL;
                $_SESSION['Username'] = $fullName;
            } else {
                $error = "Failed to update profile.";
            }
            $updateStmt->close();
            break;

        case 'update_email':
            $newEmail = sanitize($_POST['newEmail']);
            $confirmEmail = sanitize($_POST['confirmEmail']);

            if ($newEmail !== $confirmEmail) {
                $error = "Email addresses do not match.";
            } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format.";
            } else {
                // Check if email already exists
                $checkStmt = $conn->prepare("SELECT LoginID FROM login_tbl WHERE Email = ? AND LoginID != ?");
                $checkStmt->bind_param("si", $newEmail, $loginID);
                $checkStmt->execute();

                if ($checkStmt->get_result()->num_rows > 0) {
                    $error = "Email address already exists.";
                } else {
                    $updateEmailStmt = $conn->prepare("UPDATE login_tbl SET Email = ? WHERE LoginID = ?");
                    $updateEmailStmt->bind_param("si", $newEmail, $loginID);

                    if ($updateEmailStmt->execute()) {
                        $success = "Email updated successfully!";
                        $admin['Email'] = $newEmail;
                    } else {
                        $error = "Failed to update email.";
                    }
                    $updateEmailStmt->close();
                }
                $checkStmt->close();
            }
            break;

        case 'update_password':
            $currentPassword = $_POST['currentPassword'];
            $newPassword = $_POST['newPassword'];
            $confirmPassword = $_POST['confirmPassword'];

            if ($newPassword !== $confirmPassword) {
                $error = "New passwords do not match.";
            } elseif (strlen($newPassword) < 6) {
                $error = "Password must be at least 6 characters long.";
            } else {
                // Verify current password
                $passStmt = $conn->prepare("SELECT Password FROM login_tbl WHERE LoginID = ?");
                $passStmt->bind_param("i", $loginID);
                $passStmt->execute();
                $passResult = $passStmt->get_result();
                $passRow = $passResult->fetch_assoc();
                $passStmt->close();

                $passwordMatch = false;
                if (str_starts_with($passRow['Password'], '$2y$')) {
                    $passwordMatch = password_verify($currentPassword, $passRow['Password']);
                } else {
                    $passwordMatch = ($currentPassword === $passRow['Password']);
                }

                if (!$passwordMatch) {
                    $error = "Current password is incorrect.";
                } else {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updatePassStmt = $conn->prepare("UPDATE login_tbl SET Password = ? WHERE LoginID = ?");
                    $updatePassStmt->bind_param("si", $hashedPassword, $loginID);

                    if ($updatePassStmt->execute()) {
                        $success = "Password updated successfully!";
                    } else {
                        $error = "Failed to update password.";
                    }
                    $updatePassStmt->close();
                }
            }
            break;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile | Attendify+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/profile_admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_admin.css">
    <link rel="stylesheet" href="../../assets/css/manage_admin.css">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard_admin.php">
                <i class="fas fa-arrow-left me-2"></i>
                Back to Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?= htmlspecialchars($admin['FullName']) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../login.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="profile-container dashboard-container">
        <div class="container-fluid">
            <div class="row">
                <!-- Profile Header -->
                <div class="col-12">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php if ($admin['PhotoURL'] && file_exists("../../" . $admin['PhotoURL'])): ?>
                                <img src="../../<?= htmlspecialchars($admin['PhotoURL']) ?>" alt="Profile Photo" id="profile-image">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <div class="avatar-overlay">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        <div class="profile-info">
                            <h1 class="profile-name"><?= htmlspecialchars($admin['FullName']) ?></h1>
                            <p class="profile-role"><i class="fas fa-shield-alt me-2"></i>System Administrator</p>
                            <p class="profile-email"><i class="fas fa-envelope me-2"></i><?= htmlspecialchars($admin['Email']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($success)): ?>
                    <div class="col-12">
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="col-12">
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Profile Forms -->
                <div class="col-xl-4 col-lg-6 mb-4">
                    <div class="profile-card">
                        <div class="card-header">
                            <h5><i class="fas fa-user-edit me-2"></i>Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="profile-form">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="form-group">
                                    <label for="photo" class="form-label">Profile Photo</label>
                                    <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                    <small class="form-text text-muted">JPG, JPEG, PNG, GIF (Max: 2MB)</small>
                                </div>

                                <div class="form-group">
                                    <label for="fullName" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="fullName" name="fullName"
                                        value="<?= htmlspecialchars($admin['FullName']) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="contact" class="form-label">Contact Number</label>
                                    <input type="tel" class="form-control" id="contact" name="contact"
                                        value="<?= htmlspecialchars($admin['Contact'] ?? '') ?>">
                                </div>

                                <div class="form-group">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($admin['Address'] ?? '') ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-6 mb-4">
                    <div class="profile-card">
                        <div class="card-header">
                            <h5><i class="fas fa-envelope me-2"></i>Email Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="email-form">
                                <input type="hidden" name="action" value="update_email">

                                <div class="form-group">
                                    <label class="form-label">Current Email</label>
                                    <input type="email" class="form-control" value="<?= htmlspecialchars($admin['Email']) ?>" readonly>
                                </div>

                                <div class="form-group">
                                    <label for="newEmail" class="form-label">New Email</label>
                                    <input type="email" class="form-control" id="newEmail" name="newEmail" required>
                                </div>

                                <div class="form-group">
                                    <label for="confirmEmail" class="form-label">Confirm New Email</label>
                                    <input type="email" class="form-control" id="confirmEmail" name="confirmEmail" required>
                                </div>

                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="fas fa-envelope me-2"></i>Update Email
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-6 mb-4">
                    <div class="profile-card">
                        <div class="card-header">
                            <h5><i class="fas fa-lock me-2"></i>Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="password-form">
                                <input type="hidden" name="action" value="update_password">

                                <div class="form-group">
                                    <label for="currentPassword" class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('currentPassword')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="newPassword" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="newPassword" name="newPassword"
                                            minlength="6" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('newPassword')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="form-text text-muted">Minimum 6 characters</small>
                                </div>

                                <div class="form-group">
                                    <label for="confirmPassword" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword"
                                            minlength="6" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmPassword')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="fas fa-key me-2"></i>Update Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');

            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Photo preview
        document.getElementById('photo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const profileImage = document.getElementById('profile-image');
                    if (profileImage) {
                        profileImage.src = e.target.result;
                    } else {
                        // Create image if placeholder exists
                        const placeholder = document.querySelector('.avatar-placeholder');
                        if (placeholder) {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = 'Profile Photo';
                            img.id = 'profile-image';
                            placeholder.parentNode.replaceChild(img, placeholder);
                        }
                    }
                };
                reader.readAsDataURL(file);
            }
        });

        // Form validation
        document.getElementById('email-form').addEventListener('submit', function(e) {
            const newEmail = document.getElementById('newEmail').value;
            const confirmEmail = document.getElementById('confirmEmail').value;

            if (newEmail !== confirmEmail) {
                e.preventDefault();
                alert('Email addresses do not match!');
            }
        });

        document.getElementById('password-form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return;
            }

            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return;
            }
        });

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 300);
                }
            });
        }, 5000);
    </script>
</body>

</html>