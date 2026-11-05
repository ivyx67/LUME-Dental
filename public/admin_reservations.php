<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin_helpers.php';
require_role('admin');

$statuses = ['pending', 'confirmed', 'completed', 'cancelled'];

if (is_post_request()) {
    if (!admin_post_is_valid()) {
        admin_redirect('admin_reservations.php');
    }

    $reservationId = filter_var($_POST['reservation_id'] ?? null, FILTER_VALIDATE_INT);
    $status = post_string('status');
    if (post_string('action') !== 'status' || $reservationId === false || $reservationId === null
        || $reservationId < 1 || !in_array($status, $statuses, true)
    ) {
        set_flash('error', 'Nieprawidłowe dane zmiany statusu rezerwacji.');
        admin_redirect('admin_reservations.php');
    }

    $exists = db()->prepare('SELECT id FROM reservations WHERE id = :id');
    $exists->execute(['id' => $reservationId]);
    if ($exists->fetch() === false) {
        set_flash('error', 'Nie znaleziono rezerwacji.');
        admin_redirect('admin_reservations.php');
    }
    $update = db()->prepare('UPDATE reservations SET status = :status WHERE id = :id');
    $update->execute(['status' => $status, 'id' => $reservationId]);
    set_flash('success', $update->rowCount() === 1 ? 'Status rezerwacji został zmieniony.' : 'Rezerwacja ma już wybrany status.');
    admin_redirect('admin_reservations.php');
}

$dateFromValue = $_GET['date_from'] ?? '';
$dateToValue = $_GET['date_to'] ?? '';
$statusValue = $_GET['status'] ?? '';
$employeeValue = $_GET['employee_id'] ?? '';
$searchValue = $_GET['q'] ?? '';
$dateFrom = is_string($dateFromValue) ? trim($dateFromValue) : '';
$dateTo = is_string($dateToValue) ? trim($dateToValue) : '';
$statusFilter = is_string($statusValue) ? $statusValue : '';
$employeeFilter = is_string($employeeValue) ? $employeeValue : '';
$search = is_string($searchValue) ? trim($searchValue) : '';
$filterErrors = [];

if ($dateFrom !== '' && !admin_is_valid_date($dateFrom)) {
    $filterErrors[] = 'Data początkowa jest nieprawidłowa.';
    $dateFrom = '';
}
if ($dateTo !== '' && !admin_is_valid_date($dateTo)) {
    $filterErrors[] = 'Data końcowa jest nieprawidłowa.';
    $dateTo = '';
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $filterErrors[] = 'Data początkowa nie może być późniejsza od końcowej.';
    $dateFrom = '';
    $dateTo = '';
}
if ($statusFilter !== '' && !in_array($statusFilter, $statuses, true)) {
    $filterErrors[] = 'Wybrano nieprawidłowy status.';
    $statusFilter = '';
}
$employeeId = $employeeFilter === '' ? null : filter_var($employeeFilter, FILTER_VALIDATE_INT);
if ($employeeFilter !== '' && ($employeeId === false || $employeeId < 1)) {
    $filterErrors[] = 'Wybrano nieprawidłowego pracownika.';
    $employeeId = null;
}
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}

$conditions = [];
$parameters = [];
if ($dateFrom !== '') {
    $conditions[] = 'r.reservation_date >= :date_from';
    $parameters['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $conditions[] = 'r.reservation_date <= :date_to';
    $parameters['date_to'] = $dateTo;
}
if ($statusFilter !== '') {
    $conditions[] = 'r.status = :status';
    $parameters['status'] = $statusFilter;
}
if ($employeeId !== null) {
    $conditions[] = 'r.employee_id = :employee_id';
    $parameters['employee_id'] = $employeeId;
}
if ($search !== '') {
    $conditions[] = '(client.name LIKE :client_name OR client.surname LIKE :client_surname OR client.email LIKE :client_email OR s.name LIKE :service_name)';
    $like = '%' . $search . '%';
    $parameters['client_name'] = $like;
    $parameters['client_surname'] = $like;
    $parameters['client_email'] = $like;
    $parameters['service_name'] = $like;
}
$where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
$joins = ' FROM reservations r
    INNER JOIN users client ON client.id = r.user_id
    INNER JOIN employees e ON e.id = r.employee_id
    INNER JOIN users employee ON employee.id = e.user_id
    INNER JOIN services s ON s.id = r.service_id';
$count = db()->prepare('SELECT COUNT(*)' . $joins . $where);
$count->execute($parameters);
$totalRows = (int) $count->fetchColumn();
$pageValue = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$currentPage = max(1, is_int($pageValue) ? $pageValue : 1);
$pageSize = 20;
$pageCount = max(1, (int) ceil($totalRows / $pageSize));
$currentPage = min($currentPage, $pageCount);
$list = db()->prepare(
    'SELECT r.id, r.reservation_date, r.start_time, r.end_time, r.status, r.comment,
            client.name AS client_name, client.surname AS client_surname, client.email AS client_email,
            employee.name AS employee_name, employee.surname AS employee_surname,
            s.name AS service_name, s.price
     ' . $joins . $where . '
     ORDER BY r.reservation_date DESC, r.start_time DESC
     LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $key => $value) {
    $list->bindValue(':' . $key, $value);
}
$list->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$list->bindValue(':offset', ($currentPage - 1) * $pageSize, PDO::PARAM_INT);
$list->execute();
$reservations = $list->fetchAll();
$employees = db()->query(
    'SELECT e.id, u.name, u.surname FROM employees e
     INNER JOIN users u ON u.id = e.user_id ORDER BY u.surname, u.name'
)->fetchAll();
$filters = [];
if ($dateFrom !== '') {
    $filters['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $filters['date_to'] = $dateTo;
}
if ($statusFilter !== '') {
    $filters['status'] = $statusFilter;
}
if ($employeeId !== null) {
    $filters['employee_id'] = $employeeId;
}
if ($search !== '') {
    $filters['q'] = $search;
}

admin_page_start('Rezerwacje', 'reservations');
?>
<div class="dashboard-welcome">
    <div><span class="eyebrow">PANEL ADMINISTRATORA</span><h1>Rezerwacje</h1><p>Przeglądaj terminy, wyszukuj wizyty i aktualizuj ich status.</p></div>
    <span class="role-pill"><?= $totalRows ?> wyników</span>
</div>
<?php if ($filterErrors !== []): ?>
    <div class="notice notice-error admin-wide-notice" role="alert"><ul><?php foreach ($filterErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<section class="admin-panel admin-list-panel">
    <form class="admin-reservation-filters" method="get" action="<?= e(app_url('admin_reservations.php')) ?>">
        <label class="field"><span>Od dnia</span><input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label>
        <label class="field"><span>Do dnia</span><input type="date" name="date_to" value="<?= e($dateTo) ?>"></label>
        <label class="field"><span>Status</span><select name="status"><option value="">Wszystkie</option>
            <?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e(admin_status_label($status)) ?></option><?php endforeach; ?>
        </select></label>
        <label class="field"><span>Pracownik</span><select name="employee_id"><option value="">Wszyscy</option>
            <?php foreach ($employees as $employee): ?><option value="<?= (int) $employee['id'] ?>" <?= $employeeId !== null && $employeeId === (int) $employee['id'] ? 'selected' : '' ?>><?= e($employee['name'] . ' ' . $employee['surname']) ?></option><?php endforeach; ?>
        </select></label>
        <label class="field filter-search"><span>Szukaj klienta lub usługi</span><input name="q" maxlength="100" value="<?= e($search) ?>" placeholder="Imię, e-mail, usługa"></label>
        <button class="button button-small" type="submit">Filtruj</button>
        <a class="quiet-link" href="<?= e(app_url('admin_reservations.php')) ?>">Wyczyść filtry</a>
    </form>
    <div class="table-wrap">
        <table class="admin-table reservation-table">
            <thead><tr><th>Termin</th><th>Klient</th><th>Usługa</th><th>Pracownik</th><th>Cena</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($reservations as $reservation): ?>
                <tr>
                    <td data-label="Termin"><strong><?= e($reservation['reservation_date']) ?></strong><small class="table-subtext"><?= e(substr((string) $reservation['start_time'], 0, 5)) ?>–<?= e(substr((string) $reservation['end_time'], 0, 5)) ?></small></td>
                    <td data-label="Klient"><?= e($reservation['client_name'] . ' ' . $reservation['client_surname']) ?><small class="table-subtext"><?= e($reservation['client_email']) ?></small></td>
                    <td data-label="Usługa"><?= e($reservation['service_name']) ?><?php if (!empty($reservation['comment'])): ?><small class="table-subtext"><?= e($reservation['comment']) ?></small><?php endif; ?></td>
                    <td data-label="Pracownik"><?= e($reservation['employee_name'] . ' ' . $reservation['employee_surname']) ?></td>
                    <td data-label="Cena"><?= e(number_format((float) $reservation['price'], 2, ',', ' ')) ?> zł</td>
                    <td data-label="Status">
                        <form class="status-edit-form" method="post" action="<?= e(app_url('admin_reservations.php')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>">
                            <select name="status" aria-label="Status rezerwacji" data-current-status="<?= e($reservation['status']) ?>">
                                <?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $reservation['status'] === $status ? 'selected' : '' ?>><?= e(admin_status_label($status)) ?></option><?php endforeach; ?>
                            </select>
                            <button class="admin-link" type="submit">Zapisz</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($reservations === []): ?><tr><td colspan="6" class="table-empty">Nie znaleziono rezerwacji spełniających filtry.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php admin_page_links($currentPage, $pageCount, 'admin_reservations.php', $filters); ?>
</section>
<?php admin_page_end(); ?>
