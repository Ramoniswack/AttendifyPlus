<?php
session_start();
require_once(__DIR__ . '/../../config/db_config.php');

// Check session
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$loginID = $_SESSION['LoginID'];

// Get student info
$studentStmt = $conn->prepare("SELECT StudentID, FullName, SemesterID, DepartmentID FROM students WHERE LoginID = ?");
$studentStmt->bind_param("i", $loginID);
$studentStmt->execute();
$studentRes = $studentStmt->get_result();
$studentRow = $studentRes->fetch_assoc();

if (!$studentRow) {
    header("Location: ../../logout.php");
    exit();
}

$studentID = $studentRow['StudentID'];
$studentName = $studentRow['FullName'];
$semesterID = $studentRow['SemesterID'];
$departmentID = $studentRow['DepartmentID'];

// Get student's subjects
$subjectsQuery = $conn->prepare("
    SELECT s.SubjectID, s.SubjectCode, s.SubjectName, d.DepartmentName, sem.SemesterNumber
    FROM subjects s
    JOIN departments d ON s.DepartmentID = d.DepartmentID
    JOIN semesters sem ON s.SemesterID = sem.SemesterID
    WHERE s.SemesterID = ? AND s.DepartmentID = ?
    ORDER BY s.SubjectName
");
$subjectsQuery->bind_param("ii", $semesterID, $departmentID);
$subjectsQuery->execute();
$subjectsResult = $subjectsQuery->get_result();

// Search and filter functionality
$searchTerm = $_GET['search'] ?? '';
$subjectFilter = $_GET['subject_filter'] ?? '';
$typeFilter = $_GET['type_filter'] ?? '';

// Build materials query
$materialsQuery = "
    SELECT m.*, s.SubjectCode, s.SubjectName, t.FullName as TeacherName
    FROM materials m
    JOIN subjects s ON m.SubjectID = s.SubjectID
    JOIN teachers t ON m.TeacherID = t.TeacherID
    WHERE s.SemesterID = ? AND s.DepartmentID = ? AND m.IsActive = 1
";

$params = [$semesterID, $departmentID];
$paramTypes = "ii";

if (!empty($searchTerm)) {
    $materialsQuery .= " AND (m.Title LIKE ? OR m.Description LIKE ? OR m.Tags LIKE ? OR s.SubjectName LIKE ?)";
    $searchPattern = "%$searchTerm%";
    $params = array_merge($params, [$searchPattern, $searchPattern, $searchPattern, $searchPattern]);
    $paramTypes .= "ssss";
}

if (!empty($subjectFilter)) {
    $materialsQuery .= " AND m.SubjectID = ?";
    $params[] = $subjectFilter;
    $paramTypes .= "i";
}

if (!empty($typeFilter)) {
    $typeFilters = explode(',', $typeFilter);
    $typePlaceholders = str_repeat('?,', count($typeFilters) - 1) . '?';
    $materialsQuery .= " AND m.FileType IN ($typePlaceholders)";
    $params = array_merge($params, $typeFilters);
    $paramTypes .= str_repeat('s', count($typeFilters));
}

$materialsQuery .= " ORDER BY m.UploadDateTime DESC LIMIT 100";

$materialsStmt = $conn->prepare($materialsQuery);
$materialsStmt->bind_param($paramTypes, ...$params);
$materialsStmt->execute();
$materialsResult = $materialsStmt->get_result();

// Get statistics
$totalMaterialsStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM materials m
    JOIN subjects s ON m.SubjectID = s.SubjectID
    WHERE s.SemesterID = ? AND s.DepartmentID = ? AND m.IsActive = 1
");
$totalMaterialsStmt->bind_param("ii", $semesterID, $departmentID);
$totalMaterialsStmt->execute();
$totalMaterials = $totalMaterialsStmt->get_result()->fetch_assoc()['total'];

// Get recent downloads count (if you have a downloads table)
$recentDownloads = 0; // Placeholder - implement if needed
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>View Materials | Attendify+</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/view_materials.css">
    <link rel="stylesheet" href="../../assets/css/dashboard_student.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- JS Libraries -->
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/view_materials.js" defer></script>
    <script src="../../assets/js/dashboard_student.js" defer></script>
</head>

<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include '../components/sidebar_student_dashboard.php'; ?>

    <!-- Navbar -->
    <?php include '../components/navbar_student.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container main-content">
        <!-- Page Header -->
        <div class="page-header mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div>
                    <h2 class="page-title">
                        <i data-lucide="folder-open"></i>
                        Course Materials
                    </h2>
                    <p class="page-subtitle mb-0">Access lecture slides, notes and resources from your teachers</p>
                </div>
                <div class="header-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?= $totalMaterials ?></span>
                        <span class="stat-label">Available</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $materialsResult->num_rows ?></span>
                        <span class="stat-label">Showing</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Bar -->
        <div class="search-filter-bar mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="search-group">
                        <div class="search-input-wrapper">
                            <i data-lucide="search" class="search-icon"></i>
                            <input type="text" id="searchInput" class="search-input" 
                                   placeholder="Search materials, subjects, tags..."
                                   value="<?= htmlspecialchars($searchTerm) ?>">
                            <button type="button" id="clearSearch" class="clear-search-btn" style="display: none;">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="subjectFilter" class="filter-select">
                        <option value="">All Subjects</option>
                        <?php
                        $subjectsResult->data_seek(0);
                        while ($subject = $subjectsResult->fetch_assoc()): ?>
                            <option value="<?= $subject['SubjectID'] ?>" 
                                    <?= $subjectFilter == $subject['SubjectID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subject['SubjectCode']) ?> - <?= htmlspecialchars($subject['SubjectName']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="typeFilter" class="filter-select">
                        <option value="">All Types</option>
                        <option value="pdf" <?= $typeFilter == 'pdf' ? 'selected' : '' ?>>PDF</option>
                        <option value="ppt,pptx" <?= $typeFilter == 'ppt,pptx' ? 'selected' : '' ?>>PowerPoint</option>
                        <option value="doc,docx" <?= $typeFilter == 'doc,docx' ? 'selected' : '' ?>>Word Document</option>
                        <option value="txt" <?= $typeFilter == 'txt' ? 'selected' : '' ?>>Text File</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="filter-actions">
                        <button id="applyFilters" class="btn btn-primary">
                            <i data-lucide="filter"></i>
                            Apply Filters
                        </button>
                        <button id="clearFilters" class="btn btn-outline-secondary">
                            <i data-lucide="x"></i>
                            Clear
                        </button>
                    </div>
                </div>
            </div>
            <div class="search-results-info mt-2">
                <span id="resultsCount" class="results-count">
                    Showing <?= $materialsResult->num_rows ?> of <?= $totalMaterials ?> materials
                </span>
            </div>
        </div>

        <!-- Materials Grid -->
        <div class="materials-container">
            <?php if ($materialsResult->num_rows > 0): ?>
                <div class="materials-grid" id="materialsGrid">
                    <?php while ($material = $materialsResult->fetch_assoc()): ?>
                        <div class="material-card" 
                             data-title="<?= strtolower(htmlspecialchars($material['Title'])) ?>"
                             data-description="<?= strtolower(htmlspecialchars($material['Description'])) ?>"
                             data-tags="<?= strtolower(htmlspecialchars($material['Tags'])) ?>"
                             data-subject="<?= htmlspecialchars($material['SubjectID']) ?>"
                             data-filetype="<?= strtolower($material['FileType']) ?>">
                            
                            <div class="material-header">
                                <div class="file-type-badge file-type-<?= strtolower($material['FileType']) ?>">
                                    <i data-lucide="<?= getFileIcon($material['FileType']) ?>"></i>
                                    <span><?= strtoupper($material['FileType']) ?></span>
                                </div>
                                <div class="material-actions">
                                    <div class="dropdown">
                                        <button class="action-btn" data-bs-toggle="dropdown">
                                            <i data-lucide="more-horizontal"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="download_material.php?id=<?= $material['MaterialID'] ?>" target="_blank">
                                                    <i data-lucide="download"></i>
                                                    Download
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="previewMaterial(<?= $material['MaterialID'] ?>)">
                                                    <i data-lucide="eye"></i>
                                                    Preview
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="shareMaterial(<?= $material['MaterialID'] ?>)">
                                                    <i data-lucide="share-2"></i>
                                                    Share
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="material-content">
                                <h5 class="material-title"><?= htmlspecialchars($material['Title']) ?></h5>
                                
                                <div class="material-meta">
                                    <span class="subject-badge">
                                        <i data-lucide="book"></i>
                                        <?= htmlspecialchars($material['SubjectCode']) ?>
                                    </span>
                                    <span class="teacher-info">
                                        <i data-lucide="user"></i>
                                        <?= htmlspecialchars($material['TeacherName']) ?>
                                    </span>
                                </div>

                                <?php if (!empty($material['Description'])): ?>
                                    <p class="material-description">
                                        <?= htmlspecialchars(strlen($material['Description']) > 100 ? 
                                            substr($material['Description'], 0, 100) . '...' : 
                                            $material['Description']) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($material['Tags'])): ?>
                                    <div class="material-tags">
                                        <?php
                                        $tags = explode(',', $material['Tags']);
                                        foreach (array_slice($tags, 0, 3) as $tag): ?>
                                            <span class="tag"><?= htmlspecialchars(trim($tag)) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($tags) > 3): ?>
                                            <span class="tag more-tags">+<?= count($tags) - 3 ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="material-footer">
                                <div class="material-info">
                                    <span class="file-size">
                                        <i data-lucide="hard-drive"></i>
                                        <?= formatFileSize($material['FileSize']) ?>
                                    </span>
                                    <span class="upload-date">
                                        <i data-lucide="calendar"></i>
                                        <?= date('M j, Y', strtotime($material['UploadDateTime'])) ?>
                                    </span>
                                </div>
                                <div class="material-actions-primary">
                                    <a href="download_material.php?id=<?= $material['MaterialID'] ?>" 
                                       class="btn btn-primary btn-sm" target="_blank">
                                        <i data-lucide="download"></i>
                                        Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i data-lucide="folder-x"></i>
                    </div>
                    <h4 class="empty-state-title">No Materials Found</h4>
                    <p class="empty-state-text">
                        <?php if (!empty($searchTerm) || !empty($subjectFilter) || !empty($typeFilter)): ?>
                            No materials match your search criteria. Try adjusting your filters.
                        <?php else: ?>
                            Your teachers haven't uploaded any materials yet. Check back later!
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($searchTerm) || !empty($subjectFilter) || !empty($typeFilter)): ?>
                        <button id="clearAllFilters" class="btn btn-outline-primary">
                            <i data-lucide="refresh-cw"></i>
                            Clear All Filters
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Load More Button (if needed) -->
        <?php if ($materialsResult->num_rows >= 50): ?>
            <div class="load-more-container text-center mt-4">
                <button id="loadMoreBtn" class="btn btn-outline-primary">
                    <i data-lucide="plus"></i>
                    Load More Materials
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Material Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="eye"></i>
                        Material Preview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="previewContent" class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="downloadFromPreview" class="btn btn-primary">
                        <i data-lucide="download"></i>
                        Download
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize icons
        lucide.createIcons();

        // Page-specific initialization
        document.addEventListener('DOMContentLoaded', function() {
            initializeMaterialsPage();
        });
    </script>
</body>

</html>

<?php
// Helper functions
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function getFileIcon($fileType) {
    switch (strtolower($fileType)) {
        case 'pdf':
            return 'file-text';
        case 'ppt':
        case 'pptx':
            return 'presentation';
        case 'doc':
        case 'docx':
            return 'file-text';
        case 'txt':
            return 'file-text';
        default:
            return 'file';
    }
}
?>