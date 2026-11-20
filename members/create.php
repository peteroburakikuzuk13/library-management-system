<?php
/**
 * members/create.php
 * CREATE: register a new member.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];

try {
    $next = (int) $pdo->query('SELECT COALESCE(MAX(member_id), 0) + 1 FROM members')->fetchColumn();
} catch (PDOException $e) {
    error_log('Member number suggestion error: ' . $e->getMessage());
    $next = 1;
}
$data = ['member_number' => 'LM-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT), 'full_name' => '', 'email' => '',
         'phone' => '', 'status' => 'active', 'date_joined' => today()];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_post('members/create.php');
    try {
        [$data, $errors] = validate_member($pdo, $_POST);
        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO members (member_number, full_name, email, phone, status, date_joined)
                                   VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$data['member_number'], $data['full_name'], $data['email'], $data['phone'], $data['status'], $data['date_joined']]);
            set_flash('success', 'Member added successfully. ' . $data['full_name'] . ' is registered as ' . $data['member_number'] . '.');
            redirect('members/index.php');
        }
    } catch (PDOException $e) {
        if (is_duplicate_error($e)) {
            $errors['email'] = 'This email address or member number is already registered.';
        } else {
            set_flash('danger', db_error_message($e));
        }
    }
}

$page_title = 'Add a member';
$active_nav = 'members';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Add a member</h1><p>Every field is required.</p></div>
    <a class="btn btn-outline-secondary" href="<?= e(base_url('members/index.php')) ?>">Back to members</a>
</div>

<div class="panel panel-pad">
    <?= error_summary($errors) ?>
    <form method="post" action="">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="member_number" class="form-label">Member number</label>
                <input type="text" class="form-control<?= invalid_class($errors, 'member_number') ?>" id="member_number" name="member_number"
                       value="<?= e($data['member_number']) ?>" maxlength="20" required>
                <?= field_error($errors, 'member_number') ?>
                <div class="form-text">Suggested automatically. You can change it.</div>
            </div>
            <div class="col-md-8">
                <label for="full_name" class="form-label">Full name</label>
                <input type="text" class="form-control<?= invalid_class($errors, 'full_name') ?>" id="full_name" name="full_name"
                       value="<?= e($data['full_name']) ?>" maxlength="120" required>
                <?= field_error($errors, 'full_name') ?>
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control<?= invalid_class($errors, 'email') ?>" id="email" name="email"
                       value="<?= e($data['email']) ?>" maxlength="150" required>
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="col-md-6">
                <label for="phone" class="form-label">Phone</label>
                <input type="tel" class="form-control<?= invalid_class($errors, 'phone') ?>" id="phone" name="phone"
                       value="<?= e($data['phone']) ?>" maxlength="20" required>
                <?= field_error($errors, 'phone') ?>
            </div>
            <div class="col-md-6">
                <label for="status" class="form-label">Status</label>
                <select class="form-select<?= invalid_class($errors, 'status') ?>" id="status" name="status" required>
                    <option value="active"<?= $data['status'] === 'active' ? ' selected' : '' ?>>Active</option>
                    <option value="suspended"<?= $data['status'] === 'suspended' ? ' selected' : '' ?>>Suspended</option>
                </select>
                <?= field_error($errors, 'status') ?>
                <div class="form-text">Suspended members cannot borrow books.</div>
            </div>
            <div class="col-md-6">
                <label for="date_joined" class="form-label">Date joined</label>
                <input type="date" class="form-control<?= invalid_class($errors, 'date_joined') ?>" id="date_joined" name="date_joined"
                       value="<?= e($data['date_joined']) ?>" max="<?= e(today()) ?>" required>
                <?= field_error($errors, 'date_joined') ?>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Add member</button>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('members/index.php')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
