<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
$user = require_login();

$destination = match ($user['role']) {
    'admin' => 'admin.php',
    'employee' => 'employee.php',
    default => 'client.php',
};

header('Location: ' . app_url($destination));
exit;
