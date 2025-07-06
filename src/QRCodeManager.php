<?php

/**
 * QR Code Manager for AttendifyPlus
 * Handles QR code generation with classroom-optimized settings
 */

namespace AttendifyPlus;

require_once(__DIR__ . '/../vendor/autoload.php');

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Color\Color;

class QRCodeManager
{
    // Predefined configurations for different use cases
    public const CLASSROOM_MODE = 'classroom';
    public const STANDARD_MODE = 'standard';
    public const COMPACT_MODE = 'compact';
    public const PROJECTOR_MODE = 'projector';

    private $configurations = [
        self::CLASSROOM_MODE => [
            'size' => 300,
            'margin' => 20,
            'error_correction' => 'high',
            'foreground' => [0, 0, 0],
            'background' => [255, 255, 255],
            'description' => 'Optimized for classroom scanning from various distances'
        ],
        self::PROJECTOR_MODE => [
            'size' => 400,
            'margin' => 30,
            'error_correction' => 'high',
            'foreground' => [0, 0, 0],
            'background' => [255, 255, 255],
            'description' => 'Extra large for projector display with maximum contrast'
        ],
        self::STANDARD_MODE => [
            'size' => 250,
            'margin' => 15,
            'error_correction' => 'high',
            'foreground' => [26, 115, 232],
            'background' => [255, 255, 255],
            'description' => 'Standard quality with AttendifyPlus branding'
        ],
        self::COMPACT_MODE => [
            'size' => 200,
            'margin' => 10,
            'error_correction' => 'medium',
            'foreground' => [0, 0, 0],
            'background' => [255, 255, 255],
            'description' => 'Compact size for mobile displays'
        ]
    ];

    /**
     * Generate QR code with specified configuration
     */
    public function generateQRCode($data, $mode = self::CLASSROOM_MODE, $format = 'png', $customOptions = [])
    {
        try {
            if (!isset($this->configurations[$mode])) {
                $mode = self::CLASSROOM_MODE;
            }

            $config = array_merge($this->configurations[$mode], $customOptions);

            // Create QR code instance
            $qrCode = new QrCode($data);
            $qrCode->setEncoding(new Encoding('UTF-8'));
            $qrCode->setRoundBlockSizeMode(new RoundBlockSizeModeMargin());

            // Set size and margin
            $qrCode->setSize($config['size']);
            $qrCode->setMargin($config['margin']);

            // Set error correction level
            if ($config['error_correction'] === 'high') {
                $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh());
            } else {
                $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevelMedium());
            }

            // Set colors - Convert array to Color objects
            $foregroundColor = new Color($config['foreground'][0], $config['foreground'][1], $config['foreground'][2]);
            $backgroundColor = new Color($config['background'][0], $config['background'][1], $config['background'][2]);

            $qrCode->setForegroundColor($foregroundColor);
            $qrCode->setBackgroundColor($backgroundColor);

            // Generate based on format
            if ($format === 'svg') {
                $writer = new SvgWriter();
                return [
                    'content' => $writer->write($qrCode)->getString(),
                    'mime_type' => 'image/svg+xml',
                    'success' => true
                ];
            } else {
                $writer = new PngWriter();
                return [
                    'content' => $writer->write($qrCode)->getString(),
                    'mime_type' => 'image/png',
                    'success' => true
                ];
            }
        } catch (\Exception $e) {
            return [
                'content' => null,
                'mime_type' => null,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get available configurations
     */
    public function getConfigurations()
    {
        return $this->configurations;
    }

    /**
     * Generate attendance scan URL
     */
    public function generateAttendanceScanUrl($token, $baseUrl = null)
    {
        if (!$baseUrl) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'];
            $basePath = dirname(dirname($_SERVER['REQUEST_URI']));
            $baseUrl .= $basePath;
        }

        return $baseUrl . "/views/student/scan_qr.php?token=" . urlencode($token);
    }

    /**
     * Generate QR code for attendance with optimized settings
     */
    public function generateAttendanceQR($token, $mode = self::CLASSROOM_MODE, $format = 'png')
    {
        $scanUrl = $this->generateAttendanceScanUrl($token);
        return $this->generateQRCode($scanUrl, $mode, $format);
    }

    /**
     * Generate data URL for direct embedding
     */
    public function generateDataUrl($data, $mode = self::CLASSROOM_MODE, $format = 'png')
    {
        $result = $this->generateQRCode($data, $mode, $format);

        if ($result['success']) {
            $base64 = base64_encode($result['content']);
            return "data:{$result['mime_type']};base64,{$base64}";
        }

        return null;
    }

    /**
     * Test QR code generation capabilities
     */
    public function testGeneration()
    {
        $testData = "https://example.com/test";
        $results = [];

        foreach (array_keys($this->configurations) as $mode) {
            foreach (['png', 'svg'] as $format) {
                $result = $this->generateQRCode($testData, $mode, $format);
                $results[] = [
                    'mode' => $mode,
                    'format' => $format,
                    'success' => $result['success'],
                    'error' => $result['error'] ?? null,
                    'size' => $result['success'] ? strlen($result['content']) : 0
                ];
            }
        }

        return $results;
    }
}
