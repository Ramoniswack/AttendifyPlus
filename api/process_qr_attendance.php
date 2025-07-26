<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once(__DIR__ . '/../config/db_config.php');
require_once(__DIR__ . '/../helpers/notification_helpers.php');

// Debug logging
error_log("=== PROCESS QR ATTENDANCE REQUEST ===");
error_log("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST DATA: " . json_encode($_POST));
error_log("SESSION DATA: " . json_encode(['UserID' => $_SESSION['UserID'] ?? 'not set', 'Role' => $_SESSION['Role'] ?? 'not set', 'LoginID' => $_SESSION['LoginID'] ?? 'not set']));

header('Content-Type: application/json');

// Check if user is logged in as student
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    error_log("UNAUTHORIZED: UserID = " . ($_SESSION['UserID'] ?? 'not set') . ", Role = " . ($_SESSION['Role'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$token = $_POST['token'] ?? '';
if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'No attendance token provided']);
    exit();
}

$loginID = $_SESSION['LoginID'];

try {
    // Get student info
    $studentStmt = $conn->prepare("SELECT StudentID, FullName, DeviceRegistered FROM students WHERE LoginID = ?");
    $studentStmt->bind_param("i", $loginID);
    $studentStmt->execute();
    $studentRes = $studentStmt->get_result();
    $student = $studentRes->fetch_assoc();
    $studentStmt->close();

    error_log("STUDENT INFO: " . json_encode($student));

    if (!$student) {
        error_log("ERROR: Student not found for LoginID: " . $loginID);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }

    // Check if device is registered OR auto-register for testing
    if (!$student['DeviceRegistered']) {
        error_log("DEVICE NOT REGISTERED: Auto-registering device for StudentID: " . $student['StudentID']);

        // Auto-register device and clean up duplicate device fingerprints
        // First, check if this device fingerprint is already registered to another student
        if (isset($_POST['device_fingerprint'])) {
            $deviceFingerprint = $_POST['device_fingerprint'];
            error_log("DEVICE FINGERPRINT PROVIDED: " . $deviceFingerprint);

            $existingDeviceStmt = $conn->prepare("SELECT StudentID, s.FullName FROM student_devices sd JOIN students s ON sd.StudentID = s.StudentID WHERE sd.DeviceFingerprint = ? AND sd.StudentID != ?");
            $existingDeviceStmt->bind_param("si", $deviceFingerprint, $student['StudentID']);
            $existingDeviceStmt->execute();
            $existingDeviceRes = $existingDeviceStmt->get_result();
            $existingDevice = $existingDeviceRes->fetch_assoc();
            $existingDeviceStmt->close();

            if ($existingDevice) {
                error_log("DEVICE SHARING DETECTED: Device {$deviceFingerprint} is already registered to StudentID: {$existingDevice['StudentID']} ({$existingDevice['FullName']})");
                echo json_encode([
                    'success' => false,
                    'message' => 'This device is already registered to another student (' . $existingDevice['FullName'] . '). Each student must use their own device.',
                    'error_code' => 'DEVICE_ALREADY_REGISTERED'
                ]);
                exit();
            }

            // Clean up any old device entries for this student
            $cleanupStmt = $conn->prepare("DELETE FROM student_devices WHERE StudentID = ?");
            $cleanupStmt->bind_param("i", $student['StudentID']);
            $cleanupStmt->execute();
            $cleanedRows = $cleanupStmt->affected_rows;
            $cleanupStmt->close();
            error_log("CLEANED UP {$cleanedRows} old device records for StudentID: " . $student['StudentID']);

            // Register the new device
            $insertDeviceStmt = $conn->prepare("INSERT INTO student_devices (StudentID, DeviceFingerprint, RegisteredAt) VALUES (?, ?, NOW())");
            $insertDeviceStmt->bind_param("is", $student['StudentID'], $deviceFingerprint);
            if ($insertDeviceStmt->execute()) {
                error_log("DEVICE REGISTERED: Device {$deviceFingerprint} registered to StudentID: {$student['StudentID']}");
            } else {
                error_log("DEVICE REGISTRATION FAILED: Could not insert device fingerprint for StudentID: " . $student['StudentID'] . ". Error: " . $insertDeviceStmt->error);
                echo json_encode([
                    'success' => false,
                    'message' => 'Device registration failed. Please contact administrator.',
                    'error_code' => 'DEVICE_INSERT_FAILED'
                ]);
                exit();
            }
            $insertDeviceStmt->close();
        } else {
            error_log("ERROR: No device fingerprint provided for auto-registration of StudentID: " . $student['StudentID']);
            echo json_encode([
                'success' => false,
                'message' => 'Device information missing. Please refresh the page and try again.',
                'error_code' => 'NO_DEVICE_FINGERPRINT'
            ]);
            exit();
        }

        $updateDeviceStmt = $conn->prepare("UPDATE students SET DeviceRegistered = TRUE WHERE StudentID = ?");
        $updateDeviceStmt->bind_param("i", $student['StudentID']);
        if ($updateDeviceStmt->execute()) {
            error_log("AUTO-REGISTRATION SUCCESS: Device registered for StudentID: " . $student['StudentID']);
        } else {
            error_log("AUTO-REGISTRATION FAILED: Could not update DeviceRegistered flag for StudentID: " . $student['StudentID'] . ". Error: " . $updateDeviceStmt->error);
            echo json_encode([
                'success' => false,
                'message' => 'Device registration incomplete. Please contact administrator.',
                'error_code' => 'DEVICE_FLAG_UPDATE_FAILED'
            ]);
            exit();
        }
        $updateDeviceStmt->close();
    } else {
        // Device is registered, but check if the current device fingerprint matches
        if (isset($_POST['device_fingerprint'])) {
            $deviceFingerprint = $_POST['device_fingerprint'];

            $deviceCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM student_devices WHERE StudentID = ? AND DeviceFingerprint = ?");
            $deviceCheckStmt->bind_param("is", $student['StudentID'], $deviceFingerprint);
            $deviceCheckStmt->execute();
            $deviceCheckRes = $deviceCheckStmt->get_result();
            $deviceMatch = $deviceCheckRes->fetch_assoc()['count'] > 0;
            $deviceCheckStmt->close();

            if (!$deviceMatch) {
                // Check if this device belongs to another student
                $otherStudentStmt = $conn->prepare("SELECT s.FullName FROM student_devices sd JOIN students s ON sd.StudentID = s.StudentID WHERE sd.DeviceFingerprint = ? AND sd.StudentID != ?");
                $otherStudentStmt->bind_param("si", $deviceFingerprint, $student['StudentID']);
                $otherStudentStmt->execute();
                $otherStudentRes = $otherStudentStmt->get_result();
                $otherStudent = $otherStudentRes->fetch_assoc();
                $otherStudentStmt->close();

                if ($otherStudent) {
                    error_log("DEVICE MISMATCH: Device {$deviceFingerprint} belongs to another student: {$otherStudent['FullName']}");
                    echo json_encode([
                        'success' => false,
                        'message' => 'This device is registered to another student. Please use your own registered device.',
                        'error_code' => 'DEVICE_BELONGS_TO_OTHER_STUDENT'
                    ]);
                    exit();
                } else {
                    // Device fingerprint not found for this student - this can happen after admin device reset
                    // Auto-register the current device (admin reset scenario)
                    error_log("AUTO-REREGISTERING: Device fingerprint missing for StudentID {$student['StudentID']}, re-registering device {$deviceFingerprint}");

                    // Clean up any old device entries for this student
                    $cleanupStmt = $conn->prepare("DELETE FROM student_devices WHERE StudentID = ?");
                    $cleanupStmt->bind_param("i", $student['StudentID']);
                    $cleanupStmt->execute();
                    $cleanupStmt->close();

                    // Register the current device
                    $insertDeviceStmt = $conn->prepare("INSERT INTO student_devices (StudentID, DeviceFingerprint, RegisteredAt) VALUES (?, ?, NOW())");
                    $insertDeviceStmt->bind_param("is", $student['StudentID'], $deviceFingerprint);
                    if ($insertDeviceStmt->execute()) {
                        error_log("DEVICE RE-REGISTERED: Device {$deviceFingerprint} re-registered to StudentID: {$student['StudentID']}");
                    } else {
                        error_log("DEVICE RE-REGISTRATION FAILED: Could not re-register device for StudentID: " . $student['StudentID']);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Device registration failed. Please contact administrator.',
                            'error_code' => 'DEVICE_REGISTRATION_FAILED'
                        ]);
                        exit();
                    }
                    $insertDeviceStmt->close();
                }
            }
        }
    }

    // Find active QR session with this token - check both IsActive and ExpiresAt
    $qrSessionStmt = $conn->prepare("
        SELECT qs.SessionID, qs.TeacherID, qs.SubjectID, qs.Date, qs.ExpiresAt, s.SubjectName, s.SubjectCode, t.FullName as TeacherName
        FROM qr_attendance_sessions qs
        JOIN subjects s ON qs.SubjectID = s.SubjectID
        JOIN teachers t ON qs.TeacherID = t.TeacherID
        WHERE qs.QRToken = ? AND qs.IsActive = 1 AND qs.ExpiresAt > NOW()
    ");
    $qrSessionStmt->bind_param("s", $token);
    $qrSessionStmt->execute();
    $qrSessionRes = $qrSessionStmt->get_result();
    $qrSession = $qrSessionRes->fetch_assoc();
    $qrSessionStmt->close();

    error_log("QR SESSION LOOKUP: Token = " . substr($token, 0, 10) . "..., Found = " . ($qrSession ? 'YES' : 'NO'));
    if ($qrSession) {
        error_log("QR SESSION DETAILS: " . json_encode($qrSession));
    }

    if (!$qrSession) {
        // Check if session exists but is inactive or expired
        $sessionCheckStmt = $conn->prepare("
            SELECT qs.IsActive, qs.ExpiresAt, s.SubjectCode, s.SubjectName
            FROM qr_attendance_sessions qs
            JOIN subjects s ON qs.SubjectID = s.SubjectID
            WHERE qs.QRToken = ?
        ");
        $sessionCheckStmt->bind_param("s", $token);
        $sessionCheckStmt->execute();
        $sessionCheckRes = $sessionCheckStmt->get_result();
        $sessionCheck = $sessionCheckRes->fetch_assoc();
        $sessionCheckStmt->close();

        if ($sessionCheck) {
            if (!$sessionCheck['IsActive']) {
                error_log("ERROR: QR session is inactive for token: " . substr($token, 0, 10) . "...");
                echo json_encode([
                    'success' => false,
                    'message' => 'QR session has been stopped by the teacher. Please ask your teacher to generate a new QR code.',
                    'subject' => $sessionCheck['SubjectCode'] . ' - ' . $sessionCheck['SubjectName']
                ]);
            } elseif (strtotime($sessionCheck['ExpiresAt']) <= time()) {
                error_log("ERROR: QR session expired for token: " . substr($token, 0, 10) . "...");
                echo json_encode([
                    'success' => false,
                    'message' => 'QR code has expired. Please ask your teacher to generate a new QR code.',
                    'subject' => $sessionCheck['SubjectCode'] . ' - ' . $sessionCheck['SubjectName']
                ]);
            } else {
                error_log("ERROR: Unknown QR session issue for token: " . substr($token, 0, 10) . "...");
                echo json_encode(['success' => false, 'message' => 'QR code is invalid. Please try scanning again.']);
            }
        } else {
            error_log("ERROR: QR session not found for token: " . substr($token, 0, 10) . "...");
            echo json_encode(['success' => false, 'message' => 'Invalid QR code. Please ask your teacher to generate a new QR code.']);
        }
        exit();
    }

    // Check if student is enrolled in this subject
    $enrollmentStmt = $conn->prepare("
        SELECT COUNT(*) as count FROM students s
        JOIN subjects sub ON s.SemesterID = sub.SemesterID AND s.DepartmentID = sub.DepartmentID
        WHERE s.StudentID = ? AND sub.SubjectID = ?
    ");
    $enrollmentStmt->bind_param("ii", $student['StudentID'], $qrSession['SubjectID']);
    $enrollmentStmt->execute();
    $enrollmentRes = $enrollmentStmt->get_result();
    $isEnrolled = $enrollmentRes->fetch_assoc()['count'] > 0;
    $enrollmentStmt->close();

    if (!$isEnrolled) {
        echo json_encode(['success' => false, 'message' => 'You are not enrolled in this subject']);
        exit();
    }

    // Check if attendance already marked for today
    $existingStmt = $conn->prepare("
        SELECT Status, Method FROM attendance_records
        WHERE StudentID = ? AND SubjectID = ? AND TeacherID = ? AND DATE(DateTime) = ?
    ");
    $existingStmt->bind_param("iiis", $student['StudentID'], $qrSession['SubjectID'], $qrSession['TeacherID'], $qrSession['Date']);
    $existingStmt->execute();
    $existingRes = $existingStmt->get_result();
    $existing = $existingRes->fetch_assoc();
    $existingStmt->close();

    if ($existing) {
        if ($existing['Method'] === 'qr') {
            echo json_encode([
                'success' => false,
                'message' => 'You have already marked attendance via QR code for this subject today',
                'subject' => $qrSession['SubjectCode'] . ' - ' . $qrSession['SubjectName'],
                'status' => $existing['Status']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Your attendance has already been marked manually for this subject today',
                'subject' => $qrSession['SubjectCode'] . ' - ' . $qrSession['SubjectName'],
                'status' => $existing['Status']
            ]);
        }
        exit();
    }

    // Check if student already has a pending QR scan for this session
    $pendingStmt = $conn->prepare("
        SELECT PendingID, CreatedAt FROM qr_attendance_pending
        WHERE SessionID = ? AND StudentID = ?
    ");
    $pendingStmt->bind_param("ii", $qrSession['SessionID'], $student['StudentID']);
    $pendingStmt->execute();
    $pendingRes = $pendingStmt->get_result();
    $existingPending = $pendingRes->fetch_assoc();
    $pendingStmt->close();

    if ($existingPending) {
        // Allow re-scan if more than 1 minute has passed
        $lastScan = new DateTime($existingPending['CreatedAt']);
        $now = new DateTime();
        $timeDiff = $now->getTimestamp() - $lastScan->getTimestamp();

        if ($timeDiff < 60) { // Less than 1 minute ago
            echo json_encode([
                'success' => false,
                'message' => 'You scanned ' . $timeDiff . ' seconds ago. Please wait for teacher to save attendance or try again in 1 minute.',
                'subject' => $qrSession['SubjectCode'] . ' - ' . $qrSession['SubjectName'],
                'status' => 'pending'
            ]);
            exit();
        } else {
            // Update existing pending record  
            $updatePendingStmt = $conn->prepare("
                UPDATE qr_attendance_pending 
                SET CreatedAt = NOW()
                WHERE PendingID = ?
            ");
            $updatePendingStmt->bind_param("i", $existingPending['PendingID']);
            $updatePendingStmt->execute();
            $updatePendingStmt->close();

            error_log("UPDATED PENDING QR SCAN: StudentID {$student['StudentID']} - updated existing pending record");
        }
    } else {
        // Insert new pending QR scan
        $insertPendingStmt = $conn->prepare("
            INSERT INTO qr_attendance_pending (SessionID, StudentID, TeacherID, SubjectID, CreatedAt, Status)
            VALUES (?, ?, ?, ?, NOW(), 'present')
        ");
        $insertPendingStmt->bind_param("iiii", $qrSession['SessionID'], $student['StudentID'], $qrSession['TeacherID'], $qrSession['SubjectID']);
        $insertPendingStmt->execute();
        $insertPendingStmt->close();

        error_log("INSERTED NEW PENDING QR SCAN: StudentID {$student['StudentID']} - new pending record created");
        
        // Create notification for teacher about QR scan
        notifyQRScan($conn, $student['StudentID'], $qrSession['TeacherID'], $qrSession['SubjectID'], $qrSession['SessionID']);
    }

    echo json_encode([
        'success' => true,
        'message' => 'QR scanned successfully! Your attendance is pending teacher approval.',
        'subject' => $qrSession['SubjectCode'] . ' - ' . $qrSession['SubjectName'],
        'teacher' => $qrSession['TeacherName'],
        'time' => date('Y-m-d H:i:s'),
        'status' => 'pending',
        'method' => 'qr'
    ]);
} catch (Exception $e) {
    error_log("QR Attendance Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
}
