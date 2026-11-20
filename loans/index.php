<?php
/**
 * loans/index.php
 * READ: list of loans with search and status filter (on loan, overdue, returned),
 * automatic fine calculation, pagination and CSV export.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$q      = get_str('q');
$status = get_str('status');
if (!in_array($status, ['', 'active', 'overdue', 'returned'], true)) {
    $status = '';
}
$today = today();

$where  = [];
$params = [];
if ($q !== '') {
    $where[] = '(m.full_name LIKE :q1 OR m.member_number LIKE :q2 OR b.title LIKE :q3)';
    foreach (['q1', 'q2', 'q3'] as $key) {
        $params[':' . $key] = '%' . like_escape($q) . '%';
    }
}
if ($status === 'active') {
    $where[] = 'l.date_returned IS NULL';
} elseif ($status === 'overdue') {
    $where[] = 'l.date_returned IS NULL AND l.due_date < :today';
    $params[':today'] = $today;
} elseif ($status === 'returned') {
    $where[] = 'l.date_returned IS NOT NULL';
}

$from = 'FROM loans l
         JOIN members m ON m.member_id = l.member_id
         JOIN books   b ON b.book_id   = l.book_id ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '');

// Books still out first (soonest due date first), then returned loans (latest return first).
$order = 'ORDER BY (l.date_returned IS NULL) DESC,
                   IF(l.date_returned IS NULL, l.due_date, NULL) ASC,
                   l.date_returned DESC, l.loan_id DESC';

$select = 'SELECT l.loan_id, l.date_issued, l.due_date, l.date_returned,
                  m.full_name, m.member_number, b.title, b.isbn';

try {
    if (isset($_GET['export'])) {
        $stmt = $pdo->prepare($select . ' ' . $from . ' ' . $order);
        bind_all($stmt, $params);
        $stmt->execute();
        $rows = [];
        foreach ($stmt as $r) {
            $rows[] = [$r['member_number'], $r['full_name'], $r['isbn'], $r['title'], $r['date_issued'], $r['due_date'],
                       $r['date_returned'] ?? '', loan_state($r), loan_fine($r['due_date'], $r['date_returned'])];
        }
        send_csv('loans_' . date('Y-m-d') . '.csv',
            ['Member number', 'Member', 'ISBN', 'Book', 'Date issued', 'Due date', 'Date returned', 'Status', 'Fine'], $rows);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) ' . $from);
    bind_all($stmt, $params);
    $stmt->execute();
    $pager = paginate((int) $stmt->fetchColumn(), get_int('page'));

    $stmt = $pdo->prepare($select . ' ' . $from . ' ' . $order . ' LIMIT :limit OFFSET :offset');
    bind_all($stmt, $params + [':limit' => $pager['per_page'], ':offset' => $pager['offset']]);
    $stmt->execute();
    $loans = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Loans list error: ' . $e->getMessage());
    $loans = [];
    $pager = paginate(0, 1);
    set_flash('danger', 'The loan list could not be loaded. Please try again.');
}

$query = array_filter(['q' => $q, 'status' => $status], 'strlen');

$page_title = 'Loans';
$active_nav = 'loans';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Loans</h1>
        <p>Track every book out, spot late returns and record when books come back.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(base_url('loans/issue.php')) ?>">Issue a book</a>
</div>

<section class="panel">
    <form method="get" class="filter-bar row g-2 align-items-end" role="search">
        <div class="col-12 col-md-5">
            <label for="q" class="form-label">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Member name, member number or book title">
        </div>
        <div class="col-12 col-md-3">
            <label for="status" class="form-label">Loan status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All loans</option>
                <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Still on loan</option>
                <option value="overdue"<?= $status === 'overdue' ? ' selected' : '' ?>>Overdue</option>
                <option value="returned"<?= $status === 'returned' ? ' selected' : '' ?>>Returned</option>
            </select>
        </div>
        <div class="col-12 col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">Search</button>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('loans/index.php')) ?>">Reset</a>
        </div>
    </form>

<?php if (!$loans): ?>
    <div class="empty-state">
        <?php if ($query): ?>
            <strong>No loans match your search.</strong>Try another name or title, or clear the filters.
        <?php else: ?>
            <strong>No loans yet.</strong>Issue a book to a member and it will appear here.
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table data-table table-stack">
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Member</th>
                    <th class="d-none d-md-table-cell">Issued</th>
                    <th>Due</th>
                    <th class="d-none d-lg-table-cell">Returned</th>
                    <th>Status</th>
                    <th class="num d-none d-md-table-cell">Fine</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($loans as $l):
                $state = loan_state($l);
                $spine = $state === 'overdue' ? 'var(--overdue)' : ($state === 'active' ? 'var(--catalogue)' : '#b8c0cc');
                $fine  = loan_fine($l['due_date'], $l['date_returned']);
            ?>
                <tr class="spine" style="--spine: <?= $spine ?>">
                    <td class="title-cell"><?= e($l['title']) ?><span class="sub"><?= e($l['isbn']) ?></span></td>
                    <td data-label="Member"><?= e($l['full_name']) ?><span class="sub"><?= e($l['member_number']) ?></span></td>
                    <td class="d-none d-md-table-cell"><?= e(format_date($l['date_issued'])) ?></td>
                    <td data-label="Due"><?= e(format_date($l['due_date'])) ?></td>
                    <td class="d-none d-lg-table-cell"><?= e(format_date($l['date_returned'])) ?></td>
                    <td data-label="Status"><?= loan_badge($l) ?></td>
                    <td class="num d-none d-md-table-cell show-stacked" data-label="Fine"><?= $fine > 0 ? e(money($fine)) : '-' ?></td>
                    <td class="text-end text-nowrap actions">
                        <?php if ($state !== 'returned'): ?>
                            <form method="post" action="<?= e(base_url('loans/return.php')) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="loan_id" value="<?= (int) $l['loan_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Mark returned</button>
                            </form>
                        <?php else: ?>
                            <span class="text-secondary small">Done</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="list-foot">
        <span>Showing <?= $pager['offset'] + 1 ?> to <?= $pager['offset'] + count($loans) ?> of <?= (int) $pager['total'] ?> loans</span>
        <?php render_pagination($pager, $query); ?>
        <a href="?<?= e(http_build_query($query + ['export' => 1])) ?>">Export to CSV</a>
    </div>
<?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

