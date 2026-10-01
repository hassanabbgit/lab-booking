<?php
/**
 * Computer Laboratory Booking System - opening document head and body.
 * Included by layout_start(). Do not include this file directly.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/bootstrap.php';
}

require_once __DIR__ . '/alerts.php';

$__opts = isset($GLOBALS['__layout'])
    ? $GLOBALS['__layout']
    : array('title' => '', 'subtitle' => '', 'layout' => 'app', 'active' => '');
$__isAppLayout = ($__opts['layout'] === 'app') && is_logged_in();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo e($pageTitle); ?></title>

<!-- Vendored locally: the server must work with no internet access on the LAN. -->
<link rel="stylesheet" href="<?php echo e(asset('vendor/bootstrap/css/bootstrap.min.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
</head>
<body class="<?php echo $__isAppLayout ? 'layout-app' : 'layout-plain'; ?>">

<?php require __DIR__ . '/navbar.php'; ?>

<?php if ($__isAppLayout): ?>
<div class="app-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <main class="app-main">
        <div class="container-fluid app-content">
<?php else: ?>
<div class="plain-shell">
    <div class="container py-4 py-lg-5">
<?php endif; ?>
