<?php
/**
 * includes/header.php
 * Opens the page: <head>, navigation and the flash messages.
 * Set $page_title and $active_nav before requiring this file.
 */
require_once __DIR__ . '/functions.php';
$page_title = $page_title ?? 'Library';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> | Library Desk</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(base_url('assets/images/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<main class="container app-main">
<?php render_flash(); ?>
