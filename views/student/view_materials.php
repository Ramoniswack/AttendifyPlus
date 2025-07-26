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
    <link rel="stylesheet" href="../../assets/css/dashboard_student.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_student.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/view_materials.css">
    <!-- JS Libraries -->
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/navbar_student.js" defer></script>
</head>

<body>
    <?php include '../components/sidebar_student_dashboard.php'; ?>
    <?php include '../components/navbar_student.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="container-fluid dashboard-container main-content">
        <div class="page-header mb-4">
            <h2 class="page-title d-flex align-items-center gap-2">
                        <i data-lucide="folder-open"></i>
                        Course Materials
                    </h2>
                    <p class="page-subtitle mb-0">Access lecture slides, notes and resources from your teachers</p>
                </div>
        <!-- Search Bar -->
        <div class="mb-4" id="materialsSearchBar">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0"><i data-lucide="search"></i></span>
                <input type="text" class="form-control" id="materialsSearchInput" placeholder="Search materials by title, subject, or tag...">
            </div>
        </div>
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
                                        <?= htmlspecialchars($material['SubjectCode']) ?> &bull; <?= htmlspecialchars($material['TeacherName']) ?>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($material['Description'])): ?>
                                <div class="text-muted mb-2" style="font-size:0.95rem;">
                                    <?= htmlspecialchars(strlen($material['Description']) > 80 ? substr($material['Description'], 0, 80) . '...' : $material['Description']) ?>
                                </div>
                                <?php endif; ?>
                            <div class="d-flex gap-2 mt-auto">
                                <button class="btn btn-outline-primary btn-sm preview-btn" data-id="<?= $material['MaterialID'] ?>" data-type="<?= strtolower($material['FileType']) ?>" data-file="<?= htmlspecialchars($material['FileName']) ?>">Preview</button>
                                <a class="btn btn-primary btn-sm download-btn" href="download_material.php?id=<?= $material['MaterialID'] ?>" data-id="<?= $material['MaterialID'] ?>" target="_blank" download>Download</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i data-lucide="file-x" style="font-size:2.5rem;"></i>
                    <div class="fw-semibold mt-3" style="font-size:1.2rem;">No materials found</div>
                    <div class="text-muted mt-2">Your teachers haven't uploaded any materials yet. Check back later!</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="previewModalTitle">
                        <i data-lucide="eye"></i> <span>Material Preview</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="previewBody">
                    <div class="text-center text-muted">Loading preview...</div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard_student.js" defer></script>
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
        // Preview functionality
        const previewBtns = document.querySelectorAll('.preview-btn');
        previewBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const type = this.getAttribute('data-type');
                const file = this.getAttribute('data-file');
                const title = this.closest('.teams-card').querySelector('.teams-file-name').textContent.trim();
                const modal = new bootstrap.Modal(document.getElementById('previewModal'));
                const body = document.getElementById('previewBody');
                const modalTitle = document.getElementById('previewModalTitle').querySelector('span');
                if (modalTitle) modalTitle.textContent = title;
                body.innerHTML = '<div class="text-center text-muted">Loading preview...</div>';
                modal.show();
                // Log preview
                fetch('log_material_access.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${id}&action=view`
                });
                // Device detection
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                setTimeout(() => {
                    if (type === 'pdf') {
                        if (isMobile) {
                            body.innerHTML = `
                                <div class='text-center text-muted mb-3'>PDF preview is not supported on your device.</div>
                                <div class='text-center'>
                                    <a href="../../uploads/materials/${file}" target="_blank" class="btn btn-primary mb-2">Open PDF in New Tab</a>
                                </div>
                            `;
                        } else {
                            body.innerHTML = `
                                <div class='d-flex flex-column align-items-center'>
                                    <iframe src="../../uploads/materials/${file}#toolbar=0" style="width:100%;min-height:60vh;border:none;"></iframe>
                                    <a href="../../uploads/materials/${file}" target="_blank" class="btn btn-primary mt-3">Open PDF in New Tab</a>
                                </div>
                            `;
                        }
                    } else if (type === 'pptx' || type === 'ppt') {
                        body.innerHTML = `
                            <div class='d-flex flex-column align-items-center'>
                                <iframe src="https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(window.location.origin + '/uploads/materials/' + file)}" style="width:100%;min-height:60vh;border:none;"></iframe>
                                <a href="../../uploads/materials/${file}" target="_blank" class="btn btn-primary mt-3">Open in New Tab</a>
                            </div>
                        `;
                    } else {
                        body.innerHTML = `
                            <div class='text-center text-muted mb-3'>Preview not supported for this file type.<br>Please download to view.</div>
                            <div class='text-center'>
                                <a href="../../uploads/materials/${file}" target="_blank" class="btn btn-primary">Download File</a>
                            </div>
                        `;
                    }
                }, 500);
            });
        });
        // Download logging
        document.querySelectorAll('.download-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                fetch('log_material_access.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${id}&action=download`
                });
            });
        });

        // Accessibility fix: blur focused element inside modal when modal is hidden
        const previewModal = document.getElementById('previewModal');
        if (previewModal) {
            previewModal.addEventListener('hidden.bs.modal', function() {
                if (document.activeElement && previewModal.contains(document.activeElement)) {
                    document.activeElement.blur();
                }
            });
        }
    </script>
</body>

</html>

<?php
// Helper functions
function formatFileSize($bytes)
{
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function getFileIcon($fileType)
{
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