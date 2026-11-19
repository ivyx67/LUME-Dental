<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
$user = require_role('client');
$pageTitle = 'Mój panel';
require dirname(__DIR__) . '/app/views/header.php';
?>
<section class="dashboard-page">
    <div class="container">
        <div class="dashboard-welcome">
            <div><span class="eyebrow">STREFA PACJENTA</span><h1>Dzień dobry, <?= e($user['name']) ?>.</h1><p>To Twoje miejsce, w którym zadbasz o sprawy związane z kontem.</p></div>
            <span class="role-pill">Klient</span>
        </div>
        <div class="dashboard-grid">
            <article class="dashboard-card profile-summary">
                <span class="card-icon">◉</span><span class="eyebrow">TWOJE DANE</span><h2>Profil pacjenta</h2>
                <p><?= e($user['name'] . ' ' . $user['surname']) ?><br><?= e($user['email']) ?></p>
                <a class="quiet-link" href="<?= e(app_url('profile.php')) ?>">Edytuj profil <span aria-hidden="true">↗</span></a>
            </article>
            <article class="dashboard-card next-step-card">
                <span class="card-icon">✳</span><span class="eyebrow">REZERWACJE ONLINE</span><h2>Umów dogodną wizytę.</h2>
                <p>Wybierz usługę, pracownika i jeden z dostępnych terminów.</p>
                <a class="quiet-link" href="<?= e(app_url('booking.php')) ?>">Zarezerwuj wizytę <span aria-hidden="true">↗</span></a>
                <p><a class="quiet-link" href="<?= e(app_url('client_reservations.php')) ?>">Zobacz moje rezerwacje <span aria-hidden="true">↗</span></a></p>
                <a class="quiet-link" href="<?= e(app_url('services.php')) ?>">Poznaj ofertę gabinetu <span aria-hidden="true">↗</span></a>
            </article>
        </div>
    </div>
</section>
<?php require dirname(__DIR__) . '/app/views/footer.php'; ?>
