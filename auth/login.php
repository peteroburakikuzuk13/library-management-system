<?php
/**
 * auth/login.php
 * Librarian login. Passwords are checked with password_verify() against the
 * bcrypt hash stored in the users table. Never plain text.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (is_logged_in()) {
    redirect('dashboard/index.php');
}

const MAX_FAILED_LOGINS = 5;     // after this many wrong attempts...
const LOCKOUT_SECONDS   = 60;    // ...wait this long before trying again

$errors   = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post_str('username');
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } elseif (($_SESSION['login_locked_until'] ?? 0) > time()) {
        $wait = $_SESSION['login_locked_until'] - time();
        $errors['form'] = "Too many failed attempts. Please wait {$wait} seconds and try again.";
    } elseif ($username === '' || $password === '') {
        $errors['form'] = 'Enter your username and password.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT user_id, username, password_hash, full_name, role FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Verify against a dummy hash when the user does not exist, so both cases take the same time.
            $hash  = $user['password_hash'] ?? '$2y$10$g8Nhjp9IXaVnkw3fQpoxQ.HV2hM8kWW4NkCWYBcK5oyQ6T0ncY1Ue';
            $match = password_verify($password, $hash) && $user;

            if ($match) {
                session_regenerate_id(true);   // new session id after login stops session fixation
                $_SESSION['user_id']   = (int) $user['user_id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['last_seen'] = time();
                unset($_SESSION['login_fails'], $_SESSION['login_locked_until']);
                set_flash('success', 'Welcome back, ' . $user['full_name'] . '.');
                redirect('dashboard/index.php');
            }

            $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
            if ($_SESSION['login_fails'] >= MAX_FAILED_LOGINS) {
                $_SESSION['login_locked_until'] = time() + LOCKOUT_SECONDS;
                $_SESSION['login_fails'] = 0;
            }
            $errors['form'] = 'The username or password is not correct.';   // same message either way
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $errors['form'] = 'The system is not available right now. Please try again shortly.';
        }
    }
}

$page_title = 'Log in';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="login-wrap">
    <div class="panel login-card panel-pad">
        <h1 class="login-title">Library Desk</h1>
        <p class="text-secondary mb-4">Log in to manage books, members and loans.</p>

        <?php if (isset($errors['form'])): ?>
            <div class="alert alert-danger" role="alert"><?= e($errors['form']) ?></div>
        <?php endif; ?>

        <form method="post" action="" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" maxlength="50"
                       value="<?= e($username) ?>" required autofocus>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" maxlength="200" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Log in</button>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
