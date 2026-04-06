<?php
/**
 * Export Functions - shared utilities for all report exports
 * Path: modules/reports/functions/export-functions.php
 */

/**
 * Set HTTP headers for a file download
 */
function setDownloadHeaders(string $filename, string $mimeType): void
{
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
}

/**
 * Format report rows as CSV string
 */
function rowsToCsv(array $headers, array $rows): string
{
    $buffer = fopen('php://temp', 'r+');
    fputcsv($buffer, $headers);
    foreach ($rows as $row) {
        fputcsv($buffer, array_values($row));
    }
    rewind($buffer);
    $csv = stream_get_contents($buffer);
    fclose($buffer);
    return $csv;
}

/**
 * Stream a CSV string directly to the browser
 */
function streamCsv(string $filename, array $headers, array $rows): void
{
    setDownloadHeaders($filename, 'text/csv; charset=utf-8');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}

/**
 * Format a number as Philippine Peso
 */
function formatPeso(float $amount): string
{
    return '₱ ' . number_format($amount, 2);
}

/**
 * Convert date range to human-readable label
 */
function formatDateRange(string $from, string $to): string
{
    $f = date('M j, Y', strtotime($from));
    $t = date('M j, Y', strtotime($to));
    return "{$f} – {$t}";
}
