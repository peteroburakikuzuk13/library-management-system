<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$q            = get_str('q');
$category     = get_int('category');
$availability = get_str('availability');
if (!in_array($availability, ['', 'available', 'out'], true)) {
    $availability = '';
}

// Build the WHERE clause from fixed fragments. The user's values only ever travel as parameters.
$where  = [];
$params = [];
if ($q !== '') {
    $where[] = '(b.title LIKE :q1 OR a.name LIKE :q2 OR b.isbn LIKE :q3 OR b.publisher LIKE :q4)';
    foreach (['q1', 'q2', 'q3', 'q4'] as $key) {
        $params[':' . $key] = '%' . like_escape($q) . '%';
    }
}
if ($category > 0) {
    $where[] = 'b.category_id = :category';
    $params[':category'] = $category;
}
if ($availability === 'available') {
    $where[] = 'b.available_copies > 0';
} elseif ($availability === 'out') {
    $where[] = 'b.available_copies = 0';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from = 'FROM books b
         JOIN authors a    ON a.author_id    = b.author_id
         JOIN categories c ON c.category_id  = b.category_id ' . $whereSql;

$select = 'SELECT b.book_id, b.isbn, b.title, b.publisher, b.year_published, b.total_copies, b.available_copies,
                  a.name AS author, c.category_id, c.name AS category,
                  (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.book_id) AS loan_count,
                  (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.book_id AND l.date_returned IS NULL) AS active_count';

try {
    // Export the whole filtered list as CSV (bonus)
    if (isset($_GET['export'])) {
        $stmt = $pdo->prepare($select . ' ' . $from . ' ORDER BY b.title');
        bind_all($stmt, $params);
        $stmt->execute();
        $rows = [];
        foreach ($stmt as $r) {
            $rows[] = [$r['isbn'], $r['title'], $r['author'], $r['category'], $r['publisher'],
                       $r['year_published'], $r['total_copies'], $r['available_copies']];
        }
        send_csv('books_' . date('Y-m-d') . '.csv',
            ['ISBN', 'Title', 'Author', 'Category', 'Publisher', 'Year', 'Total copies', 'Available copies'], $rows);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) ' . $from);
    bind_all($stmt, $params);
    $stmt->execute();
    $pager = paginate((int) $stmt->fetchColumn(), get_int('page'));

    $stmt = $pdo->prepare($select . ' ' . $from . ' ORDER BY b.title LIMIT :limit OFFSET :offset');
    bind_all($stmt, $params + [':limit' => $pager['per_page'], ':offset' => $pager['offset']]);
    $stmt->execute();
    $books = $stmt->fetchAll();

    $categories = $pdo->query('SELECT category_id, name FROM categories ORDER BY name')->fetchAll();
} catch (PDOException $e) {
    error_log('Books list error: ' . $e->getMessage());
    $books = [];
    $categories = [];
    $pager = paginate(0, 1);
    set_flash('danger', 'The book list could not be loaded. Please try again.');
}

$query = array_filter(['q' => $q, 'category' => $category ?: '', 'availability' => $availability], 'strlen');

$page_title = 'Books';
$active_nav = 'books';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Books</h1>
        <p>Search the catalogue, edit details and issue copies.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(base_url('books/create.php')) ?>">Add a book</a>
</div>

<section class="panel">
    <form method="get" class="filter-bar row g-2 align-items-end" role="search">
        <div class="col-12 col-md-4">
            <label for="q" class="form-label">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Title, author, ISBN or publisher">
        </div>
        <div class="col-6 col-md-3">
            <label for="category" class="form-label">Category</label>
            <select class="form-select" id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['category_id'] ?>"<?= $category === (int) $c['category_id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="availability" class="form-label">Availability</label>
            <select class="form-select" id="availability" name="availability">
                <option value="">Any</option>
                <option value="available"<?= $availability === 'available' ? ' selected' : '' ?>>Copies available</option>
                <option value="out"<?= $availability === 'out' ? ' selected' : '' ?>>All copies out</option>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">Search</button>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('books/index.php')) ?>">Reset</a>
        </div>
    </form>

<?php if (!$books): ?>
    <div class="empty-state">
        <?php if ($query): ?>
            <strong>No books match your search.</strong>Try a different word, or clear the filters.
        <?php else: ?>
            <strong>The catalogue is empty.</strong>Add the first book to get started.
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table data-table table-stack">
            <thead>
                <tr>
                    <th>Title</th>
                    <th class="d-none d-md-table-cell">ISBN</th>
                    <th>Category</th>
                    <th class="d-none d-lg-table-cell">Publisher</th>
                    <th class="num">Copies</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($books as $b):
                $avail = (int) $b['available_copies'];
                $stockClass = $avail === 0 ? 'badge-stock-out' : ($avail === 1 ? 'badge-stock-low' : 'badge-stock');
                if ((int) $b['active_count'] > 0) {
                    $confirm = '“' . $b['title'] . '” still has ' . plural((int) $b['active_count'], 'copy', 'copies')
                             . ' out on loan, so it cannot be deleted until they are returned. Try anyway?';
                } else {
                    $confirm = 'Delete “' . $b['title'] . '”? ' . ((int) $b['loan_count'] > 0
                        ? 'Its ' . plural((int) $b['loan_count'], 'past loan record', 'past loan records') . ' will be deleted too. '
                        : '') . 'This cannot be undone.';
                }
            ?>
                <tr class="spine" style="--spine: <?= e(spine_color((int) $b['category_id'])) ?>">
                    <td class="title-cell"><?= e($b['title']) ?><span class="sub"><?= e($b['author']) ?>, <?= (int) $b['year_published'] ?></span></td>
                    <td class="d-none d-md-table-cell" data-label="ISBN"><?= e($b['isbn']) ?></td>
                    <td data-label="Category"><?= e($b['category']) ?></td>
                    <td class="d-none d-lg-table-cell" data-label="Publisher"><?= e($b['publisher']) ?></td>
                    <td class="num" data-label="Copies available"><span class="badge <?= $stockClass ?>"><?= $avail ?> of <?= (int) $b['total_copies'] ?></span></td>
                    <td class="text-end text-nowrap actions">
                        <?php if ($avail > 0): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('loans/issue.php?book_id=' . (int) $b['book_id'])) ?>">Issue</a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('books/edit.php?id=' . (int) $b['book_id'])) ?>">Edit</a>
                        <form method="post" action="<?= e(base_url('books/delete.php')) ?>" class="d-inline" data-confirm="<?= e($confirm) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="book_id" value="<?= (int) $b['book_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="list-foot">
        <span>Showing <?= $pager['offset'] + 1 ?> to <?= $pager['offset'] + count($books) ?> of <?= (int) $pager['total'] ?> books</span>
        <?php render_pagination($pager, $query); ?>
        <a href="?<?= e(http_build_query($query + ['export' => 1])) ?>">Export to CSV</a>
    </div>
<?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
