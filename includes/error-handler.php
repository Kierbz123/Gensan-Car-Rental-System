<?php
// includes/error-handler.php

/**
 * Global Exception and Error Handler
 */

set_exception_handler(function ($exception) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<div style='padding: 20px; background: #fee; border: 1px solid #f99; border-radius: 8px; font-family: sans-serif;'>";
        echo "<h2 style='color: #900;'>System Exception</h2>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . " (Line " . $exception->getLine() . ")</p>";
        echo "<pre style='background: #fff; padding: 10px;'>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        echo "</div>";
    } else {
        // Production view
        if (file_exists(__DIR__ . '/500.php')) {
            include_once __DIR__ . '/500.php';
        } else {
            http_response_code(500);
            echo "<h1>500 Internal Server Error</h1><p>The system encountered an intelligence breach. Please contact support.</p>";
        }
        logError("Exception: " . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
    }
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno))
        return;

    $message = "Error [$errno]: $errstr in $errfile on line $errline";

    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<div style='color: #856404; background-color: #fff3cd; border: 1px solid #ffeeba; padding: 10px; margin: 10px 0; border-radius: 4px;'>$message</div>";
    }

    logError($message);
    return true;
});
