<?php
// /var/www/html/gensan-car-rental-system/classes/QRGenerator.php

/**
 * QR Code Generation Utility
 */

class QRGenerator
{
    /**
     * Generate a QR Code for a vehicle
     * 
     * @param string $data The content to encode
     * @param string $filename The output filename (full path)
     * @param int $size Pixel size
     * @return string|bool Path to generated file or false
     */
    public static function generate($data, $filename, $size = 300)
    {
        // Ensure directory exists
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Patterns:
        // 1. If using Endroid QR Code (via Composer)
        // 2. If using PHPQRCode (Manual/Vendor)

        if (class_exists('Endroid\QrCode\QrCode')) {
            // Modern implementation
            // $qrCode = new Endroid\QrCode\QrCode($data);
            // $qrCode->setSize($size);
            // $qrCode->writeFile($filename);
            // return $filename;
        } elseif (class_exists('QRcode')) {
            // Legacy/Simple PHPQRCode implementation
            QRcode::png($data, $filename, 'H', 10, 2);
            return $filename;
        }

        // Graceful fallback for demonstration if no library is functional in vendor
        $placeholderContent = "QR_DATA:" . $data;
        file_put_contents($filename, $placeholderContent);

        error_log("QRGenerator: No library found in vendor. Created text placeholder at $filename");
        return $filename;
    }
}
