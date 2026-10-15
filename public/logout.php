<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!is_post_request()) {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
if (!csrf_is_valid()) {
    http_response_code(403);
    exit('Sesja formularza wygasła. Wróć do strony i spróbuj ponownie.');
}

$_SESSION = [];
session_regenerate_id(true);
session_destroy();
header('Location: ' . app_url('login.php'));
exit;
