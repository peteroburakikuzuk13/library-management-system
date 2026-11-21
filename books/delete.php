<?php
/**
 * books/delete.php
 * DELETE: remove a book after the user confirms.
 * Rule: a book with unreturned loans can never be deleted.
 * The confirmation dialog normally comes from the browser. If JavaScript is off,
 * this page shows its own confirmation screen instead, so deleting always asks first.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('books/index.php');
}
require_valid_post('books/index.php');

try {
    $stmt = $pdo->prepare('SELECT book_id, title FROM books WHERE book_id = ?');
    $stmt->execute([$id]);
    $book = $stmt->fetch();
    if (!$book) {
        set_flash('warning', 'That book was already deleted or does not exist.');
        redirect('books/index.php');
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE book_id = ? AND date_returned IS NULL');
    $stmt->execute([$id]);
    $unreturned = (int) $stmt->fetchColumn();
    if ($unreturned > 0) {
        set_flash('danger', '“' . $book['title'] . '” cannot be deleted. ' . $unreturned
            . ($unreturned === 1 ? ' copy is' : ' copies are') . ' still out on loan. Delete it after they are returned.');
        redirect('books/index.php');
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE book_id = ?');
    $stmt->execute([$id]);
    $history = (int) $stmt->fetchColumn();

    if (!$confirmed) {
        $page_title = 'Confirm delete';
        $active_nav = 'books';
        require_once __DIR__ . '/../includes/header.php';
        ?>
        <div class="panel panel-pad" style="max-width: 36rem">
            <h1 class="panel-title mb-2">Delete this book?</h1>
            <p>“<?= e($book['title']) ?>” will be removed from the catalogue<?= $history ? ', together with its ' . $history . ' past loan record(s)' : '' ?>. This cannot be undone.</p>
            <form method="post" action="" class="d-flex gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="book_id" value="<?= (int) $id ?>">
                <input type="hidden" name="confirmed" value="1">
                <button type="submit" class="btn btn-danger">Yes, delete</button>
                <a class="btn btn-outline-secondary" href="<?= e(base_url('books/index.php')) ?>">Cancel</a>
            </form>
        </div>
        <?php
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }

    // Re-check inside a transaction with the book row locked, so a loan issued a moment ago is not missed.
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT book_id FROM books WHERE book_id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE book_id = ? AND date_returned IS NULL');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuleException('“' . $book['title'] . '” was just issued to a member, so it cannot be deleted.');
    }
    $stmt = $pdo->prepare('DELETE FROM books WHERE book_id = ?');
    $stmt->execute([$id]);
    $pdo->commit();

    set_flash('success', 'Book deleted successfully: “' . $book['title'] . '”.');
} catch (RuleException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    set_flash('danger', $e->getMessage());
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('Book delete error: ' . $e->getMessage());
    set_flash('danger', 'This book could not be deleted because other records still depend on it. Nothing was changed.');
}
redirect('books/index.php');
