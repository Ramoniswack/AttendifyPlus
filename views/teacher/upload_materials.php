<?php

session_start();
require_once(__DIR__ . '/../../config/db_config.php');

// Check session
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    header("Location: ../auth/login.php");
    exit();
}

$loginID = $_SESSION['LoginID'];

// Get teacher info
$teacherStmt = $conn->prepare("SELECT TeacherID, FullName FROM teachers WHERE LoginID = ?");
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
$teacherRow = $teacherRes->fetch_assoc();

if (!$teacherRow) {
    header("Location: ../../logout.php");
    exit();
}

$teacherID = $teacherRow['TeacherID'];
$teacherName = $teacherRow['FullName'];

// Get teacher's subjects
$subjectsQuery = $conn->prepare("
    SELECT s.SubjectID, s.SubjectCode, s.SubjectName, d.DepartmentName, sem.SemesterNumber
    FROM subjects s
    JOIN teacher_subject_map tsm ON s.SubjectID = tsm.SubjectID
    JOIN departments d ON s.DepartmentID = d.DepartmentID
    JOIN semesters sem ON s.SemesterID = sem.SemesterID
    WHERE tsm.TeacherID = ?
    ORDER BY sem.SemesterNumber, s.SubjectName
");
$subjectsQuery->bind_param("i", $teacherID);
$subjectsQuery->execute();
$subjectsResult = $subjectsQuery->get_result();

// Search functionality
$searchTerm = $_GET['search'] ?? '';
$subjectFilter = $_GET['subject_filter'] ?? '';

// Build materials query with search
$materialsQuery = "
    SELECT m.*, s.SubjectCode, s.SubjectName
    FROM materials m
    JOIN subjects s ON m.SubjectID = s.SubjectID
    WHERE m.TeacherID = ? AND m.IsActive = 1
";

$params = [$teacherID];
$paramTypes = "i";

if (!empty($searchTerm)) {
    $materialsQuery .= " AND (m.Title LIKE ? OR m.Description LIKE ? OR m.Tags LIKE ? OR m.OriginalFileName LIKE ?)";
    $searchPattern = "%$searchTerm%";
    $params = array_merge($params, [$searchPattern, $searchPattern, $searchPattern, $searchPattern]);
    $paramTypes .= "ssss";
}

if (!empty($subjectFilter)) {
    $materialsQuery .= " AND m.SubjectID = ?";
    $params[] = $subjectFilter;
    $paramTypes .= "i";
}

$materialsQuery .= " ORDER BY m.UploadDateTime DESC LIMIT 50";

$materialsStmt = $conn->prepare($materialsQuery);
$materialsStmt->bind_param($paramTypes, ...$params);
$materialsStmt->execute();
$materialsResult = $materialsStmt->get_result();

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    $subjectID = $_POST['subject_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $tags = trim($_POST['tags']);

    // Validation
    if (empty($subjectID) || empty($title)) {
        $_SESSION['upload_error'] = "Subject and title are required.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Check for duplicate title in same subject (case-insensitive)
    $titleCheckStmt = $conn->prepare("SELECT MaterialID FROM materials WHERE TeacherID = ? AND SubjectID = ? AND LOWER(Title) = LOWER(?) AND IsActive = 1");
    $titleCheckStmt->bind_param("iis", $teacherID, $subjectID, $title);
    $titleCheckStmt->execute();
    $titleCheckResult = $titleCheckStmt->get_result();

    if ($titleCheckResult->num_rows > 0) {
        $_SESSION['upload_error'] = "A material with this title already exists for the selected subject. Please choose a different title.";
        $titleCheckStmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $titleCheckStmt->close();

    if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === 0) {
        $file = $_FILES['material_file'];
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileTmp = $file['tmp_name'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validate file
        $allowedTypes = ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'txt'];
        $maxSize = 100 * 1024 * 1024; // 100MB

        if (!in_array($fileType, $allowedTypes)) {
            $_SESSION['upload_error'] = "Invalid file type. Only PDF, PPT, DOC, and TXT files are allowed.";
        } elseif ($fileSize > $maxSize) {
            $_SESSION['upload_error'] = "File too large. Maximum size is 100MB.";
        } else {
            // Create upload directory
            $uploadDir = __DIR__ . '/../../uploads/materials/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $uniqueFileName = uniqid() . '_' . time() . '.' . $fileType;
            $filePath = $uploadDir . $uniqueFileName;

            if (move_uploaded_file($fileTmp, $filePath)) {
                // Save to database
                $insertStmt = $conn->prepare("
                    INSERT INTO materials (TeacherID, SubjectID, Title, Description, FileName, OriginalFileName, FileSize, FileType, FilePath, Tags)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $relativePath = 'uploads/materials/' . $uniqueFileName;
                $insertStmt->bind_param("iissssssss", $teacherID, $subjectID, $title, $description, $uniqueFileName, $fileName, $fileSize, $fileType, $relativePath, $tags);

                if ($insertStmt->execute()) {
                    $_SESSION['upload_success'] = "Material uploaded successfully!";
                    $insertStmt->close();
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $_SESSION['upload_error'] = "Database error: " . $conn->error;
                    unlink($filePath); // Delete uploaded file
                    $insertStmt->close();
                }
            } else {
                $_SESSION['upload_error'] = "Failed to upload file.";
            }
        }
    } else {
        $_SESSION['upload_error'] = "Please select a file to upload.";
    }

    // Redirect to prevent re-submission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get messages from session and clear them
$successMsg = '';
$errorMsg = '';

if (isset($_SESSION['upload_success'])) {
    $successMsg = $_SESSION['upload_success'];
    unset($_SESSION['upload_success']);
}

if (isset($_SESSION['upload_error'])) {
    $errorMsg = $_SESSION['upload_error'];
    unset($_SESSION['upload_error']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Upload Materials | Attendify+</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/dashboard_teacher.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* CSS Variables - EXACT MATCH FROM MY_SUBJECTS */
        :root {
            --primary-color: #1a73e8;
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --border-light: #e2e8f0;
            --bg-subtle: #f8fafc;
            --accent-light: #1A73E8;
            --accent-dark: #00ffc8;
            --card-light: #ffffff;
            --card-dark: #1f1f1f;
            --text-light: #333;
            --text-dark: #eee;
            --text-muted-light: #6c757d;
            --text-muted-dark: #a0a0a0;
            --shadow-light: rgba(0, 0, 0, 0.1);
            --shadow-dark: rgba(0, 0, 0, 0.3);
            --input-bg-light: #ffffff;
            --input-bg-dark: #2a2a2a;
            --hover-light: rgba(0, 0, 0, 0.05);
            --hover-dark: rgba(255, 255, 255, 0.1);
        }

        body.dark-mode {
            --primary-color: #00ffc8;
            --text-primary: #e2e8f0;
            --text-secondary: #a0aec0;
            --border-light: #2d3748;
            --bg-subtle: #1a202c;
        }

        /* Enhanced Upload Card */
        .upload-card {
            border: 1px solid var(--border-light);
            background: var(--card-light);
            border-radius: 12px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px var(--shadow-light);
        }

        body.dark-mode .upload-card {
            background: var(--card-dark);
            border-color: var(--border-light);
            box-shadow: 0 2px 8px var(--shadow-dark);
        }

        .upload-card:hover {
            box-shadow: 0 4px 12px var(--shadow-light);
        }

        body.dark-mode .upload-card:hover {
            box-shadow: 0 4px 12px var(--shadow-dark);
        }

        /* Material Cards */
        .material-card {
            border: 1px solid var(--border-light);
            background: var(--card-light);
            border-radius: 8px;
            transition: all 0.2s ease;
            margin-bottom: 0.75rem;
        }

        body.dark-mode .material-card {
            background: var(--card-dark);
            border-color: var(--border-light);
        }

        .material-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px var(--shadow-light);
        }

        body.dark-mode .material-card:hover {
            box-shadow: 0 3px 8px var(--shadow-dark);
        }

        /* File Type Icons */
        .file-type-icon {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.65rem;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .file-type-pdf {
            background: #e53e3e;
            color: white;
        }

        .file-type-ppt,
        .file-type-pptx {
            background: #d69e2e;
            color: white;
        }

        .file-type-doc,
        .file-type-docx {
            background: #3182ce;
            color: white;
        }

        .file-type-txt {
            background: #38a169;
            color: white;
        }

        /* Form Styling */
        .form-control,
        .form-select {
            background: var(--input-bg-light);
            border: 1px solid var(--border-light);
            color: var(--text-light);
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background: var(--input-bg-dark);
            border-color: var(--border-light);
            color: var(--text-dark);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--accent-light);
            box-shadow: 0 0 0 0.2rem rgba(26, 115, 232, 0.15);
            background: var(--input-bg-light);
        }

        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus {
            border-color: var(--accent-dark);
            box-shadow: 0 0 0 0.2rem rgba(0, 255, 200, 0.15);
            background: var(--input-bg-dark);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        body.dark-mode .form-label {
            color: var(--text-dark);
        }

        /* Responsive Buttons */
        .upload-btn {
            background: linear-gradient(135deg, var(--accent-light) 0%, #0056b3 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        body.dark-mode .upload-btn {
            background: linear-gradient(135deg, var(--accent-dark) 0%, #00d4aa 100%);
            color: black;
        }

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 115, 232, 0.3);
            color: white;
        }

        body.dark-mode .upload-btn:hover {
            box-shadow: 0 5px 15px rgba(0, 255, 200, 0.3);
            color: black;
        }

        /* Minimal Search Inside Materials Section */
        .materials-search-bar {
            background: var(--bg-subtle);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }

        body.dark-mode .materials-search-bar {
            background: rgba(255, 255, 255, 0.03);
            border-color: var(--border-light);
        }

        .search-input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input {
            background: var(--input-bg-light);
            border: 1px solid var(--border-light);
            color: var(--text-light);
            padding: 0.5rem 2.5rem 0.5rem 0.75rem;
            font-size: 0.8rem;
            width: 100%;
            border-radius: 6px;
            outline: none;
            transition: all 0.2s ease;
        }

        body.dark-mode .search-input {
            background: var(--input-bg-dark);
            border-color: var(--border-light);
            color: var(--text-dark);
        }

        .search-input:focus {
            border-color: var(--accent-light);
            box-shadow: 0 0 0 0.15rem rgba(26, 115, 232, 0.15);
        }

        body.dark-mode .search-input:focus {
            border-color: var(--accent-dark);
            box-shadow: 0 0 0 0.15rem rgba(0, 255, 200, 0.15);
        }

        .search-input::placeholder {
            color: var(--text-secondary);
            font-size: 0.75rem;
        }

        .search-btn-minimal {
            position: absolute;
            right: 0.5rem;
            background: none;
            border: none;
            color: var(--text-secondary);
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .search-btn-minimal:hover {
            color: var(--accent-light);
            background: var(--hover-light);
        }

        body.dark-mode .search-btn-minimal:hover {
            color: var(--accent-dark);
            background: var(--hover-dark);
        }

        .filter-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-top: 0.5rem;
        }

        .filter-select-mini {
            background: var(--input-bg-light);
            border: 1px solid var(--border-light);
            color: var(--text-light);
            padding: 0.35rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
            flex: 1;
            min-width: 0;
        }

        body.dark-mode .filter-select-mini {
            background: var(--input-bg-dark);
            border-color: var(--border-light);
            color: var(--text-dark);
        }

        .clear-filters-mini {
            background: none;
            border: 1px solid var(--border-light);
            color: var(--text-secondary);
            padding: 0.35rem 0.6rem;
            font-size: 0.7rem;
            border-radius: 4px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .clear-filters-mini:hover {
            color: var(--accent-light);
            border-color: var(--accent-light);
            background: var(--hover-light);
        }

        body.dark-mode .clear-filters-mini:hover {
            color: var(--accent-dark);
            border-color: var(--accent-dark);
            background: var(--hover-dark);
        }

        /* Stats */
        .stats-text {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .minimal-badge {
            background: var(--bg-subtle);
            color: var(--text-primary);
            border: 1px solid var(--border-light);
            font-weight: 500;
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .file-size {
            color: var(--text-secondary);
            font-size: 0.7rem;
        }

        /* Materials Header */
        .materials-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .materials-count {
            color: var(--text-secondary);
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Page Title */
        .page-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--accent-light);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        body.dark-mode .page-title {
            color: var(--accent-dark);
        }

        /* Results Count */
        .results-count-mini {
            color: var(--text-secondary);
            font-size: 0.7rem;
            margin-top: 0.5rem;
            padding: 0.25rem 0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .upload-btn {
                padding: 0.625rem 1rem;
                font-size: 0.8rem;
            }

            .file-type-icon {
                width: 32px;
                height: 32px;
                font-size: 0.6rem;
            }

            .material-card {
                padding: 0.75rem !important;
            }

            .form-control,
            .form-select {
                padding: 0.5rem;
                font-size: 0.8rem;
            }

            .upload-card {
                padding: 1rem !important;
            }

            .btn {
                font-size: 0.8rem;
                padding: 0.5rem 0.75rem;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .filter-row {
                flex-direction: column;
                gap: 0.4rem;
            }

            .filter-select-mini {
                width: 100%;
            }

            .search-input {
                padding: 0.45rem 2.2rem 0.45rem 0.6rem;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 576px) {
            .upload-btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.75rem;
            }

            .file-type-icon {
                width: 28px;
                height: 28px;
                font-size: 0.55rem;
            }

            .minimal-badge {
                font-size: 0.65rem;
                padding: 0.2rem 0.4rem;
            }

            .material-card h6 {
                font-size: 0.9rem;
            }

            .stats-text {
                font-size: 0.8rem;
            }

            .file-size {
                font-size: 0.65rem;
            }

            .materials-count {
                font-size: 0.7rem;
            }

            .page-title {
                font-size: 1.6rem;
            }

            .materials-search-bar {
                padding: 0.5rem;
            }

            .search-input {
                padding: 0.4rem 2rem 0.4rem 0.5rem;
                font-size: 0.7rem;
            }

            .filter-select-mini {
                padding: 0.3rem 0.4rem;
                font-size: 0.7rem;
            }

            .clear-filters-mini {
                padding: 0.3rem 0.5rem;
                font-size: 0.65rem;
            }
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .no-results i {
            opacity: 0.5;
            margin-bottom: 1rem;
        }

        /* Alert Styling */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        body.dark-mode .alert-success {
            background-color: #1e3a2e;
            color: #5cb85c;
        }

        body.dark-mode .alert-danger {
            background-color: #3a1e20;
            color: #d9534f;
        }
    </style>

    <!-- JS Libraries -->
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/dashboard_teacher.js" defer></script>
</head>

<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include '../components/sidebar_teacher_dashboard.php'; ?>

    <!-- Navbar -->
    <?php include '../components/navbar_teacher.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="page-title">
                    <i data-lucide="upload-cloud"></i>
                    Upload Materials
                </h2>
                <p class="stats-text mb-0">Share lecture slides, notes and resources with students</p>
            </div>
            <div class="stats-text">
                <?= date('M j, Y') ?> • <?= date('g:i A') ?>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($successMsg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i data-lucide="check-circle" class="me-2" style="width: 16px; height: 16px;"></i>
                <?= htmlspecialchars($successMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i data-lucide="alert-circle" class="me-2" style="width: 16px; height: 16px;"></i>
                <?= htmlspecialchars($errorMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Upload Section -->
            <div class="col-lg-6">
                <div class="upload-card p-4" id="uploadCard">
                    <div class="text-center mb-4">
                        <div class="mb-3">
                            <i data-lucide="upload-cloud" style="width: 40px; height: 40px; color: var(--accent-light);"></i>
                        </div>
                        <h5 class="mb-2">Upload Course Material</h5>
                        <p class="stats-text">Upload your lecture slides, notes and resources</p>
                        <p class="stats-text">Supported: PDF, PPT, DOC, TXT • Max size: 100MB</p>
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="subject_id" class="form-label">
                                    <i data-lucide="book"></i>
                                    Subject <span style="color: #dc3545;">*</span>
                                </label>
                                <select class="form-select" id="subject_id" name="subject_id" required>
                                    <option value="">Select Subject</option>
                                    <?php
                                    // Reset the result pointer for subjects
                                    $subjectsResult->data_seek(0);
                                    while ($subject = $subjectsResult->fetch_assoc()): ?>
                                        <option value="<?= $subject['SubjectID'] ?>">
                                            <?= htmlspecialchars($subject['SubjectCode']) ?> - <?= htmlspecialchars($subject['SubjectName']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="title" class="form-label">
                                    <i data-lucide="edit"></i>
                                    Title <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="text" class="form-control" id="title" name="title" required
                                    placeholder="e.g., Lecture 5: Database Design">
                                <small class="form-text text-muted">Must be unique within the selected subject (case-insensitive)</small>
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label">
                                    <i data-lucide="file-text"></i>
                                    Description
                                </label>
                                <textarea class="form-control" id="description" name="description" rows="2"
                                    placeholder="Brief description of the material content..."></textarea>
                            </div>

                            <div class="col-12">
                                <label for="tags" class="form-label">
                                    <i data-lucide="tag"></i>
                                    Tags (Optional)
                                </label>
                                <input type="text" class="form-control" id="tags" name="tags"
                                    placeholder="e.g., database, sql, design">
                                <small class="form-text text-muted">Comma-separated keywords</small>
                            </div>

                            <div class="col-12">
                                <label for="material_file" class="form-label">
                                    <i data-lucide="paperclip"></i>
                                    File <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="file" class="form-control" id="material_file" name="material_file"
                                    accept=".pdf,.ppt,.pptx,.doc,.docx,.txt" required>
                                <small class="form-text text-muted">PDF, PPT, DOC, TXT (Max 100MB)</small>
                            </div>

                            <div class="col-12">
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="submit" name="upload_material" class="upload-btn">
                                        <i data-lucide="upload" class="me-1" style="width: 14px; height: 14px;"></i>
                                        Upload Material
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i data-lucide="x" class="me-1" style="width: 14px; height: 14px;"></i>
                                        Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Materials List -->
            <div class="col-lg-6">
                <div class="card border-0 h-100" style="border-radius: 12px; box-shadow: 0 2px 8px var(--shadow-light);">
                    <div class="card-header bg-transparent border-0 pb-0">
                        <div class="materials-header">
                            <h6 class="mb-0 fw-semibold">
                                <i data-lucide="folder" class="me-1"></i>
                                Your Materials
                            </h6>
                            <span class="materials-count" id="materialsCount">
                                Loading...
                            </span>
                        </div>

                        <!-- Minimal Search Bar Inside Materials Section -->
                        <div class="materials-search-bar">
                            <div class="search-input-group">
                                <input id="materialSearch" type="text" class="search-input"
                                    placeholder="Search your materials..."
                                    value="<?= htmlspecialchars($searchTerm) ?>" />
                                <button type="button" class="search-btn-minimal">
                                    <i data-lucide="search" style="width: 14px; height: 14px;"></i>
                                </button>
                            </div>
                            <div class="filter-row">
                                <select id="filterSubject" class="filter-select-mini">
                                    <option value="">All Subjects</option>
                                    <?php
                                    $subjectsResult->data_seek(0);
                                    while ($subject = $subjectsResult->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($subject['SubjectID']) ?>"
                                            <?= $subjectFilter == $subject['SubjectID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($subject['SubjectCode']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <select id="filterFileType" class="filter-select-mini">
                                    <option value="">All Types</option>
                                    <option value="pdf">PDF</option>
                                    <option value="ppt,pptx">PPT</option>
                                    <option value="doc,docx">DOC</option>
                                    <option value="txt">TXT</option>
                                </select>
                                <button id="clearFilters" class="clear-filters-mini">
                                    <i data-lucide="x" style="width: 10px; height: 10px;"></i>
                                    Clear
                                </button>
                            </div>
                            <div class="results-count-mini" id="resultsCount"></div>
                        </div>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <div id="materialsContainer">
                            <?php if ($materialsResult->num_rows > 0): ?>
                                <?php while ($material = $materialsResult->fetch_assoc()): ?>
                                    <div class="material-card p-3 material-item"
                                        data-title="<?= strtolower(htmlspecialchars($material['Title'])) ?>"
                                        data-description="<?= strtolower(htmlspecialchars($material['Description'])) ?>"
                                        data-tags="<?= strtolower(htmlspecialchars($material['Tags'])) ?>"
                                        data-subject="<?= htmlspecialchars($material['SubjectID']) ?>"
                                        data-filetype="<?= strtolower($material['FileType']) ?>">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="file-type-icon file-type-<?= strtolower($material['FileType']) ?>">
                                                <?= strtoupper($material['FileType']) ?>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <h6 class="mb-1 fw-medium text-truncate" style="font-size: 0.9rem;">
                                                    <?= htmlspecialchars($material['Title']) ?>
                                                </h6>
                                                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                                    <span class="minimal-badge badge rounded-pill">
                                                        <?= htmlspecialchars($material['SubjectCode']) ?>
                                                    </span>
                                                    <span class="file-size">
                                                        <?= formatFileSize($material['FileSize']) ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($material['Description'])): ?>
                                                    <p class="stats-text mb-2 text-truncate" style="font-size: 0.8rem;">
                                                        <?= htmlspecialchars(substr($material['Description'], 0, 80)) ?><?= strlen($material['Description']) > 80 ? '...' : '' ?>
                                                    </p>
                                                <?php endif; ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="stats-text" style="font-size: 0.75rem;">
                                                        <?= date('M j, g:i A', strtotime($material['UploadDateTime'])) ?>
                                                    </small>
                                                    <div class="dropdown">
                                                        <button class="btn btn-link btn-sm text-muted p-0" data-bs-toggle="dropdown">
                                                            <i data-lucide="more-horizontal" style="width: 16px; height: 16px;"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li><a class="dropdown-item" href="download_material.php?id=<?= $material['MaterialID'] ?>">
                                                                    <i data-lucide="download" class="me-2" style="width: 14px; height: 14px;"></i>Download
                                                                </a></li>
                                                            <li><a class="dropdown-item text-danger" href="delete_material.php?id=<?= $material['MaterialID'] ?>">
                                                                    <i data-lucide="trash-2" class="me-2" style="width: 14px; height: 14px;"></i>Delete
                                                                </a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="no-results">
                                    <i data-lucide="file-x" class="text-muted mb-2" style="width: 32px; height: 32px;"></i>
                                    <p class="stats-text mb-0">No materials uploaded yet</p>
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
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Search and filter functionality
        const searchInput = document.getElementById('materialSearch');
        const subjectFilter = document.getElementById('filterSubject');
        const fileTypeFilter = document.getElementById('filterFileType');
        const clearFiltersBtn = document.getElementById('clearFilters');
        const resultsCount = document.getElementById('resultsCount');
        const materialsCount = document.getElementById('materialsCount');
        const materialItems = document.querySelectorAll('.material-item');

        function updateResultsCount() {
            const visibleItems = document.querySelectorAll('.material-item:not([style*="display: none"])').length;
            const totalItems = materialItems.length;
            resultsCount.textContent = `Showing ${visibleItems} of ${totalItems} materials`;
            materialsCount.textContent = `${visibleItems} found`;
        }

        function filterMaterials() {
            const searchTerm = searchInput.value.toLowerCase();
            const subjectFilterValue = subjectFilter.value;
            const fileTypeFilterValue = fileTypeFilter.value;

            materialItems.forEach(item => {
                const title = item.dataset.title || '';
                const description = item.dataset.description || '';
                const tags = item.dataset.tags || '';
                const subject = item.dataset.subject || '';
                const filetype = item.dataset.filetype || '';

                let showItem = true;

                // Search filter
                if (searchTerm) {
                    const searchMatch = title.includes(searchTerm) ||
                        description.includes(searchTerm) ||
                        tags.includes(searchTerm);
                    if (!searchMatch) showItem = false;
                }

                // Subject filter
                if (subjectFilterValue && subject !== subjectFilterValue) {
                    showItem = false;
                }

                // File type filter
                if (fileTypeFilterValue) {
                    const allowedTypes = fileTypeFilterValue.split(',');
                    if (!allowedTypes.includes(filetype)) {
                        showItem = false;
                    }
                }

                item.style.display = showItem ? '' : 'none';
            });

            updateResultsCount();
        }

        // Event listeners for filters
        searchInput.addEventListener('input', filterMaterials);
        subjectFilter.addEventListener('change', filterMaterials);
        fileTypeFilter.addEventListener('change', filterMaterials);

        // Clear filters
        clearFiltersBtn.addEventListener('click', () => {
            searchInput.value = '';
            subjectFilter.value = '';
            fileTypeFilter.value = '';
            filterMaterials();
        });

        // File input change handler
        document.getElementById('material_file').addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                const file = e.target.files[0];
                const fileSize = formatFileSize(file.size);
                const fileName = file.name;
                console.log(`Selected: ${fileName} (${fileSize})`);
            }
        });

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

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

        // Final icon refresh
        setTimeout(() => {
            lucide.createIcons();
        }, 100);
    </script>
</body>

</html>

<?php
// Helper function for file size formatting
function formatFileSize($bytes)
{
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>