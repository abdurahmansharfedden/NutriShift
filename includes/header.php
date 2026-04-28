<?php
// =============================================================================
// header.php — Shared HTML Header / Navigation
// =============================================================================
// TEACHING NOTE: This file is "included" into every page using require_once().
// It keeps the <head> and <nav> HTML in one place so we don't repeat ourselves
// (DRY — Don't Repeat Yourself principle).
//
// $page_title must be set by the including page BEFORE this file is included.
// =============================================================================
$page_title = $page_title ?? 'NutriShift';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="NutriShift — Adaptive nutrition tracking built around your biological cycle, not the clock.">
    <title><?= sanitize($page_title) ?> | NutriShift</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- NAVIGATION BAR — only shown when the user is logged in -->
<?php if (is_logged_in()): ?>
<header class="site-header">
    <nav class="navbar" role="navigation" aria-label="Main navigation">
        <a href="<?= $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php' ?>" class="nav-logo" aria-label="NutriShift Home">
            <span class="logo-icon">⚡</span>
            <span class="logo-text">NutriShift</span>
        </a>

        <ul class="nav-links" role="list">
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li><a href="admin_dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'admin_dashboard.php' ? 'active' : '' ?>">Admin Panel</a></li>
            <?php else: ?>
                <li><a href="user_dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'user_dashboard.php' ? 'active' : '' ?>">My Dashboard</a></li>
            <?php endif; ?>
        </ul>

        <div class="nav-actions">
            <span class="nav-user-badge">
                <span class="user-avatar"><?= strtoupper(substr(sanitize($_SESSION['username']), 0, 1)) ?></span>
                <?= sanitize($_SESSION['username']) ?>
                <span class="role-tag role-<?= sanitize($_SESSION['role']) ?>"><?= sanitize($_SESSION['role']) ?></span>
            </span>
            <!-- Dark/Light mode toggle — JS in main.js handles the switch -->
            <button id="theme-toggle" class="btn-icon" aria-label="Toggle dark/light mode" title="Toggle theme">
                <span id="theme-icon">🌙</span>
            </button>
            <a href="index.php?action=logout" class="btn btn-outline btn-sm" id="logout-btn">Logout</a>
        </div>
    </nav>
</header>
<?php endif; ?>

<main class="main-content">
