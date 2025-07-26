<?php
session_start();
require_once('../config/db_config.php');
require_once('../helpers/notification_helpers.php');

if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $assignmentId = intval($_POST['assignment_id'] ?? 0);
    $submissionText = trim($_POST['submission_text'] ?? '');

    if ($assignmentId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid assignment ID']);
        exit;
    }

    // Get student information
    $studentQuery = "SELECT s.StudentID, s.FullName FROM students s WHERE s.LoginID = ?";
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param("i", $_SESSION['LoginID']);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if (!$student) {
        echo json_encode(['success' => false, 'error' => 'Student information not found']);
        exit;
    }

    // Check if assignment exists and is active
    $assignmentQuery = "SELECT a.*, s.SubjectID, t.TeacherID, t.FullName as TeacherName 
                       FROM assignments a 
                       JOIN subjects s ON a.SubjectID = s.SubjectID 
                       JOIN teachers t ON a.TeacherID = t.TeacherID 
                       WHERE a.AssignmentID = ? AND a.IsActive = 1";
    $stmt = $conn->prepare($assignmentQuery);
    $stmt->bind_param("i", $assignmentId);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();

    if (!$assignment) {
        echo json_encode(['success' => false, 'error' => 'Assignment not found or inactive']);
        exit;
    }

    // Check if already submitted
    $existingQuery = "SELECT SubmissionID FROM assignment_submissions WHERE AssignmentID = ? AND StudentID = ?";
    $stmt = $conn->prepare($existingQuery);
    $stmt->bind_param("ii", $assignmentId, $student['StudentID']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Assignment already submitted']);
        exit;
    }

    // Handle file uploads
    $uploadedFiles = [];
    if (isset($_FILES['files']) && is_array($_FILES['files']['name'])) {
        $uploadDir = '../../uploads/assignments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['files']['name'][$i];
                $fileSize = $_FILES['files']['size'][$i];
                $fileTmpName = $_FILES['files']['tmp_name'][$i];

                // Generate unique filename
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $uniqueFileName;

                if (move_uploaded_file($fileTmpName, $filePath)) {
                    $uploadedFiles[] = [
                        'original_name' => $fileName,
                        'file_path' => $filePath,
                        'file_size' => $fileSize
                    ];
                }
            }
        }
    }

    // Insert submission
    $conn->begin_transaction();

    try {
        // Insert main submission
        $insertQuery = "INSERT INTO assignment_submissions (AssignmentID, StudentID, SubmissionText, Status, SubmittedAt) VALUES (?, ?, ?, 'submitted', NOW())";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("iis", $assignmentId, $student['StudentID'], $submissionText);
        $stmt->execute();
        $submissionId = $conn->insert_id;

        // Insert file attachments if any
        if (!empty($uploadedFiles)) {
            $fileQuery = "INSERT INTO assignment_submission_files (SubmissionID, FileName, FilePath, FileSize) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($fileQuery);

            foreach ($uploadedFiles as $file) {
                $stmt->bind_param("issi", $submissionId, $file['original_name'], $file['file_path'], $file['file_size']);
                $stmt->execute();
            }
        }

        // Create notification for teacher
        notifyAssignmentSubmission(
            $conn,
            $student['StudentID'],
            $assignmentId,
            $assignment['TeacherID'],
            $assignment['SubjectID']
        );

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Assignment submitted successfully',
            'submission_id' => $submissionId,
            'files_count' => count($uploadedFiles)
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    error_log("Error submitting assignment: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
