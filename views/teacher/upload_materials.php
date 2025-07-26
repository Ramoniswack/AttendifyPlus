<?php

session_start();
require_once(__DIR__ . '/../../config/db_config.php');
require_once(__DIR__ . '/../../helpers/notification_helpers.php');

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
                    $materialId = $conn->insert_id;

                    // Create notification for students about new material
                    notifyMaterialUpload($conn, $teacherID, $subjectID, $materialId, $title);

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
    <link rel="stylesheet" href="../../assets/css/dashboard_teacher.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_teacher.css">
    <link rel="stylesheet" href="../../assets/css/upload_materials.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">
    <script src="../../assets/js/sidebar_teacher.js" defer></script>
    <script src="../../assets/js/dashboard_teacher.js" defer></script>
    <script src="../../assets/js/upload_materials.js" defer></script>
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/navbar_teacher.js" defer></script>
</head>

<body>
    <?php include '../components/sidebar_teacher_dashboard.php'; ?>
    <?php include '../components/navbar_teacher.php'; ?>
    <div class="container-fluid dashboard-container main-content">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap mb-4">
            <div>
                <h2 class="page-title">
                    <i data-lucide="upload-cloud"></i>
                    Upload Materials
                </h2>
                <p class="text-muted mb-0">Share lecture slides, notes and resources with students</p>
            </div>
        </div>
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
        <div class="teams-tabs mb-4">
            <button class="teams-tab active" id="tab-files">Files</button>
            <button class="teams-tab" id="tab-upload">Upload</button>
        </div>
        <!-- Search Bar -->
        <div class="mb-4" id="materialsSearchBar">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0"><i data-lucide="search"></i></span>
                <input type="text" class="form-control" id="materialsSearchInput" placeholder="Search materials by title, subject, or tag...">
            </div>
        </div>
        <!-- Files Tab -->
        <section id="filesSection">
            <div class="row g-4" id="materialsCardGrid">
                <?php if ($materialsResult->num_rows > 0): ?>
                    <?php while ($material = $materialsResult->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4 material-card-item" data-title="<?= strtolower(htmlspecialchars($material['Title'])) ?>" data-subject="<?= strtolower(htmlspecialchars($material['SubjectCode'])) ?>" data-tags="<?= strtolower(htmlspecialchars($material['Tags'])) ?>">
                            <div class="teams-card p-4 d-flex flex-column gap-2 h-100">
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <span class="teams-file-icon" data-lucide="file"></span>
                                    <div>
                                        <div class="fw-semibold teams-file-name" style="font-size:1.1rem;"> <?= htmlspecialchars($material['Title']) ?> </div>
                                        <div class="teams-file-meta" style="font-size:0.92rem;">
                                            <?= htmlspecialchars($material['SubjectCode']) ?> &bull; <?= formatFileSize($material['FileSize']) ?> &bull; <?= date('M j, g:i A', strtotime($material['UploadDateTime'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($material['Description'])): ?>
                                    <div class="text-muted mb-2" style="font-size:0.95rem;">
                                        <?= htmlspecialchars(strlen($material['Description']) > 80 ? substr($material['Description'], 0, 80) . '...' : $material['Description']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex gap-2 mt-auto">
                                    <a class="btn btn-outline-primary btn-sm" href="download_material.php?id=<?= $material['MaterialID'] ?>" title="Download"><i data-lucide="download"></i></a>
                                    <a class="btn btn-outline-danger btn-sm" href="delete_material.php?id=<?= $material['MaterialID'] ?>" title="Delete"><i data-lucide="trash-2"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i data-lucide="file-x" style="font-size:2.5rem;"></i>
                        <div class="fw-semibold mt-3" style="font-size:1.2rem;">No materials uploaded yet</div>
                        <div class="text-muted mt-2">Upload your lecture slides, notes, and resources here. Students will be able to view and download them.</div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <!-- Upload Tab -->
        <section id="uploadSection" style="display:none;">
            <div class="teams-card p-0 mx-auto" style="max-width:600px;">
                <div class="px-4 pt-4 pb-2 d-flex align-items-center gap-2">
                    <span style="font-size:1.3rem;color:var(--accent-light);"><i data-lucide="upload-cloud"></i></span>
                    <span class="fw-semibold" style="font-size:1.1rem;color:var(--accent-light);">Upload Material</span>
                </div>
                <hr class="m-0" />
                <div class="p-4">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="mb-3">
                            <label for="subject_id" class="form-label">Subject <span style="color:#dc3545;">*</span></label>
                            <select class="form-select" id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php $subjectsResult->data_seek(0);
                                while ($subject = $subjectsResult->fetch_assoc()): ?>
                                    <option value="<?= $subject['SubjectID'] ?>">
                                        <?= htmlspecialchars($subject['SubjectCode']) ?> - <?= htmlspecialchars($subject['SubjectName']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span style="color:#dc3545;">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required placeholder="e.g., Lecture 5: Database Design">
                            <small class="form-text text-muted">Must be unique within the selected subject (case-insensitive)</small>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2" placeholder="Brief description of the material content..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags (Optional)</label>
                            <input type="text" class="form-control" id="tags" name="tags" placeholder="e.g., database, sql, design">
                            <small class="form-text text-muted">Comma-separated keywords</small>
                        </div>
                        <div class="mb-3">
                            <label for="material_file" class="form-label">File <span style="color:#dc3545;">*</span></label>
                            <input type="file" class="form-control" id="material_file" name="material_file" accept=".pdf,.ppt,.pptx,.doc,.docx,.txt" required>
                            <small class="form-text text-muted">PDF, PPT, DOC, TXT (Max 100MB)</small>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="upload_material" class="teams-upload-btn">
                                <i data-lucide="upload"></i> Upload Material
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        lucide.createIcons();
        // Live search filter
        const searchInput = document.getElementById('materialsSearchInput');
        const cardGrid = document.getElementById('materialsCardGrid');
        if (searchInput && cardGrid) {
            searchInput.addEventListener('input', function() {
                const term = this.value.toLowerCase();
                cardGrid.querySelectorAll('.material-card-item').forEach(card => {
                    const title = card.getAttribute('data-title') || '';
                    const subject = card.getAttribute('data-subject') || '';
                    const tags = card.getAttribute('data-tags') || '';
                    card.style.display = (title.includes(term) || subject.includes(term) || tags.includes(term)) ? '' : 'none';
                });
            });
        }
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