<?php
/**
 * categories/create.php
 * CREATE: add a new category.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$data = ['name' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_post('categories/create.php');
    $data = ['name' => post_str('name'), 'description' => post_str('description')];

    if (str_length($data['name']) < 2 || str_length($data['name']) > 80) {
        $errors['name'] = 'Category name must be between 2 and 80 characters.';
    }
    if (str_length($data['description']) > 255) {
        $errors['description'] = 'Description must be 255 characters or fewer.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            $stmt->execute([$data['name'], $data['description'] === '' ? null : $data['description']]);
            set_flash('success', 'Category added successfully: ' . $data['name'] . '.');
            redirect('categories/index.php');
        } catch (PDOException $e) {
            if (is_duplicate_error($e)) {
                $errors['name'] = 'A category with this name already exists.';
            } else {
                set_flash('danger', db_error_message($e));
            }
        }
    }
}

$page_title = 'Add a category';
$active_nav = 'categories';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Add a category</h1><p>Categories can then be chosen when adding a book.</p></div>
    <a class="btn btn-outline-secondary" href="<?= e(base_url('categories/index.php')) ?>">Back to categories</a>
</div>

<div class="panel panel-pad" style="max-width: 40rem">
    <?= error_summary($errors) ?>
    <form method="post" action="">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" class="form-control<?= invalid_class($errors, 'name') ?>" id="name" name="name"
                   value="<?= e($data['name']) ?>" maxlength="80" required>
            <?= field_error($errors, 'name') ?>
        </div>
        <div class="mb-4">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control<?= invalid_class($errors, 'description') ?>" id="description" name="description"
                      rows="3" maxlength="255"><?= e($data['description']) ?></textarea>
            <?= field_error($errors, 'description') ?>
            <div class="form-text">Optional. One line about what belongs here.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Add category</button>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('categories/index.php')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
