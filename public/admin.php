<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin_helpers.php';
$user = require_role('admin');

$statistics = [
    'clients' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn(),
    'employees' => (int) db()->query('SELECT COUNT(*) FROM employees')->fetchColumn(),
    'services' => (int) db()->query('SELECT COUNT(*) FROM services')->fetchColumn(),
    'reservations' => (int) db()->query('SELECT COUNT(*) FROM reservations')->fetchColumn(),
    'today' => (int) db()->query('SELECT COUNT(*) FROM reservations WHERE reservation_date = CURDATE()')->fetchColumn(),
];

admin_page_start('Panel administratora', 'dashboard');
?>
<div class="dashboard-welcome">
    <div><span class="eyebrow">STREFA ADMINISTRATORA</span><h1>Dzień dobry, <?= e($user['name']) ?>.</h1><p>Najważniejsze informacje o gabinecie w jednym miejscu.</p></div>
    <span class="role-pill role-pill-admin">Administrator</span>
</div>
<div class="admin-stat-grid">
    <article class="admin-stat-card"><span>Klienci</span><strong><?= $statistics['clients'] ?></strong></article>
    <article class="admin-stat-card"><span>Pracownicy</span><strong><?= $statistics['employees'] ?></strong></article>
    <article class="admin-stat-card"><span>Usługi</span><strong><?= $statistics['services'] ?></strong></article>
    <article class="admin-stat-card"><span>Wszystkie rezerwacje</span><strong><?= $statistics['reservations'] ?></strong></article>
    <article class="admin-stat-card admin-stat-highlight"><span>Rezerwacje na dziś</span><strong><?= $statistics['today'] ?></strong></article>
</div>
<div class="section-heading admin-section-heading">
    <div><span class="eyebrow">ZARZĄDZANIE</span><h2>Wybierz moduł</h2></div>
    <p>Każdy moduł pozwala zapisać zmiany bezpośrednio w bazie gabinetu.</p>
</div>
<div class="admin-module-grid">
    <a class="admin-module-card" href="<?= e(app_url('admin_categories.php')) ?>"><span class="card-icon">01</span><h2>Kategorie</h2><p>Dodawaj, poprawiaj i usuwaj kategorie usług.</p></a>
    <a class="admin-module-card" href="<?= e(app_url('admin_services.php')) ?>"><span class="card-icon">02</span><h2>Usługi</h2><p>Zmieniaj ofertę, ceny, czas trwania i aktywność.</p></a>
    <a class="admin-module-card" href="<?= e(app_url('admin_employees.php')) ?>"><span class="card-icon">03</span><h2>Pracownicy</h2><p>Twórz konta, przypisuj usługi i ustawiaj godziny pracy.</p></a>
    <a class="admin-module-card" href="<?= e(app_url('admin_reservations.php')) ?>"><span class="card-icon">04</span><h2>Rezerwacje</h2><p>Przeglądaj terminy, filtruj i zmieniaj ich status.</p></a>
    <a class="admin-module-card" href="<?= e(app_url('admin_users.php')) ?>"><span class="card-icon">05</span><h2>Użytkownicy</h2><p>Wyszukuj konta i włączaj lub wyłączaj dostęp.</p></a>
</div>
<?php admin_page_end(); ?>
