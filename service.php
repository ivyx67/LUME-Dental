<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/reservation_helpers.php';

$serviceId = reservation_parse_id($_GET['id'] ?? null);
if ($serviceId === null) {
    http_response_code(404);
    $service = false;
} else {
    $statement = db()->prepare(
        'SELECT s.id, s.name, s.description, s.duration, s.price, c.name AS category_name,
                COUNT(DISTINCT CASE WHEN e.active = 1 AND u.active = 1 AND a.id IS NOT NULL THEN e.id END) AS employee_count
         FROM services s
         INNER JOIN service_categories c ON c.id = s.category_id
         LEFT JOIN employee_services es ON es.service_id = s.id
         LEFT JOIN employees e ON e.id = es.employee_id
         LEFT JOIN users u ON u.id = e.user_id
         LEFT JOIN employee_availability a ON a.employee_id = e.id
         WHERE s.id = :id AND s.active = 1
         GROUP BY s.id, s.name, s.description, s.duration, s.price, c.name
         LIMIT 1'
    );
    $statement->execute(['id' => $serviceId]);
    $service = $statement->fetch();
    if ($service === false) {
        http_response_code(404);
    }
}

$pageTitle = $service === false ? 'Nie znaleziono usługi' : (string) $service['name'];
require dirname(__DIR__) . '/app/views/header.php';
?>
<section class="dashboard-page">
    <div class="container">
        <?php if ($service === false): ?>
            <div class="notice notice-info">Nie znaleziono tej aktywnej usługi. <a href="<?= e(app_url('services.php')) ?>">Wróć do oferty</a>.</div>
        <?php else: ?>
            <article class="dashboard-card role-dashboard-card">
                <span class="eyebrow"><?= e($service['category_name']) ?></span>
                <h1><?= e($service['name']) ?></h1>
                <p><?= e((string) ($service['description'] ?? 'Brak dodatkowego opisu.')) ?></p>
                <p><strong>Cena:</strong> <?= e(number_format((float) $service['price'], 2, ',', ' ')) ?> zł</p>
                <p><strong>Czas trwania:</strong> <?= (int) $service['duration'] ?> min</p>
                <p><strong>Dostępność:</strong> <?= (int) $service['employee_count'] > 0 ? 'Usługa jest dostępna u aktywnych pracowników.' : 'Brak aktywnych pracowników przypisanych do tej usługi.' ?></p>
                <?php if ((int) $service['employee_count'] > 0): ?>
                    <?php $signedInUser = current_user(); ?>
                    <?php if ($signedInUser !== null && $signedInUser['role'] === 'client'): ?>
                        <a class="button" href="<?= e(app_url('booking.php?service_id=' . (int) $service['id'])) ?>">Wybierz termin</a>
                    <?php elseif ($signedInUser === null): ?>
                        <a class="button" href="<?= e(app_url('login.php')) ?>">Zaloguj się, aby zarezerwować</a>
                    <?php endif; ?>
                <?php endif; ?>
                <p><a class="quiet-link" href="<?= e(app_url('services.php')) ?>">Wróć do oferty</a></p>
            </article>
        <?php endif; ?>
    </div>
</section>
<?php require dirname(__DIR__) . '/app/views/footer.php'; ?>
