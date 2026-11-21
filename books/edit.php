
<?php
/**
 * books/edit.php
 * UPDATE: change a book's details. Changing the total number of copies also
 * adjusts the available copies, and never goes below the copies still on loan.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost) {
    require_valid_post('books/index.php');
}
$id = $isPost ? post_int('book_id') : get_int('id');

try {
    $stmt = $pdo->prepare('SELECT * FROM books WHERE book_id = ?');
    $stmt->execute([$id]);
    $book = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE book_id = ? AND date_returned IS NULL');
    $stmt->execute([$id]);
    $onLoan = (int) $stmt->fetchColumn();

    $authors    = $pdo->query('SELECT author_id, name FROM authors ORDER BY name')->fetchAll();
    $categories = $pdo->query('SELECT category_id, name FROM categories ORDER BY name')->fetchAll();
} catch (PDOException $e) {
    error_log('Book edit load error: ' . $e->getMessage());
    set_flash('danger', 'The book could not be loaded. Please try again.');
    redirect('books/index.php');
}

if (!$book) {
    set_flash('danger', 'That book could not be found. It may have been deleted.');
    redirect('books/index.php');
}

$errors = [];
$data = [
    'isbn' => $book['isbn'], 'title' => $book['title'], 'author_id' => (int) $book['author_id'],
    'category_id' => (int) $book['category_id'], 'publisher' => $book['publisher'],
    'year_published' => $book['year_published'], 'total_copies' => $book['total_copies'],
];

if ($isPost) {
    try {
        [$data, $errors] = validate_book($pdo, $_POST, $id);
        if (!$errors) {
            $pdo->beginTransaction();

            // Lock the book row, then count what is out. issue.php locks the same row, so the two cannot clash.
            $stmt = $pdo->prepare('SELECT book_id FROM books WHERE book_id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE book_id = ? AND date_returned IS NULL');
            $stmt->execute([$id]);
            $onLoan = (int) $stmt->fetchColumn();

            $total = (int) $data['total_copies'];
            if ($total < $onLoan) {
                throw new RuleException("{$onLoan} cop" . ($onLoan === 1 ? 'y is' : 'ies are')
                    . " currently on loan, so total copies cannot be lower than {$onLoan}.");
            }

            $stmt = $pdo->prepare('UPDATE books SET isbn = ?, title = ?, author_id = ?, category_id = ?, publisher = ?,
                                          year_published = ?, total_copies = ?, available_copies = ?
                                   WHERE book_id = ?');
            $stmt->execute([$data['isbn'], $data['title'], $data['author_id'], $data['category_id'], $data['publisher'],
                            (int) $data['year_published'], $total, $total - $onLoan, $id]);
            $pdo->commit();

            set_flash('success', 'Book updated successfully. Changes to “' . $data['title'] . '” are saved.');
            redirect('books/index.php');
        }
    } catch (RuleException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $errors['total_copies'] = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if (is_duplicate_error($e)) {
            $errors['isbn'] = 'Another book already uses this ISBN.';
        } else {
            set_flash('danger', db_error_message($e));
        }
    }
}

$page_title = 'Edit book';
$active_nav = 'books';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Edit book</h1><p><?= e($book['title']) ?></p></div>
    <a class="btn btn-outline-secondary" href="<?= e(base_url('books/index.php')) ?>">Back to books</a>
</div>

<div class="panel panel-pad">
    <?= error_summary($errors) ?>

</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
