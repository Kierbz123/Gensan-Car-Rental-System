<?php
/**
 * 500 Internal Server Error Page
 * High-Premium SaaS Design
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Critical System Failure | GCR Intelligence</title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/app.css">
    <script src="<?php echo ASSETS_URL; ?>js/lucide.min.js"></script>
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
            padding: 2rem;
            color: #f8fafc;
            text-align: center;
        }

        .error-card {
            max-width: 600px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
            padding: 4rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .error-icon {
            width: 80px;
            height: 80px;
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-radius: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
        }
    </style>
</head>

<body>
    <div class="error-container">
        <div class="error-card">
            <div class="error-icon">
                <i data-lucide="shield-alert" size="48"></i>
            </div>
            <h1 class="text-4xl font-black mb-4">Intelligence Breach</h1>
            <p class="text-slate-400 text-lg mb-8 leading-relaxed">
                The system encountered a critical synchronization failure. Our high-velocity maintenance protocols have
                been initialized.
            </p>
            <div class="flex gap-4 justify-center">
                <a href="<?php echo BASE_URL; ?>"
                    class="btn btn-primary px-8 py-3 rounded-xl font-bold uppercase tracking-widest text-xs">
                    Re-initialize Base
                </a>
            </div>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>

</html>