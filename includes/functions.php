<?php
/**
 * includes/functions.php
 * Shared helpers used by every page: settings, session, flash messages, CSRF,
 * validation, pagination, CSV export and the loan / fine calculations.
 * Load it with require_once. It never prints anything by itself.
 */

// ---------------------------------------------------------------------------
// Settings you may change
// ---------------------------------------------------------------------------
const APP_NAME     = 'Library Management System';
const LOAN_DAYS    = 14;     // due date is set automatically: issue date + 14 days
const FINE_PER_DAY = 500;    // bonus feature: fine charged for each day a book is late
const PER_PAGE     = 10;     // bonus feature: rows per page in the lists
const APP_TIMEZONE = 'Africa/Kampala';   // XAMPP defaults to another zone, so we set ours

date_default_timezone_set(APP_TIMEZONE);

// Never show raw PHP or SQL errors to the user. They go to the PHP error log.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Any error we did not catch ends up here: a friendly page, never a stack trace.
set_exception_handler(function (Throwable $e) {
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Something went wrong</title></head>'
       . '<body style="font-family:Segoe UI,system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1rem">'
       . '<h1 style="font-family:Georgia,serif">Something went wrong</h1>'
       . '<p>The page could not be completed. Nothing was lost. Go back and try again, '
       . 'or contact the administrator if it keeps happening.</p>'
       . '<p><a href="javascript:history.back()">Go back</a></p></body></html>';
});

// Business-rule failures (for example "no copies available") use this class,
// so they are never confused with real database problems.
class RuleException extends Exception
{
}

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name('lms_session');   // own cookie name so other localhost projects cannot clash
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

// ---------------------------------------------------------------------------
// URLs and redirects. Works wherever the folder sits under htdocs.
// ---------------------------------------------------------------------------
function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $root = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
        $doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $base = ($doc !== '' && stripos($root, $doc) === 0) ? substr($root, strlen($doc)) : '';
        $base = rtrim($base, '/');
    }
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path)
{
    header('Location: ' . base_url($path));
    exit;
}

// ---------------------------------------------------------------------------
// Output escaping. Every value printed from the database or the user goes through e().
// ---------------------------------------------------------------------------
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------------------
// Flash messages: set one before a redirect, it shows once on the next page.
// ---------------------------------------------------------------------------
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    $allowed = ['success', 'danger', 'warning', 'info'];
    foreach ($_SESSION['flash'] as $flash) {
        $type = in_array($flash['type'], $allowed, true) ? $flash['type'] : 'info';
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
           . e($flash['message'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
}

// ---------------------------------------------------------------------------
// CSRF protection for every form that changes data
// ---------------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf_token']);
}

/** Stops the request unless it is a POST carrying a valid CSRF token. */
function require_valid_post(string $redirectTo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
        set_flash('danger', 'That request could not be verified. Please try again.');
        redirect($redirectTo);
    }
}

// ---------------------------------------------------------------------------
// Reading input safely (values may arrive as arrays if someone tampers)
// ---------------------------------------------------------------------------
function clean(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_scalar($value) ? trim((string) $value) : '';
}

function post_str(string $key): string
{
    return clean($_POST, $key);
}

function get_str(string $key): string
{
    return clean($_GET, $key);
}

/** Returns a whole number, or 0 when the value is missing or not a number. */
function to_int($value): int
{
    $n = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    return $n === false ? 0 : (int) $n;
}

function post_int(string $key): int
{
    return to_int(post_str($key));
}

function get_int(string $key): int
{
    return to_int(get_str($key));
}

function str_length(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

/** Binds every named parameter. Whole numbers are bound as integers (needed for LIMIT and OFFSET). */
function bind_all(PDOStatement $stmt, array $params): void
{
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

/** Escapes % and _ so a search for "50%" does not act as a wildcard. */
function like_escape(string $s): string
{
    return addcslashes($s, '%_\\');
}

// ---------------------------------------------------------------------------
// Form helpers
// ---------------------------------------------------------------------------
function invalid_class(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

function field_error(array $errors, string $field): string
{
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>'
        : '';
}

function error_summary(array $errors): string
{
    if (!$errors) {
        return '';
    }
    return '<div class="alert alert-danger" role="alert">Please fix the highlighted fields and try again.</div>';
}

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------
function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

function valid_email(string $email): bool
{
    return str_length($email) <= 150 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_phone(string $phone): bool
{
    return (bool) preg_match('/^\+?[0-9][0-9\s\-()]{6,18}$/', $phone);
}

/** ISBN-10 (last character may be X) or ISBN-13, hyphens and spaces already removed. */
function valid_isbn(string $isbn): bool
{
    return (bool) preg_match('/^(\d{13}|\d{9}[\dX])$/', $isbn);
}

/** True when a row with this id exists. Each query is written out in full, nothing is built from variables. */
function row_exists(PDO $pdo, string $table, int $id): bool
{
    $queries = [
        'authors'    => 'SELECT 1 FROM authors WHERE author_id = ?',
        'categories' => 'SELECT 1 FROM categories WHERE category_id = ?',
        'books'      => 'SELECT 1 FROM books WHERE book_id = ?',
        'members'    => 'SELECT 1 FROM members WHERE member_id = ?',
    ];
    if (!isset($queries[$table]) || $id < 1) {
        return false;
    }
    $stmt = $pdo->prepare($queries[$table]);
    $stmt->execute([$id]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Validates the book form. Returns [cleaned values, errors].
 * $ignoreId is the book being edited, so its own ISBN is not reported as a duplicate.
 */
function validate_book(PDO $pdo, array $in, int $ignoreId = 0): array
{
    $data = [
        'isbn'           => strtoupper(preg_replace('/[\s-]+/', '', clean($in, 'isbn'))),
        'title'          => clean($in, 'title'),
        'author_id'      => to_int(clean($in, 'author_id')),
        'category_id'    => to_int(clean($in, 'category_id')),
        'publisher'      => clean($in, 'publisher'),
        'year_published' => clean($in, 'year_published'),
        'total_copies'   => clean($in, 'total_copies'),
    ];
    $errors = [];

    if (!valid_isbn($data['isbn'])) {
        $errors['isbn'] = 'Enter a 10 or 13 digit ISBN. Hyphens are fine.';
    } else {
        $stmt = $pdo->prepare('SELECT book_id FROM books WHERE isbn = ? AND book_id <> ?');
        $stmt->execute([$data['isbn'], $ignoreId]);
        if ($stmt->fetchColumn()) {
            $errors['isbn'] = 'Another book already uses this ISBN.';
        }
    }
    if ($data['title'] === '' || str_length($data['title']) > 200) {
        $errors['title'] = 'Title is required and must be 200 characters or fewer.';
    }
    if (!row_exists($pdo, 'authors', $data['author_id'])) {
        $errors['author_id'] = 'Choose an author from the list.';
    }
    if (!row_exists($pdo, 'categories', $data['category_id'])) {
        $errors['category_id'] = 'Choose a category from the list.';
    }
    if ($data['publisher'] === '' || str_length($data['publisher']) > 120) {
        $errors['publisher'] = 'Publisher is required and must be 120 characters or fewer.';
    }
    $maxYear = (int) date('Y') + 1;
    if (!ctype_digit($data['year_published']) || (int) $data['year_published'] < 1000 || (int) $data['year_published'] > $maxYear) {
        $errors['year_published'] = "Enter a year between 1000 and {$maxYear}.";
    }
    if (!ctype_digit($data['total_copies']) || (int) $data['total_copies'] < 1 || (int) $data['total_copies'] > 500) {
        $errors['total_copies'] = 'Total copies must be a whole number from 1 to 500.';
    }
    return [$data, $errors];
}

/** Validates the member form. Returns [cleaned values, errors]. */
function validate_member(PDO $pdo, array $in, int $ignoreId = 0): array
{
    $data = [
        'member_number' => strtoupper(clean($in, 'member_number')),
        'full_name'     => clean($in, 'full_name'),
        'email'         => strtolower(clean($in, 'email')),
        'phone'         => clean($in, 'phone'),
        'status'        => clean($in, 'status'),
        'date_joined'   => clean($in, 'date_joined'),
    ];
    $errors = [];

    if (!preg_match('/^[A-Z0-9-]{3,20}$/', $data['member_number'])) {
        $errors['member_number'] = 'Use 3 to 20 letters, numbers or hyphens, for example LM-0013.';
    } else {
        $stmt = $pdo->prepare('SELECT member_id FROM members WHERE member_number = ? AND member_id <> ?');
        $stmt->execute([$data['member_number'], $ignoreId]);
        if ($stmt->fetchColumn()) {
            $errors['member_number'] = 'This member number is already in use.';
        }
    }
    if (str_length($data['full_name']) < 2 || str_length($data['full_name']) > 120) {
        $errors['full_name'] = 'Full name must be between 2 and 120 characters.';
    }
    if (!valid_email($data['email'])) {
        $errors['email'] = 'Enter a valid email address, for example name@example.com.';
    } else {
        $stmt = $pdo->prepare('SELECT member_id FROM members WHERE email = ? AND member_id <> ?');
        $stmt->execute([$data['email'], $ignoreId]);
        if ($stmt->fetchColumn()) {
            $errors['email'] = 'This email address is already registered.';
        }
    }
    if (!valid_phone($data['phone'])) {
        $errors['phone'] = 'Enter a phone number of 7 to 20 digits. Spaces, + and hyphens are fine.';
    }
    if (!in_array($data['status'], ['active', 'suspended'], true)) {
        $errors['status'] = 'Choose active or suspended.';
    }
    if (!valid_date($data['date_joined'])) {
        $errors['date_joined'] = 'Enter a valid date.';
    } elseif ($data['date_joined'] > today()) {
        $errors['date_joined'] = 'The join date cannot be in the future.';
    }
    return [$data, $errors];
}

// ---------------------------------------------------------------------------
// Database error helpers
// ---------------------------------------------------------------------------
/** Logs the real error and returns a message that is safe to show. */
function db_error_message(PDOException $e): string
{
    error_log('Database error: ' . $e->getMessage());
    return 'Something went wrong while saving your changes. Nothing was changed. Please try again.';
}

function is_duplicate_error(PDOException $e): bool
{
    return (int) ($e->errorInfo[1] ?? 0) === 1062;
}

// ---------------------------------------------------------------------------
// Dates, loans and fines
// ---------------------------------------------------------------------------
function today(): string
{
    return date('Y-m-d');
}

function add_days(string $date, int $days): string
{
    return (new DateTime($date))->modify("+{$days} days")->format('Y-m-d');
}

function format_date(?string $date): string
{
    return $date ? date('d M Y', strtotime($date)) : '-';
}

/** Whole days a loan is (or was) late. 0 when on time. */
function days_late(string $due, ?string $returned): int
{
    $diff = (new DateTime($due))->diff(new DateTime($returned ?? today()));
    return $diff->invert ? 0 : (int) $diff->days;
}

function loan_fine(string $due, ?string $returned): int
{
    return days_late($due, $returned) * FINE_PER_DAY;
}

/** 'returned', 'overdue' or 'active' */
function loan_state(array $loan): string
{
    if (!empty($loan['date_returned'])) {
        return 'returned';
    }
    return $loan['due_date'] < today() ? 'overdue' : 'active';
}

function loan_badge(array $loan): string
{
    switch (loan_state($loan)) {
        case 'returned':
            $late = days_late($loan['due_date'], $loan['date_returned']);
            return '<span class="badge badge-returned">Returned' . ($late ? ' late' : '') . '</span>';
        case 'overdue':
            $d = days_late($loan['due_date'], null);
            return '<span class="badge badge-overdue">Overdue ' . $d . ($d === 1 ? ' day' : ' days') . '</span>';
        default:
            return $loan['due_date'] === today()
                ? '<span class="badge badge-due">Due today</span>'
                : '<span class="badge badge-active">On loan</span>';
    }
}

/** plural(1, 'copy', 'copies') -> "1 copy", plural(3, 'copy', 'copies') -> "3 copies" */
function plural(int $n, string $one, string $many): string
{
    return $n . ' ' . ($n === 1 ? $one : $many);
}

function money(int $amount): string
{
    return number_format($amount);
}

// ---------------------------------------------------------------------------
// Little colour "spine" used on book and loan rows (purely visual)
// ---------------------------------------------------------------------------
function spine_color(int $id): string
{
    $palette = ['#1f5f8b', '#8b3a3a', '#2f6f4f', '#b8862b', '#5b4b8a', '#3a7d8c', '#a65a2e', '#6b6f76'];
    return $palette[$id % count($palette)];
}

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------
function paginate(int $total, int $requestedPage, int $perPage = PER_PAGE): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, $requestedPage), $pages);
    return ['total' => $total, 'pages' => $pages, 'page' => $page, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage];
}

/** Prints Bootstrap pagination. $query holds the current search and filter values. */
function render_pagination(array $p, array $query = []): void
{
    if ($p['pages'] <= 1) {
        return;
    }
    $link = function (int $n) use ($query) {
        return '?' . e(http_build_query(array_merge($query, ['page' => $n])));
    };
    echo '<nav aria-label="Pages"><ul class="pagination pagination-sm justify-content-center mb-0">';
    $prevDisabled = $p['page'] <= 1 ? ' disabled' : '';
    echo '<li class="page-item' . $prevDisabled . '"><a class="page-link" href="' . $link($p['page'] - 1) . '">Previous</a></li>';
    for ($n = 1; $n <= $p['pages']; $n++) {
        if ($n === 1 || $n === $p['pages'] || abs($n - $p['page']) <= 2) {
            $active = $n === $p['page'] ? ' active' : '';
            echo '<li class="page-item' . $active . '"><a class="page-link" href="' . $link($n) . '">' . $n . '</a></li>';
        } elseif (abs($n - $p['page']) === 3) {
            echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
    }
    $nextDisabled = $p['page'] >= $p['pages'] ? ' disabled' : '';
    echo '<li class="page-item' . $nextDisabled . '"><a class="page-link" href="' . $link($p['page'] + 1) . '">Next</a></li>';
    echo '</ul></nav>';
}

// ---------------------------------------------------------------------------
// CSV export (bonus). Cells that start with = + - @ get a quote so Excel
// cannot run them as formulas.
// ---------------------------------------------------------------------------
function csv_safe($value): string
{
    $value = (string) $value;
    return ($value !== '' && strpos("=+-@\t\r", $value[0]) !== false) ? "'" . $value : $value;
}

function send_csv(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // byte order mark so Excel reads UTF-8 correctly
    fputcsv($out, $header, ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($out, array_map('csv_safe', $row), ',', '"', '\\');
    }
    fclose($out);
    exit;
}
