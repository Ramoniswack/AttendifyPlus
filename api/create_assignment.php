<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\api\create_assignment.php
ob_start(); // Start output buffering
session_start();
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1); // TEMP: Show errors in browser for debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

if (!isset($_SESSION['UserID']) || !isset($_SESSION['LoginID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    ob_end_flush();
    exit();
}

try {
    include '../config/db_config.php';
    include '../helpers/notification_helpers.php';
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        throw new Exception('Database connection failed');
    }

    $loginID = (int)$_SESSION['LoginID'];
    $teacherStmt = $conn->prepare("SELECT TeacherID FROM teachers WHERE LoginID = ?");
    if (!$teacherStmt) {
        error_log("Teacher query preparation failed: " . $conn->error);
        throw new Exception('Failed to prepare teacher query');
    }
    $teacherStmt->bind_param("i", $loginID);
    $teacherStmt->execute();
    $teacherRes = $teacherStmt->get_result();
    $teacherRow = $teacherRes->fetch_assoc();
    $teacherStmt->close();

    if (!$teacherRow) {
        error_log("Teacher not found for LoginID: $loginID");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Teacher not found']);
        ob_end_flush();
        exit();
    }
    $teacherID = (int)$teacherRow['TeacherID'];

    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    // Robust file upload error handling
    error_log('FILES: ' . print_r($_FILES, true));
    error_log('POST: ' . print_r($_POST, true));
    if (!isset($_FILES['assignment_file']) || $_FILES['assignment_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'No temporary directory available',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            0 => 'No error, file uploaded successfully'
        ];
        $errorCode = $_FILES['assignment_file']['error'] ?? 'not set';
        $errorMsg = $uploadErrors[$errorCode] ?? 'Unknown upload error';
        error_log('File upload error: ' . $errorMsg . ' (code: ' . $errorCode . ')');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'File upload failed: ' . $errorMsg . ' (code: ' . $errorCode . ')'
        ]);
        ob_end_flush();
        exit();
    }

    // Validate required fields
    if (empty($_POST['title']) || empty($_POST['subject_id'])) {
        throw new Exception('Title and Subject are required');
    }

    // Sanitize and validate inputs
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $subjectID = (int)$_POST['subject_id'];
    $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $maxPoints = (int)($_POST['max_points'] ?? 100);
    $submissionType = $_POST['submission_type'] ?? 'both';
    $allowLateSubmissions = isset($_POST['allow_late_submissions']) ? 1 : 0;
    $gradingRubric = trim($_POST['grading_rubric'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $assignmentID = !empty($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : null;

    // Validate inputs
    if (strlen($title) > 255) {
        throw new Exception('Title cannot exceed 255 characters');
    }
    if ($maxPoints < 1 || $maxPoints > 1000) {
        throw new Exception('Maximum points must be between 1 and 1000');
    }
    $validSubmissionTypes = ['both', 'text', 'file'];
    if (!in_array($submissionType, $validSubmissionTypes)) {
        throw new Exception('Invalid submission type');
    }
    if ($dueDate && !DateTime::createFromFormat('Y-m-d\\TH:i', $dueDate)) {
        throw new Exception('Invalid due date format');
    }

    // Validate SubjectID
    $subjectCheck = $conn->prepare("SELECT SubjectID FROM subjects WHERE SubjectID = ?");
    if (!$subjectCheck) {
        error_log("Subject check query preparation failed: " . $conn->error);
        throw new Exception('Failed to prepare subject check query');
    }
    $subjectCheck->bind_param("i", $subjectID);
    $subjectCheck->execute();
    $subjectResult = $subjectCheck->get_result();
    if ($subjectResult->num_rows === 0) {
        error_log("Invalid SubjectID: $subjectID");
        throw new Exception('Invalid SubjectID');
    }
    $subjectCheck->close();

    // File upload handling
    $filePath = null;
    $originalFileName = null;
    $fileSize = null;
    $fileType = null;

    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        error_log("Processing file upload for teacherID: $teacherID");

        $baseUploadDir = realpath(__DIR__ . '/../uploads') ?: __DIR__ . '/../uploads';
        $uploadDir = $baseUploadDir . DIRECTORY_SEPARATOR . 'assignments';

        // Create directories
        if (!is_dir($baseUploadDir) && !mkdir($baseUploadDir, 0755, true)) {
            error_log("Failed to create base uploads directory: $baseUploadDir");
            throw new Exception('Failed to create base uploads directory');
        }
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            error_log("Failed to create assignments upload directory: $uploadDir");
            throw new Exception('Failed to create assignments upload directory');
        }
        if (!is_writable($uploadDir)) {
            error_log("Upload directory not writable: $uploadDir");
            throw new Exception('Upload directory is not writable');
        }

        $uploadedFile = $_FILES['assignment_file'];
        $originalFileName = $uploadedFile['name'];
        $fileSize = $uploadedFile['size'];
        $fileTmpName = $uploadedFile['tmp_name'];
        $fileType = $uploadedFile['type'];

        if ($fileSize > 10 * 1024 * 1024) {
            error_log("File size exceeds 10MB limit: $fileSize bytes");
            throw new Exception('File size exceeds 10MB limit');
        }

        $allowedTypes = ['pdf', 'doc', 'docx'];
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedTypes)) {
            error_log("Invalid file type: $fileExtension");
            throw new Exception('Invalid file type. Only PDF, DOC, and DOCX files are allowed');
        }

        $uniqueFileName = $teacherID . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $fullUploadPath = $uploadDir . DIRECTORY_SEPARATOR . $uniqueFileName;

        if (!move_uploaded_file($fileTmpName, $fullUploadPath)) {
            error_log("Failed to move uploaded file to: $fullUploadPath");
            throw new Exception('Failed to move uploaded file');
        }

        $filePath = 'uploads/assignments/' . $uniqueFileName;
        error_log("File uploaded successfully: $filePath");
    } elseif (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'No temporary directory available',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $errorCode = $_FILES['assignment_file']['error'];
        error_log("File upload error: " . ($uploadErrors[$errorCode] ?? 'Unknown upload error'));
        throw new Exception($uploadErrors[$errorCode] ?? 'Unknown upload error');
    } elseif (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_NO_FILE && !empty($_POST['assignment_file'])) {
        // File was expected but not uploaded
        error_log('File was expected but not uploaded.');
        throw new Exception('File was expected but not uploaded.');
    }

    $conn->begin_transaction();

    try {
        if ($assignmentID) {
            // Update existing assignment
            $updateQuery = "UPDATE assignments SET 
                Title = ?, Description = ?, Instructions = ?, SubjectID = ?, 
                DueDate = ?, MaxPoints = ?, SubmissionType = ?, 
                AllowLateSubmissions = ?, GradingRubric = ?, Status = ?, 
                UpdatedAt = CURRENT_TIMESTAMP";
            $params = [$title, $description, $instructions, $subjectID, $dueDate, $maxPoints, $submissionType, $allowLateSubmissions, $gradingRubric, $status];
            $types = "ssssisiiss";

            if ($filePath) {
                $updateQuery .= ", AttachmentPath = ?, AttachmentFileName = ?, AttachmentFileSize = ?, AttachmentFileType = ?";
                $params[] = $filePath;
                $params[] = $originalFileName;
                $params[] = $fileSize;
                $params[] = $fileType;
                $types .= "ssis";
            }

            $updateQuery .= " WHERE AssignmentID = ? AND TeacherID = ? AND IsActive = 1";
            $params[] = $assignmentID;
            $params[] = $teacherID;
            $types .= "ii";

            $stmt = $conn->prepare($updateQuery);
            if (!$stmt) {
                error_log("Update query preparation failed: " . $conn->error);
                throw new Exception('Failed to prepare update statement');
            }
            $stmt->bind_param($types, ...$params);

            if (!$stmt->execute()) {
                error_log("Update query execution failed: " . $stmt->error);
                throw new Exception('Failed to update assignment');
            }
            if ($stmt->affected_rows === 0) {
                error_log("No rows updated for AssignmentID: $assignmentID, TeacherID: $teacherID");
                throw new Exception('Assignment not found or no changes made');
            }
            $stmt->close();

            $finalAssignmentID = $assignmentID;
        } else {
            // Insert new assignment
            $insertQuery = "INSERT INTO assignments (
                TeacherID, Title, Description, Instructions, SubjectID, 
                DueDate, MaxPoints, SubmissionType, AllowLateSubmissions, GradingRubric, Status, 
                AttachmentPath, AttachmentFileName, AttachmentFileSize, AttachmentFileType, IsActive, 
                CreatedAt, UpdatedAt
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

            $stmt = $conn->prepare($insertQuery);
            if (!$stmt) {
                error_log("Insert query preparation failed: " . $conn->error);
                throw new Exception('Failed to prepare insert statement');
            }

            $stmt->bind_param(
                "isssisiisssssis",
                $teacherID,
                $title,
                $description,
                $instructions,
                $subjectID,
                $dueDate,
                $maxPoints,
                $submissionType,
                $allowLateSubmissions,
                $gradingRubric,
                $status,
                $filePath,
                $originalFileName,
                $fileSize,
                $fileType
            );

            if (!$stmt->execute()) {
                error_log("Insert query execution failed: " . $stmt->error);
                throw new Exception('Failed to create assignment');
            }
            $finalAssignmentID = $conn->insert_id;
            $stmt->close();
            
            // Create notification for students about new assignment (only for new assignments, not updates)
            notifyAssignmentCreated($conn, $teacherID, $subjectID, $finalAssignmentID, $title);
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => $assignmentID ? 'Assignment updated successfully' : 'Assignment created successfully',
            'assignment_id' => $finalAssignmentID,
            'file_uploaded' => !!$filePath,
            'attachment_path' => $filePath,
            'attachment_filename' => $originalFileName,
            'attachment_filesize' => $fileSize,
            'attachment_filetype' => $fileType
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        if ($filePath && file_exists(__DIR__ . '/../' . $filePath)) {
            unlink(__DIR__ . '/../' . $filePath);
            error_log("Cleaned up uploaded file: $filePath");
        }
        throw $e;
    }
} catch (Exception $e) {
    error_log("Assignment creation error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500); // Always return 500 for server errors
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() . ' (Line: ' . $e->getLine() . ')'
    ]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
    ob_end_flush();
}
