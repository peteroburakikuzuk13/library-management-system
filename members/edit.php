<?php
/**
 * members/edit.php
 * UPDATE: change a member's details, including suspending or reactivating them.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost) {
    require_valid_post('members/index.php');
}
$id = $isPost ? post_int('member_id') : get_int('id');

try {
    $stmt = $pdo->prepare('SELECT * FROM members WHERE member_id = ?');
    $stmt->execute([$id]);
    $member = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Member edit load error: ' . $e->getMessage());
    set_flash('danger', 'The member could not be loaded. Please try again.');
    redirect('members/index.php');
}
if (!$member) {
    set_flash('danger', 'That member could not be found. They may have been deleted.');
    redirect('members/index.php');
}

$errors = [];
$data = ['member_number' => $member['member_number'], 'full_name' => $member['full_name'], 'email' => $member['email'],
         'phone' => $member['phone'], 'status' => $member['status'], 'date_joined' => $member['date_joined']];

if ($isPost) {
    try {
        [$data, $errors] = validate_member($pdo, $_POST, $id);
        if (!$errors) {
            $stmt = $pdo->prepare('UPDATE members SET member_number = ?, full_name = ?, email = ?, phone = ?, status = ?, date_joined = ?
                                   WHERE member_id = ?');
            $stmt->execute([$data['member_number'], $data['full_name'], $data['email'], $data['phone'], $data['status'], $data['date_joined'], $id]);
            set_flash('success', 'Member updated successfully. Changes to ' . $data['full_name'] . ' are saved.');
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

$page_title = 'Edit member';
$active_nav = 'members';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Edit member</h1><p><?= e($member['full_name']) ?>, <?= e($member['member_number']) ?></p></div>
    <a class="btn btn-outline-secondary" href="<?= e(base_url('members/index.php')) ?>">Back to members</a>
</div>

<div class="panel panel-pad">
    <?= error_summary($errors) ?>
    <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="member_id" value="<?= (int) $id ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="member_number" class="form-label">Member number</label>
                <input type="text" class="form-control<?= invalid_class($errors, 'member_number') ?>" id="member_number" name="member_number"
                       value="<?= e($data['member_number']) ?>" maxlength="20" required>
                <?= field_error($errors, 'member_number') ?>
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
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('members/index.php')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
