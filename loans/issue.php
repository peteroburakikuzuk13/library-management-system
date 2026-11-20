
<?php
/**
 * loans/issue.php
 * CREATE: issue a book to a member.
 *
 * Business rules enforced here, in code (not only in the browser):
 *   1. A suspended member cannot borrow.
 *   2. A book with zero available copies cannot be issued.
 *   3. A member cannot hold the same book twice at the same time.
 *   4. The due date is set automatically: issue date + LOAN_DAYS.
 *   5. The loan record and the copy count change together or not at all (one transaction).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$ruleFailed = false;
$memberId = get_int('member_id');
$bookId   = get_int('book_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_post('loans/issue.php');
    $memberId = post_int('member_id');
    $bookId   = post_int('book_id');

    if ($memberId < 1) { $errors['member_id'] = 'Choose a member.'; }
    if ($bookId < 1)   { $errors['book_id']   = 'Choose a book.'; }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock the member and the book rows. Anyone else issuing the same book waits here,
            // so two librarians can never hand out the last copy at the same moment.
            $stmt = $pdo->prepare('SELECT member_id, full_name, status FROM members WHERE member_id = ? FOR UPDATE');
            $stmt->execute([$memberId]);
            $member = $stmt->fetch();

            $stmt = $pdo->prepare('SELECT book_id, title, available_copies FROM books WHERE book_id = ? FOR UPDATE');
            $stmt->execute([$bookId]);
            $book = $stmt->fetch();

            if (!$member) {
                $errors['member_id'] = 'That member does not exist.';
                throw new RuleException('The member could not be found.');
            }
            if (!$book) {
                $errors['book_id'] = 'That book does not exist.';
                throw new RuleException('The book could not be found.');
            }

            // Rule 1
            if ($member['status'] !== 'active') {
                $errors['member_id'] = 'This member is suspended and cannot borrow books.';
                throw new RuleException($member['full_name'] . ' is suspended, so no book was issued.');
            }
            // Rule 2
            if ((int) $book['available_copies'] < 1) {
                $errors['book_id'] = 'No copies of this book are available right now.';
                throw new RuleException('No copies of “' . $book['title'] . '” are available, so no book was issued.');
            }
            // Rule 3
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE member_id = ? AND book_id = ? AND date_returned IS NULL');
            $stmt->execute([$memberId, $bookId]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errors['book_id'] = 'This member already has this book. It must be returned first.';
                throw new RuleException($member['full_name'] . ' already has “' . $book['title'] . '” on loan, so no book was issued.');
            }

            // Rule 4
            $issued = today();
            $due    = add_days($issued, LOAN_DAYS);

            // Rule 5: both statements succeed together, or the rollback undoes them.
            $stmt = $pdo->prepare('INSERT INTO loans (member_id, book_id, issued_by, date_issued, due_date) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$memberId, $bookId, (int) $_SESSION['user_id'], $issued, $due]);

            $stmt = $pdo->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ? AND available_copies > 0');
            $stmt->execute([$bookId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuleException('The copy count could not be updated, so no book was issued.');
            }

            $pdo->commit();
            set_flash('success', 'Book issued successfully. ' . $member['full_name'] . ' has “' . $book['title']
                . '” until ' . format_date($due) . '.');
            redirect('loans/index.php');
        } catch (RuleException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $ruleFailed = true;   // the flash message already explains it
            set_flash('danger', $e->getMessage());
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            set_flash('danger', db_error_message($e));
        }
    }
}

try {
    $members = $pdo->query('SELECT member_id, member_number, full_name, status FROM members ORDER BY full_name')->fetchAll();
    $books   = $pdo->query('SELECT b.book_id, b.title, b.available_copies, a.name AS author
                            FROM books b JOIN authors a ON a.author_id = b.author_id ORDER BY b.title')->fetchAll();
} catch (PDOException $e) {
    error_log('Issue form lists error: ' . $e->getMessage());
    $members = $books = [];
    set_flash('danger', 'The member and book lists could not be loaded.');
}

$page_title = 'Issue a book';
$active_nav = 'loans';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Issue a book</h1><p>Lend a copy to a member. The due date is set for you.</p></div>
    <a class="btn btn-outline-secondary" href="<?= e(base_url('loans/index.php')) ?>">Back to loans</a>
</div>

<div class="panel panel-pad">
    <?= $ruleFailed ? '' : error_summary($errors) ?>
    <form method="post" action="">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="member_id" class="form-label">Member</label>
                <select class="form-select<?= invalid_class($errors, 'member_id') ?>" id="member_id" name="member_id" required>
                    <option value="">Choose a member</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= (int) $m['member_id'] ?>"<?= $memberId === (int) $m['member_id'] ? ' selected' : '' ?>>
                            <?= e($m['full_name'] . ' (' . $m['member_number'] . ')' . ($m['status'] === 'suspended' ? ' - suspended' : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'member_id') ?>
            </div>
            <div class="col-md-6">
                <label for="book_id" class="form-label">Book</label>
                <select class="form-select<?= invalid_class($errors, 'book_id') ?>" id="book_id" name="book_id" required>
                    <option value="">Choose a book</option>
                    <?php foreach ($books as $b): ?>
                        <option value="<?= (int) $b['book_id'] ?>"<?= $bookId === (int) $b['book_id'] ? ' selected' : '' ?>>
                            <?= e($b['title'] . ' by ' . $b['author'] . ' (' . (int) $b['available_copies'] . ' available)') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'book_id') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="issue_date_view">Issue date</label>
                <input type="text" class="form-control" id="issue_date_view" value="<?= e(format_date(today())) ?>" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="due_date_view">Due date</label>
                <input type="text" class="form-control" id="due_date_view" value="<?= e(format_date(add_days(today(), LOAN_DAYS))) ?>" disabled>
                <div class="form-text">Set automatically, <?= (int) LOAN_DAYS ?> days after the issue date.</div>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Issue book</button>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('loans/index.php')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
