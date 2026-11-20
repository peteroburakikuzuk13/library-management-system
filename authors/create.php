<?php
/**
 * authors/create.php
 * CREATE: add a new author.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$data = ['name' => '', 'country' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_post('authors/create.php');
    $data = ['name' => post_str('name'), 'country' => post_str('country')];

    if (str_length($data['name']) < 2 || str_length($data['name']) > 120) {
        $errors['name'] = 'Author name must be between 2 and 120 characters.';
    }
    if (str_length($data['country']) > 80) {
        $errors['country'] = 'Country must be 80 characters or fewer.';
    }

    if (!$errors) {
        $country = $data['country'] === '' ? 'Unknown' : $data['country'];
        try {
            $stmt = $pdo->prepare('INSERT INTO authors (name, country) VALUES (?, ?)');
            $stmt->execute([$data['name'], $country]);
            set_flash('success', 'Author added successfully: ' . $data['name'] . '.');
            redirect('authors/index.php');
        } catch (PDOException $e) {
            if (is_duplicate_error($e)) {
                $errors['name'] = 'This author is already in the list for that country.';
            } else {
                set_flash('danger', db_error_message($e));
            }
        }
    }
}

$page_title = 'Add an author';
$active_nav = 'authors';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Add an author</h1><p>Authors can then be chosen when adding a book.</p></div>
    <a class="btn btn-outline-secondary" href="<?= e(base_url('authors/index.php')) ?>">Back to authors</a>
</div>

<div class="panel panel-pad" style="max-width: 40rem">
    <?= error_summary($errors) ?>
    <form method="post" action="">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" class="form-control<?= invalid_class($errors, 'name') ?>" id="name" name="name"
                   value="<?= e($data['name']) ?>" maxlength="120" required>
            <?= field_error($errors, 'name') ?>
        </div>
        <div class="mb-4">
            <label for="country" class="form-label">Country</label>
            <input type="text" class="form-control<?= invalid_class($errors, 'country') ?>" id="country" name="country"
                   value="<?= e($data['country']) ?>" maxlength="80">
            <?= field_error($errors, 'country') ?>
            <div class="form-text">Optional. Shown as “Unknown” when left empty.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Add author</button>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('authors/index.php')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

