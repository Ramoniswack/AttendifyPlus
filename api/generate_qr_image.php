<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\api\generate_qr_image.php

header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Pragma: no-cache');
header('Expires: 0');

// Add error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in image output

$token = $_GET['token'] ?? '';
$size = (int)($_GET['size'] ?? 200);

// Ensure size is within reasonable bounds
$size = max(100, min(400, $size));

if (empty($token)) {
    // Return a simple error image
    $im = imagecreate(200, 200);
    $bg = imagecolorallocate($im, 255, 255, 255);
    $text_color = imagecolorallocate($im, 255, 0, 0);
    $border = imagecolorallocate($im, 0, 0, 0);

    // Draw border
    imagerectangle($im, 0, 0, 199, 199, $border);

    // Add error text
    imagestring($im, 3, 50, 90, 'No Token', $text_color);
    imagestring($im, 2, 30, 110, 'Provided', $text_color);

    imagepng($im);
    imagedestroy($im);
    exit();
}

// Create the scan URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'];
$basePath = dirname(dirname($_SERVER['REQUEST_URI']));
$scanUrl = $baseUrl . $basePath . "/views/student/scan_qr.php?token=" . urlencode($token);

// Log the URL being generated (for debugging)
error_log("QR Generator: Creating QR for URL: " . $scanUrl);

// Try multiple QR code generation methods
$qrGenerated = false;
$method_used = 'unknown';

// Method 1: Google Charts API
if (!$qrGenerated) {
    try {
        $googleQR = "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl=" . urlencode($scanUrl) . "&choe=UTF-8&chld=M|2";

        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'ignore_errors' => true,
                'user_agent' => 'AttendifyPlus QR Generator 1.0',
                'method' => 'GET'
            ],
            'https' => [
                'timeout' => 8,
                'ignore_errors' => true,
                'user_agent' => 'AttendifyPlus QR Generator 1.0',
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $qrImage = @file_get_contents($googleQR, false, $context);

        if ($qrImage !== false && !empty($qrImage) && strlen($qrImage) > 100) {
            error_log("QR Generator: Successfully used Google Charts API");
            $method_used = 'google';
            echo $qrImage;
            $qrGenerated = true;
        }
    } catch (Exception $e) {
        error_log("QR Generator: Google Charts failed - " . $e->getMessage());
    }
}

// Method 2: QR Server API (alternative)
if (!$qrGenerated) {
    try {
        $qrServerUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($scanUrl) . "&format=png&margin=10";

        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'ignore_errors' => true,
                'user_agent' => 'AttendifyPlus QR Generator 1.0',
                'method' => 'GET'
            ],
            'https' => [
                'timeout' => 8,
                'ignore_errors' => true,
                'user_agent' => 'AttendifyPlus QR Generator 1.0',
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $qrImage = @file_get_contents($qrServerUrl, false, $context);

        if ($qrImage !== false && !empty($qrImage) && strlen($qrImage) > 100) {
            error_log("QR Generator: Successfully used QR Server API");
            $method_used = 'qrserver';
            echo $qrImage;
            $qrGenerated = true;
        }
    } catch (Exception $e) {
        error_log("QR Generator: QR Server failed - " . $e->getMessage());
    }
}

// Method 3: QuickChart API (another alternative)
if (!$qrGenerated) {
    try {
        $quickChartUrl = "https://quickchart.io/qr?text=" . urlencode($scanUrl) . "&size={$size}&format=png&margin=2";

        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'ignore_errors' => true,
                'user_agent' => 'AttendifyPlus QR Generator 1.0'
            ],
            'https' => [
                'timeout' => 8,
                'ignore_errors' => true,
                'user_agent' => 'AttendifyPlus QR Generator 1.0',
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $qrImage = @file_get_contents($quickChartUrl, false, $context);

        if ($qrImage !== false && !empty($qrImage) && strlen($qrImage) > 100) {
            error_log("QR Generator: Successfully used QuickChart API");
            $method_used = 'quickchart';
            echo $qrImage;
            $qrGenerated = true;
        }
    } catch (Exception $e) {
        error_log("QR Generator: QuickChart failed - " . $e->getMessage());
    }
}

// Method 4: QRCode Generator API
if (!$qrGenerated) {
    try {
        $qrCodeGenUrl = "https://qr-code-generator.com/api/qr-code.php?size={$size}&data=" . urlencode($scanUrl);

        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'ignore_errors' => true,
                'user_agent' => 'AttendifyPlus QR Generator 1.0'
            ],
            'https' => [
                'timeout' => 8,
                'ignore_errors' => true,
                'user_agent' => 'AttendifyPlus QR Generator 1.0',
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $qrImage = @file_get_contents($qrCodeGenUrl, false, $context);

        if ($qrImage !== false && !empty($qrImage) && strlen($qrImage) > 100) {
            error_log("QR Generator: Successfully used QRCode Generator API");
            $method_used = 'qrcodegen';
            echo $qrImage;
            $qrGenerated = true;
        }
    } catch (Exception $e) {
        error_log("QR Generator: QRCode Generator failed - " . $e->getMessage());
    }
}

// Fallback: Generate a visual QR-like image with improved design
if (!$qrGenerated) {
    error_log("QR Generator: Using fallback image generation");
    $method_used = 'fallback';

    // Use imagecreatetruecolor for better quality
    $im = imagecreatetruecolor($size, $size);

    // Enable anti-aliasing if available
    if (function_exists('imageantialias')) {
        imageantialias($im, true);
    }

    $bg = imagecolorallocate($im, 255, 255, 255);
    $border = imagecolorallocate($im, 0, 0, 0);
    $text_color = imagecolorallocate($im, 0, 100, 200);
    $green = imagecolorallocate($im, 0, 150, 0);
    $pattern = imagecolorallocate($im, 50, 50, 50);
    $light_pattern = imagecolorallocate($im, 150, 150, 150);

    // Fill with white background
    imagefill($im, 0, 0, $bg);

    // Draw border
    imagerectangle($im, 0, 0, $size - 1, $size - 1, $border);
    imagerectangle($im, 2, 2, $size - 3, $size - 3, $border);

    // Generate a more complex pattern based on the token
    $hash = hash('md5', $token);
    $gridSize = min(25, max(15, $size / 12));
    $cellSize = max(1, ($size - 20) / $gridSize);

    // Create a more QR-like pattern
    for ($i = 0; $i < $gridSize; $i++) {
        for ($j = 0; $j < $gridSize; $j++) {
            // Use hash characters to determine pattern
            $hashIndex = ($i * $gridSize + $j) % strlen($hash);
            $hashChar = hexdec($hash[$hashIndex]);

            $x = 10 + $i * $cellSize;
            $y = 10 + $j * $cellSize;

            // Create varied patterns based on hash
            if ($hashChar % 3 === 0) {
                imagefilledrectangle($im, $x, $y, $x + $cellSize - 1, $y + $cellSize - 1, $pattern);
            } elseif ($hashChar % 5 === 0) {
                imagefilledrectangle($im, $x, $y, $x + $cellSize - 1, $y + $cellSize - 1, $light_pattern);
            }
        }
    }

    // Add corner markers (QR code style) - improved
    $markerSize = max(3, $cellSize * 3);
    $positions = [[1, 1], [1, $gridSize - 4], [$gridSize - 4, 1]];

    foreach ($positions as [$x, $y]) {
        $startX = 10 + $x * $cellSize;
        $startY = 10 + $y * $cellSize;

        // Ensure markers fit within bounds
        if ($startX + $markerSize < $size - 10 && $startY + $markerSize < $size - 10) {
            // Outer square
            imagefilledrectangle($im, $startX, $startY, $startX + $markerSize, $startY + $markerSize, $border);
            // Inner white
            imagefilledrectangle($im, $startX + 2, $startY + 2, $startX + $markerSize - 2, $startY + $markerSize - 2, $bg);
            // Center dot
            imagefilledrectangle($im, $startX + 4, $startY + 4, $startX + $markerSize - 4, $startY + $markerSize - 4, $border);
        }
    }

    // Add text content based on size
    if ($size >= 250) {
        // Large size - more information
        imagestring($im, 5, max(10, $size / 2 - 60), $size - 60, 'ATTENDANCE QR', $green);
        imagestring($im, 3, 10, $size - 40, 'Token: ' . substr($token, 0, min(20, ($size - 20) / 6)), $text_color);
        if (strlen($token) > 20) {
            imagestring($im, 3, 10, $size - 25, substr($token, 20, min(20, ($size - 20) / 6)), $text_color);
        }
        imagestring($im, 2, 10, $size - 12, 'Scan or use manual entry', $border);
    } elseif ($size >= 200) {
        // Medium size
        imagestring($im, 4, max(10, $size / 2 - 50), $size - 45, 'ATTENDANCE QR', $green);
        imagestring($im, 2, 10, $size - 25, 'Token: ' . substr($token, 0, min(16, ($size - 20) / 8)), $text_color);
        if (strlen($token) > 16) {
            imagestring($im, 2, 10, $size - 12, substr($token, 16, min(16, ($size - 20) / 8)), $text_color);
        }
    } elseif ($size >= 150) {
        // Small size - minimal text
        imagestring($im, 3, 10, $size - 30, 'QR CODE', $green);
        imagestring($im, 1, 10, $size - 15, substr($token, 0, min(12, $size / 10)), $text_color);
    } else {
        // Very small - just basic text
        imagestring($im, 2, 5, $size - 20, 'QR', $green);
    }

    // Add a small indicator that this is a fallback
    imagestring($im, 1, $size - 30, 5, 'v' . $method_used, $light_pattern);

    imagepng($im);
    imagedestroy($im);
}

// Log the method used
error_log("QR Generator: Completed using method: " . $method_used);
