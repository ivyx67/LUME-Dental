<div class="container admin-navigation-wrap">
    <nav class="admin-navigation" aria-label="Nawigacja administratora">
        <a class="<?= $activePage === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(app_url('admin.php')) ?>" <?= $activePage === 'dashboard' ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-label">Pulpit</span><span class="admin-nav-description">Podsumowanie gabinetu</span>
        </a>
        <a class="<?= $activePage === 'categories' ? 'is-active' : '' ?>" href="<?= e(app_url('admin_categories.php')) ?>" <?= $activePage === 'categories' ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-label">Kategorie</span><span class="admin-nav-description">Grupy usług</span>
        </a>
        <a class="<?= $activePage === 'services' ? 'is-active' : '' ?>" href="<?= e(app_url('admin_services.php')) ?>" <?= $activePage === 'services' ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-label">Usługi</span><span class="admin-nav-description">Cennik i czas wizyt</span>
        </a>
        <a class="<?= $activePage === 'employees' ? 'is-active' : '' ?>" href="<?= e(app_url('admin_employees.php')) ?>" <?= $activePage === 'employees' ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-label">Pracownicy</span><span class="admin-nav-description">Konta, zakres i grafik</span>
        </a>
        <a class="<?= $activePage === 'reservations' ? 'is-active' : '' ?>" href="<?= e(app_url('admin_reservations.php')) ?>" <?= $activePage === 'reservations' ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-label">Rezerwacje</span><span class="admin-nav-description">Terminy i status wizyt</span>
        </a>
        <a class="<?= $activePage === 'users' ? 'is-active' : '' ?>" href="<?= e(app_url('admin_users.php')) ?>" <?= $activePage === 'users' ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-label">Użytkownicy</span><span class="admin-nav-description">Konta i dostęp</span>
        </a>
    </nav>
</div>
