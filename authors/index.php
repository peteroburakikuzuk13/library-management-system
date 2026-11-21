<?php
/**
 * authors/index.php
 * READ: list of authors with how many books each has in the catalogue.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$q = get_str('q');

try {
    $sql = 'SELECT a.author_id, a.name, a.country, COUNT(b.book_id) AS books
            FROM authors a LEFT JOIN books b ON b.author_id = a.author_id ';
    $params = [];
    if ($q !== '') {
        $sql .= 'WHERE a.name LIKE :q1 OR a.country LIKE :q2 ';
        $params = [':q1' => '%' . like_escape($q) . '%', ':q2' => '%' . like_escape($q) . '%'];
    }
    $sql .= 'GROUP BY a.author_id, a.name, a.country ORDER BY a.name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $authors = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Authors list error: ' . $e->getMessage());
    $authors = [];
    set_flash('danger', 'The author list could not be loaded. Please try again.');
}

$page_title = 'Authors';
$active_nav = 'authors';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Authors</h1><p>Everyone who has written a book in the catalogue.</p></div>
    <a class="btn btn-primary" href="<?= e(base_url('authors/create.php')) ?>">Add an author</a>
</div>

<section class="panel">
    <form method="get" class="filter-bar row g-2 align-items-end" role="search">
        <div class="col-12 col-md-8">
            <label for="q" class="form-label">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Author name or country">
        </div>
        <div class="col-12 col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">Search</button>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('authors/index.php')) ?>">Reset</a>
        </div>
    </form>
<?php if (!$authors): ?>
    <div class="empty-state"><strong>No authors found.</strong>Add an author, then add their books.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table data-table table-stack">
            <thead><tr><th>Author</th><th>Country</th><th class="num">Books</th></tr></thead>
            <tbody>
            <?php foreach ($authors as $a): ?>
                <tr>
                    <td class="title-cell"><?= e($a['name']) ?></td>
                    <td data-label="Country"><?= e($a['country']) ?></td>
                    <td class="num" data-label="Books"><?= (int) $a['books'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="list-foot"><span><?= count($authors) ?> author<?= count($authors) === 1 ? '' : 's' ?></span></div>
<?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

