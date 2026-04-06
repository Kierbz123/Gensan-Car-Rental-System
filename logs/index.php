<?php
/**
 * Gensan Car Rental System - Secure Log Repository
 * Unauthorized access is strictly monitored and logged.
 */
header("HTTP/1.1 403 Forbidden");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security protocol violation | GCR Intelligence</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <script src="../assets/js/lucide.min.js"></script>
    <style>
        body { background-color: #0f172a; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; font-family: 'Inter', sans-serif; }
        .secure-card { background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); padding: 3rem; border-radius: 2rem; max-width: 480px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .icon-shield { width: 80px; height: 80px; background: rgba(244, 63, 94, 0.1); color: #f43f5e; border-radius: 1.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; }
        h1 { color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.5rem; letter-spacing: -0.025em; margin-bottom: 1rem; }
        p { color: #94a3b8; font-size: 0.875rem; line-height: 1.6; margin-bottom: 2rem; }
        .btn-return { background: #3b82f6; color: #fff; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 0.75rem; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-return:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3); }
        .trace-id { margin-top: 3rem; padding-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.05); color: #475569; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.2em; }
    </style>
</head>
<body>
    <div class="secure-card">
        <div class="icon-shield">
            <i data-lucide="shield-alert" style="width: 40px; height: 40px;"></i>
        </div>
        <h1>Security Breach Prevented</h1>
        <p>You have attempted to access a restricted system repository. This interaction has been logged with your identity markers and terminal metadata.</p>
        <a href="../login.php" class="btn-return">
            <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
            Authorization Portal
        </a>
        <div class="trace-id">Trace ID: GCR-SEC-<?php echo strtoupper(bin2hex(random_bytes(4))); ?></div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
