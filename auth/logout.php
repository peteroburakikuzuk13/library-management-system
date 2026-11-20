<?php
/**
 * auth/logout.php
 * Ends the session completely, then returns to the login page with a message.
 */
require_once __DIR__ . '/../includes/functions.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

session_start();                 // fresh session, only to carry the goodbye message
session_regenerate_id(true);
set_flash('success', 'You have been logged out.');
redirect('auth/login.php');
