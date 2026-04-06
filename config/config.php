<?php
// config/config.php

/**
 * Gensan Car Rental Services - Main Configuration
 * Integrated Asset Tracking & Procurement Management System
 */

// Prevent direct access
if (!defined('GCR_SYSTEM')) {
    define('GCR_SYSTEM', true);
}

// Load environment variables from .env file
$_envFile = dirname(__DIR__) . '/.env';
if (file_exists($_envFile)) {
    $_envLines = file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($_envLines as $_line) {
        $_line = trim($_line);
        if ($_line === '' || $_line[0] === '#')
            continue;
        if (strpos($_line, '=') === false)
            continue;
        [$_key, $_val] = explode('=', $_line, 2);
        $_ENV[trim($_key)] = trim($_val);
    }
    unset($_envLines, $_line, $_key, $_val);
}
unset($_envFile);

// Environment settings
define('ENVIRONMENT', $_ENV['ENVIRONMENT'] ?? 'development'); // development, production
define('DEBUG_MODE', ENVIRONMENT === 'development');

// Display errors in development only
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// System paths
define('BASE_PATH', dirname(__DIR__) . '/');
define('CONFIG_PATH', BASE_PATH . 'config/');
define('INCLUDES_PATH', BASE_PATH . 'includes/');
define('MODULES_PATH', BASE_PATH . 'modules/');
define('CLASSES_PATH', BASE_PATH . 'classes/');
define('ASSETS_PATH', BASE_PATH . 'assets/');
define('LOGS_PATH', BASE_PATH . 'logs/');
define('BACKUPS_PATH', BASE_PATH . 'backups/');
define('TEMP_PATH', BASE_PATH . 'temp/');

// URL paths
// Dynamically calculate BASE_URL based on the physical path relative to the Document Root
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$basePath = str_replace('\\', '/', BASE_PATH);
$baseUrl = '/' . ltrim(str_replace($docRoot, '', $basePath), '/');
$baseUrl = rtrim($baseUrl, '/') . '/';

define('BASE_URL', $baseUrl);
define('ASSETS_URL', BASE_URL . 'assets/');
define('MODULES_URL', BASE_URL . 'modules/');
define('API_URL', BASE_URL . 'api/v1/');

// Absolute URL (with scheme + host) — used for QR codes that must be scannable on mobile devices.
// When accessed via "localhost", QR codes would embed "localhost" which phones cannot resolve.
// We detect this and substitute the machine's real LAN IP instead.
// Override entirely by setting APP_URL in .env (e.g. https://your-domain.com/)
$_appUrlEnv = $_ENV['APP_URL'] ?? '';
if (!empty($_appUrlEnv)) {
    define('APP_URL', rtrim($_appUrlEnv, '/') . '/');
} else {
    $_scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $_host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $_rawHost = strtolower(explode(':', $_host)[0]); // strip port
    // If running on localhost/127.0.0.1, resolve to real LAN IP so phones can reach it
    if ($_rawHost === 'localhost' || $_rawHost === '127.0.0.1') {
        $_lanIp = gethostbyname(gethostname());
        // gethostbyname returns the hostname unchanged if resolution fails — fall back gracefully
        if ($_lanIp && $_lanIp !== gethostname() && filter_var($_lanIp, FILTER_VALIDATE_IP)) {
            // Preserve any port suffix from HTTP_HOST (e.g. localhost:8080 → 192.168.x.x:8080)
            $_port   = strpos($_host, ':') !== false ? ':' . explode(':', $_host)[1] : '';
            $_host   = $_lanIp . $_port;
            unset($_port);
        }
        unset($_lanIp);
    }
    define('APP_URL', $_scheme . '://' . $_host . $baseUrl);
    unset($_scheme, $_host, $_rawHost);
}
unset($_appUrlEnv);

// Database configuration (loaded from .env, with safe fallbacks for dev)
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'gensan_car_rental_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'gcr_user');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// Session configuration
// SESSION_NAME = the custom DB-backed cookie used by User::createSession/validateSession
// PHP_SESSION_NAME = PHP's native session (must be different to avoid collisions)
define('SESSION_NAME', 'GCR_Session');
define('PHP_SESSION_NAME', 'GCR_PHP_Session');
define('SESSION_TIMEOUT', 3600); // 1 hour
define('SESSION_PATH', TEMP_PATH . 'sessions/');

// Security settings
define('HASH_COST', 12); // Bcrypt cost factor
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 1800); // 30 minutes
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour

// File upload settings
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'image/jpeg', 'image/png']);

// Upload paths
define('UPLOAD_PATH', ASSETS_PATH . 'images/uploads/');
define('VEHICLE_PHOTOS_PATH', ASSETS_PATH . 'images/vehicles/');
define('QR_CODES_PATH', ASSETS_PATH . 'images/qr-codes/');
define('DOCUMENTS_PATH', UPLOAD_PATH . 'documents/');
define('CUSTOMER_IDS_PATH', UPLOAD_PATH . 'customer-ids/');
define('DAMAGE_PHOTOS_PATH', UPLOAD_PATH . 'damage-photos/');
define('MAINTENANCE_PHOTOS_PATH', UPLOAD_PATH . 'maintenance/');
define('SIGNATURES_PATH', UPLOAD_PATH . 'signatures/');
define('AGREEMENTS_PATH', UPLOAD_PATH . 'agreements/');

// Company information
define('COMPANY_NAME', 'Gensan Car Rental Services');
define('COMPANY_ADDRESS', 'Plaza Heneral Santos, Pendatun Avenue, General Santos City, South Cotabato, Philippines 9500');
define('COMPANY_PHONE', '+63-965-129-6777');
define('COMPANY_EMAIL', 'info@gensancarrental.com');

// Date and time settings
date_default_timezone_set('Asia/Manila');
define('DATE_FORMAT', 'F j, Y');
define('DATETIME_FORMAT', 'F j, Y g:i A');
define('DB_DATE_FORMAT', 'Y-m-d');
define('DB_DATETIME_FORMAT', 'Y-m-d H:i:s');

// Pagination defaults
define('ITEMS_PER_PAGE', 25);
define('MAX_ITEMS_PER_PAGE', 100);

// Email settings (SMTP) — loaded from .env
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', (int) ($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? '');
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? '');
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@gensancarrental.com');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'Gensan Car Rental Services');

// Encryption key — MUST be set in .env for production
define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? 'CHANGE_ME_IN_PRODUCTION');

// Currency settings
define('CURRENCY_SYMBOL', '₱');
define('CURRENCY_CODE', 'PHP');
define('CURRENCY_DECIMALS', 2);

// Maintenance alert settings
define('MAINTENANCE_ALERT_DAYS', 7);
define('COMPLIANCE_ALERT_DAYS', 30);

// Approval limits (PHP)
define('PR_APPROVAL_LEVEL1_LIMIT', 5000);
define('PR_APPROVAL_LEVEL2_LIMIT', 20000);

// QR Code settings
define('QR_CODE_SIZE', 300);
define('QR_CODE_FORMAT', 'png');

// Load additional configurations
require_once CONFIG_PATH . 'constants.php';
require_once CONFIG_PATH . 'database.php';

// Load Composer's autoloader if it exists
if (file_exists(BASE_PATH . 'vendor/autoload.php')) {
    require_once BASE_PATH . 'vendor/autoload.php';
}

// Autoloader for local classes
spl_autoload_register(function ($class) {
    // If the class has a namespace (like PhpOffice), the local autoloader should skip it
    if (strpos($class, '\\') !== false) {
        return;
    }
    $file = CLASSES_PATH . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Global helper functions
require_once INCLUDES_PATH . 'functions.php';
require_once INCLUDES_PATH . 'security.php';
