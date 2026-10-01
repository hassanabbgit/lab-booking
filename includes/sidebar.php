<?php
/**
 * Computer Laboratory Booking System - sidebar navigation.
 * Included by header.php. Do not include this file directly.
 *
 * Renders twice: once as an always-visible column on desktop and once as a
 * Bootstrap offcanvas on mobile, so the same markup serves both breakpoints.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/bootstrap.php';
}

$__active = isset($GLOBALS['__active']) ? $GLOBALS['__active'] : '';
$__isAdmin = is_admin();

if ($__isAdmin) {
    $__links = array(
        array('key' => 'dashboard',     'icon' => 'bi-speedometer2',   'label' => 'Dashboard',      'href' => 'admin/index.php'),
        array('key' => 'laboratories',  'icon' => 'bi-pc-display',     'label' => 'Laboratories',   'href' => 'admin/laboratories.php'),
        array('key' => 'bookings',      'icon' => 'bi-calendar-check',  'label' => 'Bookings',       'href' => 'admin/bookings.php'),
        array('key' => 'approvals',     'icon' => 'bi-inbox',           'label' => 'Approvals',      'href' => 'admin/approvals.php', 'badge' => 'pending'),
        array('key' => 'users',         'icon' => 'bi-people',          'label' => 'Users',          'href' => 'admin/users.php'),
        array('key' => 'slots',         'icon' => 'bi-clock-history',   'label' => 'Time Slots',     'href' => 'admin/time-slots.php'),
        array('key' => 'reports',       'icon' => 'bi-bar-chart-line',  'label' => 'Reports',        'href' => 'admin/reports.php'),
        array('key' => 'activity',      'icon' => 'bi-activity',        'label' => 'Activity Log',   'href' => 'admin/activity.php'),
    );
    $__section = 'Administration';
} else {
    $__links = array(
        array('key' => 'dashboard',     'icon' => 'bi-speedometer2',  'label' => 'Dashboard',      'href' => 'user/index.php'),
        array('key' => 'laboratories',  'icon' => 'bi-pc-display',    'label' => 'Laboratories',   'href' => 'user/laboratories.php'),
        array('key' => 'new-booking',   'icon' => 'bi-plus-circle',   'label' => 'New Booking',    'href' => 'user/book.php'),
        array('key' => 'bookings',      'icon' => 'bi-calendar-check', 'label' => 'My Bookings',   'href' => 'user/bookings.php'),
        array('key' => 'history',       'icon' => 'bi-clock-history', 'label' => 'History',        'href' => 'user/history.php'),
    );
    $__section = 'Booking';
}

// Live badge for the admin approvals link.
$__pendingCount = 0;
if ($__isAdmin) {
    try {
        $__pendingCount = (int) db_value("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
    } catch (Exception $e) {
        $__pendingCount = 0;
    }
}
?>
<aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar"
       aria-label="Main navigation">
  <div class="offcanvas-header d-lg-none">
    <h5 class="offcanvas-title">Menu</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>

  <div class="offcanvas-body d-lg-flex flex-column p-0">
    <div class="sidebar-section"><?php echo e($__section); ?></div>

    <nav class="sidebar-nav">
      <?php foreach ($__links as $__link):
          $__count = 0;
          if (isset($__link['badge']) && $__link['badge'] === 'pending') {
              $__count = $__pendingCount;
          }
      ?>
        <a class="sidebar-link text-primary<?php echo nav_active($__active, $__link['key']); ?>"
           href="<?php echo e(url($__link['href'])); ?>">
          <i class="bi <?php echo e($__link['icon']); ?>"></i>
          <span><?php echo e($__link['label']); ?></span>
          <?php if ($__count > 0): ?>
            <span class="sidebar-badge"><?php echo e($__count); ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
      <div class="sidebar-foot-title text-secondary"><i class="bi bi-hdd-network"></i> Local Network</div>
      <p class="mb-1 text-secondary">This server is reachable on your LAN at:</p>
      <code class="lan-url text-primary">http://<?php echo e(isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'SERVER_IP'); ?></code>
    </div>
  </div>
</aside>
