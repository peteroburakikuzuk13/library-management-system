<?php
/**
 * index.php  -  the front door. Sends visitors to the dashboard when logged in,
 * otherwise to the login page.
 */
require_once __DIR__ . '/includes/functions.php';

redirect(is_logged_in() ? 'dashboard/index.php' : 'auth/login.php');
