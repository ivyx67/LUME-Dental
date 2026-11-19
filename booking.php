<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/reservation_helpers.php';
$client = require_role('client');
$connection = db();
$errors = [];
$confirmation = false;
$serviceId = reservation_parse_id($_POST['service_id'] ?? $_GET['service_id'] ?? null);
$employeeId = reservation_parse_id($_POST['employee_id'] ?? $_GET['employee_id'] ?? null);
$date = is_string($_POST['reservation_date'] ?? $_GET['reservation_date'] ?? null)
    ? trim((string) ($_POST['reservation_date'] ?? $_GET['reservation_date']))
    : '';
$startTime = is_string($_POST['start_time'] ?? null) ? trim($_POST['start_time']) : '';
$confirmationToken = is_string($_POST['confirmation_token'] ?? null)
    ? trim($_POST['confirmation_token'])
    : '';
$action = post_string('action');

if (is_post_request()) {
    if (!csrf_is_valid()) {
        http_response_code(403);
        $errors[] = 'Formularz wygasł. Odśwież stronę i wybierz termin ponownie.';
    } elseif ($serviceId === null || $employeeId === null || !reservation_valid_date($date)) {
        $errors[] = 'Nieprawidłowe dane rezerwacji. Wybierz usługę, pracownika i datę ponownie.';
    } elseif ($action === 'review') {
        if (!reservation_slot_is_available($connection, $serviceId, $employeeId, $date, $startTime)) {
            unset($_SESSION['booking_confirmation']);
            $errors[] = 'Ten termin nie jest już dostępny. Wybierz inną godzinę.';
        } else {
            $confirmationToken = bin2hex(random_bytes(32));
            $_SESSION['booking_confirmation'] = [
                'token' => $confirmationToken,
                'client_id' => (int) $client['id'],
                'service_id' => $serviceId,
                'employee_id' => $employeeId,
                'date' => $date,
                'start_time' => $startTime,
            ];
            $confirmation = true;
        }
    } elseif ($action === 'confirm') {
        $pendingConfirmation = $_SESSION['booking_confirmation'] ?? null;
        $confirmationMatches = is_array($pendingConfirmation)
            && is_string($pendingConfirmation['token'] ?? null)
            && hash_equals($pendingConfirmation['token'], $confirmationToken)
            && (int) ($pendingConfirmation['client_id'] ?? 0) === (int) $client['id']
            && (int) ($pendingConfirmation['service_id'] ?? 0) === $serviceId
            && (int) ($pendingConfirmation['employee_id'] ?? 0) === $employeeId
            && ($pendingConfirmation['date'] ?? null) === $date
            && ($pendingConfirmation['start_time'] ?? null) === $startTime;
        if (!$confirmationMatches) {
            $errors[] = 'Potwierdzenie wygasło. Wybierz termin ponownie.';
        } else {
            unset($_SESSION['booking_confirmation']);
            try {
                $connection->beginTransaction();
                $lockEmployee = $connection->prepare(
                    'SELECT id FROM employees WHERE id = :id AND active = 1 FOR UPDATE'
                );
                $lockEmployee->execute(['id' => $employeeId]);
                if ($lockEmployee->fetch() === false
                    || !reservation_slot_is_available($connection, $serviceId, $employeeId, $date, $startTime)
                ) {
                    $connection->rollBack();
                    $errors[] = 'Wybrany termin nie jest już dostępny. Wybierz inną godzinę.';
                } else {
                    $context = reservation_booking_context($connection, $serviceId, $employeeId, $date);
                    if ($context === null) {
                        $connection->rollBack();
                        $errors[] = 'Nie można zarezerwować wybranej usługi i pracownika.';
                    } else {
                        $startMinutes = reservation_time_minutes($startTime);
                        $endTime = reservation_time_from_minutes($startMinutes + $context['duration']);
                        $insert = $connection->prepare(
                            "INSERT INTO reservations
                                (user_id, employee_id, service_id, reservation_date, start_time, end_time, status)
                             VALUES (:user_id, :employee_id, :service_id, :reservation_date, :start_time, :end_time, 'pending')"
                        );
                        $insert->execute([
                            'user_id' => (int) $client['id'],
                            'employee_id' => $employeeId,
                            'service_id' => $serviceId,
                            'reservation_date' => $date,
                            'start_time' => $startTime . ':00',
                            'end_time' => $endTime,
                        ]);
                        $connection->commit();
                        set_flash('success', 'Rezerwacja została utworzona i oczekuje na potwierdzenie.');
                        header('Location: ' . app_url('client_reservations.php'));
                        exit;
                    }
                }
            } catch (PDOException $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                error_log('Lume Dental reservation could not be saved: ' . $exception->getMessage());
                $errors[] = 'Nie udało się zapisać rezerwacji. Spróbuj ponownie za chwilę.';
            }
        }
    } else {
        $errors[] = 'Nieprawidłowa operacja. Odśwież stronę i spróbuj ponownie.';
    }
}

$service = false;
if ($serviceId !== null) {
    $serviceQuery = $connection->prepare(
        'SELECT s.id, s.name, s.duration, s.price, c.name AS category_name
         FROM services s INNER JOIN service_categories c ON c.id = s.category_id
         WHERE s.id = :id AND s.active = 1 LIMIT 1'
    );
    $serviceQuery->execute(['id' => $serviceId]);
    $service = $serviceQuery->fetch();
    if ($service === false) {
        $errors[] = 'Wybrana usługa nie jest dostępna.';
        $serviceId = null;
        $employeeId = null;
    }
}

$employees = [];
if ($serviceId !== null) {
    $employeeQuery = $connection->prepare(
        'SELECT e.id, u.name, u.surname
         FROM employee_services es
         INNER JOIN employees e ON e.id = es.employee_id AND e.active = 1
         INNER JOIN users u ON u.id = e.user_id AND u.active = 1
         WHERE es.service_id = :service_id
         ORDER BY u.surname, u.name'
    );
    $employeeQuery->execute(['service_id' => $serviceId]);
    $employees = $employeeQuery->fetchAll();
    if ($employeeId !== null && !in_array($employeeId, array_map(static fn(array $item): int => (int) $item['id'], $employees), true)) {
        $errors[] = 'Wybrany pracownik nie wykonuje tej usługi lub jest nieaktywny.';
        $employeeId = null;
    }
}

$slots = [];
if ($employeeId !== null && reservation_valid_date($date)) {
    $slots = reservation_available_slots($connection, $serviceId, $employeeId, $date);
}

$selectedEmployee = null;
foreach ($employees as $employee) {
    if ((int) $employee['id'] === $employeeId) {
        $selectedEmployee = $employee;
        break;
    }
}

$pageTitle = 'Rezerwacja wizyty';
require dirname(__DIR__) . '/app/views/header.php';
?>
<section class="dashboard-page">
    <div class="container">
        <div class="dashboard-welcome">
            <div><span class="eyebrow">REZERWACJA WIZYTY</span><h1>Wybierz dogodny termin</h1><p>Kolejne kroki: usługa, pracownik, data, godzina i potwierdzenie.</p></div>
        </div>
        <?php if ($errors !== []): ?><div class="notice notice-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <div class="dashboard-grid">
            <article class="dashboard-card">
                <span class="eyebrow">KROK 1 · USŁUGA</span>
                <form class="form-stack" method="get" action="<?= e(app_url('booking.php')) ?>">
                    <label class="field"><span>Usługa</span><select name="service_id" required>
                        <option value="">Wybierz usługę</option>
                        <?php foreach ($connection->query('SELECT id, name, duration, price FROM services WHERE active = 1 ORDER BY name')->fetchAll() as $option): ?>
                            <option value="<?= (int) $option['id'] ?>" <?= $serviceId === (int) $option['id'] ? 'selected' : '' ?>><?= e($option['name']) ?> · <?= (int) $option['duration'] ?> min · <?= e(number_format((float) $option['price'], 2, ',', ' ')) ?> zł</option>
                        <?php endforeach; ?>
                    </select></label>
                    <button class="button button-small" type="submit">Wybierz usługę</button>
                </form>
            </article>
            <?php if ($service !== false): ?>
                <article class="dashboard-card">
                    <span class="eyebrow">KROK 2 · PRACOWNIK</span>
                    <h2><?= e($service['name']) ?></h2>
                    <?php if ($employees === []): ?>
                        <p>Brak aktywnych pracowników przypisanych do tej usługi.</p>
                    <?php else: ?>
                        <form class="form-stack" method="get" action="<?= e(app_url('booking.php')) ?>">
                            <input type="hidden" name="service_id" value="<?= (int) $serviceId ?>">
                            <label class="field"><span>Pracownik</span><select name="employee_id" required>
                                <option value="">Wybierz pracownika</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?= (int) $employee['id'] ?>" <?= $employeeId === (int) $employee['id'] ? 'selected' : '' ?>><?= e($employee['name'] . ' ' . $employee['surname']) ?></option>
                                <?php endforeach; ?>
                            </select></label>
                            <button class="button button-small" type="submit">Wybierz pracownika</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endif; ?>
            <?php if ($selectedEmployee !== null): ?>
                <article class="dashboard-card">
                    <span class="eyebrow">KROK 3 · DATA</span>
                    <p>Pracownik: <strong><?= e($selectedEmployee['name'] . ' ' . $selectedEmployee['surname']) ?></strong></p>
                    <form class="form-stack" method="get" action="<?= e(app_url('booking.php')) ?>">
                        <input type="hidden" name="service_id" value="<?= (int) $serviceId ?>">
                        <input type="hidden" name="employee_id" value="<?= (int) $employeeId ?>">
                        <label class="field"><span>Data wizyty</span><input type="date" name="reservation_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e($date) ?>" required></label>
                        <button class="button button-small" type="submit">Pokaż godziny</button>
                    </form>
                </article>
            <?php endif; ?>
            <?php if ($selectedEmployee !== null && reservation_valid_date($date)): ?>
                <article class="dashboard-card">
                    <span class="eyebrow">KROK 4 · GODZINA</span>
                    <p><?= e($date) ?> · usługa trwa <?= (int) $service['duration'] ?> min</p>
                    <?php if ($slots === []): ?>
                        <p>Brak wolnych godzin w wybranym dniu. Wybierz inną datę.</p>
                    <?php else: ?>
                        <form class="form-stack" method="post" action="<?= e(app_url('booking.php')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="review">
                            <input type="hidden" name="service_id" value="<?= (int) $serviceId ?>">
                            <input type="hidden" name="employee_id" value="<?= (int) $employeeId ?>">
                            <input type="hidden" name="reservation_date" value="<?= e($date) ?>">
                            <label class="field"><span>Godzina rozpoczęcia</span><select name="start_time" required>
                                <option value="">Wybierz godzinę</option>
                                <?php foreach ($slots as $slot): ?><option value="<?= e($slot) ?>" <?= $startTime === $slot ? 'selected' : '' ?>><?= e($slot) ?></option><?php endforeach; ?>
                            </select></label>
                            <button class="button button-small" type="submit">Przejdź do potwierdzenia</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endif; ?>
            <?php if ($confirmation && $service !== false && $selectedEmployee !== null): ?>
                <article class="dashboard-card">
                    <span class="eyebrow">KROK 5 · POTWIERDZENIE</span>
                    <h2>Sprawdź szczegóły wizyty</h2>
                    <p><?= e($service['name']) ?>, <?= e($selectedEmployee['name'] . ' ' . $selectedEmployee['surname']) ?></p>
                    <p><?= e($date) ?>, godz. <?= e($startTime) ?> · <?= (int) $service['duration'] ?> min</p>
                    <p>Cena: <?= e(number_format((float) $service['price'], 2, ',', ' ')) ?> zł</p>
                    <form method="post" action="<?= e(app_url('booking.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="confirm">
                        <input type="hidden" name="confirmation_token" value="<?= e($confirmationToken) ?>">
                        <input type="hidden" name="service_id" value="<?= (int) $serviceId ?>">
                        <input type="hidden" name="employee_id" value="<?= (int) $employeeId ?>">
                        <input type="hidden" name="reservation_date" value="<?= e($date) ?>">
                        <input type="hidden" name="start_time" value="<?= e($startTime) ?>">
                        <button class="button" type="submit">Potwierdź rezerwację</button>
                    </form>
                </article>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require dirname(__DIR__) . '/app/views/footer.php'; ?>
