<?php
/**
 * Computer Laboratory Booking System - top navigation bar.
 * Included by header.php. Do not include this file directly.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/bootstrap.php';
}

$__user = current_user();
$__unread = 0;
if ($__user !== null) {
    try {
        $__unread = unread_notification_count();
    } catch (Exception $e) {
        $__unread = 0;
    }
}
$__serverIp = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
?>
<nav class="navbar navbar-expand-lg app-navbar sticky-top">
  <div class="container-fluid">

    <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo e(url(home_path())); ?>">
      <span class="brand-mark"><i class="bi bi-pc-display"></i></span>
      <span class="brand-text">
        <strong>Lab Booking</strong>
        <small>Computer Laboratory Booking System</small>
      </span>
    </a>

    <button class="navbar-toggler border-0 shadow-none" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
            aria-controls="appSidebar" aria-label="Toggle navigation">
      <i class="bi bi-list fs-3"></i>
    </button>

    <div class="collapse navbar-collapse justify-content-end">
      <ul class="navbar-nav align-items-lg-center gap-lg-1">

        <?php if (!is_logged_in()): ?>
          <li class="nav-item">
            <a class="btn btn-sm btn-outline-primary" href="<?php echo e(url('auth/login.php')); ?>">Sign in</a>
          </li>
        <?php else: ?>
          <?php if ($__serverIp !== ''): ?>
          <li class="nav-item d-none d-xl-block">
            <span class="nav-server" title="Clients on the local network can reach this server here">
              <i class="bi bi-router"></i> <?php echo e($__serverIp); ?>
            </span>
          </li>
          <?php endif; ?>

          <li class="nav-item">
            <a class="nav-icon" href="<?php echo e(url(is_admin() ? 'admin/notifications.php' : 'user/notifications.php')); ?>"
               title="Notifications">
              <i class="bi bi-bell"></i>
              <?php if ($__unread > 0): ?>
                <span class="nav-badge"><?php echo $__unread > 9 ? '9+' : e($__unread); ?></span>
              <?php endif; ?>
            </a>
          </li>

          <li class="nav-item dropdown">
            <a class="nav-user dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="avatar"><?php echo e(initials($__user['name'])); ?></span>
              <span class="d-none d-sm-inline">
                <strong><?php echo e($__user['name']); ?></strong>
                <small class="role-tag"><?php echo e($__user['role'] === 'admin' ? 'Administrator' : 'Student'); ?></small>
              </span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
              <li class="dropdown-header">
                <strong><?php echo e($__user['name']); ?></strong><br>
                <small><?php echo e($__user['email']); ?></small>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item" href="<?php echo e(url(is_admin() ? 'admin/profile.php' : 'user/profile.php')); ?>">
                  <i class="bi bi-person me-2"></i>My profile
                </a>
              </li>
              <li>
                <a class="dropdown-item text-danger" href="<?php echo e(url('auth/logout.php')); ?>">
                  <i class="bi bi-box-arrow-right me-2"></i>Sign out
                </a>
              </li>
            </ul>
          </li>
        <?php endif; ?>

      </ul>
    </div>
  </div>
</nav>
