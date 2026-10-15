<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
$user = require_role('admin');
$pageTitle = 'Panel administratora';
require dirname(__DIR__) . '/app/views/header.php';
?>
<section class="dashboard-page">
    <div class="container">
        <div class="dashboard-welcome">
            <div><span class="eyebrow">STREFA ADMINISTRATORA</span><h1>Witaj, <?= e($user['name']) ?>.</h1><p>Twoje konto ma uprawnienia administratora systemu.</p></div>
            <span class="role-pill role-pill-admin">Administrator</span>
        </div>
        <article class="dashboard-card role-dashboard-card">
            <span class="card-icon">✳</span><span class="eyebrow">PANEL ADMINISTRATORA</span><h2>Bezpieczny dostęp potwierdzony.</h2>
            <p>W tym etapie działa uwierzytelnianie i kontrola uprawnień. Zarządzanie ofertą i personelem zostanie dodane w kolejnym etapie.</p>
            <a class="quiet-link" href="<?= e(app_url('profile.php')) ?>">Edytuj profil <span aria-hidden="true">↗</span></a>
        </article>
    </div>
</section>
<?php require dirname(__DIR__) . '/app/views/footer.php'; ?>
