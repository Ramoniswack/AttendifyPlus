<?php


session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../../config/db_config.php';

$successMsg = '';
$errorMsg = '';
$errors = [];             //declare array

// Handle form submission for adding admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admin') {
    // Collect form data
    $FullName = trim($_POST['FullName'] ?? '');
    $Email = trim($_POST['Email'] ?? '');
    $Contact = trim($_POST['Contact'] ?? '');
    $Password = $_POST['Password'] ?? '';
    $ConfirmPassword = $_POST['ConfirmPassword'] ?? '';
    $Address = trim($_POST['Address'] ?? '');
    $PhotoURL = '';

    function isValidFormattedName($Fullname) {
        $Fullname = trim($Fullname);
        if (!preg_match('/^[A-Za-z. ]+$/', $Fullname)) return false;
        if (preg_match('/[.]{2,}|[ ]{2,}/', $Fullname)) return false;
        if (!preg_match('/^[A-Z]/', $Fullname)) return false;

        $words = explode(' ', $Fullname);
        foreach ($words as $word) {
        if ($word === '') continue;
        $parts = explode('.', $word);
        foreach ($parts as $part) {
            if ($part === '') continue;
            if (!preg_match('/^[A-Z][a-z]*$/', $part)) return false;
        }
        }
        return true;
    }

    function validateEmail($Email) {
        $Email = trim($Email);
        if (!preg_match('/^[a-zA-Z0-9._%+-]+@lagrandee\.com$/', $Email)) return false;

        return true;
    }

    // VALIDATION
   if (empty($FullName)) {
        $errors['FullName'] = "Full name is required.";
    } elseif (!isValidFormattedName($FullName)) {
        $errors['FullName'] = "Only letters, spaces, and dots allowed. Each part must start with a capital letter.";
    }

    if (empty($Email)) {
        $errors['Email'] = "Email is required.";
    } elseif (!validateEmail($Email)) {
        $errors['Email'] = "Invalid email format. Example: example1@lagrandee.com";
    }

    if (empty($Contact)) {
        $errors['Contact'] = "Contact number is required.";
    } elseif (!preg_match('/^\d{10}$/', $Contact)) {
        $errors['Contact'] = "Contact number must be exactly 10 digits.";
    }

    if (empty($Address)) {
        $errors['Address'] = "Address is required.";
    }

    if (empty($Password)) {
        $errors['Password'] = "Password is required.";
    } elseif (!preg_match('/^(?=.*[0-9])(?=.*[!@#\$%\^&\*\-_])[A-Za-z0-9!@#\$%\^&\*\-_]{6,}$/', $Password)) {
        $errors['Password'] = "Password must be at least 6 characters long, with a number and a special character.";
    }

    if (empty($ConfirmPassword)) {
        $errors['ConfirmPassword'] = "Please confirm your password.";
    } elseif ($Password !== $ConfirmPassword) {
        $errors['ConfirmPassword'] = "Passwords do not match.";
    }

    // Photo upload
    if (isset($_FILES['PhotoFile']) && $_FILES['PhotoFile']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/admins/';
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = $_FILES['PhotoFile']['type'];

        if (!in_array($fileType, $allowedTypes)) {
            $errorMsg = "Only JPEG, PNG, and GIF images are allowed.";
        } elseif ($_FILES['PhotoFile']['size'] > 5 * 1024 * 1024) {
            $errorMsg = "Image size must be less than 5MB.";
        } else {
            $ext = pathinfo($_FILES['PhotoFile']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('admin_', true) . '.' . $ext;
            $targetPath = $uploadDir . $filename;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (move_uploaded_file($_FILES['PhotoFile']['tmp_name'], $targetPath)) {
                $PhotoURL = $targetPath;
            } else {
                $errorMsg = "Failed to upload photo.";
            }
        }
    }

    // Only proceed if no error so far
    if ((empty($errors)) && (empty($errorMsg))) {
        // Check if email already exists
        $emailCheck = $conn->prepare("SELECT LoginID FROM login_tbl WHERE Email = ?");
        $emailCheck->bind_param("s", $Email);
        $emailCheck->execute();
        $emailCheck->store_result();

        if ($emailCheck->num_rows > 0) {
            $errorMsg = "This email is already registered.";
        } else {
            // Begin transaction
            $conn->begin_transaction();

            try {
                // Insert login
                $stmt1 = $conn->prepare("INSERT INTO login_tbl (Email, Password, Role, Status, CreatedDate) VALUES (?, ?, 'admin', 'active', NOW())");
                $hashedPass = password_hash($Password, PASSWORD_BCRYPT);
                $stmt1->bind_param("ss", $Email, $hashedPass);
                $stmt1->execute();
                $loginID = $conn->insert_id;

                // Insert admin
                $stmt2 = $conn->prepare("INSERT INTO admins (LoginID, FullName, Contact, Address, PhotoURL) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("issss", $loginID, $FullName, $Contact, $Address, $PhotoURL);
                $stmt2->execute();

                $conn->commit();
                $_SESSION['success_message'] = "Admin added successfully.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $errorMsg = "Error adding admin: " . $e->getMessage();
            }

            if (isset($stmt1)) $stmt1->close();
            if (isset($stmt2)) $stmt2->close();
        }
        $emailCheck->close();
    }
}

// Handle admin status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $adminID = $_POST['admin_id'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';

    if (!empty($adminID) && !empty($newStatus)) {
        // Update status in login_tbl
        $updateStmt = $conn->prepare("UPDATE login_tbl l 
                                     JOIN admins a ON l.LoginID = a.LoginID 
                                     SET l.Status = ? 
                                     WHERE a.AdminID = ?");
        $updateStmt->bind_param("si", $newStatus, $adminID);

        if ($updateStmt->execute()) {
            $_SESSION['success_message'] = "Admin status updated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to update admin status.";
        }
        $updateStmt->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Check for messages from session (after redirect)
if (isset($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $errorMsg = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Fetch admins with enhanced data
$admins = [];
$sql = "SELECT a.AdminID, a.FullName, a.Contact, a.Address, l.Email, l.CreatedDate, l.Status, a.PhotoURL
        FROM admins a
        JOIN login_tbl l ON a.LoginID = l.LoginID
        WHERE l.Role = 'admin'
        ORDER BY l.Status ASC, a.FullName";
$res = $conn->query($sql);

while ($row = $res->fetch_assoc()) {
    $admins[] = $row;
}

// Get statistics
$stats = [];
$statsQueries = [
    'total_admins' => "SELECT COUNT(*) as count FROM admins a JOIN login_tbl l ON a.LoginID = l.LoginID WHERE l.Status = 'active' AND l.Role = 'admin'",
    'active_admins' => "SELECT COUNT(*) as count FROM admins a JOIN login_tbl l ON a.LoginID = l.LoginID WHERE l.Status = 'active' AND l.Role = 'admin'",
    'inactive_admins' => "SELECT COUNT(*) as count FROM admins a JOIN login_tbl l ON a.LoginID = l.LoginID WHERE l.Status = 'inactive' AND l.Role = 'admin'"
];

foreach ($statsQueries as $key => $query) {
    $result = $conn->query($query);
    $stats[$key] = $result->fetch_assoc()['count'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Admins | Attendify+</title>
    <link rel="stylesheet" href="../../assets/css/manage_admin.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/manage_teacher.js" defer></script>
</head>

<body>
    <!-- Include sidebar and navbar -->
    <?php include '../components/sidebar_admin_dashboard.php'; ?>
    <?php include '../components/navbar_admin.php'; ?>
    
    <!-- Main content -->
    <div class="container-fluid dashboard-container">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 class="page-title">
                    <i data-lucide="shield"></i>
                    Admin Management
                </h2>
                <p class="text-muted mb-0">Manage administrator accounts and system access</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                    <i data-lucide="user-plus"></i> Add Administrator
                </button>
                <a href="manage_teacher.php" class="btn btn-outline-primary">
                    <i data-lucide="user-check"></i> Teacher Management
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= $stats['total_admins'] ?></div>
                            <div class="stat-label">Total Administrators</div>
                            <div class="stat-change">
                                <i data-lucide="shield"></i>
                                <span>System managers</span>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="shield"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card teachers text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= $stats['active_admins'] ?></div>
                            <div class="stat-label">Active Administrators</div>
                            <div class="stat-change">
                                <i data-lucide="user-check"></i>
                                <span>Currently active</span>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="user-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card admins text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= $stats['inactive_admins'] ?></div>
                            <div class="stat-label">Inactive Administrators</div>
                            <div class="stat-change">
                                <i data-lucide="user-x"></i>
                                <span>Suspended accounts</span>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="user-x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($successMsg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i data-lucide="check-circle" class="me-2"></i>
                <?= htmlspecialchars($successMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i data-lucide="alert-circle" class="me-2"></i>
                <?= htmlspecialchars($errorMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Search and Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <h6 class="card-title">
                    <i data-lucide="filter"></i>
                    Search & Filter Administrators
                </h6>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">
                            <i data-lucide="search"></i>
                            Search Administrators
                        </label>
                        <input id="adminSearch" type="text" class="form-control" placeholder="Search by name, email, or contact..." />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">
                            <i data-lucide="layers"></i>
                            Account Status
                        </label>
                        <select id="filterStatus" class="form-select">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button id="clearFilters" class="btn btn-outline-secondary d-block w-100">
                            <i data-lucide="x"></i>
                            Clear Filters
                        </button>
                    </div>
                </div>
                <div class="mt-3">
                    <small id="resultsCount" class="text-muted"></small>
                </div>
            </div>
        </div>
        
        <!-- Administrators Table -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i data-lucide="users"></i>
                    Administrator Directory
                </h6>
            </div>
            <div class="table-responsive">
                <table id="adminsTable" class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Profile</th>
                            <th>Administrator</th>
                            <th>Contact Information</th>
                            <th>Address</th>
                            <th>Date Joined</th>
                            <th>Account Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="adminsTableBody">
                        <?php foreach ($admins as $admin): ?>
                            <tr class="admin-row"
                                data-name="<?= strtolower(htmlspecialchars($admin['FullName'])) ?>"
                                data-email="<?= strtolower(htmlspecialchars($admin['Email'])) ?>"
                                data-contact="<?= htmlspecialchars($admin['Contact']) ?>"
                                data-status="<?= htmlspecialchars($admin['Status']) ?>">
                                <td>
                                    <?php if (!empty($admin['PhotoURL']) && file_exists($admin['PhotoURL'])): ?>
                                        <img src="<?= htmlspecialchars($admin['PhotoURL']) ?>"
                                            alt="<?= htmlspecialchars($admin['FullName']) ?>"
                                            class="admin-photo">
                                    <?php else: ?>
                                        <div class="admin-placeholder">
                                            <i data-lucide="shield"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($admin['FullName']) ?></div>
                                        <small class="text-muted">ID: <?= $admin['AdminID'] ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div class="mb-1">
                                            <i data-lucide="mail" class="me-1 text-muted" style="width: 14px; height: 14px;"></i>
                                            <small><?= htmlspecialchars($admin['Email']) ?></small>
                                        </div>
                                        <?php if (!empty($admin['Contact'])): ?>
                                            <div>
                                                <i data-lucide="phone" class="me-1 text-muted" style="width: 14px; height: 14px;"></i>
                                                <small><?= htmlspecialchars($admin['Contact']) ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($admin['Address'] ?: 'Not provided') ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('M j, Y', strtotime($admin['CreatedDate'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge <?= $admin['Status'] === 'active' ? 'bg-success' : 'bg-danger' ?> status-badge">
                                        <?= ucfirst($admin['Status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-info"
                                            data-bs-toggle="modal"
                                            data-bs-target="#viewAdminModal<?= $admin['AdminID'] ?>"
                                            title="View Details">
                                            <i data-lucide="eye"></i>
                                        </button>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="Change Status">
                                                <i data-lucide="settings"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="admin_id" value="<?= $admin['AdminID'] ?>">
                                                        <input type="hidden" name="new_status" value="<?= $admin['Status'] === 'active' ? 'inactive' : 'active' ?>">
                                                        <button type="submit" class="dropdown-item" onclick="return confirm('Are you sure you want to change the status?')">
                                                            <i data-lucide="<?= $admin['Status'] === 'active' ? 'user-x' : 'user-check' ?>"></i>
                                                            <?= $admin['Status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- View Admin Modals -->
        <?php foreach ($admins as $admin): ?>
            <div class="modal fade" id="viewAdminModal<?= $admin['AdminID'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i data-lucide="shield"></i>
                                Administrator Profile - <?= htmlspecialchars($admin['FullName']) ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <?php if (!empty($admin['PhotoURL']) && file_exists($admin['PhotoURL'])): ?>
                                        <img src="<?= htmlspecialchars($admin['PhotoURL']) ?>"
                                            alt="<?= htmlspecialchars($admin['FullName']) ?>"
                                            class="admin-photo-large mb-3">
                                    <?php else: ?>
                                        <div class="admin-placeholder-large mb-3">
                                            <i data-lucide="shield"></i>
                                        </div>
                                    <?php endif; ?>
                                    <h5><?= htmlspecialchars($admin['FullName']) ?></h5>
                                    <span class="badge <?= $admin['Status'] === 'active' ? 'bg-success' : 'bg-danger' ?> mb-2">
                                        <?= ucfirst($admin['Status']) ?>
                                    </span>
                                </div>
                                <div class="col-md-8">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="40%">Admin ID:</th>
                                            <td><?= $admin['AdminID'] ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email:</th>
                                            <td><?= htmlspecialchars($admin['Email']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Contact:</th>
                                            <td><?= htmlspecialchars($admin['Contact'] ?: 'Not provided') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Address:</th>
                                            <td><?= htmlspecialchars($admin['Address'] ?: 'Not provided') ?></td>
                                        </tr>
                                        <tr>
                                            <th>Joined Date:</th>
                                            <td><?= date('F j, Y', strtotime($admin['CreatedDate'])) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Account Status:</th>
                                            <td>
                                                <span class="badge <?= $admin['Status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= ucfirst($admin['Status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i data-lucide="x" class="me-1"></i>
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Add Admin Modal -->
        <div class="modal fade" id="addAdminModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <form class="modal-content" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_admin">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i data-lucide="user-plus"></i>
                            Add New Administrator
                        </h5>
                        <button type="button" class="btn-close btn-close-red" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Personal Information -->
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2"><i data-lucide="user"></i> Personal Information</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Full Name <span class="required-field">*</span>
                                </label>
                                <input name="FullName" type="text" class="form-control" required placeholder="Enter full name"
                                    value="<?php echo htmlspecialchars($_POST['FullName'] ?? ''); ?>" />
                                 <span class="error text-danger"><?php echo $errors['FullName'] ?? ''; ?></span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Email Address <span class="required-field">*</span>
                                </label>
                                <input name="Email" type="email" class="form-control" required placeholder="admin@example.com" 
                                    value="<?php echo htmlspecialchars($_POST['Email'] ?? ''); ?>" />
                                 <span class="error text-danger"><?php echo $errors['Email'] ?? ''; ?></span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input name="Contact" type="tel" class="form-control" placeholder="98xxxxxxxx"
                                 value="<?php echo htmlspecialchars($_POST['Contact'] ?? ''); ?>" />
                                <span class="error text-danger"><?php echo $errors['Contact'] ?? ''; ?></span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Profile Photo
                                    <small class="text-muted">(Optional, max 5MB)</small>
                                </label>
                                <input name="PhotoFile" type="file" class="form-control" accept="image/*" />
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="Address" class="form-control" rows="2" placeholder="Enter full address"><?php echo htmlspecialchars($_POST['Address'] ?? ''); ?></textarea>
                                <span class="error text-danger"><?php echo $errors['Address'] ?? ''; ?></span>
                            </div>

                            <!-- Account Information -->
                            <div class="col-12 mt-4">
                                <h6 class="text-primary border-bottom pb-2"><i data-lucide="lock"></i> Account Information</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Password <span class="required-field">*</span>
                                </label>
                                <input name="Password" type="password" class="form-control" required minlength="6" placeholder="Minimum 6 characters" 
                                value="<?php echo htmlspecialchars($_POST['Password'] ?? ''); ?>" />
                                 <span class="error text-danger"><?php echo $errors['Password'] ?? ''; ?></span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Confirm Password <span class="required-field">*</span>
                                </label>
                                <input name="ConfirmPassword" type="password" class="form-control" required placeholder="Re-enter password" 
                                value="<?php echo htmlspecialchars($_POST['ConfirmPassword'] ?? ''); ?>" />
                                <span class="error text-danger"><?php echo $errors['ConfirmPassword'] ?? ''; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i data-lucide="x" class="me-1"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save"></i>
                            Create Administrator
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Search and filter functionality
        const searchInput = document.getElementById('adminSearch');
        const statusFilter = document.getElementById('filterStatus');
        const clearFiltersBtn = document.getElementById('clearFilters');
        const resultsCount = document.getElementById('resultsCount');
        const adminRows = document.querySelectorAll('.admin-row');

        function updateResultsCount() {
            const visibleRows = document.querySelectorAll('.admin-row:not([style*="display: none"])').length;
            const totalRows = adminRows.length;
            resultsCount.textContent = `Showing ${visibleRows} of ${totalRows} admins`;
        }

        function filterAdmins() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusFilterValue = statusFilter.value.toLowerCase();

            adminRows.forEach(row => {
                const name = row.dataset.name || '';
                const email = row.dataset.email || '';
                const contact = row.dataset.contact || '';
                const status = row.dataset.status || '';

                let showRow = true;

                // Search filter
                if (searchTerm) {
                    const searchMatch = name.includes(searchTerm) ||
                        email.includes(searchTerm) ||
                        contact.includes(searchTerm);
                    if (!searchMatch) showRow = false;
                }

                // Status filter
                if (statusFilterValue && status !== statusFilterValue) {
                    showRow = false;
                }

                row.style.display = showRow ? '' : 'none';
            });

            updateResultsCount();
        }

        // Event listeners for filters
        searchInput.addEventListener('input', filterAdmins);
        statusFilter.addEventListener('change', filterAdmins);

        // Clear filters
        clearFiltersBtn.addEventListener('click', () => {
            searchInput.value = '';
            statusFilter.value = '';
            filterAdmins();
        });

        // Reset add admin modal on close
        const addAdminModal = document.getElementById('addAdminModal');
        addAdminModal.addEventListener('hidden.bs.modal', () => {
            addAdminModal.querySelector('form').reset();
        });

        // Password confirmation validation
        const passwordInput = document.querySelector('input[name="Password"]');
        const confirmPasswordInput = document.querySelector('input[name="ConfirmPassword"]');

        function validatePasswordMatch() {
            if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        }

        passwordInput.addEventListener('input', validatePasswordMatch);
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);

        // Initialize results count
        updateResultsCount();

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>

</html>