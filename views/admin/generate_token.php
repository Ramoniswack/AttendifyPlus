<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../../config/db_config.php';
include '../../helpers/helpers.php';

$successMsg = '';
$errorMsg = '';

// Handle AJAX request for token generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_token') {
    header('Content-Type: application/json');

    $studentID = $_POST['student_id'] ?? null;

    if (!$studentID) {
        echo json_encode(['success' => false, 'error' => 'Student ID is required']);
        exit();
    }

    try {
        // Verify student exists
        $studentStmt = $conn->prepare("SELECT StudentID, FullName, DeviceRegistered FROM students WHERE StudentID = ?");
        $studentStmt->bind_param("i", $studentID);
        $studentStmt->execute();
        $studentResult = $studentStmt->get_result();
        $student = $studentResult->fetch_assoc();

        if (!$student) {
            echo json_encode(['success' => false, 'error' => 'Student not found']);
            exit();
        }

        if ($student['DeviceRegistered']) {
            echo json_encode(['success' => false, 'error' => 'Student device already registered']);
            exit();
        }

        // Check for existing pending token
        $existingTokenStmt = $conn->prepare("
            SELECT TokenID FROM device_registration_tokens 
            WHERE StudentID = ? AND Used = FALSE AND ExpiresAt > NOW()
        ");
        $existingTokenStmt->bind_param("i", $studentID);
        $existingTokenStmt->execute();
        $existingResult = $existingTokenStmt->get_result();

        if ($existingResult->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'Active registration token already exists for this student']);
            exit();
        }

        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Insert new token
        $insertStmt = $conn->prepare("
            INSERT INTO device_registration_tokens (StudentID, Token, ExpiresAt) 
            VALUES (?, ?, ?)
        ");
        $insertStmt->bind_param("iss", $studentID, $token, $expiresAt);

        if ($insertStmt->execute()) {
            // Create notification for token generation
            createNotification(
                $conn,
                null,
                'admin',
                "Device Token Generated",
                "Device registration token generated for student '{$student['FullName']}'.",
                'key',
                'info'
            );

            echo json_encode([
                'success' => true,
                'message' => 'Device registration token generated successfully',
                'student_name' => $student['FullName'],
                'expires_at' => $expiresAt,
                'token' => $token
            ]);
        } else {
            throw new Exception('Failed to generate token');
        }
    } catch (Exception $e) {
        error_log("Token generation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to generate registration token']);
    }
    exit();
}

// Fetch students who need device registration
$students = [];
$sql = "SELECT s.StudentID, s.FullName, s.Contact, s.ProgramCode, d.DepartmentName, sem.SemesterNumber, s.DeviceRegistered
        FROM students s
        JOIN departments d ON s.DepartmentID = d.DepartmentID
        JOIN semesters sem ON s.SemesterID = sem.SemesterID
        WHERE s.DeviceRegistered = FALSE
        ORDER BY s.FullName";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

// Get statistics
$stats = [];
$statsQueries = [
    'total_students' => "SELECT COUNT(*) as count FROM students",
    'registered_devices' => "SELECT COUNT(*) as count FROM students WHERE DeviceRegistered = TRUE",
    'pending_registration' => "SELECT COUNT(*) as count FROM students WHERE DeviceRegistered = FALSE",
    'active_tokens' => "SELECT COUNT(*) as count FROM device_registration_tokens WHERE Used = FALSE AND ExpiresAt > NOW()"
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
    <title>Generate Device Tokens | Attendify+</title>
    <link rel="stylesheet" href="../../assets/css/manage_admin.css" />
    <link rel="stylesheet" href="../../assets/css/sidebar_admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/navbar_admin.js" defer></script>
</head>

<body>
    <?php include '../components/sidebar_admin_dashboard.php'; ?>
    <?php include '../components/navbar_admin.php'; ?>

    <!-- Main content -->
    <div class="container-fluid dashboard-container">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 class="page-title">
                    <i data-lucide="key"></i>
                    Device Token Generation
                </h2>
                <p class="text-muted mb-0">Generate device registration tokens for students</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="manage_admin.php" class="btn btn-outline-primary">
                    <i data-lucide="shield"></i> Admin Management
                </a>
                <a href="manage_student.php" class="btn btn-outline-primary">
                    <i data-lucide="users"></i> Student Management
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= $stats['total_students'] ?></div>
                            <div class="stat-label">Total Students</div>
                            <div class="stat-change">
                                <i data-lucide="users"></i>
                                <span>All students</span>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="users"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card teachers text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= $stats['registered_devices'] ?></div>
                            <div class="stat-label">Registered Devices</div>
                            <div class="stat-change">
                                <i data-lucide="smartphone"></i>
                                <span>Active devices</span>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="smartphone"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card admins text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= $stats['pending_registration'] ?></div>
                            <div class="stat-label">Pending Registration</div>
                            <div class="stat-change">
                                <i data-lucide="clock"></i>
                                <span>Need tokens</span>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="clock"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card students text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= $stats['active_tokens'] ?></div>
                            <div class="stat-label">Active Tokens</div>
                            <div class="stat-change">
                                <i data-lucide="key"></i>
                                <span>Valid tokens</span>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="key"></i>
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

        <!-- Students Table -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i data-lucide="users"></i>
                    Students Requiring Device Registration
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Department</th>
                            <th>Semester</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i data-lucide="check-circle" class="me-2"></i>
                                    All students have registered their devices!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($student['FullName']) ?></div>
                                            <small class="text-muted">ID: <?= $student['StudentID'] ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($student['ProgramCode']) ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($student['DepartmentName']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">Semester <?= $student['SemesterNumber'] ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($student['Contact']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning">Pending Registration</span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-primary generate-token-btn"
                                            data-student-id="<?= $student['StudentID'] ?>"
                                            data-student-name="<?= htmlspecialchars($student['FullName']) ?>">
                                            <i data-lucide="key"></i>
                                            Generate Token
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Token Result Modal -->
        <div class="modal fade" id="tokenResultModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i data-lucide="key"></i>
                            Device Registration Token
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="tokenResultContent">
                            <!-- Content will be populated by JavaScript -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="copyTokenBtn" style="display: none;">
                            <i data-lucide="copy"></i>
                            Copy Token
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Token generation functionality
        document.querySelectorAll('.generate-token-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const studentId = this.dataset.studentId;
                const studentName = this.dataset.studentName;

                // Show loading state
                this.innerHTML = '<i data-lucide="loader-2" class="spin"></i> Generating...';
                this.disabled = true;

                // Make AJAX request
                fetch('generate_token.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=generate_token&student_id=${studentId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showTokenResult(data, studentName);
                        } else {
                            showError(data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showError('Failed to generate token. Please try again.');
                    })
                    .finally(() => {
                        // Reset button state
                        this.innerHTML = '<i data-lucide="key"></i> Generate Token';
                        this.disabled = false;
                        lucide.createIcons();
                    });
            });
        });

        function showTokenResult(data, studentName) {
            const modal = new bootstrap.Modal(document.getElementById('tokenResultModal'));
            const content = document.getElementById('tokenResultContent');
            const copyBtn = document.getElementById('copyTokenBtn');

            content.innerHTML = `
                <div class="alert alert-success">
                    <i data-lucide="check-circle" class="me-2"></i>
                    Token generated successfully for ${studentName}
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Registration Token:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="tokenInput" value="${data.token}" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToken()">
                                <i data-lucide="copy"></i>
                            </button>
                        </div>
                        <small class="text-muted">This token will expire in 10 minutes</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Student Name:</label>
                        <input type="text" class="form-control" value="${data.student_name}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Expires At:</label>
                        <input type="text" class="form-control" value="${new Date(data.expires_at).toLocaleString()}" readonly>
                    </div>
                </div>
                <div class="alert alert-info mt-3">
                    <i data-lucide="info" class="me-2"></i>
                    <strong>Instructions:</strong> Share this token with the student. They need to use it within 10 minutes to register their device.
                </div>
            `;

            copyBtn.style.display = 'inline-block';
            modal.show();
            lucide.createIcons();
        }

        function showError(message) {
            const modal = new bootstrap.Modal(document.getElementById('tokenResultModal'));
            const content = document.getElementById('tokenResultContent');
            const copyBtn = document.getElementById('copyTokenBtn');

            content.innerHTML = `
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" class="me-2"></i>
                    ${message}
                </div>
            `;

            copyBtn.style.display = 'none';
            modal.show();
            lucide.createIcons();
        }

        function copyToken() {
            const tokenInput = document.getElementById('tokenInput');
            tokenInput.select();
            tokenInput.setSelectionRange(0, 99999);
            document.execCommand('copy');

            // Show feedback
            const copyBtn = document.querySelector('#tokenResultModal .btn-outline-secondary');
            const originalText = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i data-lucide="check"></i> Copied!';
            copyBtn.classList.remove('btn-outline-secondary');
            copyBtn.classList.add('btn-success');

            setTimeout(() => {
                copyBtn.innerHTML = originalText;
                copyBtn.classList.remove('btn-success');
                copyBtn.classList.add('btn-outline-secondary');
                lucide.createIcons();
            }, 2000);
        }

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