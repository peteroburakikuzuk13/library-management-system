<?php
/**
 * categories/index.php
 * READ: list of categories with how many books each holds.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

try {
    $categories = $pdo->query('SELECT c.category_id, c.name, c.description, COUNT(b.book_id) AS books
                               FROM categories c LEFT JOIN books b ON b.category_id = c.category_id
                               GROUP BY c.category_id, c.name, c.description ORDER BY c.name')->fetchAll();
} catch (PDOException $e) {
    error_log('Categories list error: ' . $e->getMessage());
    $categories = [];
    set_flash('danger', 'The category list could not be loaded. Please try again.');
}

$page_title = 'Categories';
$active_nav = 'categories';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Categories</h1><p>The shelves the catalogue is sorted into.</p></div>
    <a class="btn btn-primary" href="<?= e(base_url('categories/create.php')) ?>">Add a category</a>
</div>

<section class="panel">
<?php if (!$categories): ?>
    <div class="empty-state"><strong>No categories yet.</strong>Add a category, then file books under it.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table data-table table-stack">
            <thead><tr><th>Category</th><th class="d-none d-md-table-cell">Description</th><th class="num">Books</th><th class="text-end">View</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr class="spine" style="--spine: <?= e(spine_color((int) $c['category_id'])) ?>">
                    <td class="title-cell"><?= e($c['name']) ?></td>
                    <td class="d-none d-md-table-cell show-stacked" data-label="Description"><?= e($c['description'] ?? '') ?></td>
                    <td class="num" data-label="Books"><?= (int) $c['books'] ?></td>
                    <td class="text-end actions"><a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('books/index.php?category=' . (int) $c['category_id'])) ?>">See books</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="list-foot"><span><?= count($categories) ?> categor<?= count($categories) === 1 ? 'y' : 'ies' ?></span></div>
<?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
