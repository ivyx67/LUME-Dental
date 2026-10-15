<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
$user = require_login();
$errors = [];
$name = (string) $user['name'];
$surname = (string) $user['surname'];
$email = (string) $user['email'];
$phone = (string) ($user['phone'] ?? '');

if (is_post_request()) {
    $name = post_string('name');
    $surname = post_string('surname');
    $email = strtolower(post_string('email'));
    $phone = post_string('phone');

    if (!csrf_is_valid()) {
        http_response_code(403);
        $errors[] = 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.';
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

    if ($errors === []) {
        $duplicate = db()->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
        $duplicate->execute(['email' => $email, 'id' => $user['id']]);
        if ($duplicate->fetch() !== false) {
            $errors[] = 'Konto z tym adresem e-mail już istnieje.';
        } else {
            $update = db()->prepare(
                'UPDATE users
                 SET name = :name, surname = :surname, email = :email, phone = :phone
                 WHERE id = :id'
            );
            try {
                $update->execute([
                    'name' => $name,
                    'surname' => $surname,
                    'email' => $email,
                    'phone' => $phone === '' ? null : $phone,
                    'id' => $user['id'],
                ]);
                set_flash('success', 'Dane profilu zostały zapisane.');
                header('Location: ' . app_url('profile.php'));
                exit;
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000' && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                    $errors[] = 'Konto z tym adresem e-mail już istnieje.';
                } else {
                    throw $exception;
                }
            }
        }
    }
}

$pageTitle = 'Moje konto';
require dirname(__DIR__) . '/app/views/header.php';
?>
<section class="form-page profile-page">
    <div class="container profile-layout">
        <div class="profile-intro">
            <span class="eyebrow">MOJE KONTO</span><h1>Twoje dane, <em>Twoja kontrola.</em></h1>
            <p>Aktualizuj swoje dane kontaktowe, abyśmy mogli zapewnić Ci najlepszą opiekę.</p>
            <div class="profile-role"><span class="role-pill"><?= e(role_label((string) $user['role'])) ?></span><span>Rola przypisana do konta</span></div>
        </div>
        <div class="form-card profile-card">
            <div class="form-heading"><h2>Dane profilu</h2><p>Wszystkie pola poza telefonem są wymagane.</p></div>
            <?php if ($errors !== []): ?>
                <div class="notice notice-error" role="alert">
                    <strong>Sprawdź formularz:</strong>
                    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <form class="form-stack" method="post" action="<?= e(app_url('profile.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="field-row">
                    <label class="field"><span>Imię</span><input name="name" type="text" autocomplete="given-name" maxlength="50" value="<?= e($name) ?>" required></label>
                    <label class="field"><span>Nazwisko</span><input name="surname" type="text" autocomplete="family-name" maxlength="80" value="<?= e($surname) ?>" required></label>
                </div>
                <label class="field"><span>Adres e-mail</span><input name="email" type="email" autocomplete="email" maxlength="100" value="<?= e($email) ?>" required></label>
                <label class="field"><span>Telefon <small>(opcjonalnie)</small></span><input name="phone" type="tel" autocomplete="tel" maxlength="20" value="<?= e($phone) ?>" placeholder="+48 500 000 000"></label>
                <button class="button button-full" type="submit">Zapisz zmiany <span aria-hidden="true">↗</span></button>
            </form>
        </div>
    </div>
</section>
<?php require dirname(__DIR__) . '/app/views/footer.php'; ?>
