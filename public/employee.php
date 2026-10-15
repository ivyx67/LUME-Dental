<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
$user = require_role('employee');
$pageTitle = 'Panel pracownika';
require dirname(__DIR__) . '/app/views/header.php';
?>
<section class="dashboard-page">
    <div class="container">
        <div class="dashboard-welcome">
            <div><span class="eyebrow">STREFA ZESPOŁU</span><h1>Witaj, <?= e($user['name']) ?>.</h1><p>To bezpieczny panel pracownika Lume Dental.</p></div>
            <span class="role-pill">Pracownik</span>
        </div>
        <article class="dashboard-card role-dashboard-card">
            <span class="card-icon">✳</span><span class="eyebrow">PANEL PRACOWNIKA</span><h2>Twoje konto jest gotowe.</h2>
            <p>W tym etapie dostępne jest logowanie, kontrola roli i edycja profilu. Zarządzanie wizytami zostanie dodane w kolejnym etapie.</p>
            <a class="quiet-link" href="<?= e(app_url('profile.php')) ?>">Edytuj profil <span aria-hidden="true">↗</span></a>
        </article>
    </div>
</section>
<?php require dirname(__DIR__) . '/app/views/footer.php'; ?>
