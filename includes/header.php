<?php
if (!isset($pageTitle))
    $pageTitle = 'GCR Admin';
$base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | GCR Admin</title>

    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/app.css?v=<?= filemtime(ASSETS_PATH . 'css/app.css') ?>">
    <script src="<?= ASSETS_URL ?>js/lucide.min.js"></script>
</head>

<body>
    <div id="app">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <?php require_once __DIR__ . '/topbar.php'; ?>
        <main>