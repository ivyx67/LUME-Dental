<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/reservation_helpers.php';
$user = require_role('employee');
$employeeQuery = db()->prepare('SELECT id FROM employees WHERE user_id = :user_id AND active = 1 LIMIT 1');
$employeeQuery->execute(['user_id' => (int) $user['id']]);
$employeeId = $employeeQuery->fetchColumn();
if ($employeeId === false) {
    http_response_code(403);
    exit('Brak dostępu do panelu pracownika.');
}
$employeeId = (int) $employeeId;
$now = new DateTimeImmutable();
$today = $now->format('Y-m-d');
$currentTime = $now->format('H:i:s');

if (is_post_request()) {
    if (!csrf_is_valid()) {
        http_response_code(403);
        set_flash('error', 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.');
    } else {
        $reservationId = reservation_parse_id($_POST['reservation_id'] ?? null);
        $status = post_string('status');
        if ($reservationId === null || !in_array($status, ['confirmed', 'completed', 'cancelled'], true)) {
            set_flash('error', 'Nieprawidłowe dane zmiany statusu.');
        } else {
            $update = db()->prepare(
                "UPDATE reservations
                 SET status = :status
                 WHERE id = :id AND employee_id = :employee_id AND status IN ('pending', 'confirmed')
                   AND (:status_check <> 'completed'
                     OR reservation_date < :completion_date
                     OR (reservation_date = :completion_today AND end_time <= :completion_time))"
            );
            $update->execute([
                'status' => $status,
                'status_check' => $status,
                'id' => $reservationId,
                'employee_id' => $employeeId,
                'completion_date' => $today,
                'completion_today' => $today,
                'completion_time' => $currentTime,
            ]);
            set_flash($update->rowCount() === 1 ? 'success' : 'error', $update->rowCount() === 1
                ? 'Status wizyty został zmieniony.'
                : 'Nie można zmienić statusu tej wizyty.');
        }
    }
    $redirect = 'employee_reservations.php';
    $detailId = reservation_parse_id($_POST['reservation_id'] ?? null);
    if ($detailId !== null) {
        $redirect .= '?id=' . $detailId;
    }
    header('Location: ' . app_url($redirect));
    exit;
}

$reservationSelect = 'SELECT r.id, r.user_id, r.reservation_date, r.start_time, r.end_time, r.status, r.comment,
                             client.name AS client_name, client.surname AS client_surname,
                             client.email AS client_email, client.phone AS client_phone,
                             s.name AS service_name, s.description AS service_description,
                             s.duration, s.price
                      FROM reservations r
                      INNER JOIN users client ON client.id = r.user_id
                      INNER JOIN services s ON s.id = r.service_id
                      WHERE r.employee_id = :employee_id';
$todayQuery = db()->prepare($reservationSelect . ' AND r.reservation_date = :today ORDER BY r.start_time');
$todayQuery->execute(['employee_id' => $employeeId, 'today' => $today]);
$todayReservations = $todayQuery->fetchAll();
$futureQuery = db()->prepare(
    $reservationSelect . " AND r.reservation_date > :today AND r.status IN ('pending', 'confirmed')
                          ORDER BY r.reservation_date, r.start_time"
);
$futureQuery->execute(['employee_id' => $employeeId, 'today' => $today]);
$futureReservations = $futureQuery->fetchAll();
$historyQuery = db()->prepare(
    $reservationSelect . " AND (r.reservation_date < :today OR r.status IN ('completed', 'cancelled'))
                           ORDER BY r.reservation_date DESC, r.start_time DESC"
);
$historyQuery->execute(['employee_id' => $employeeId, 'today' => $today]);
$history = $historyQuery->fetchAll();

$detailId = reservation_parse_id($_GET['id'] ?? null);
$detail = false;
if ($detailId !== null) {
    $detailQuery = db()->prepare($reservationSelect . ' AND r.id = :id LIMIT 1');
    $detailQuery->execute(['employee_id' => $employeeId, 'id' => $detailId]);
    $detail = $detailQuery->fetch();
    if ($detail === false) {
        http_response_code(404);
    }
}

$pageTitle = 'Wizyty pracownika';
require dirname(__DIR__) . '/app/views/header.php';
?>
<section class="dashboard-page">
    <div class="container">
        <div class="dashboard-welcome">
            <div><span class="eyebrow">STREFA ZESPOŁU</span><h1>Twoje wizyty</h1><p>Wizyty na dziś, przyszłe terminy oraz historia obsługi.</p></div>
        </div>
        <?php if ($detailId !== null): ?>
            <?php if ($detail === false): ?>
                <div class="notice notice-info">Nie znaleziono wizyty w Twoim grafiku.</div>
            <?php else: ?>
                <article class="dashboard-card role-dashboard-card">
                    <span class="eyebrow">SZCZEGÓŁY WIZYTY</span>
                    <h2><?= e($detail['service_name']) ?> · <?= e($detail['reservation_date']) ?></h2>
                    <p><strong>Godzina:</strong> <?= e(substr((string) $detail['start_time'], 0, 5) . '–' . substr((string) $detail['end_time'], 0, 5)) ?></p>
                    <p><strong>Klient:</strong> <?= e($detail['client_name'] . ' ' . $detail['client_surname']) ?></p>
                    <p><strong>Kontakt:</strong> <?= e($detail['client_email']) ?><?= !empty($detail['client_phone']) ? ' · ' . e($detail['client_phone']) : '' ?></p>
                    <p><strong>Usługa:</strong> <?= e((string) ($detail['service_description'] ?? '')) ?> · <?= (int) $detail['duration'] ?> min · <?= e(number_format((float) $detail['price'], 2, ',', ' ')) ?> zł</p>
                    <?php if (!empty($detail['comment'])): ?><p><strong>Uwagi:</strong> <?= e($detail['comment']) ?></p><?php endif; ?>
                    <p><strong>Status:</strong> <?= e(reservation_status_label((string) $detail['status'])) ?></p>
                    <?php if (in_array($detail['status'], ['pending', 'confirmed'], true)): ?>
                        <form method="post" action="<?= e(app_url('employee_reservations.php')) ?>" class="form-stack">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="reservation_id" value="<?= (int) $detail['id'] ?>">
                            <label class="field"><span>Zmień status</span><select name="status" required>
                                <?php if ($detail['status'] === 'pending'): ?><option value="confirmed">Potwierdzona</option><?php endif; ?>
                                <option value="completed">Zrealizowana</option>
                                <option value="cancelled">Anulowana</option>
                            </select></label>
                            <button class="button button-small" type="submit">Zapisz status</button>
                        </form>
                    <?php endif; ?>
                    <p><a class="quiet-link" href="<?= e(app_url('employee_reservations.php')) ?>">Wróć do listy wizyt</a></p>
                </article>
            <?php endif; ?>
        <?php endif; ?>
        <?php foreach (['Wizyty na dziś' => $todayReservations, 'Przyszłe wizyty' => $futureReservations, 'Historia wizyt' => $history] as $title => $reservations): ?>
            <section class="admin-panel">
                <div class="form-heading"><h2><?= e($title) ?></h2></div>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Termin</th><th>Klient</th><th>Usługa</th><th>Status</th><th>Szczegóły</th></tr></thead>
                        <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td data-label="Termin"><?= e($reservation['reservation_date']) ?><small class="table-subtext"><?= e(substr((string) $reservation['start_time'], 0, 5) . '–' . substr((string) $reservation['end_time'], 0, 5)) ?></small></td>
                                <td data-label="Klient"><?= e($reservation['client_name'] . ' ' . $reservation['client_surname']) ?></td>
                                <td data-label="Usługa"><?= e($reservation['service_name']) ?></td>
                                <td data-label="Status"><span class="<?= e(reservation_status_class((string) $reservation['status'])) ?>"><?= e(reservation_status_label((string) $reservation['status'])) ?></span></td>
                                <td data-label="Szczegóły"><a class="admin-link" href="<?= e(app_url('employee_reservations.php?id=' . (int) $reservation['id'])) ?>">Otwórz wizytę</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($reservations === []): ?><tr><td class="table-empty" colspan="5">Brak wizyt w tej sekcji.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>
<?php require dirname(__DIR__) . '/app/views/footer.php'; ?>
