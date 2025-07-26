<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\api\edit_assignment.php
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include '../config/db_config.php';

// Get teacher ID
$loginID = $_SESSION['LoginID'];
$teacherStmt = $conn->prepare("SELECT TeacherID FROM teachers WHERE LoginID = ?");
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
$teacherRow = $teacherRes->fetch_assoc();

if (!$teacherRow) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    exit();
}

$teacherID = $teacherRow['TeacherID'];

try {
    $assignmentID = intval($_POST['assignment_id'] ?? 0);

    if ($assignmentID <= 0) {
        throw new Exception('Invalid assignment ID');
    }

    // Verify teacher owns this assignment
    $ownerCheck = $conn->prepare("SELECT AssignmentID FROM assignments WHERE AssignmentID = ? AND TeacherID = ?");
    $ownerCheck->bind_param("ii", $assignmentID, $teacherID);
    $ownerCheck->execute();
    if ($ownerCheck->get_result()->num_rows === 0) {
        throw new Exception('Assignment not found or you do not have permission to edit it');
    }

    // Get form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $subjectID = intval($_POST['subject_id'] ?? 0);
    $dueDate = $_POST['due_date'] ?? null;
    $maxPoints = intval($_POST['max_points'] ?? 100);
    $submissionType = $_POST['submission_type'] ?? 'both';
    $allowLateSubmissions = isset($_POST['allow_late_submissions']) ? 1 : 0;
    $gradingRubric = trim($_POST['grading_rubric'] ?? '');
    $status = $_POST['status'] ?? 'draft';

    // Validate required fields
    if (empty($title)) {
        throw new Exception('Assignment title is required');
    }

    if ($subjectID <= 0) {
        throw new Exception('Please select a valid subject');
    }

    // Verify teacher has access to this subject
    $subjectCheck = $conn->prepare("
        SELECT s.SubjectID FROM subjects s 
        JOIN teacher_subject_map tsm ON s.SubjectID = tsm.SubjectID 
        WHERE s.SubjectID = ? AND tsm.TeacherID = ?
    ");
    $subjectCheck->bind_param("ii", $subjectID, $teacherID);
    $subjectCheck->execute();
    if ($subjectCheck->get_result()->num_rows === 0) {
        throw new Exception('You do not have access to this subject');
    }

    // Handle file upload if present
    $attachmentPath = null;
    $attachmentFileName = null;
    $attachmentFileSize = null;
    $attachmentFileType = null;

    // Robust file upload error handling
    error_log('FILES: ' . print_r($_FILES, true));
    error_log('POST: ' . print_r($_POST, true));
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] !== UPLOAD_ERR_OK && $_FILES['assignment_file']['error'] !== UPLOAD_ERR_NO_FILE) {
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
        $errorCode = $_FILES['assignment_file']['error'];
        $errorMsg = $uploadErrors[$errorCode] ?? 'Unknown upload error';
        error_log('File upload error: ' . $errorMsg . ' (code: ' . $errorCode . ')');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'File upload failed: ' . $errorMsg . ' (code: ' . $errorCode . ')'
        ]);
        exit();
    }

    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['assignment_file'];

        // Validate file
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Only PDF, DOC, and DOCX files are allowed.');
        }

        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds 10MB limit.');
        }

        // Create upload directory if it doesn't exist
        $uploadDir = '../uploads/assignments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uniqueFileName = 'assignment_' . $teacherID . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $uniqueFileName;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Get old file to delete it
            $oldFileQuery = $conn->prepare("SELECT AttachmentPath FROM assignments WHERE AssignmentID = ?");
            $oldFileQuery->bind_param("i", $assignmentID);
            $oldFileQuery->execute();
            $oldFileResult = $oldFileQuery->get_result();
            $oldFile = $oldFileResult->fetch_assoc();

            // Delete old file if it exists
            if ($oldFile && $oldFile['AttachmentPath'] && file_exists($oldFile['AttachmentPath'])) {
                unlink($oldFile['AttachmentPath']);
            }

            $attachmentPath = $uploadPath;
            $attachmentFileName = $file['name'];
            $attachmentFileSize = $file['size'];
            $attachmentFileType = $file['type'];
        } else {
            throw new Exception('Failed to upload file');
        }
    }

    // Convert due date format
    if ($dueDate) {
        $dueDate = date('Y-m-d H:i:s', strtotime($dueDate));
    } else {
        $dueDate = null;
    }

    // Prepare update query
    if ($attachmentPath) {
        // Update with new file
        $updateStmt = $conn->prepare("
            UPDATE assignments SET 
                Title = ?, Description = ?, Instructions = ?, SubjectID = ?, 
                DueDate = ?, MaxPoints = ?, Status = ?, SubmissionType = ?, 
                AllowLateSubmissions = ?, GradingRubric = ?, 
                AttachmentPath = ?, AttachmentFileName = ?, 
                AttachmentFileSize = ?, AttachmentFileType = ?,
                UpdatedAt = CURRENT_TIMESTAMP
            WHERE AssignmentID = ? AND TeacherID = ?
        ");

        $updateStmt->bind_param(
            "ssissississsisii",
            $title,
            $description,
            $instructions,
            $subjectID,
            $dueDate,
            $maxPoints,
            $status,
            $submissionType,
            $allowLateSubmissions,
            $gradingRubric,
            $attachmentPath,
            $attachmentFileName,
            $attachmentFileSize,
            $attachmentFileType,
            $assignmentID,
            $teacherID
        );
    } else {
        // Update without changing file
        $updateStmt = $conn->prepare("
            UPDATE assignments SET 
                Title = ?, Description = ?, Instructions = ?, SubjectID = ?, 
                DueDate = ?, MaxPoints = ?, Status = ?, SubmissionType = ?, 
                AllowLateSubmissions = ?, GradingRubric = ?,
                UpdatedAt = CURRENT_TIMESTAMP
            WHERE AssignmentID = ? AND TeacherID = ?
        ");

        $updateStmt->bind_param(
            "ssississisii",
            $title,
            $description,
            $instructions,
            $subjectID,
            $dueDate,
            $maxPoints,
            $status,
            $submissionType,
            $allowLateSubmissions,
            $gradingRubric,
            $assignmentID,
            $teacherID
        );
    }

    if ($updateStmt->execute()) {
        $message = $status === 'draft' ?
            'Assignment updated and saved as draft!' :
            'Assignment updated successfully!';

        echo json_encode([
            'success' => true,
            'message' => $message,
            'assignment_id' => $assignmentID
        ]);
    } else {
        throw new Exception('Failed to update assignment: ' . $conn->error);
    }
} catch (Exception $e) {
    // Clean up uploaded file if assignment update failed
    if (isset($attachmentPath) && file_exists($attachmentPath)) {
        unlink($attachmentPath);
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
