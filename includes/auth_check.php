<?php
/**
 * includes/auth_check.php
 * Blocks any page when nobody is logged in. Every protected page requires this
 * file FIRST, before any HTML is printed.
 */
require_once __DIR__ . '/functions.php';

if (!is_logged_in()) {
    set_flash('warning', 'Please log in to continue.');
    redirect('auth/login.php');
}

// Sign the librarian out after 2 hours without activity.
if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 7200) {
    $_SESSION = [];
    session_regenerate_id(true);
    set_flash('warning', 'Your session expired. Please log in again.');
    redirect('auth/login.php');
}
$_SESSION['last_seen'] = time();
