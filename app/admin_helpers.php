<?php
declare(strict_types=1);

function admin_page_start(string $title, string $activePage): void
{
    $pageTitle = $title;
    require __DIR__ . '/views/header.php';
    require __DIR__ . '/views/admin_navigation.php';
    echo '<section class="dashboard-page admin-page"><div class="container">';
}

function admin_page_end(): void
{
    echo '</div></section>';
    require __DIR__ . '/views/footer.php';
}

function admin_post_is_valid(): bool
{
    if (!is_post_request()) {
        http_response_code(405);
        header('Allow: POST');
        return false;
    }

    if (!csrf_is_valid()) {
        http_response_code(403);
        set_flash('error', 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.');
        return false;
    }

    return true;
}

function admin_redirect(string $page): never
{
    header('Location: ' . app_url($page));
    exit;
}

function admin_is_valid_date(string $date): bool
{
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsedDate !== false && $parsedDate->format('Y-m-d') === $date;
}

function admin_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Oczekująca',
        'confirmed' => 'Potwierdzona',
        'completed' => 'Zrealizowana',
        'cancelled' => 'Anulowana',
        default => 'Nieznany status',
    };
}

function admin_page_links(int $currentPage, int $pageCount, string $path, array $filters = []): void
{
    if ($pageCount < 2) {
        return;
    }

    echo '<nav class="pagination" aria-label="Strony wyników">';
    $startPage = max(1, $currentPage - 2);
    $endPage = min($pageCount, $currentPage + 2);
    if ($startPage > 1) {
        $query = http_build_query(array_merge($filters, ['page' => 1]));
        echo '<a href="' . e(app_url($path . '?' . $query)) . '">1</a>';
        if ($startPage > 2) {
            echo '<span aria-hidden="true">…</span>';
        }
    }
    for ($page = $startPage; $page <= $endPage; $page++) {
        $query = http_build_query(array_merge($filters, ['page' => $page]));
        $class = $page === $currentPage ? ' class="is-current" aria-current="page"' : '';
        echo '<a' . $class . ' href="' . e(app_url($path . '?' . $query)) . '">' . $page . '</a>';
    }
    if ($endPage < $pageCount) {
        if ($endPage < $pageCount - 1) {
            echo '<span aria-hidden="true">…</span>';
        }
        $query = http_build_query(array_merge($filters, ['page' => $pageCount]));
        echo '<a href="' . e(app_url($path . '?' . $query)) . '">' . $pageCount . '</a>';
    }
    echo '</nav>';
}
