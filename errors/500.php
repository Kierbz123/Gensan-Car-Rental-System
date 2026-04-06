<?php
/**
 * 500 - Internal Server Error
 */
require_once dirname(__DIR__) . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Server Error | GCR Admin</title>
    <style>
        :root {
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --warning: #f59e0b;
            --accent: #2563eb;
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
            color: var(--warning);
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
            background: #1d4ed8;
        }
    </style>
</head>

<body>
    <div class="error-container">
        <div class="error-code">500</div>
        <h1 class="error-title">Something Went Wrong</h1>
        <p class="error-message">An internal server error occurred. Our team has been notified. Please try again in a
            few moments.</p>
        <a class="btn" href="<?= BASE_URL ?>modules/dashboard/index.php">← Back to Dashboard</a>
    </div>
</body>

</html>