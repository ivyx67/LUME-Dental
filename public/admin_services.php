<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin_helpers.php';
require_role('admin');

$errors = [];
$form = ['id' => '', 'category_id' => '', 'name' => '', 'description' => '', 'duration' => '30', 'price' => '', 'active' => '1'];
$categories = db()->query('SELECT id, name FROM service_categories ORDER BY name')->fetchAll();
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

if ($editId !== null && $editId !== false && $editId > 0) {
    $edit = db()->prepare(
        'SELECT id, category_id, name, description, duration, price, active
         FROM services WHERE id = :id'
    );
    $edit->execute(['id' => $editId]);
    $service = $edit->fetch();
    if ($service === false) {
        http_response_code(404);
        $errors[] = 'Nie znaleziono wybranej usługi.';
    } else {
        $form = [
            'id' => (string) $service['id'],
            'category_id' => (string) $service['category_id'],
            'name' => (string) $service['name'],
            'description' => (string) ($service['description'] ?? ''),
            'duration' => (string) $service['duration'],
            'price' => (string) $service['price'],
            'active' => (int) $service['active'] === 1 ? '1' : '0',
        ];
    }
}

if (is_post_request()) {
    if (!admin_post_is_valid()) {
        admin_redirect('admin_services.php');
    }

    $action = post_string('action');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (($action === 'delete' || $action === 'toggle') && $id !== false && $id !== null && $id > 0) {
        try {
            if ($action === 'delete') {
                $delete = db()->prepare('DELETE FROM services WHERE id = :id');
                $delete->execute(['id' => $id]);
                set_flash('success', 'Usługa została usunięta.');
            } else {
                $toggle = db()->prepare('UPDATE services SET active = 1 - active WHERE id = :id');
                $toggle->execute(['id' => $id]);
                set_flash('success', 'Status usługi został zmieniony.');
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                set_flash('error', 'Nie można usunąć usługi używanej przez pracowników lub rezerwacje. Zamiast tego ją dezaktywuj.');
            } else {
                throw $exception;
            }
        }
        admin_redirect('admin_services.php');
    }

    $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
    $duration = filter_var($_POST['duration'] ?? null, FILTER_VALIDATE_INT);
    $name = post_string('name');
    $description = post_string('description');
    $price = str_replace(',', '.', post_string('price'));
    $active = isset($_POST['active']) ? '1' : '0';
    $form = [
        'id' => $id === false || $id === null ? '' : (string) $id,
        'category_id' => $categoryId === false || $categoryId === null ? '' : (string) $categoryId,
        'name' => $name,
        'description' => $description,
        'duration' => $duration === false || $duration === null ? post_string('duration') : (string) $duration,
        'price' => $price,
        'active' => $active,
    ];

    if ($action !== 'save') {
        $errors[] = 'Nieznana operacja. Odśwież stronę i spróbuj ponownie.';
    }
    if ($categoryId === false || $categoryId === null || $categoryId < 1) {
        $errors[] = 'Wybierz kategorię usługi.';
    } else {
        $categoryExists = db()->prepare('SELECT id FROM service_categories WHERE id = :id');
        $categoryExists->execute(['id' => $categoryId]);
        if ($categoryExists->fetch() === false) {
            $errors[] = 'Wybrana kategoria już nie istnieje.';
        }
    }
    if ($name === '' || mb_strlen($name) > 150) {
        $errors[] = 'Nazwa usługi jest wymagana i może mieć maksymalnie 150 znaków.';
    }
    if (mb_strlen($description) > 5000) {
        $errors[] = 'Opis usługi może mieć maksymalnie 5000 znaków.';
    }
    if ($duration === false || $duration < 5 || $duration > 600) {
        $errors[] = 'Czas trwania musi wynosić od 5 do 600 minut.';
    }
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $price)) {
        $errors[] = 'Podaj poprawną cenę, np. 120 lub 120,50.';
    }
    if ($form['id'] !== '' && ($id === false || $id < 1)) {
        $errors[] = 'Nieprawidłowy identyfikator usługi.';
    }

    if ($errors === []) {
        $parameters = [
            'category_id' => $categoryId,
            'name' => $name,
            'description' => $description === '' ? null : $description,
            'duration' => $duration,
            'price' => $price,
            'active' => $active === '1' ? 1 : 0,
        ];
        if ($form['id'] === '') {
            $save = db()->prepare(
                'INSERT INTO services (category_id, name, description, duration, price, active)
                 VALUES (:category_id, :name, :description, :duration, :price, :active)'
            );
            $save->execute($parameters);
            set_flash('success', 'Usługa została dodana.');
        } else {
            $parameters['id'] = $form['id'];
            $save = db()->prepare(
                'UPDATE services
                 SET category_id = :category_id, name = :name, description = :description,
                     duration = :duration, price = :price, active = :active
                 WHERE id = :id'
            );
            $save->execute($parameters);
            set_flash('success', 'Zmiany usługi zostały zapisane.');
        }
        admin_redirect('admin_services.php');
    }
}

$searchValue = $_GET['q'] ?? '';
$search = is_string($searchValue) ? trim($searchValue) : '';
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}
$pageValue = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$currentPage = max(1, is_int($pageValue) ? $pageValue : 1);
$pageSize = 10;
$where = $search === '' ? '' : ' WHERE s.name LIKE :name_search OR s.description LIKE :description_search OR c.name LIKE :category_search';
$count = db()->prepare(
    'SELECT COUNT(*) FROM services s INNER JOIN service_categories c ON c.id = s.category_id' . $where
);
if ($search !== '') {
    $like = '%' . $search . '%';
    $count->execute([
        'name_search' => $like,
        'description_search' => $like,
        'category_search' => $like,
    ]);
} else {
    $count->execute();
}
$totalRows = (int) $count->fetchColumn();
$pageCount = max(1, (int) ceil($totalRows / $pageSize));
$currentPage = min($currentPage, $pageCount);
$list = db()->prepare(
    'SELECT s.id, s.name, s.description, s.duration, s.price, s.active, c.name AS category_name
     FROM services s
     INNER JOIN service_categories c ON c.id = s.category_id' . $where . '
     ORDER BY s.name
     LIMIT :limit OFFSET :offset'
);
if ($search !== '') {
    $like = '%' . $search . '%';
    $list->bindValue(':name_search', $like);
    $list->bindValue(':description_search', $like);
    $list->bindValue(':category_search', $like);
}
$list->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$list->bindValue(':offset', ($currentPage - 1) * $pageSize, PDO::PARAM_INT);
$list->execute();
$services = $list->fetchAll();

admin_page_start('Usługi', 'services');
?>
<div class="dashboard-welcome">
    <div><span class="eyebrow">PANEL ADMINISTRATORA</span><h1>Usługi</h1><p>Zarządzaj ofertą, kategoriami, cenami i czasem wizyty.</p></div>
    <span class="role-pill"><?= $totalRows ?> usług</span>
</div>
<div class="admin-content-grid">
    <section class="admin-panel">
        <div class="form-heading"><h2><?= $form['id'] === '' ? 'Dodaj usługę' : 'Edytuj usługę' ?></h2><p>Zmiany są widoczne na publicznej liście aktywnych usług.</p></div>
        <?php if ($errors !== []): ?>
            <div class="notice notice-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form class="form-stack" method="post" action="<?= e(app_url('admin_services.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e($form['id']) ?>">
            <label class="field"><span>Nazwa usługi</span><input name="name" maxlength="150" value="<?= e($form['name']) ?>" required></label>
            <label class="field"><span>Kategoria</span>
                <select name="category_id" required>
                    <option value="">Wybierz kategorię</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= $form['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Opis</span><textarea name="description" rows="3" maxlength="5000"><?= e($form['description']) ?></textarea></label>
            <div class="field-row">
                <label class="field"><span>Czas (minuty)</span><input name="duration" type="number" min="5" max="600" step="1" value="<?= e($form['duration']) ?>" required></label>
                <label class="field"><span>Cena (zł)</span><input name="price" inputmode="decimal" value="<?= e($form['price']) ?>" placeholder="120,00" required></label>
            </div>
            <label class="admin-checkbox"><input type="checkbox" name="active" value="1" <?= $form['active'] === '1' ? 'checked' : '' ?>> Usługa aktywna</label>
            <?php if ($categories === []): ?><p class="notice notice-info">Najpierw dodaj kategorię, aby utworzyć usługę.</p><?php endif; ?>
            <button class="button button-full" type="submit" <?= $categories === [] ? 'disabled' : '' ?>>Zapisz usługę</button>
            <?php if ($form['id'] !== ''): ?><a class="quiet-link" href="<?= e(app_url('admin_services.php')) ?>">Anuluj edycję</a><?php endif; ?>
        </form>
    </section>
    <section class="admin-panel admin-list-panel">
        <div class="admin-list-heading"><div><span class="eyebrow">KATALOG USŁUG</span><h2>Wyniki</h2></div></div>
        <form class="admin-filter-form" method="get" action="<?= e(app_url('admin_services.php')) ?>">
            <label class="field"><span>Szukaj usługi</span><input name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Usługa lub kategoria"></label>
            <button class="button button-small" type="submit">Szukaj</button>
        </form>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Usługa</th><th>Cena / czas</th><th>Status</th><th>Operacje</th></tr></thead>
                <tbody>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td data-label="Usługa"><strong><?= e($service['name']) ?></strong><small class="table-subtext"><?= e($service['category_name']) ?></small></td>
                        <td data-label="Cena / czas"><?= e(number_format((float) $service['price'], 2, ',', ' ')) ?> zł<br><?= (int) $service['duration'] ?> min</td>
                        <td data-label="Status"><span class="status-badge <?= (int) $service['active'] === 1 ? 'status-active' : 'status-inactive' ?>"><?= (int) $service['active'] === 1 ? 'Aktywna' : 'Nieaktywna' ?></span></td>
                        <td data-label="Operacje" class="admin-actions">
                            <a class="admin-link" href="<?= e(app_url('admin_services.php?edit=' . (int) $service['id'])) ?>">Edytuj</a>
                            <form method="post" action="<?= e(app_url('admin_services.php')) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                                <button class="admin-link" type="submit"><?= (int) $service['active'] === 1 ? 'Dezaktywuj' : 'Aktywuj' ?></button>
                            </form>
                            <form method="post" action="<?= e(app_url('admin_services.php')) ?>" data-confirm="Usunąć tę usługę? Jeśli jest przypisana do pracownika lub rezerwacji, należy ją dezaktywować.">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                                <button class="admin-link admin-link-danger" type="submit">Usuń</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($services === []): ?><tr><td colspan="4" class="table-empty">Nie znaleziono usług.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php admin_page_links($currentPage, $pageCount, 'admin_services.php', $search === '' ? [] : ['q' => $search]); ?>
    </section>
</div>
<?php admin_page_end(); ?>
