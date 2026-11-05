<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin_helpers.php';
require_role('admin');

$errors = [];
$dayNames = [
    1 => 'Poniedziałek',
    2 => 'Wtorek',
    3 => 'Środa',
    4 => 'Czwartek',
    5 => 'Piątek',
    6 => 'Sobota',
    7 => 'Niedziela',
];
$services = db()->query('SELECT id, name, active FROM services ORDER BY name')->fetchAll();

if (is_post_request()) {
    if (!admin_post_is_valid()) {
        admin_redirect('admin_employees.php');
    }

    $action = post_string('action');
    $employeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'create') {
        $name = post_string('name');
        $surname = post_string('surname');
        $email = strtolower(post_string('email'));
        $phone = post_string('phone');
        $description = post_string('description');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if (!preg_match('/^[\p{L}\p{M}][\p{L}\p{M} .\'-]*$/u', $name) || mb_strlen($name) > 50) {
            $errors[] = 'Wpisz poprawne imię (maksymalnie 50 znaków).';
        }
        if (!preg_match('/^[\p{L}\p{M}][\p{L}\p{M} .\'-]*$/u', $surname) || mb_strlen($surname) > 80) {
            $errors[] = 'Wpisz poprawne nazwisko (maksymalnie 80 znaków).';
        }
        if (strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Wpisz poprawny adres e-mail.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+() .-]{6,20}$/', $phone)) {
            $errors[] = 'Wpisz poprawny numer telefonu lub pozostaw pole puste.';
        }
        if (preg_match('//u', $password) !== 1 || strlen($password) < 10 || strlen($password) > 72) {
            $errors[] = 'Hasło pracownika musi mieć od 10 do 72 bajtów.';
        }
        if (mb_strlen($description) > 5000) {
            $errors[] = 'Opis pracownika może mieć maksymalnie 5000 znaków.';
        }

        if ($errors === []) {
            $connection = db();
            $connection->beginTransaction();
            try {
                $createUser = $connection->prepare(
                    "INSERT INTO users (name, surname, email, password, phone, role)
                     VALUES (:name, :surname, :email, :password, :phone, 'employee')"
                );
                $createUser->execute([
                    'name' => $name,
                    'surname' => $surname,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'phone' => $phone === '' ? null : $phone,
                ]);
                $createEmployee = $connection->prepare(
                    'INSERT INTO employees (user_id, description) VALUES (:user_id, :description)'
                );
                $createEmployee->execute([
                    'user_id' => $connection->lastInsertId(),
                    'description' => $description === '' ? null : $description,
                ]);
                $connection->commit();
                set_flash('success', 'Konto pracownika zostało utworzone. Pracownik może zalogować się podanym adresem e-mail i hasłem.');
                admin_redirect('admin_employees.php');
            } catch (PDOException $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                if ($exception->getCode() === '23000' && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                    $errors[] = 'Konto z tym adresem e-mail już istnieje.';
                } else {
                    throw $exception;
                }
            }
        }
    } elseif ($action === 'save' && $employeeId !== false && $employeeId !== null && $employeeId > 0) {
        $name = post_string('name');
        $surname = post_string('surname');
        $email = strtolower(post_string('email'));
        $phone = post_string('phone');
        $description = post_string('description');
        $selectedServices = $_POST['service_ids'] ?? [];
        $employeeQuery = db()->prepare(
            'SELECT e.id, e.user_id FROM employees e WHERE e.id = :id'
        );
        $employeeQuery->execute(['id' => $employeeId]);
        $employee = $employeeQuery->fetch();

        if ($employee === false) {
            $errors[] = 'Nie znaleziono pracownika do edycji.';
        }
        if (!preg_match('/^[\p{L}\p{M}][\p{L}\p{M} .\'-]*$/u', $name) || mb_strlen($name) > 50) {
            $errors[] = 'Wpisz poprawne imię (maksymalnie 50 znaków).';
        }
        if (!preg_match('/^[\p{L}\p{M}][\p{L}\p{M} .\'-]*$/u', $surname) || mb_strlen($surname) > 80) {
            $errors[] = 'Wpisz poprawne nazwisko (maksymalnie 80 znaków).';
        }
        if (strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Wpisz poprawny adres e-mail.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+() .-]{6,20}$/', $phone)) {
            $errors[] = 'Wpisz poprawny numer telefonu lub pozostaw pole puste.';
        }
        if (mb_strlen($description) > 5000) {
            $errors[] = 'Opis pracownika może mieć maksymalnie 5000 znaków.';
        }
        if (!is_array($selectedServices)) {
            $errors[] = 'Nieprawidłowa lista usług.';
            $selectedServices = [];
        }

        $serviceIds = [];
        foreach ($selectedServices as $selectedService) {
            $serviceId = filter_var($selectedService, FILTER_VALIDATE_INT);
            if ($serviceId === false || $serviceId < 1) {
                $errors[] = 'Lista przypisanych usług zawiera nieprawidłowe dane.';
                break;
            }
            $serviceIds[] = $serviceId;
        }
        $serviceIds = array_values(array_unique($serviceIds));
        if ($errors === [] && $serviceIds !== []) {
            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            $validServices = db()->prepare('SELECT COUNT(*) FROM services WHERE id IN (' . $placeholders . ')');
            $validServices->execute($serviceIds);
            if ((int) $validServices->fetchColumn() !== count($serviceIds)) {
                $errors[] = 'Jedna z wybranych usług już nie istnieje.';
            }
        }

        if ($errors === []) {
            $duplicate = db()->prepare('SELECT id FROM users WHERE email = :email AND id <> :user_id LIMIT 1');
            $duplicate->execute(['email' => $email, 'user_id' => $employee['user_id']]);
            if ($duplicate->fetch() !== false) {
                $errors[] = 'Konto z tym adresem e-mail już istnieje.';
            }
        }

        if ($errors === []) {
            $connection = db();
            $connection->beginTransaction();
            try {
                $updateUser = $connection->prepare(
                    'UPDATE users SET name = :name, surname = :surname, email = :email, phone = :phone
                     WHERE id = :user_id'
                );
                $updateUser->execute([
                    'name' => $name,
                    'surname' => $surname,
                    'email' => $email,
                    'phone' => $phone === '' ? null : $phone,
                    'user_id' => $employee['user_id'],
                ]);
                $updateEmployee = $connection->prepare('UPDATE employees SET description = :description WHERE id = :id');
                $updateEmployee->execute([
                    'description' => $description === '' ? null : $description,
                    'id' => $employeeId,
                ]);
                $removeServices = $connection->prepare('DELETE FROM employee_services WHERE employee_id = :employee_id');
                $removeServices->execute(['employee_id' => $employeeId]);
                $assignService = $connection->prepare(
                    'INSERT INTO employee_services (employee_id, service_id) VALUES (:employee_id, :service_id)'
                );
                foreach ($serviceIds as $serviceId) {
                    $assignService->execute(['employee_id' => $employeeId, 'service_id' => $serviceId]);
                }
                $connection->commit();
                set_flash('success', 'Dane pracownika i przypisane usługi zostały zapisane.');
                admin_redirect('admin_employees.php');
            } catch (PDOException $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                if ($exception->getCode() === '23000') {
                    $errors[] = 'Nie udało się zapisać danych. Sprawdź adres e-mail i wybrane usługi.';
                } else {
                    throw $exception;
                }
            }
        }
    } elseif ($action === 'toggle' && $employeeId !== false && $employeeId !== null && $employeeId > 0) {
        $connection = db();
        $connection->beginTransaction();
        try {
            $findEmployee = $connection->prepare('SELECT user_id FROM employees WHERE id = :id');
            $findEmployee->execute(['id' => $employeeId]);
            $employee = $findEmployee->fetch();
            if ($employee === false) {
                set_flash('error', 'Nie znaleziono pracownika.');
            } else {
                $toggleEmployee = $connection->prepare('UPDATE employees SET active = 1 - active WHERE id = :id');
                $toggleEmployee->execute(['id' => $employeeId]);
                $toggleUser = $connection->prepare('UPDATE users SET active = 1 - active WHERE id = :id');
                $toggleUser->execute(['id' => $employee['user_id']]);
                set_flash('success', 'Dostęp pracownika został zmieniony.');
            }
            $connection->commit();
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
        admin_redirect('admin_employees.php');
    } elseif ($action === 'add_availability' && $employeeId !== false && $employeeId !== null && $employeeId > 0) {
        $day = filter_var($_POST['day_of_week'] ?? null, FILTER_VALIDATE_INT);
        $start = post_string('start_time');
        $end = post_string('end_time');
        if ($day === false || $day === null || !isset($dayNames[$day])) {
            $errors[] = 'Wybierz prawidłowy dzień tygodnia.';
        }
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start)
            || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end)
            || $start >= $end
        ) {
            $errors[] = 'Podaj prawidłowy przedział godzin. Godzina końcowa musi być późniejsza od początkowej.';
        }
        $employeeExists = db()->prepare('SELECT id FROM employees WHERE id = :id');
        $employeeExists->execute(['id' => $employeeId]);
        if ($employeeExists->fetch() === false) {
            $errors[] = 'Nie znaleziono pracownika.';
        }
        if ($errors === []) {
            $overlap = db()->prepare(
                'SELECT id FROM employee_availability
                 WHERE employee_id = :employee_id AND day_of_week = :day
                   AND start_time < :end_time AND end_time > :start_time
                 LIMIT 1'
            );
            $overlap->execute([
                'employee_id' => $employeeId,
                'day' => $day,
                'start_time' => $start . ':00',
                'end_time' => $end . ':00',
            ]);
            if ($overlap->fetch() !== false) {
                $errors[] = 'Ten przedział nakłada się na już zapisane godziny pracy.';
            }
        }
        if ($errors === []) {
            $add = db()->prepare(
                'INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time)
                 VALUES (:employee_id, :day, :start_time, :end_time)'
            );
            $add->execute([
                'employee_id' => $employeeId,
                'day' => $day,
                'start_time' => $start . ':00',
                'end_time' => $end . ':00',
            ]);
            set_flash('success', 'Godziny pracy zostały dodane.');
            admin_redirect('admin_employees.php');
        }
    } elseif ($action === 'delete_availability' && $employeeId !== false && $employeeId !== null && $employeeId > 0) {
        $availabilityId = filter_var($_POST['availability_id'] ?? null, FILTER_VALIDATE_INT);
        if ($availabilityId === false || $availabilityId === null || $availabilityId < 1) {
            set_flash('error', 'Nieprawidłowy przedział dostępności.');
        } else {
            $delete = db()->prepare(
                'DELETE FROM employee_availability WHERE id = :id AND employee_id = :employee_id'
            );
            $delete->execute(['id' => $availabilityId, 'employee_id' => $employeeId]);
            set_flash($delete->rowCount() === 1 ? 'success' : 'error', $delete->rowCount() === 1
                ? 'Przedział godzin pracy został usunięty.'
                : 'Nie znaleziono tego przedziału dostępności.');
        }
        admin_redirect('admin_employees.php');
    } else {
        $errors[] = 'Nieprawidłowa operacja. Odśwież stronę i spróbuj ponownie.';
    }
}

$employees = db()->query(
    'SELECT e.id, e.description, e.active AS employee_active,
            u.id AS user_id, u.name, u.surname, u.email, u.phone, u.active AS user_active
     FROM employees e
     INNER JOIN users u ON u.id = e.user_id
     ORDER BY u.surname, u.name'
)->fetchAll();
$assigned = db()->query('SELECT employee_id, service_id FROM employee_services')->fetchAll();
$assignedServices = [];
foreach ($assigned as $assignment) {
    $assignedServices[(int) $assignment['employee_id']][] = (int) $assignment['service_id'];
}
$availabilityRows = db()->query(
    'SELECT id, employee_id, day_of_week, start_time, end_time
     FROM employee_availability ORDER BY day_of_week, start_time'
)->fetchAll();
$availability = [];
foreach ($availabilityRows as $row) {
    $availability[(int) $row['employee_id']][] = $row;
}

admin_page_start('Pracownicy', 'employees');
?>
<div class="dashboard-welcome">
    <div><span class="eyebrow">PANEL ADMINISTRATORA</span><h1>Pracownicy</h1><p>Ustawiaj dane kont, przypisane usługi oraz godziny pracy.</p></div>
    <span class="role-pill"><?= count($employees) ?> pracowników</span>
</div>
<?php if ($errors !== []): ?>
    <div class="notice notice-error admin-wide-notice" role="alert"><strong>Nie udało się zapisać zmian:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<section class="admin-panel admin-create-employee">
    <div class="form-heading"><h2>Dodaj pracownika</h2><p>System utworzy konto z rolą pracownika. Hasło przekaż pracownikowi bezpiecznie.</p></div>
    <form class="form-stack" method="post" action="<?= e(app_url('admin_employees.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <div class="field-row">
            <label class="field"><span>Imię</span><input name="name" maxlength="50" required></label>
            <label class="field"><span>Nazwisko</span><input name="surname" maxlength="80" required></label>
            <label class="field"><span>Adres e-mail</span><input name="email" type="email" maxlength="100" required></label>
            <label class="field"><span>Telefon (opcjonalnie)</span><input name="phone" type="tel" maxlength="20"></label>
            <label class="field"><span>Hasło początkowe (min. 10 znaków)</span><input name="password" type="password" minlength="10" maxlength="72" required></label>
            <label class="field"><span>Opis / specjalizacja</span><input name="description" maxlength="5000"></label>
        </div>
        <button class="button" type="submit">Utwórz konto pracownika</button>
    </form>
</section>
<div class="admin-employee-list">
    <?php foreach ($employees as $employee): ?>
        <?php $employeeId = (int) $employee['id']; ?>
        <article class="admin-panel employee-admin-card">
            <div class="admin-list-heading">
                <div><span class="eyebrow">PRACOWNIK</span><h2><?= e($employee['name'] . ' ' . $employee['surname']) ?></h2></div>
                <span class="status-badge <?= (int) $employee['employee_active'] === 1 && (int) $employee['user_active'] === 1 ? 'status-active' : 'status-inactive' ?>">
                    <?= (int) $employee['employee_active'] === 1 && (int) $employee['user_active'] === 1 ? 'Aktywny' : 'Nieaktywny' ?>
                </span>
            </div>
            <div class="employee-management-grid">
                <form class="form-stack" method="post" action="<?= e(app_url('admin_employees.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
                    <div class="form-heading"><h3>Dane i usługi</h3></div>
                    <div class="field-row">
                        <label class="field"><span>Imię</span><input name="name" maxlength="50" value="<?= e($employee['name']) ?>" required></label>
                        <label class="field"><span>Nazwisko</span><input name="surname" maxlength="80" value="<?= e($employee['surname']) ?>" required></label>
                    </div>
                    <label class="field"><span>Adres e-mail</span><input name="email" type="email" maxlength="100" value="<?= e($employee['email']) ?>" required></label>
                    <label class="field"><span>Telefon</span><input name="phone" type="tel" maxlength="20" value="<?= e((string) ($employee['phone'] ?? '')) ?>"></label>
                    <label class="field"><span>Opis / specjalizacja</span><textarea name="description" rows="3" maxlength="5000"><?= e((string) ($employee['description'] ?? '')) ?></textarea></label>
                    <fieldset class="service-assignment">
                        <legend>Usługi wykonywane przez pracownika</legend>
                        <?php foreach ($services as $service): ?>
                            <label class="admin-checkbox">
                                <input type="checkbox" name="service_ids[]" value="<?= (int) $service['id'] ?>"
                                    <?= in_array((int) $service['id'], $assignedServices[$employeeId] ?? [], true) ? 'checked' : '' ?>>
                                <?= e($service['name']) ?><?= (int) $service['active'] === 1 ? '' : ' (nieaktywna)' ?>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($services === []): ?><p>Najpierw dodaj usługę.</p><?php endif; ?>
                    </fieldset>
                    <button class="button button-small" type="submit">Zapisz dane i usługi</button>
                </form>
                <div class="availability-management">
                    <div class="form-heading"><h3>Dostępność tygodniowa</h3><p>Dodaj godziny osobno dla każdego dnia. Przedziały nie mogą się nakładać.</p></div>
                    <form class="availability-form" method="post" action="<?= e(app_url('admin_employees.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="add_availability">
                        <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
                        <label class="field"><span>Dzień</span><select name="day_of_week" required>
                            <?php foreach ($dayNames as $day => $dayName): ?><option value="<?= $day ?>"><?= e($dayName) ?></option><?php endforeach; ?>
                        </select></label>
                        <label class="field"><span>Od</span><input name="start_time" type="time" required></label>
                        <label class="field"><span>Do</span><input name="end_time" type="time" required></label>
                        <button class="button button-small" type="submit">Dodaj godziny</button>
                    </form>
                    <ul class="availability-list">
                        <?php foreach ($availability[$employeeId] ?? [] as $time): ?>
                            <li>
                                <span><?= e($dayNames[(int) $time['day_of_week']] ?? 'Nieznany dzień') ?>, <?= e(substr((string) $time['start_time'], 0, 5)) ?>–<?= e(substr((string) $time['end_time'], 0, 5)) ?></span>
                                <form method="post" action="<?= e(app_url('admin_employees.php')) ?>" data-confirm="Usunąć ten przedział godzin pracy?">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_availability">
                                    <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
                                    <input type="hidden" name="availability_id" value="<?= (int) $time['id'] ?>">
                                    <button class="admin-link admin-link-danger" type="submit">Usuń</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                        <?php if (($availability[$employeeId] ?? []) === []): ?><li>Nie ustawiono jeszcze godzin pracy.</li><?php endif; ?>
                    </ul>
                    <form method="post" action="<?= e(app_url('admin_employees.php')) ?>" data-confirm="Zmienić aktywność konta pracownika?">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
                        <button class="admin-link" type="submit"><?= (int) $employee['employee_active'] === 1 && (int) $employee['user_active'] === 1 ? 'Dezaktywuj konto' : 'Aktywuj konto' ?></button>
                    </form>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if ($employees === []): ?><div class="notice notice-info">Nie dodano jeszcze żadnego pracownika.</div><?php endif; ?>
</div>
<?php admin_page_end(); ?>
