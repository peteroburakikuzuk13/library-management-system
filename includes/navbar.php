<?php
/**
 * includes/navbar.php
 * Top navigation. Shows the menu only when a librarian is logged in.
 */
$active_nav = $active_nav ?? '';
$nav_links = [
    'dashboard'  => ['dashboard',  'dashboard/index.php'],
    'books'      => ['books',      'books/index.php'],
    'members'    => ['members',    'members/index.php'],
    'loans'      => ['loans',      'loans/index.php'],
    'authors'    => ['Authors',    'authors/index.php'],
    'categories' => ['Categories', 'categories/index.php'],
];
?>
<nav class="navbar navbar-expand-lg navbar-dark app-nav">
    <div class="container">
        <a class="navbar-brand" href="<?= e(base_url(is_logged_in() ? 'dashboard/index.php' : 'auth/login.php')) ?>">Library Desk</a>
<?php if (is_logged_in()): ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Show or hide the menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
<?php foreach ($nav_links as $key => $item): ?>
                <li class="nav-item">
                    <a class="nav-link<?= $active_nav === $key ? ' active' : '' ?>"
                       <?= $active_nav === $key ? 'aria-current="page"' : '' ?>
                       href="<?= e(base_url($item[1])) ?>"><?= e($item[0]) ?></a>
                </li>
<?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="navbar-text small text-white-50">
                    <?= e($_SESSION['full_name'] ?? '') ?> (<?= e($_SESSION['role'] ?? '') ?>)
                </span>
                <a class="btn btn-outline-light btn-sm" href="<?= e(base_url('auth/logout.php')) ?>">Log out</a>
            </div>
        </div>
<?php endif; ?>
    </div>
</nav>
