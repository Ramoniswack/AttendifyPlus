<?php
session_start();
require_once(__DIR__ . '/../config/db_config.php');

// Check session
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$studentID = $_SESSION['UserID'];

try {
    // Validate input
    if (!isset($_POST['assignment_id']) || empty($_POST['assignment_id'])) {
        throw new Exception('Assignment ID is required');
    }

    $assignmentID = intval($_POST['assignment_id']);
    $submissionText = $_POST['submission_text'] ?? '';

    // Check if assignment exists and is active
    $assignmentQuery = $conn->prepare("
        SELECT a.*, s.SubjectCode, s.SubjectName, s.DepartmentID, s.SemesterID, t.FullName as TeacherName
        FROM assignments a
        JOIN subjects s ON a.SubjectID = s.SubjectID
        JOIN teachers t ON a.TeacherID = t.TeacherID
        WHERE a.AssignmentID = ? AND a.Status IN ('active', 'graded')
    ");
    $assignmentQuery->bind_param("i", $assignmentID);
    $assignmentQuery->execute();
    $assignmentResult = $assignmentQuery->get_result();
    $assignment = $assignmentResult->fetch_assoc();

    if (!$assignment) {
        throw new Exception('Assignment not found or not available for submission');
    }

    // Check if student has access to this assignment (same department and semester)
    $studentQuery = $conn->prepare("
        SELECT DepartmentID, SemesterID
        FROM students
        WHERE StudentID = ?
    ");
    $studentQuery->bind_param("i", $studentID);
    $studentQuery->execute();
    $studentResult = $studentQuery->get_result();
    $student = $studentResult->fetch_assoc();

    if ($student['DepartmentID'] != $assignment['DepartmentID'] || $student['SemesterID'] != $assignment['SemesterID']) {
        throw new Exception('Access denied to this assignment');
    }

    // Check if already submitted
    $existingSubmissionQuery = $conn->prepare("
        SELECT SubmissionID FROM assignment_submissions 
        WHERE AssignmentID = ? AND StudentID = ?
    ");
    $existingSubmissionQuery->bind_param("ii", $assignmentID, $studentID);
    $existingSubmissionQuery->execute();
    $existingSubmissionResult = $existingSubmissionQuery->get_result();

    if ($existingSubmissionResult->num_rows > 0) {
        throw new Exception('Assignment already submitted');
    }

    // Check if due date has passed
    $isLate = false;
    if ($assignment['DueDate'] && strtotime($assignment['DueDate']) < time()) {
        $isLate = true;
        if (!$assignment['AllowLateSubmissions']) {
            throw new Exception('Assignment due date has passed and late submissions are not allowed');
        }
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert submission record
        $insertSubmissionQuery = $conn->prepare("
            INSERT INTO assignment_submissions (
                AssignmentID, StudentID, SubmissionText, SubmittedAt, IsLate, Status
            ) VALUES (?, ?, ?, NOW(), ?, 'submitted')
        ");
        $insertSubmissionQuery->bind_param("iisi", $assignmentID, $studentID, $submissionText, $isLate);
        $insertSubmissionQuery->execute();

        $submissionID = $conn->insert_id;

        // Handle file uploads
        if (isset($_FILES['submission_files']) && !empty($_FILES['submission_files']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/assignments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $allowedTypes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
                'image/jpeg',
                'image/jpg',
                'image/png'
            ];

            $maxFileSize = 10 * 1024 * 1024; // 10MB

            foreach ($_FILES['submission_files']['tmp_name'] as $key => $tmpName) {
                $fileName = $_FILES['submission_files']['name'][$key];
                $fileSize = $_FILES['submission_files']['size'][$key];
                $fileType = $_FILES['submission_files']['type'][$key];

                // Validate file
                if ($fileSize > $maxFileSize) {
                    throw new Exception("File $fileName is too large. Maximum size is 10MB.");
                }

                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception("File type not allowed for $fileName");
                }

                // Generate unique filename
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $uniqueFileName = $submissionID . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
                $filePath = $uploadDir . $uniqueFileName;

                // Move uploaded file
                if (!move_uploaded_file($tmpName, $filePath)) {
                    throw new Exception("Failed to upload file $fileName");
                }

                // Insert file record
                $insertFileQuery = $conn->prepare("
                    INSERT INTO assignment_submission_files (
                        SubmissionID, FileName, FilePath, FileSize, FileType
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                $relativePath = 'uploads/assignments/' . $uniqueFileName;
                $insertFileQuery->bind_param("issis", $submissionID, $fileName, $relativePath, $fileSize, $fileType);
                $insertFileQuery->execute();
            }
        }

        // Create notification for teacher
        $notificationQuery = $conn->prepare("
            INSERT INTO notifications (
                teacher_id, title, message, icon, type, action_type, action_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $notificationTitle = "New Assignment Submission";
        $notificationMessage = "Student " . $_SESSION['Username'] . " has submitted assignment: " . $assignment['Title'];
        $notificationIcon = "upload";
        $notificationType = "info";
        $actionType = "assignment_submitted";
        $actionData = json_encode([
            'assignment_id' => $assignmentID,
            'submission_id' => $submissionID,
            'student_id' => $studentID
        ]);

        $notificationQuery->bind_param(
            "issssss",
            $assignment['TeacherID'],
            $notificationTitle,
            $notificationMessage,
            $notificationIcon,
            $notificationType,
            $actionType,
            $actionData
        );
        $notificationQuery->execute();

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Assignment submitted successfully',
            'submission_id' => $submissionID
        ]);
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
