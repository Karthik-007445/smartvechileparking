<?php
declare(strict_types=1);

function render_flashes(): void
{
    $flashes = get_flashes();
    if (!$flashes) {
        return;
    }
    $icons = [
        'success' => 'bi-check-circle-fill', 'danger' => 'bi-exclamation-octagon-fill',
        'warning' => 'bi-exclamation-triangle-fill', 'info' => 'bi-info-circle-fill',
    ];
    echo '<div class="toast-stack">';
    foreach ($flashes as $f) {
        $icon = $icons[$f['type']] ?? $icons['info'];
        echo '<div class="flash-toast t-' . e($f['type']) . '"><i class="bi ' . $icon . '"></i><span>'
           . e($f['msg']) . '</span><button type="button" class="flash-close" aria-label="Close">&times;</button></div>';
    }
    echo '</div>';
}

/**
 * Prints <head>, and for the "app" layout the sidebar + top bar.
 * $layout: app | auth | public
 */
function render_header(string $title, string $layout = 'app', string $active = ''): void
{
    $user = current_user();
    $app  = config('app_name');
    $fav  = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop offset='0' stop-color='%2322d3ee'/%3E%3Cstop offset='1' stop-color='%238b5cf6'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='64' height='64' rx='16' fill='url(%23g)'/%3E%3Ctext x='32' y='46' font-size='40' font-weight='800' text-anchor='middle' fill='white' font-family='Arial'%3EP%3C/text%3E%3C/svg%3E";
    ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($app) ?></title>
<link rel="icon" href="<?= $fav ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="layout-<?= e($layout) ?>"
      data-base="<?= e(url()) ?>"
      data-now="<?= time() ?>"
      data-currency="<?= e(config('currency')) ?>"
      data-tax="<?= e((string)config('tax_percent')) ?>"
      data-grace="<?= e((string)config('grace_minutes')) ?>">
<div class="bg-orbs" aria-hidden="true"><span></span><span></span><span></span></div>
<?php
    render_flashes();

    if ($layout !== 'app' || !$user) {
        return;
    }

    $nav = [
        ['dashboard', 'dashboard.php', 'bi-grid-1x2-fill', 'Dashboard'],
        ['book',      'book.php',      'bi-p-square-fill', 'Book Parking'],
        ['vehicles',  'vehicles.php',  'bi-car-front-fill', 'My Vehicles'],
        ['bookings',  'bookings.php',  'bi-receipt-cutoff', 'My Bookings'],
        ['profile',   'profile.php',   'bi-person-circle', 'Profile'],
    ];
    $adminNav = [
        ['a_overview', 'admin/index.php',    'bi-speedometer2',         'Overview'],
        ['a_slots',    'admin/slots.php',    'bi-bounding-box-circles', 'Manage Slots'],
        ['a_bookings', 'admin/bookings.php', 'bi-journal-check',        'All Bookings'],
        ['a_users',    'admin/users.php',    'bi-people-fill',          'Users'],
    ];
    $isAdmin = ($user['role'] ?? '') === 'admin';
    $GLOBALS['shell_open'] = true;
    ?>
<div class="sidebar-backdrop" data-toggle-sidebar></div>
<aside class="sidebar">
    <a class="brand" href="<?= e(url($isAdmin ? 'admin/index.php' : 'dashboard.php')) ?>">
        <span class="brand-mark">P</span>
        <span class="brand-text"><?= e($app) ?><small>Smart Parking</small></span>
    </a>

    <?php if ($isAdmin): ?>
    <div class="nav-label">Administration</div>
    <nav class="nav-list">
        <?php foreach ($adminNav as [$key, $href, $icon, $label]): ?>
        <a class="nav-item<?= $active === $key ? ' active' : '' ?>" href="<?= e(url($href)) ?>"><i class="bi <?= $icon ?>"></i><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <div class="nav-label"><?= $isAdmin ? 'My Parking' : 'Menu' ?></div>
    <nav class="nav-list">
        <?php foreach ($nav as [$key, $href, $icon, $label]): ?>
        <a class="nav-item<?= $active === $key ? ' active' : '' ?>" href="<?= e(url($href)) ?>"><i class="bi <?= $icon ?>"></i><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-user">
        <div class="avatar"><?= e(initials($user['name'])) ?></div>
        <div class="su-meta">
            <b><?= e($user['name']) ?></b>
            <small><?= $isAdmin ? 'Administrator' : 'Member' ?></small>
        </div>
        <a class="su-logout" href="<?= e(url('logout.php')) ?>" title="Sign out"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</aside>

<main class="app-main">
    <header class="topbar">
        <button class="icon-btn d-lg-none" type="button" data-toggle-sidebar aria-label="Menu"><i class="bi bi-list"></i></button>
        <div class="topbar-title">
            <h1><?= e($title) ?></h1>
            <span class="topbar-date"><i class="bi bi-calendar3"></i> <?= date('l, d M Y') ?> · <span data-clock></span></span>
        </div>
        <a class="btn btn-neon btn-sm d-none d-sm-inline-flex" href="<?= e(url('book.php')) ?>"><i class="bi bi-plus-lg"></i> New Booking</a>
    </header>
    <div class="app-content">
<?php
}

function render_footer(): void
{
    $scripts = $GLOBALS['page_scripts'] ?? [];
    ?>
<?php if (!empty($GLOBALS['shell_open'])): ?>
    </div><!-- /.app-content -->
</main>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php foreach ($scripts as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?php if (!empty($GLOBALS['inline_js'])): ?>
<script><?= $GLOBALS['inline_js'] ?></script>
<?php endif; ?>
</body>
</html>
<?php
}
