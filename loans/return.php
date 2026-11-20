<?php
/**
 * loans/return.php
 * UPDATE: mark a loan as returned.
 * The loan record and the copy count change together or not at all (one transaction).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('loans/index.php');
}
require_valid_post('loans/index.php');

$loanId = post_int('loan_id');

try {
    $pdo->beginTransaction();

    // Lock the loan row so two clicks cannot return the same loan twice.
    $stmt = $pdo->prepare('SELECT l.loan_id, l.book_id, l.due_date, l.date_returned, l.date_issued, b.title, m.full_name
                           FROM loans l
                           JOIN books b   ON b.book_id   = l.book_id
                           JOIN members m ON m.member_id = l.member_id
                           WHERE l.loan_id = ? FOR UPDATE');
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();

    if (!$loan) {
        throw new RuleException('That loan could not be found.');
    }
    if ($loan['date_returned'] !== null) {
        throw new RuleException('“' . $loan['title'] . '” was already returned on ' . format_date($loan['date_returned']) . '.');
    }

    $returned = today();
    if ($returned < $loan['date_issued']) {
        $returned = $loan['date_issued'];    // protects the date check if the clock moved
    }

    $stmt = $pdo->prepare('UPDATE loans SET date_returned = ? WHERE loan_id = ? AND date_returned IS NULL');
    $stmt->execute([$returned, $loanId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuleException('The loan could not be updated. Nothing was changed.');
    }

    $stmt = $pdo->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ? AND available_copies < total_copies');
    $stmt->execute([$loan['book_id']]);
    if ($stmt->rowCount() !== 1) {
        throw new RuleException('The copy count could not be updated, so the return was not recorded.');
    }

    $pdo->commit();

    $late = days_late($loan['due_date'], $returned);
    $message = 'Book returned successfully. “' . $loan['title'] . '” is back on the shelf.';
    if ($late > 0) {
        $message .= ' It was ' . $late . ($late === 1 ? ' day' : ' days') . ' late. Fine: ' . money($late * FINE_PER_DAY) . '.';
    }
    set_flash($late > 0 ? 'warning' : 'success', $message);
} catch (RuleException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    set_flash('danger', $e->getMessage());
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    set_flash('danger', db_error_message($e));
}
redirect('loans/index.php');
