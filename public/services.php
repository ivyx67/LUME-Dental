<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/reservation_helpers.php';

$categoryValue = $_GET['category_id'] ?? '';
$categoryId = $categoryValue === '' ? null : reservation_parse_id($categoryValue);
$error = $categoryValue !== '' && $categoryId === null ? 'Wybrano nieprawidłową kategorię.' : '';
$categories = db()->query(
    'SELECT id, name, description FROM service_categories ORDER BY name'
)->fetchAll();

$sql = 'SELECT s.id, s.name, s.description, s.duration, s.price, c.name AS category_name,
               COUNT(DISTINCT CASE WHEN u.active = 1 AND a.id IS NOT NULL THEN e.id END) AS employee_count
        FROM services s
        INNER JOIN service_categories c ON c.id = s.category_id
        LEFT JOIN employee_services es ON es.service_id = s.id
        LEFT JOIN employees e ON e.id = es.employee_id AND e.active = 1
        LEFT JOIN users u ON u.id = e.user_id
        LEFT JOIN employee_availability a ON a.employee_id = e.id
        WHERE s.active = 1';
$parameters = [];
if ($categoryId !== null) {
    $sql .= ' AND s.category_id = :category_id';
    $parameters['category_id'] = $categoryId;
}
$sql .= ' GROUP BY s.id, s.name, s.description, s.duration, s.price, c.name ORDER BY c.name, s.name';
$statement = db()->prepare($sql);
$statement->execute($parameters);
$services = $statement->fetchAll();

$pageTitle = 'Oferta usług';
require dirname(__DIR__) . '/app/views/header.php';
?>
<section class="dashboard-page">
    <div class="container">
        <div class="dashboard-welcome">
            <div><span class="eyebrow">OFERTA LUME DENTAL</span><h1>Wybierz opiekę dla siebie</h1><p>Przeglądaj usługi według kategorii i sprawdź ich dostępność.</p></div>
        </div>
        <?php if ($error !== ''): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form class="admin-reservation-filters" method="get" action="<?= e(app_url('services.php')) ?>">
            <label class="field"><span>Kategoria</span><select name="category_id">
                <option value="">Wszystkie kategorie</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select></label>
            <button class="button button-small" type="submit">Filtruj ofertę</button>
        </form>
        <?php if ($services === []): ?>
            <div class="notice notice-info">Brak aktywnych usług w wybranej kategorii.</div>
        <?php else: ?>
            <div class="service-grid">
                <?php foreach ($services as $index => $service): ?>
                    <article class="service-card">
                        <span class="service-number"><?= sprintf('%02d', $index + 1) ?></span>
                        <span class="service-category"><?= e($service['category_name']) ?></span>
                        <h2><?= e($service['name']) ?></h2>
                        <p><?= e((string) ($service['description'] ?? '')) ?></p>
                        <div class="service-meta"><span><?= (int) $service['duration'] ?> min</span><strong><?= e(number_format((float) $service['price'], 2, ',', ' ')) ?> zł</strong></div>
                        <p class="service-category"><?= (int) $service['employee_count'] > 0 ? 'Dostępna' : 'Obecnie niedostępna' ?></p>
                        <a class="quiet-link" href="<?= e(app_url('service.php?id=' . (int) $service['id'])) ?>">Szczegóły usługi <span aria-hidden="true">↗</span></a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require dirname(__DIR__) . '/app/views/footer.php'; ?>
