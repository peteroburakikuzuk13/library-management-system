<?php
/**
 * members/index.php
 * READ: list of members with search, status filter, pagination and CSV export.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$q      = get_str('q');
$status = get_str('status');
if (!in_array($status, ['', 'active', 'suspended'], true)) {
    $status = '';
}

$where  = [];
$params = [];
if ($q !== '') {
    $where[] = '(m.full_name LIKE :q1 OR m.member_number LIKE :q2 OR m.email LIKE :q3 OR m.phone LIKE :q4)';
    foreach (['q1', 'q2', 'q3', 'q4'] as $key) {
        $params[':' . $key] = '%' . like_escape($q) . '%';
    }
}
if ($status !== '') {
    $where[] = 'm.status = :status';
    $params[':status'] = $status;
}
$from = 'FROM members m ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '');

$select = 'SELECT m.member_id, m.member_number, m.full_name, m.email, m.phone, m.status, m.date_joined,
                  (SELECT COUNT(*) FROM loans l WHERE l.member_id = m.member_id AND l.date_returned IS NULL) AS books_out,
                  (SELECT COUNT(*) FROM loans l WHERE l.member_id = m.member_id) AS loan_count';

try {
    if (isset($_GET['export'])) {
        $stmt = $pdo->prepare($select . ' ' . $from . ' ORDER BY m.full_name');
        bind_all($stmt, $params);
        $stmt->execute();
        $rows = [];
        foreach ($stmt as $r) {
            $rows[] = [$r['member_number'], $r['full_name'], $r['email'], $r['phone'], $r['status'], $r['date_joined'], $r['books_out']];
        }
        send_csv('members_' . date('Y-m-d') . '.csv',
            ['Member number', 'Full name', 'Email', 'Phone', 'Status', 'Date joined', 'Books out'], $rows);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) ' . $from);
    bind_all($stmt, $params);
    $stmt->execute();
    $pager = paginate((int) $stmt->fetchColumn(), get_int('page'));

    $stmt = $pdo->prepare($select . ' ' . $from . ' ORDER BY m.full_name LIMIT :limit OFFSET :offset');
    bind_all($stmt, $params + [':limit' => $pager['per_page'], ':offset' => $pager['offset']]);
    $stmt->execute();
    $members = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Members list error: ' . $e->getMessage());
    $members = [];
    $pager = paginate(0, 1);
    set_flash('danger', 'The member list could not be loaded. Please try again.');
}

$query = array_filter(['q' => $q, 'status' => $status], 'strlen');

$page_title = 'Members';
$active_nav = 'members';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Members</h1>
        <p>Register readers, update their details and suspend accounts.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(base_url('members/create.php')) ?>">Add a member</a>
</div>

<section class="panel">
    <form method="get" class="filter-bar row g-2 align-items-end" role="search">
        <div class="col-12 col-md-5">
            <label for="q" class="form-label">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, member number, email or phone">
        </div>
        <div class="col-12 col-md-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">Any status</option>
                <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option>
                <option value="suspended"<?= $status === 'suspended' ? ' selected' : '' ?>>Suspended</option>
            </select>
        </div>
        <div class="col-12 col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">Search</button>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('members/index.php')) ?>">Reset</a>
        </div>
    </form>

<?php if (!$members): ?>
    <div class="empty-state">
        <?php if ($query): ?>
            <strong>No members match your search.</strong>Check the spelling, or clear the filters.
        <?php else: ?>
            <strong>No members yet.</strong>Add the first member to start lending books.
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table data-table table-stack">
            <thead>
                <tr>
                    <th>Member</th>
                    <th class="d-none d-lg-table-cell">Contact</th>
                    <th>Status</th>
                    <th class="d-none d-md-table-cell">Joined</th>
                    <th class="num">Books out</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($members as $m):
                if ((int) $m['books_out'] > 0) {
                    $confirm = $m['full_name'] . ' still has ' . plural((int) $m['books_out'], 'unreturned book', 'unreturned books')
                             . ', so they cannot be deleted until everything is returned. Try anyway?';
                } else {
                    $confirm = 'Delete member ' . $m['full_name'] . ' (' . $m['member_number'] . ')? ' . ((int) $m['loan_count'] > 0
                        ? 'Their ' . plural((int) $m['loan_count'], 'past loan record', 'past loan records') . ' will be deleted too. '
                        : '') . 'This cannot be undone.';
                }
            ?>
                <tr class="spine" style="--spine: <?= $m['status'] === 'active' ? 'var(--shelf)' : 'var(--overdue)' ?>">
                    <td class="title-cell"><?= e($m['full_name']) ?><span class="sub"><?= e($m['member_number']) ?></span></td>
                    <td class="d-none d-lg-table-cell show-stacked" data-label="Contact"><?= e($m['email']) ?><span class="sub"><?= e($m['phone']) ?></span></td>
                    <td data-label="Status"><span class="badge <?= $m['status'] === 'active' ? 'badge-member' : 'badge-suspended' ?>"><?= e(ucfirst($m['status'])) ?></span></td>
                    <td class="d-none d-md-table-cell"><?= e(format_date($m['date_joined'])) ?></td>
                    <td class="num" data-label="Books out"><?= (int) $m['books_out'] ?></td>
                    <td class="text-end text-nowrap actions">
                        <?php if ($m['status'] === 'active'): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('loans/issue.php?member_id=' . (int) $m['member_id'])) ?>">Issue</a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('members/edit.php?id=' . (int) $m['member_id'])) ?>">Edit</a>
                        <form method="post" action="<?= e(base_url('members/delete.php')) ?>" class="d-inline" data-confirm="<?= e($confirm) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="member_id" value="<?= (int) $m['member_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="list-foot">
        <span>Showing <?= $pager['offset'] + 1 ?> to <?= $pager['offset'] + count($members) ?> of <?= (int) $pager['total'] ?> members</span>
        <?php render_pagination($pager, $query); ?>
        <a href="?<?= e(http_build_query($query + ['export' => 1])) ?>">Export to CSV</a>
    </div>
<?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
