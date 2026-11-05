<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin_helpers.php';
$admin = require_role('admin');

if (is_post_request()) {
    if (!admin_post_is_valid()) {
        admin_redirect('admin_users.php');
    }

    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $active = filter_var($_POST['active'] ?? null, FILTER_VALIDATE_INT);
    if (post_string('action') !== 'set_active' || $userId === false || $userId === null || $userId < 1
        || !in_array($active, [0, 1], true)
    ) {
        set_flash('error', 'Nieprawidłowe dane zmiany dostępu użytkownika.');
        admin_redirect('admin_users.php');
    }
    if ($userId === (int) $admin['id'] && $active === 0) {
        set_flash('error', 'Nie możesz wyłączyć własnego konta administratora.');
        admin_redirect('admin_users.php');
    }

    $connection = db();
    $connection->beginTransaction();
    try {
        $findUser = $connection->prepare('SELECT id, role, active FROM users WHERE id = :id');
        $findUser->execute(['id' => $userId]);
        $targetUser = $findUser->fetch();
        if ($targetUser === false) {
            $connection->rollBack();
            set_flash('error', 'Nie znaleziono użytkownika.');
            admin_redirect('admin_users.php');
        }
        if ($targetUser['role'] === 'admin' && (int) $targetUser['active'] === 1 && $active === 0) {
            $activeAdmins = (int) $connection->query(
                "SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1"
            )->fetchColumn();
            if ($activeAdmins <= 1) {
                $connection->rollBack();
                set_flash('error', 'Nie można wyłączyć ostatniego aktywnego administratora.');
                admin_redirect('admin_users.php');
            }
        }

        $updateUser = $connection->prepare('UPDATE users SET active = :active WHERE id = :id');
        $updateUser->execute(['active' => $active, 'id' => $userId]);
        if ($targetUser['role'] === 'employee') {
            $updateEmployee = $connection->prepare('UPDATE employees SET active = :active WHERE user_id = :user_id');
            $updateEmployee->execute(['active' => $active, 'user_id' => $userId]);
        }
        $connection->commit();
        set_flash('success', $active === 1 ? 'Konto użytkownika zostało aktywowane.' : 'Konto użytkownika zostało dezaktywowane.');
    } catch (PDOException $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
    admin_redirect('admin_users.php');
}

$searchValue = $_GET['q'] ?? '';
$roleValue = $_GET['role'] ?? '';
$activeValue = $_GET['active'] ?? '';
$search = is_string($searchValue) ? trim($searchValue) : '';
$roleFilter = is_string($roleValue) && in_array($roleValue, ['client', 'employee', 'admin'], true) ? $roleValue : '';
$activeFilter = is_string($activeValue) && in_array($activeValue, ['0', '1'], true) ? $activeValue : '';
$filterErrors = [];
if ($roleValue !== '' && $roleFilter === '') {
    $filterErrors[] = 'Wybrano nieprawidłową rolę.';
}
if ($activeValue !== '' && $activeFilter === '') {
    $filterErrors[] = 'Wybrano nieprawidłowy status konta.';
}
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}
$conditions = [];
$parameters = [];
if ($search !== '') {
    $conditions[] = '(name LIKE :name OR surname LIKE :surname OR email LIKE :email)';
    $like = '%' . $search . '%';
    $parameters['name'] = $like;
    $parameters['surname'] = $like;
    $parameters['email'] = $like;
}
if ($roleFilter !== '') {
    $conditions[] = 'role = :role';
    $parameters['role'] = $roleFilter;
}
if ($activeFilter !== '') {
    $conditions[] = 'active = :active';
    $parameters['active'] = (int) $activeFilter;
}
$where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
$count = db()->prepare('SELECT COUNT(*) FROM users' . $where);
$count->execute($parameters);
$totalRows = (int) $count->fetchColumn();
$pageValue = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$currentPage = max(1, is_int($pageValue) ? $pageValue : 1);
$pageSize = 20;
$pageCount = max(1, (int) ceil($totalRows / $pageSize));
$currentPage = min($currentPage, $pageCount);
$list = db()->prepare(
    'SELECT id, name, surname, email, phone, role, active, created_at
     FROM users' . $where . '
     ORDER BY created_at DESC, id DESC
     LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $key => $value) {
    $list->bindValue(':' . $key, $value);
}
$list->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$list->bindValue(':offset', ($currentPage - 1) * $pageSize, PDO::PARAM_INT);
$list->execute();
$users = $list->fetchAll();
$filters = [];
if ($search !== '') {
    $filters['q'] = $search;
}
if ($roleFilter !== '') {
    $filters['role'] = $roleFilter;
}
if ($activeFilter !== '') {
    $filters['active'] = $activeFilter;
}

admin_page_start('Użytkownicy', 'users');
?>
<div class="dashboard-welcome">
    <div><span class="eyebrow">PANEL ADMINISTRATORA</span><h1>Użytkownicy</h1><p>Przeglądaj konta i kontroluj ich dostęp do aplikacji.</p></div>
    <span class="role-pill"><?= $totalRows ?> kont</span>
</div>
<?php if ($filterErrors !== []): ?>
    <div class="notice notice-error admin-wide-notice" role="alert"><ul><?php foreach ($filterErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<section class="admin-panel admin-list-panel">
    <form class="admin-user-filters" method="get" action="<?= e(app_url('admin_users.php')) ?>">
        <label class="field filter-search"><span>Szukaj</span><input name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Imię, nazwisko lub e-mail"></label>
        <label class="field"><span>Rola</span><select name="role">
            <option value="">Wszystkie role</option>
            <option value="client" <?= $roleFilter === 'client' ? 'selected' : '' ?>>Klient</option>
            <option value="employee" <?= $roleFilter === 'employee' ? 'selected' : '' ?>>Pracownik</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrator</option>
        </select></label>
        <label class="field"><span>Dostęp</span><select name="active">
            <option value="">Aktywne i nieaktywne</option>
            <option value="1" <?= $activeFilter === '1' ? 'selected' : '' ?>>Aktywne</option>
            <option value="0" <?= $activeFilter === '0' ? 'selected' : '' ?>>Nieaktywne</option>
        </select></label>
        <button class="button button-small" type="submit">Filtruj</button>
        <a class="quiet-link" href="<?= e(app_url('admin_users.php')) ?>">Wyczyść</a>
    </form>
    <div class="table-wrap">
        <table class="admin-table">
            <thead><tr><th>Użytkownik</th><th>Telefon</th><th>Rola</th><th>Utworzono</th><th>Dostęp</th></tr></thead>
            <tbody>
            <?php foreach ($users as $account): ?>
                <tr>
                    <td data-label="Użytkownik"><strong><?= e($account['name'] . ' ' . $account['surname']) ?></strong><small class="table-subtext"><?= e($account['email']) ?></small></td>
                    <td data-label="Telefon"><?= e((string) ($account['phone'] ?? '—')) ?></td>
                    <td data-label="Rola"><?= e(role_label((string) $account['role'])) ?></td>
                    <td data-label="Utworzono"><?= e(substr((string) $account['created_at'], 0, 10)) ?></td>
                    <td data-label="Dostęp">
                        <?php if ((int) $account['id'] === (int) $admin['id']): ?>
                            <span class="status-badge status-active">Twoje konto</span>
                        <?php else: ?>
                            <form method="post" action="<?= e(app_url('admin_users.php')) ?>" data-confirm="Zmienić dostęp tego konta?">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="set_active">
                                <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                <input type="hidden" name="active" value="<?= (int) $account['active'] === 1 ? '0' : '1' ?>">
                                <button class="admin-link <?= (int) $account['active'] === 1 ? 'admin-link-danger' : '' ?>" type="submit">
                                    <?= (int) $account['active'] === 1 ? 'Dezaktywuj' : 'Aktywuj' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($users === []): ?><tr><td colspan="5" class="table-empty">Nie znaleziono użytkowników.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php admin_page_links($currentPage, $pageCount, 'admin_users.php', $filters); ?>
</section>
<?php admin_page_end(); ?>
