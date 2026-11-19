<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/reservation_helpers.php';
$client = require_role('client');
$now = new DateTimeImmutable();
$today = $now->format('Y-m-d');
$currentTime = $now->format('H:i:s');

if (is_post_request()) {
    if (!csrf_is_valid()) {
        http_response_code(403);
        set_flash('error', 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.');
    } else {
        $reservationId = reservation_parse_id($_POST['reservation_id'] ?? null);
        if ($reservationId === null) {
            set_flash('error', 'Nie można anulować tej rezerwacji.');
        } else {
            $cancel = db()->prepare(
                "UPDATE reservations
                 SET status = 'cancelled'
                 WHERE id = :id AND user_id = :user_id AND status IN ('pending', 'confirmed')
                   AND (reservation_date > :future_date
                     OR (reservation_date = :today_date AND start_time > :current_time))"
            );
            $cancel->execute([
                'id' => $reservationId,
                'user_id' => (int) $client['id'],
                'future_date' => $today,
                'today_date' => $today,
                'current_time' => $currentTime,
            ]);
            set_flash($cancel->rowCount() === 1 ? 'success' : 'error', $cancel->rowCount() === 1
                ? 'Rezerwacja została anulowana.'
                : 'Nie można anulować tej rezerwacji. Sprawdź, czy należy do Ciebie i czy jest przyszła.');
        }
    }
    header('Location: ' . app_url('client_reservations.php'));
    exit;
}

$baseQuery = 'SELECT r.id, r.reservation_date, r.start_time, r.end_time, r.status, s.name AS service_name, s.price,
                     employee.name AS employee_name, employee.surname AS employee_surname
              FROM reservations r
              INNER JOIN services s ON s.id = r.service_id
              INNER JOIN employees e ON e.id = r.employee_id
              INNER JOIN users employee ON employee.id = e.user_id
              WHERE r.user_id = :user_id';
$upcomingQuery = db()->prepare(
    $baseQuery . " AND r.status IN ('pending', 'confirmed')
                   AND (r.reservation_date > :future_date
                     OR (r.reservation_date = :today_date AND r.start_time > :current_time))
                   ORDER BY r.reservation_date, r.start_time"
);
$upcomingQuery->execute([
    'user_id' => (int) $client['id'],
    'future_date' => $today,
    'today_date' => $today,
    'current_time' => $currentTime,
]);
$upcoming = $upcomingQuery->fetchAll();
$historyQuery = db()->prepare(
    $baseQuery . " AND (r.status IN ('completed', 'cancelled')
                    OR (r.reservation_date < :past_date
                      OR (r.reservation_date = :today_date AND r.start_time <= :current_time)))
                   ORDER BY r.reservation_date DESC, r.start_time DESC"
);
$historyQuery->execute([
    'user_id' => (int) $client['id'],
    'past_date' => $today,
    'today_date' => $today,
    'current_time' => $currentTime,
]);
$history = $historyQuery->fetchAll();

$pageTitle = 'Moje rezerwacje';
require dirname(__DIR__) . '/app/views/header.php';
?>
<section class="dashboard-page">
    <div class="container">
        <div class="dashboard-welcome">
            <div><span class="eyebrow">STREFA PACJENTA</span><h1>Moje rezerwacje</h1><p>Przyszłe wizyty oraz historia zakończonych i anulowanych rezerwacji.</p></div>
            <a class="button button-small" href="<?= e(app_url('booking.php')) ?>">Zarezerwuj wizytę</a>
        </div>
        <?php foreach (['Nadchodzące wizyty' => $upcoming, 'Zakończone i anulowane' => $history] as $title => $reservations): ?>
            <section class="admin-panel">
                <div class="form-heading"><h2><?= e($title) ?></h2></div>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Usługa</th><th>Pracownik</th><th>Data i godzina</th><th>Cena</th><th>Status</th><th>Akcje</th></tr></thead>
                        <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td data-label="Usługa"><?= e($reservation['service_name']) ?></td>
                                <td data-label="Pracownik"><?= e($reservation['employee_name'] . ' ' . $reservation['employee_surname']) ?></td>
                                <td data-label="Data i godzina"><?= e($reservation['reservation_date']) ?><small class="table-subtext"><?= e(substr((string) $reservation['start_time'], 0, 5) . '–' . substr((string) $reservation['end_time'], 0, 5)) ?></small></td>
                                <td data-label="Cena"><?= e(number_format((float) $reservation['price'], 2, ',', ' ')) ?> zł</td>
                                <td data-label="Status"><span class="<?= e(reservation_status_class((string) $reservation['status'])) ?>"><?= e(reservation_status_label((string) $reservation['status'])) ?></span></td>
                                <td data-label="Akcje">
                                    <?php if (in_array($reservation['status'], ['pending', 'confirmed'], true)
                                        && ($reservation['reservation_date'] > $today
                                            || ($reservation['reservation_date'] === $today
                                                && $reservation['start_time'] > $currentTime))): ?>
                                        <form method="post" action="<?= e(app_url('client_reservations.php')) ?>" data-confirm="Czy na pewno anulować tę przyszłą rezerwację?">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>">
                                            <button class="admin-link admin-link-danger" type="submit">Anuluj</button>
                                        </form>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($reservations === []): ?><tr><td class="table-empty" colspan="6">Brak rezerwacji w tej sekcji.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>
<?php require dirname(__DIR__) . '/app/views/footer.php'; ?>
