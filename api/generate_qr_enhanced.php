<?php

/**
 * Enhanced QR Code Generator for AttendifyPlus
 * Supports both JSON and image output formats
 * Uses Composer-based libraries for high-quality QR generation
 */

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../config/db_config.php');

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Color\Color;

// Check format parameter
$format = $_REQUEST['format'] ?? 'png'; // png, svg, json
$responseType = ($format === 'json') ? 'json' : 'image';

// Set appropriate headers based on response type
if ($responseType === 'json') {
    header('Content-Type: application/json');
} else {
    header('Content-Type: image/png');
}
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Pragma: no-cache');
header('Expires: 0');

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Get parameters
if ($responseType === 'json') {
    // For JSON responses, we expect semester, subject, date
    $semester = $_REQUEST['semester'] ?? '';
    $subject = $_REQUEST['subject'] ?? '';
    $date = $_REQUEST['date'] ?? '';
    $attendanceId = $_REQUEST['attendance_id'] ?? '';

    // Validate required parameters
    if (empty($semester) || empty($subject) || empty($date)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: semester, subject, date'
        ]);
        exit();
    }

    // Generate or retrieve token
    try {    // Generate or retrieve attendance ID and token
        if (empty($attendanceId)) {
            // Create new attendance session in qr_attendance_sessions table
            $stmt = $conn->prepare("
            INSERT INTO qr_attendance_sessions (TeacherID, SubjectID, Date, QRToken, ExpiresAt, IsActive, CreatedAt) 
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 SECOND), 1, NOW())
        ");

            // Get teacher ID from session
            session_start();
            if (!isset($_SESSION['LoginID']) || strtolower($_SESSION['Role']) !== 'teacher') {
                throw new Exception("Unauthorized: Teacher access required");
            }

            $teacherStmt = $conn->prepare("SELECT TeacherID FROM teachers WHERE LoginID = ?");
            $teacherStmt->bind_param("i", $_SESSION['LoginID']);
            $teacherStmt->execute();
            $teacherResult = $teacherStmt->get_result();
            $teacherData = $teacherResult->fetch_assoc();
            $teacherStmt->close();

            if (!$teacherData) {
                throw new Exception("Teacher not found");
            }

            $teacherID = $teacherData['TeacherID'];

            // Generate a unique token for this session
            $token = bin2hex(random_bytes(16)); // 32 character token

            $stmt->bind_param("iiss", $teacherID, $subject, $date, $token);
            $stmt->execute();
            $attendanceId = $conn->insert_id;
            $stmt->close();

            error_log("Created new QR attendance session: ID=$attendanceId, Token=" . substr($token, 0, 8) . "...");
        } else {
            // Retrieve existing token from qr_attendance_sessions
            $stmt = $conn->prepare("SELECT QRToken FROM qr_attendance_sessions WHERE SessionID = ? AND IsActive = 1");
            $stmt->bind_param("i", $attendanceId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $token = $row['QRToken'];
            } else {
                throw new Exception("Active QR session not found");
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'QR generation error: ' . $e->getMessage()
        ]);
        exit();
    }
} else {
    // For image responses, we expect a token parameter
    $token = $_REQUEST['token'] ?? '';
    if (empty($token)) {
        generateErrorImage('No Token Provided', $size);
        exit();
    }
}

// Size and mode parameters
$size = (int)($_REQUEST['size'] ?? 300); // Default larger size for classroom projection
$mode = $_REQUEST['mode'] ?? 'classroom'; // classroom, standard, compact

// Validate inputs
$size = max(200, min(800, $size)); // Allow larger sizes for projection
$mode = in_array($mode, ['classroom', 'standard', 'compact']) ? $mode : 'classroom';

try {
    // Create the scan URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'];
    $basePath = dirname(dirname($_SERVER['REQUEST_URI']));
    $scanUrl = $baseUrl . $basePath . "/views/student/scan_qr.php?token=" . urlencode($token);

    // Log the URL being generated
    error_log("Enhanced QR Generator: Creating QR for URL: " . $scanUrl);

    // For JSON response type, return the data without generating image
    if ($responseType === 'json') {
        echo json_encode([
            'success' => true,
            'qr_data' => $scanUrl,
            'session_id' => $attendanceId,
            'token' => $token,
            'expiry_time' => time() + (10 * 60) // 10 minute expiry
        ]);
        exit();
    }

    // Configure QR code based on mode
    $qrCode = new QrCode($scanUrl);
    $qrCode->setEncoding(new Encoding('UTF-8'));
    $qrCode->setRoundBlockSizeMode(new RoundBlockSizeModeMargin());

    // Mode-specific configurations
    switch ($mode) {
        case 'classroom':
            // Optimized for classroom projection and distance scanning
            $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh()); // High error correction
            $qrCode->setSize($size);
            $qrCode->setMargin(20); // Larger margin for better scanning
            $foregroundColor = [0, 0, 0]; // Pure black for maximum contrast
            $backgroundColor = [255, 255, 255]; // Pure white background
            break;

        case 'projector':
            // Extra large for projector display
            $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh());
            $qrCode->setSize($size);
            $qrCode->setMargin(30); // Extra large margin for projector
            $foregroundColor = [0, 0, 0]; // Pure black for maximum contrast
            $backgroundColor = [255, 255, 255]; // Pure white background
            break;

        case 'standard':
            // Standard quality for normal use
            $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh());
            $qrCode->setSize($size);
            $qrCode->setMargin(10);
            $foregroundColor = [26, 115, 232]; // AttendifyPlus blue
            $backgroundColor = [255, 255, 255];
            break;

        case 'compact':
            // Compact for small displays
            $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh());
            $qrCode->setSize($size);
            $qrCode->setMargin(5);
            $foregroundColor = [0, 0, 0];
            $backgroundColor = [255, 255, 255];
            break;
    }

    // Set colors - Convert arrays to Color objects
    $foregroundColorObj = new Color($foregroundColor[0], $foregroundColor[1], $foregroundColor[2]);
    $backgroundColorObj = new Color($backgroundColor[0], $backgroundColor[1], $backgroundColor[2]);

    $qrCode->setForegroundColor($foregroundColorObj);
    $qrCode->setBackgroundColor($backgroundColorObj);

    // Generate based on format
    if ($format === 'svg') {
        // SVG format - scalable and crisp
        header('Content-Type: image/svg+xml');
        $writer = new SvgWriter();
        $result = $writer->write($qrCode);
        echo $result->getString();

        error_log("Enhanced QR Generator: Successfully generated SVG QR code");
    } else {
        // PNG format - default
        header('Content-Type: image/png');
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        echo $result->getString();

        error_log("Enhanced QR Generator: Successfully generated PNG QR code (mode: $mode, size: $size)");
    }
} catch (Exception $e) {
    error_log("Enhanced QR Generator Error: " . $e->getMessage());
    generateErrorImage('QR Generation Failed: ' . $e->getMessage(), $size);
}

/**
 * Generate an error image when QR generation fails
 */
function generateErrorImage($message, $size)
{
    // Create a simple error image
    $im = imagecreatetruecolor($size, $size);

    if (!$im) {
        echo "Error creating image";
        return;
    }

    // Colors
    $bg = imagecolorallocate($im, 255, 255, 255); // White background
    $border = imagecolorallocate($im, 220, 53, 69); // Bootstrap danger red
    $text = imagecolorallocate($im, 220, 53, 69);
    $gray = imagecolorallocate($im, 108, 117, 125); // Bootstrap gray

    // Fill background
    imagefill($im, 0, 0, $bg);

    // Draw border
    imagerectangle($im, 0, 0, $size - 1, $size - 1, $border);
    imagerectangle($im, 2, 2, $size - 3, $size - 3, $border);

    // Add error icon (simple X)
    $centerX = $size / 2;
    $centerY = $size / 2;
    $iconSize = min(40, $size / 6);

    // Draw X
    imageline($im, $centerX - $iconSize, $centerY - $iconSize, $centerX + $iconSize, $centerY + $iconSize, $border);
    imageline($im, $centerX + $iconSize, $centerY - $iconSize, $centerX - $iconSize, $centerY + $iconSize, $border);

    // Add text
    $fontSize = min(5, max(1, $size / 100));
    $textY = $centerY + $iconSize + 20;

    // Wrap text for better display
    $words = explode(' ', $message);
    $lines = [];
    $currentLine = '';
    $maxWidth = ($size - 20) / 6; // Approximate character width

    foreach ($words as $word) {
        if (strlen($currentLine . ' ' . $word) <= $maxWidth) {
            $currentLine .= ($currentLine ? ' ' : '') . $word;
        } else {
            if ($currentLine) {
                $lines[] = $currentLine;
            }
            $currentLine = $word;
        }
    }
    if ($currentLine) {
        $lines[] = $currentLine;
    }

    // Draw text lines
    foreach ($lines as $i => $line) {
        $textX = ($size - strlen($line) * 6) / 2; // Center text
        imagestring($im, $fontSize, max(5, $textX), $textY + ($i * 15), $line, $text);
    }

    // Add footer
    imagestring($im, 2, 5, $size - 15, 'AttendifyPlus QR', $gray);

    // Output and cleanup
    imagepng($im);
    imagedestroy($im);
}
