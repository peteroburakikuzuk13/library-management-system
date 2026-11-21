<?php
/**
 * books/create.php
 * CREATE: add a new book. A new book starts with every copy on the shelf.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$data = ['isbn' => '', 'title' => '', 'author_id' => 0, 'category_id' => (int) get_int('category'),
         'publisher' => '', 'year_published' => '', 'total_copies' => '1'];

try {
    $authors    = $pdo->query('SELECT author_id, name FROM authors ORDER BY name')->fetchAll();
    $categories = $pdo->query('SELECT category_id, name FROM categories ORDER BY name')->fetchAll();
} catch (PDOException $e) {
    error_log('Book form lists error: ' . $e->getMessage());
    $authors = $categories = [];
    set_flash('danger', 'The author and category lists could not be loaded.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_post('books/create.php');
    try {
        [$data, $errors] = validate_book($pdo, $_POST);
        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO books (isbn, title, author_id, category_id, publisher, year_published, total_copies, available_copies)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$data['isbn'], $data['title'], $data['author_id'], $data['category_id'], $data['publisher'],
                            (int) $data['year_published'], (int) $data['total_copies'], (int) $data['total_copies']]);
            set_flash('success', 'Book added successfully. “' . $data['title'] . '” is ready to issue.');
            redirect('books/index.php');
        }
    } catch (PDOException $e) {
        if (is_duplicate_error($e)) {
            $errors['isbn'] = 'Another book already uses this ISBN.';
        } else {
            set_flash('danger', db_error_message($e));
        }
    }
}

$page_title = 'Add a book';
$active_nav = 'books';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Add a book</h1><p>Every field is required.</p></div>
    <a class="btn btn-outline-secondary" href="<?= e(base_url('books/index.php')) ?>">Back to books</a>
</div>

<div class="panel panel-pad">
    <?= error_summary($errors) ?>
    <form method="post" action="">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="isbn" class="form-label">ISBN</label>
                <input type="text" class="form-control<?= invalid_class($errors, 'isbn') ?>" id="isbn" name="isbn"
                       value="<?= e($data['isbn']) ?>" maxlength="17" required>
                <?= field_error($errors, 'isbn') ?>
                <div class="form-text">10 or 13 digits. Hyphens are fine.</div>
            </div>
            <div class="col-md-8">
                <label for="title" class="form-label">Title</label>
                <input type="text" class="form-control<?= invalid_class($errors, 'title') ?>" id="title" name="title"
                       value="<?= e($data['title']) ?>" maxlength="200" required>
                <?= field_error($errors, 'Title') ?>
            </div>
            <div class="col-md-6">
                <label for="author_id" class="form-label">Author</label>
                <select class="form-select<?= invalid_class($errors, 'author_id') ?>" id="author_id" name="author_id" required>
                    <option value="">Choose an author</option>
                    <?php foreach ($authors as $a): ?>
                        <option value="<?= (int) $a['author_id'] ?>"<?= (int) $data['author_id'] === (int) $a['author_id'] ? ' selected' : '' ?>><?= e($a['Name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'author_id') ?>
                <div class="form-text">Not listed? <a href="<?= e(base_url('authors/create.php')) ?>">Add the author first</a>.</div>
            </div>
            <div class="col-md-6">
                <label for="category_id" class="form-label">Category</label>
                <select class="form-select<?= invalid_class($errors, 'category_id') ?>" id="category_id" name="category_id" required>
                    <option value="">Choose a category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['category_id'] ?>"<?= (int) $data['category_id'] === (int) $c['category_id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'category_id') ?>
                <div class="form-text">Not listed? <a href="<?= e(base_url('categories/create.php')) ?>">Add the category first</a>.</div>
            </div>
            <div class="col-md-6">
                <label for="publisher" class="form-label">Publisher</label>
                <input type="text" class="form-control<?= invalid_class($errors, 'publisher') ?>" id="publisher" name="publisher"
                       value="<?= e($data['publisher']) ?>" maxlength="120" required>
                <?= field_error($errors, 'publisher') ?>
            </div>
            <div class="col-6 col-md-3">
                <label for="year_published" class="form-label">Year published</label>
                <input type="number" class="form-control<?= invalid_class($errors, 'year_published') ?>" id="year_published" name="year_published"
                       value="<?= e($data['year_published']) ?>" min="1000" max="<?= (int) date('Y') + 1 ?>" required>
                <?= field_error($errors, 'year_published') ?>
            </div>
            <div class="col-6 col-md-3">
                <label for="total_copies" class="form-label">Total copies</label>
                <input type="number" class="form-control<?= invalid_class($errors, 'total_copies') ?>" id="total_copies" name="total_copies"
                       value="<?= e($data['total_copies']) ?>" min="1" max="500" required>
                <?= field_error($errors, 'total_copies') ?>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Add book</button>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('books/index.php')) ?>">cancel</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
