<?php
/**
 * dashboard/index.php
 * Summary after login: what needs attention at the desk, key numbers, and the shelf.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$today = today();
$soon  = add_days($today, 3);

try {
    $books = $pdo->query('SELECT COUNT(*) AS titles, COALESCE(SUM(total_copies), 0) AS copies,
                                 COALESCE(SUM(available_copies), 0) AS available FROM books')->fetch();

    $members = $pdo->query("SELECT COUNT(*) AS total,
                                   COALESCE(SUM(status = 'active'), 0) AS active,
                                   COALESCE(SUM(status = 'suspended'), 0) AS suspended FROM members")->fetch();

    $stmt = $pdo->prepare('SELECT COUNT(*) AS out_now,
                                  COALESCE(SUM(due_date < :today1), 0) AS overdue,
                                  COALESCE(SUM(GREATEST(DATEDIFF(:today2, due_date), 0)), 0) AS late_days
                           FROM loans WHERE date_returned IS NULL');
    $stmt->execute([':today1' => $today, ':today2' => $today]);
    $loans = $stmt->fetch();

    // The first job of the desk: who is overdue, longest first.
    $stmt = $pdo->prepare('SELECT l.loan_id, l.due_date, l.date_returned, m.full_name, m.member_number, m.phone, b.title
                           FROM loans l
                           JOIN members m ON m.member_id = l.member_id
                           JOIN books   b ON b.book_id   = l.book_id
                           WHERE l.date_returned IS NULL AND l.due_date < :today
                           ORDER BY l.due_date ASC LIMIT 6');
    $stmt->execute([':today' => $today]);
    $overdue = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT l.loan_id, l.due_date, l.date_returned, m.full_name, b.title
                           FROM loans l
                           JOIN members m ON m.member_id = l.member_id
                           JOIN books   b ON b.book_id   = l.book_id
                           WHERE l.date_returned IS NULL AND l.due_date BETWEEN :today AND :soon
                           ORDER BY l.due_date ASC LIMIT 6');
    $stmt->execute([':today' => $today, ':soon' => $soon]);
    $dueSoon = $stmt->fetchAll();

    $shelf = $pdo->query('SELECT c.category_id, c.name, COUNT(b.book_id) AS titles
                          FROM categories c LEFT JOIN books b ON b.category_id = c.category_id
                          GROUP BY c.category_id, c.name ORDER BY titles DESC, c.name')->fetchAll();
} catch (PDOException $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    $books = ['titles' => 0, 'copies' => 0, 'available' => 0];
    $members = ['total' => 0, 'active' => 0, 'suspended' => 0];
    $loans = ['out_now' => 0, 'overdue' => 0, 'late_days' => 0];
    $overdue = $dueSoon = $shelf = [];
    set_flash('danger', 'Some figures could not be loaded. Please refresh the page.');
}

$finesOwed = (int) $loans['late_days'] * FINE_PER_DAY;
$maxTitles = max(1, ...array_map(function ($r) { return (int) $r['titles']; }, $shelf ?: [['titles' => 1]]));

$page_title  = 'Dashboard';
$active_nav  = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p>Today is <?= e(date('l, d F Y')) ?>.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(base_url('loans/issue.php')) ?>">Issue a book</a>
</div>
 
<section class="panel stat-strip mb-4" aria-label="Library figures">
    <div class="stat">
        <div class="stat-value"><?= (int) $books['titles'] ?></div>
        <div class="stat-label">Book titles</div>
    </div>
    <div class="stat">
        <div class="stat-value"><?= (int) $books['available'] ?> <small>of <?= (int) $books['copies'] ?></small></div>
        <div class="stat-label">Copies on the shelf</div>
    </div>
    <div class="stat">
        <div class="stat-value"><?= (int) $loans['out_now'] ?></div>
        <div class="stat-label">Books out on Loan</div>
    </div>
    <div class="stat<?= (int) $loans['overdue'] > 0 ? ' is-alert' : '' ?>">
        <div class="stat-value"><?= (int) $loans['overdue'] ?></div>
        <div class="stat-label">Overdue, fines <?= e(money($finesOwed)) ?></div>
    </div>
    <div class="stat">
        <div class="stat-value"><?= (int) $members['active'] ?> <small>of <?= (int) $members['total'] ?></small></div>
        <div class="stat-label">Active members</div>
    </div>
</section>

<div class="row g-4">
    <div class="col-lg-7">
        <section class="panel h-100">
            <div class="panel-head">
                <h2 class="panel-title">Overdue loans</h2>
                <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('loans/index.php?status=overdue')) ?>">See all overdue</a>
            </div>
            <?php if (!$overdue): ?>
                <div class="empty-state"><strong>Nothing is overdue.</strong>Every borrowed book is within its due date.</div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table data-table table-stack">
                    <thead><tr><th>Book</th><th>Member</th><th>Due</th><th class="num">Fine</th></tr></thead>
                    <tbody>
                    <?php foreach ($overdue as $row): ?>
                        <tr class="spine" style="--spine: var(--overdue)">
                            <td class="title-cell"><?= e($row['title']) ?></td>
                            <td data-label="Member"><?= e($row['full_name']) ?><span class="sub"><?= e($row['phone']) ?></span></td>
                            <td data-label="Due"><?= e(format_date($row['due_date'])) ?><span class="sub"><?= (int) days_late($row['due_date'], null) ?> days late</span></td>
                            <td class="num" data-label="Fine"><?= e(money(loan_fine($row['due_date'], null))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="col-lg-5">
        <section class="panel mb-4">
            <div class="panel-head"><h2 class="panel-title">Due in the next 3 days</h2></div>
            <?php if (!$dueSoon): ?>
                <div class="empty-state"><strong>No returns expected soon.</strong>Books due in the next 3 days will appear here.</div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table data-table">
                    <tbody>
                    <?php foreach ($dueSoon as $row): ?>
                        <tr>
                            <td class="title-cell"><?= e($row['title']) ?><span class="sub"><?= e($row['full_name']) ?></span></td>
                            <td class="num"><?= loan_badge($row) ?><span class="sub"><?= e(format_date($row['due_date'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title mb-2">Titles on the shelf by category</h2>
            <?php foreach ($shelf as $row): ?>
                <div class="shelf-row" style="--spine: <?= e(spine_color((int) $row['category_id'])) ?>">
                    <span><?= e($row['name']) ?></span>
                    <span class="shelf-bar"><span style="width: <?= (int) round(((int) $row['titles'] / $maxTitles) * 100) ?>%"></span></span>
                    <span class="num"><?= (int) $row['titles'] ?></span>
                </div>
            <?php endforeach; ?>
        </section>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
