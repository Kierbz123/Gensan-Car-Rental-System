<?php
/**
 * 404 - Page Not Found
 */
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = '404 Not Found';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found | GCR Admin</title>
    <style>
        :root {
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --border: #e2e8f0;
            --radius: 16px;
            --shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }

        .error-container {
            text-align: center;
            max-width: 480px;
            background: var(--card-bg);
            padding: 3rem 2.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .error-code {
            font-size: 5rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--accent);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .error-message {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.9375rem;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn:hover {
            background: var(--accent-hover);
        }
    </style>
</head>

<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-message">The page you're looking for doesn't exist or has been moved. Please check the URL or
            navigate back to the dashboard.</p>
        <a class="btn" href="<?= BASE_URL ?>modules/dashboard/index.php">← Back to Dashboard</a>
    </div>
</body>

</html>