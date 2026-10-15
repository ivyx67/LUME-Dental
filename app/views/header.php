<?php
$pageTitle = $pageTitle ?? 'Lume Dental';
$user = current_user();
$flashMessage = take_flash();
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4faf9">
    <title><?= e($pageTitle) ?> | Lume Dental</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/script.js" defer></script>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(app_url('index.php')) ?>" aria-label="Lume Dental — strona główna">
            <span class="brand-mark" aria-hidden="true">L</span>
            <span class="brand-copy"><strong>Lume</strong><small>DENTAL STUDIO</small></span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-navigation">
            <span class="sr-only">Otwórz menu</span>
            <span></span><span></span><span></span>
        </button>
        <nav class="main-navigation" id="main-navigation" aria-label="Nawigacja główna">
            <a href="<?= e(app_url('index.php')) ?>#oferta">Oferta</a>
            <a href="<?= e(app_url('index.php')) ?>#o-nas">O nas</a>
            <?php if ($user !== null): ?>
                <a href="<?= e(app_url('dashboard.php')) ?>">Mój panel</a>
                <a href="<?= e(app_url('profile.php')) ?>">Moje konto</a>
                <form class="nav-logout" method="post" action="<?= e(app_url('logout.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="text-button" type="submit">Wyloguj</button>
                </form>
            <?php else: ?>
                <a href="<?= e(app_url('login.php')) ?>">Zaloguj się</a>
                <a class="button button-small" href="<?= e(app_url('register.php')) ?>">Utwórz konto</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main>
    <?php if ($flashMessage !== null): ?>
        <div class="container flash-wrap">
            <div class="notice notice-<?= e((string) ($flashMessage['type'] ?? 'info')) ?>" role="status">
                <?= e((string) ($flashMessage['message'] ?? '')) ?>
            </div>
        </div>
    <?php endif; ?>
