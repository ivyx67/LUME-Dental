<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (current_user() !== null) {
    redirect_to_dashboard();
}

$errors = [];
$email = '';

if (is_post_request()) {
    $email = strtolower(post_string('email'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!csrf_is_valid()) {
        http_response_code(403);
        $errors[] = 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.';
    } elseif (strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Wpisz poprawny adres e-mail i hasło.';
    } else {
        $statement = db()->prepare(
            'SELECT id, password, active
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $account = $statement->fetch();

        if (
            $account === false
            || (int) $account['active'] !== 1
            || !password_verify($password, $account['password'])
        ) {
            $errors[] = 'Nieprawidłowy adres e-mail lub hasło.';
        } else {
            if (password_needs_rehash($account['password'], PASSWORD_DEFAULT)) {
                $rehash = db()->prepare('UPDATE users SET password = :password WHERE id = :id');
                $rehash->execute([
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => $account['id'],
                ]);
            }

            session_regenerate_id(true);
            $_SESSION = ['user_id' => (int) $account['id']];
            set_flash('success', 'Miło Cię widzieć. Zalogowano pomyślnie.');
            redirect_to_dashboard();
        }
    }
}

$pageTitle = 'Zaloguj się';
require dirname(__DIR__) . '/app/views/header.php';
?>
<section class="form-page">
    <div class="container form-layout">
        <aside class="form-aside login-aside">
            <span class="eyebrow">DOBRZE, ŻE WRACASZ</span>
            <h1>Zadbaj o siebie. <em>Po swojemu.</em></h1>
            <p>Zaloguj się, aby przejść do swojego bezpiecznego panelu Lume Dental.</p>
            <div class="aside-quote"><span>“</span><p>Każda dobra zmiana zaczyna się od troski o siebie.</p></div>
        </aside>
        <div class="form-card">
            <div class="form-heading"><span class="eyebrow">STREFA PACJENTA</span><h2>Witaj ponownie</h2><p>Podaj dane logowania do swojego konta.</p></div>
            <?php if ($errors !== []): ?>
                <div class="notice notice-error" role="alert">
                    <strong>Nie udało się zalogować.</strong>
                    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <form class="form-stack" method="post" action="<?= e(app_url('login.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label class="field"><span>Adres e-mail</span><input name="email" type="email" autocomplete="email" maxlength="100" value="<?= e($email) ?>" required></label>
                <label class="field"><span>Hasło</span><span class="password-input"><input name="password" type="password" autocomplete="current-password" maxlength="72" required><button class="password-toggle" type="button" aria-label="Pokaż hasło">Pokaż</button></span></label>
                <button class="button button-full" type="submit">Zaloguj się <span aria-hidden="true">↗</span></button>
                <p class="form-footnote">Nie masz jeszcze konta? <a href="<?= e(app_url('register.php')) ?>">Utwórz je tutaj</a></p>
            </form>
        </div>
    </div>
</section>
<?php require dirname(__DIR__) . '/app/views/footer.php'; ?>
