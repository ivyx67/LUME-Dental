<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin_helpers.php';
require_role('admin');

$errors = [];
$form = ['id' => '', 'name' => '', 'description' => ''];
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

if ($editId !== null && $editId !== false && $editId > 0) {
    $edit = db()->prepare('SELECT id, name, description FROM service_categories WHERE id = :id');
    $edit->execute(['id' => $editId]);
    $category = $edit->fetch();
    if ($category === false) {
        http_response_code(404);
        $errors[] = 'Nie znaleziono wybranej kategorii.';
    } else {
        $form = [
            'id' => (string) $category['id'],
            'name' => (string) $category['name'],
            'description' => (string) ($category['description'] ?? ''),
        ];
    }
}

if (is_post_request()) {
    if (!admin_post_is_valid()) {
        admin_redirect('admin_categories.php');
    }

    $action = post_string('action');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'delete' && $id !== false && $id !== null && $id > 0) {
        try {
            $delete = db()->prepare('DELETE FROM service_categories WHERE id = :id');
            $delete->execute(['id' => $id]);
            set_flash('success', 'Kategoria została usunięta.');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                set_flash('error', 'Nie można usunąć kategorii, do której przypisano usługi. Najpierw zmień kategorię tych usług.');
            } else {
                throw $exception;
            }
        }
        admin_redirect('admin_categories.php');
    }

    $form = [
        'id' => $id === false || $id === null ? '' : (string) $id,
        'name' => post_string('name'),
        'description' => post_string('description'),
    ];

    if ($action !== 'save') {
        $errors[] = 'Nieznana operacja. Odśwież stronę i spróbuj ponownie.';
    }
    if ($form['name'] === '' || mb_strlen($form['name']) > 100) {
        $errors[] = 'Nazwa kategorii jest wymagana i może mieć maksymalnie 100 znaków.';
    }
    if (mb_strlen($form['description']) > 5000) {
        $errors[] = 'Opis kategorii może mieć maksymalnie 5000 znaków.';
    }
    if ($form['id'] !== '' && ((int) $form['id'] < 1 || $id === false)) {
        $errors[] = 'Nieprawidłowy identyfikator kategorii.';
    }

    if ($errors === []) {
        try {
            if ($form['id'] === '') {
                $save = db()->prepare(
                    'INSERT INTO service_categories (name, description) VALUES (:name, :description)'
                );
                $save->execute([
                    'name' => $form['name'],
                    'description' => $form['description'] === '' ? null : $form['description'],
                ]);
                set_flash('success', 'Kategoria została dodana.');
            } else {
                $save = db()->prepare(
                    'UPDATE service_categories SET name = :name, description = :description WHERE id = :id'
                );
                $save->execute([
                    'name' => $form['name'],
                    'description' => $form['description'] === '' ? null : $form['description'],
                    'id' => $form['id'],
                ]);
                set_flash('success', 'Zmiany kategorii zostały zapisane.');
            }
            admin_redirect('admin_categories.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000' && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                $errors[] = 'Kategoria o takiej nazwie już istnieje.';
            } else {
                throw $exception;
            }
        }
    }
}

$search = post_string('q');
if (!is_post_request()) {
    $searchValue = $_GET['q'] ?? '';
    $search = is_string($searchValue) ? trim($searchValue) : '';
}
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}
$pageValue = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$currentPage = max(1, is_int($pageValue) ? $pageValue : 1);
$pageSize = 10;
$where = $search === '' ? '' : ' WHERE name LIKE :name_search OR description LIKE :description_search';
$count = db()->prepare('SELECT COUNT(*) FROM service_categories' . $where);
if ($search !== '') {
    $count->execute([
        'name_search' => '%' . $search . '%',
        'description_search' => '%' . $search . '%',
    ]);
} else {
    $count->execute();
}
$totalRows = (int) $count->fetchColumn();
$pageCount = max(1, (int) ceil($totalRows / $pageSize));
$currentPage = min($currentPage, $pageCount);
$list = db()->prepare(
    'SELECT id, name, description
     FROM service_categories' . $where . '
     ORDER BY name
     LIMIT :limit OFFSET :offset'
);
if ($search !== '') {
    $list->bindValue(':name_search', '%' . $search . '%');
    $list->bindValue(':description_search', '%' . $search . '%');
}
$list->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$list->bindValue(':offset', ($currentPage - 1) * $pageSize, PDO::PARAM_INT);
$list->execute();
$categories = $list->fetchAll();

admin_page_start('Kategorie usług', 'categories');
?>
<div class="dashboard-welcome">
    <div><span class="eyebrow">PANEL ADMINISTRATORA</span><h1>Kategorie</h1><p>Porządkuj ofertę gabinetu w czytelne grupy.</p></div>
    <span class="role-pill"><?= $totalRows ?> kategorii</span>
</div>
<div class="admin-content-grid">
    <section class="admin-panel">
        <div class="form-heading"><h2><?= $form['id'] === '' ? 'Dodaj kategorię' : 'Edytuj kategorię' ?></h2><p>Nazwa jest wymagana. Opis jest opcjonalny.</p></div>
        <?php if ($errors !== []): ?>
            <div class="notice notice-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form class="form-stack" method="post" action="<?= e(app_url('admin_categories.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e($form['id']) ?>">
            <label class="field"><span>Nazwa kategorii</span><input name="name" maxlength="100" value="<?= e($form['name']) ?>" required></label>
            <label class="field"><span>Opis</span><textarea name="description" rows="4" maxlength="5000"><?= e($form['description']) ?></textarea></label>
            <button class="button button-full" type="submit">Zapisz kategorię</button>
            <?php if ($form['id'] !== ''): ?><a class="quiet-link" href="<?= e(app_url('admin_categories.php')) ?>">Anuluj edycję</a><?php endif; ?>
        </form>
    </section>
    <section class="admin-panel admin-list-panel">
        <div class="admin-list-heading"><div><span class="eyebrow">LISTA KATEGORII</span><h2>Wyniki</h2></div></div>
        <form class="admin-filter-form" method="get" action="<?= e(app_url('admin_categories.php')) ?>">
            <label class="field"><span>Szukaj kategorii</span><input name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Nazwa lub opis"></label>
            <button class="button button-small" type="submit">Szukaj</button>
        </form>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Nazwa</th><th>Opis</th><th>Operacje</th></tr></thead>
                <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td data-label="Nazwa"><strong><?= e($category['name']) ?></strong></td>
                        <td data-label="Opis"><?= e((string) ($category['description'] ?? '')) ?></td>
                        <td data-label="Operacje" class="admin-actions">
                            <a class="admin-link" href="<?= e(app_url('admin_categories.php?edit=' . (int) $category['id'])) ?>">Edytuj</a>
                            <form method="post" action="<?= e(app_url('admin_categories.php')) ?>" data-confirm="Usunąć tę kategorię?">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                <button class="admin-link admin-link-danger" type="submit">Usuń</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($categories === []): ?><tr><td colspan="3" class="table-empty">Nie znaleziono kategorii.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php admin_page_links($currentPage, $pageCount, 'admin_categories.php', $search === '' ? [] : ['q' => $search]); ?>
    </section>
</div>
<?php admin_page_end(); ?>
