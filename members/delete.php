<?php
/**
 * members/delete.php
 * DELETE: remove a member after the user confirms.
 * Rule: a member who still has unreturned books can never be deleted.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('members/index.php');
}
require_valid_post('members/index.php');

$id        = post_int('member_id');
$confirmed = post_str('confirmed') === '1';

try {
    $stmt = $pdo->prepare('SELECT member_id, full_name, member_number FROM members WHERE member_id = ?');
    $stmt->execute([$id]);
    $member = $stmt->fetch();
    if (!$member) {
        set_flash('warning', 'That member was already deleted or does not exist.');
        redirect('members/index.php');
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE member_id = ? AND date_returned IS NULL');
    $stmt->execute([$id]);
    $unreturned = (int) $stmt->fetchColumn();
    if ($unreturned > 0) {
        set_flash('danger', $member['full_name'] . ' cannot be deleted. They still have ' . $unreturned
            . ($unreturned === 1 ? ' unreturned book' : ' unreturned books') . '. Delete the member after everything is returned.');
        redirect('members/index.php');
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE member_id = ?');
    $stmt->execute([$id]);
    $history = (int) $stmt->fetchColumn();

    if (!$confirmed) {
        $page_title = 'Confirm delete';
        $active_nav = 'members';
        require_once __DIR__ . '/../includes/header.php';
        ?>
        <div class="panel panel-pad" style="max-width: 36rem">
            <h1 class="panel-title mb-2">Delete this member?</h1>
            <p><?= e($member['full_name']) ?> (<?= e($member['member_number']) ?>) will be removed<?= $history ? ', together with their ' . $history . ' past loan record(s)' : '' ?>. This cannot be undone.</p>
            <form method="post" action="" class="d-flex gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="member_id" value="<?= (int) $id ?>">
                <input type="hidden" name="confirmed" value="1">
                <button type="submit" class="btn btn-danger">Yes, delete</button>
                <a class="btn btn-outline-secondary" href="<?= e(base_url('members/index.php')) ?>">Cancel</a>
            </form>
        </div>
        <?php
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT member_id FROM members WHERE member_id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE member_id = ? AND date_returned IS NULL');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuleException($member['full_name'] . ' just borrowed a book, so they cannot be deleted.');
    }
    $stmt = $pdo->prepare('DELETE FROM members WHERE member_id = ?');
    $stmt->execute([$id]);
    $pdo->commit();

    set_flash('success', 'Member deleted successfully: ' . $member['full_name'] . '.');
} catch (RuleException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    set_flash('danger', $e->getMessage());
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('Member delete error: ' . $e->getMessage());
    set_flash('danger', 'This member could not be deleted because other records still depend on them. Nothing was changed.');
}
redirect('members/index.php');
