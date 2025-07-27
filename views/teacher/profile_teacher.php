<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\teacher\profile_teacher.php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['LoginID']) || $_SESSION['Role'] !== 'teacher') {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../../config/db_config.php";
require_once "../../helpers/helpers.php";

$loginID = $_SESSION['LoginID'];
$teacherID = $_SESSION['UserID'];
$success = "";
$error = "";

// Fetch current teacher data
$stmt = $conn->prepare("
    SELECT t.TeacherID, t.FullName, t.Contact, t.Address, t.PhotoURL, l.Email 
    FROM teachers t 
    JOIN login_tbl l ON t.LoginID = l.LoginID 
    WHERE t.TeacherID = ?
");
$stmt->bind_param("i", $teacherID);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();
$stmt->close();

if (!$teacher) {
    header("Location: ../auth/login.php");
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
            $photoURL = $teacher['PhotoURL'];
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = "../../assets/uploads/profiles/";
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($fileExtension, $allowedExtensions)) {
                    $fileName = "teacher_" . $teacherID . "_" . time() . "." . $fileExtension;
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

            $updateStmt = $conn->prepare("UPDATE teachers SET FullName = ?, Contact = ?, Address = ?, PhotoURL = ? WHERE TeacherID = ?");
            $updateStmt->bind_param("ssssi", $fullName, $contact, $address, $photoURL, $teacherID);

            if ($updateStmt->execute()) {
                $success = "Profile updated successfully!";
                $teacher['FullName'] = $fullName;
                $teacher['Contact'] = $contact;
                $teacher['Address'] = $address;
                $teacher['PhotoURL'] = $photoURL;
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
                $checkResult = $checkStmt->get_result();

                if ($checkResult->num_rows > 0) {
                    $error = "Email address already exists.";
                } else {
                    $updateStmt = $conn->prepare("UPDATE login_tbl SET Email = ? WHERE LoginID = ?");
                    $updateStmt->bind_param("si", $newEmail, $loginID);

                    if ($updateStmt->execute()) {
                        $success = "Email updated successfully!";
                        $teacher['Email'] = $newEmail;
                    } else {
                        $error = "Failed to update email.";
                    }
                    $updateStmt->close();
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
                $verifyStmt = $conn->prepare("SELECT Password FROM login_tbl WHERE LoginID = ?");
                $verifyStmt->bind_param("i", $loginID);
                $verifyStmt->execute();
                $verifyResult = $verifyStmt->get_result();
                $currentHash = $verifyResult->fetch_assoc()['Password'];

                if (password_verify($currentPassword, $currentHash)) {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $conn->prepare("UPDATE login_tbl SET Password = ? WHERE LoginID = ?");
                    $updateStmt->bind_param("si", $newHash, $loginID);

                    if ($updateStmt->execute()) {
                        $success = "Password updated successfully!";
                    } else {
                        $error = "Failed to update password.";
                    }
                    $updateStmt->close();
                } else {
                    $error = "Current password is incorrect.";
                }
                $verifyStmt->close();
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile | Attendify+</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard_teacher.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_teacher.css">
    <link rel="stylesheet" href="../../assets/css/profile_teacher.css">

    <!-- JS Libraries -->
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/sidebar_teacher.js" defer></script>
    <script src="../../assets/js/navbar_teacher.js" defer></script>
</head>

<body>
    <!-- Include sidebar and navbar -->
    <?php include '../components/sidebar_teacher_dashboard.php'; ?>
    <?php include '../components/navbar_teacher.php'; ?>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container main-content">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 class="page-title">
                    <i data-lucide="user"></i>
                    My Profile
                </h2>
                <p class="text-muted mb-0">Manage your account information and settings</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="dashboard_teacher.php" class="btn btn-outline-primary">
                    <i data-lucide="arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i data-lucide="check-circle" class="me-2"></i>
                <strong>Success!</strong> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i data-lucide="alert-circle" class="me-2"></i>
                <strong>Error!</strong> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Profile Information -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i data-lucide="user"></i>
                            Profile Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="fullName" value="<?= htmlspecialchars($teacher['FullName']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" name="contact" value="<?= htmlspecialchars($teacher['Contact']) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="3"><?= htmlspecialchars($teacher['Address']) ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Profile Photo</label>
                                    <input type="file" class="form-control" name="photo" accept="image/*">
                                    <small class="text-muted">Accepted formats: JPG, JPEG, PNG, GIF</small>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i data-lucide="save"></i>
                                    Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Email Settings -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i data-lucide="mail"></i>
                            Email Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_email">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">New Email Address</label>
                                    <input type="email" class="form-control" name="newEmail" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm Email Address</label>
                                    <input type="email" class="form-control" name="confirmEmail" required>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i data-lucide="mail"></i>
                                    Update Email
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Password Settings -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i data-lucide="lock"></i>
                            Password Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_password">

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="currentPassword" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="newPassword" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirmPassword" required>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-outline-warning">
                                    <i data-lucide="lock"></i>
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Profile Summary -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i data-lucide="info"></i>
                            Profile Summary
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="profile-photo mb-3">
                            <?php if ($teacher['PhotoURL']): ?>
                                <img src="../../<?= htmlspecialchars($teacher['PhotoURL']) ?>" alt="Profile Photo" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto" style="width: 120px; height: 120px;">
                                    <i data-lucide="user" style="width: 48px; height: 48px; color: white;"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h5 class="mb-1"><?= htmlspecialchars($teacher['FullName']) ?></h5>
                        <p class="text-muted mb-3">Teacher</p>

                        <div class="profile-info text-start">
                            <div class="d-flex align-items-center mb-2">
                                <i data-lucide="mail" class="me-2" style="width: 16px; height: 16px;"></i>
                                <span><?= htmlspecialchars($teacher['Email']) ?></span>
                            </div>
                            <?php if ($teacher['Contact']): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i data-lucide="phone" class="me-2" style="width: 16px; height: 16px;"></i>
                                    <span><?= htmlspecialchars($teacher['Contact']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($teacher['Address']): ?>
                                <div class="d-flex align-items-start mb-2">
                                    <i data-lucide="map-pin" class="me-2 mt-1" style="width: 16px; height: 16px;"></i>
                                    <span><?= htmlspecialchars($teacher['Address']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>