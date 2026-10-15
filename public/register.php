<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (current_user() !== null) {
    redirect_to_dashboard();
}

$errors = [];
$name = '';
$surname = '';
$email = '';
$phone = '';

if (is_post_request()) {
    $name = post_string('name');
    $surname = post_string('surname');
    $email = strtolower(post_string('email'));
    $phone = post_string('phone');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $passwordConfirmation = is_string($_POST['password_confirmation'] ?? null)
        ? $_POST['password_confirmation']
        : '';

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
    if (
        preg_match('//u', $password) !== 1
        || strlen($password) < 10
        || strlen($password) > 72
    ) {
        $errors[] = 'Hasło musi mieć co najmniej 10 znaków i nie może przekraczać 72 bajtów.';
    }
    if (!hash_equals($password, $passwordConfirmation)) {
        $errors[] = 'Podane hasła nie są zgodne.';
    }

    if ($errors === []) {
        $statement = db()->prepare(
            'INSERT INTO users (name, surname, email, password, phone, role)
             VALUES (:name, :surname, :email, :password, :phone, \'client\')'
        );

        try {
            $statement->execute([
                'name' => $name,
                'surname' => $surname,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => $phone === '' ? null : $phone,
            ]);
            set_flash('success', 'Konto zostało utworzone. Możesz się teraz zalogować.');
            header('Location: ' . app_url('login.php'));
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

$pageTitle = 'Załóż konto';
require dirname(__DIR__) . '/app/views/header.php';
?>
<section class="form-page">
    <div class="container form-layout">
        <aside class="form-aside">
            <span class="eyebrow">WITAMY W LUME</span>
            <h1>Twój uśmiech. <em>Twoje konto.</em></h1>
            <p>Załóż konto, aby wygodnie zarządzać swoim profilem i przygotować się na rezerwacje online.</p>
            <div class="aside-promise"><span>✳</span><p><strong>Bez pośpiechu, bez stresu.</strong><br>Jesteśmy tu, żeby dobrze o Ciebie zadbać.</p></div>
        </aside>
        <div class="form-card">
            <div class="form-heading"><span class="eyebrow">NOWE KONTO</span><h2>Dołącz do Lume</h2><p>Wypełnij kilka krótkich pól.</p></div>
            <?php if ($errors !== []): ?>
                <div class="notice notice-error" role="alert">
                    <strong>Sprawdź formularz:</strong>
                    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <form class="form-stack" method="post" action="<?= e(app_url('register.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="field-row">
                    <label class="field"><span>Imię</span><input name="name" type="text" autocomplete="given-name" maxlength="50" value="<?= e($name) ?>" required></label>
                    <label class="field"><span>Nazwisko</span><input name="surname" type="text" autocomplete="family-name" maxlength="80" value="<?= e($surname) ?>" required></label>
                </div>
                <label class="field"><span>Adres e-mail</span><input name="email" type="email" autocomplete="email" maxlength="100" value="<?= e($email) ?>" required></label>
                <label class="field"><span>Telefon <small>(opcjonalnie)</small></span><input name="phone" type="tel" autocomplete="tel" maxlength="20" value="<?= e($phone) ?>" placeholder="+48 500 000 000"></label>
                <label class="field"><span>Hasło <small>(min. 10 znaków)</small></span><span class="password-input"><input name="password" type="password" autocomplete="new-password" minlength="10" maxlength="72" required><button class="password-toggle" type="button" aria-label="Pokaż hasło">Pokaż</button></span></label>
                <label class="field"><span>Powtórz hasło</span><input name="password_confirmation" type="password" autocomplete="new-password" minlength="10" maxlength="72" required></label>
                <button class="button button-full" type="submit">Utwórz konto <span aria-hidden="true">↗</span></button>
                <p class="form-footnote">Masz już konto? <a href="<?= e(app_url('login.php')) ?>">Zaloguj się</a></p>
            </form>
        </div>
    </div>
</section>
<?php require dirname(__DIR__) . '/app/views/footer.php'; ?>
