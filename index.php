<?php
/**
 * Computer Laboratory Booking System
 * -----------------------------------
 * Public landing page.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();

// Statistics for the landing page. If the database is unavailable the page
// still renders, just without the counters.
$stats = array('labs' => 0, 'computers' => 0, 'bookings' => 0);
if (DB_NAME !== '') {
    try {
        $stats['labs']      = (int) db_value("SELECT COUNT(*) FROM laboratories WHERE status <> 'inactive'");
        $stats['computers'] = (int) db_value('SELECT COALESCE(SUM(computer_count), 0) FROM laboratories');
        $stats['bookings']  = (int) db_value('SELECT COUNT(*) FROM bookings');
    } catch (Exception $e) {
        error_log('CLBS landing stats failed: ' . $e->getMessage());
    }
}

$features = array(
    array('bi-pc-display',  'Browse laboratories',    'See every computer laboratory with its location, capacity and number of machines.'),
    array('bi-calendar-check', 'Request a booking',  'Pick a date and time window, state your purpose and send the request in seconds.'),
    array('bi-shield-check', 'No double bookings',    'Conflicting reservations are blocked automatically, so a laboratory is never double-allocated.'),
    array('bi-clipboard-check', 'Track approval',    'Follow your request from pending through to approved, rejected or completed.'),
    array('bi-speedometer2', 'Admin dashboard',     'See laboratory usage, pending approvals and booking statistics at a glance.'),
    array('bi-hdd-network', 'Runs on the LAN',      'Any device on the local network reaches the server through its IP address. No internet needed.'),
);

$flow = array('Choose a laboratory', 'Select date and time', 'Submit request', 'Admin approves', 'Booking confirmed');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo e(APP_NAME); ?></title>
<link rel="stylesheet" href="<?php echo e(asset('vendor/bootstrap/css/bootstrap.min.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
</head>
<body class="layout-plain d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg app-navbar">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo e(url()); ?>">
      <span class="brand-mark"><i class="bi bi-pc-display"></i></span>
      <span class="brand-text">
        <strong>Lab Booking</strong>
        <small>Computer Laboratory System</small>
      </span>
    </a>
    <div class="d-flex align-items-center gap-2">
      <?php if ($user !== null): ?>
        <a class="btn btn-sm btn-primary" href="<?php echo e(url(home_path())); ?>">
          <i class="bi bi-speedometer2 me-1"></i> Dashboard
        </a>
      <?php else: ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(url('auth/register.php')); ?>">Register</a>
        <a class="btn btn-sm btn-primary" href="<?php echo e(url('auth/login.php')); ?>">Sign in</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<main class="flex-grow-1">

  <div class="container py-5">
    <div class="hero mb-5">
      <div class="row align-items-center g-4">
        <div class="col-lg-7">
          <span class="badge bg-light text-primary mb-3">
            <i class="bi bi-wifi me-1"></i> Client-server &middot; Local network
          </span>
          <h1>Computer Laboratory Booking System</h1>
          <p class="mt-3 mb-4">
            Replace paper booking registers with a reliable web application.
            Students request a laboratory and time slot, the administrator
            approves it, and the system guarantees no two bookings ever clash.
          </p>
          <?php if ($user === null): ?>
            <a class="btn btn-light btn-lg me-2" href="<?php echo e(url('auth/register.php')); ?>">
              <i class="bi bi-person-plus me-1"></i> Create an account
            </a>
            <a class="btn btn-outline-light btn-lg" href="<?php echo e(url('auth/login.php')); ?>">
              Sign in
            </a>
          <?php else: ?>
            <a class="btn btn-light btn-lg" href="<?php echo e(url(home_path())); ?>">
              <i class="bi bi-speedometer2 me-1"></i> Go to my dashboard
            </a>
          <?php endif; ?>
        </div>
        <div class="col-lg-5">
          <div class="row g-3">
            <div class="col-6">
              <div class="p-3 rounded-3 bg-white bg-opacity-10 text-center">
                <div class="fs-2 fw-bold"><?php echo e($stats['labs']); ?></div>
                <div class="small text-white-50">Laboratories</div>
              </div>
            </div>
            <div class="col-6">
              <div class="p-3 rounded-3 bg-white bg-opacity-10 text-center">
                <div class="fs-2 fw-bold"><?php echo e($stats['computers']); ?></div>
                <div class="small text-white-50">Computers</div>
              </div>
            </div>
            <div class="col-12">
              <div class="p-3 rounded-3 bg-white bg-opacity-10 text-center">
                <div class="fs-2 fw-bold"><?php echo e($stats['bookings']); ?></div>
                <div class="small text-white-50">Bookings recorded</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <h2 class="h4 fw-bold mb-1">How a booking works</h2>
    <p class="text-muted mb-4">Five steps from request to confirmation.</p>

    <div class="row g-2 mb-5">
      <?php foreach ($flow as $i => $step): ?>
        <div class="col-12 col-sm-6 col-lg">
          <div class="flow-step h-100">
            <span class="num"><?php echo $i + 1; ?></span>
            <span><?php echo e($step); ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <h2 class="h4 fw-bold mb-1">Features</h2>
    <p class="text-muted mb-4">What the system does for students and administrators.</p>

    <div class="row g-3">
      <?php foreach ($features as $feature): ?>
        <div class="col-md-6 col-lg-4">
          <div class="feature-card">
            <i class="bi <?php echo e($feature[0]); ?>"></i>
            <h3><?php echo e($feature[1]); ?></h3>
            <p><?php echo e($feature[2]); ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</main>

<footer class="app-footer">
  <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span>&copy; <?php echo date('Y'); ?> <?php echo e(APP_NAME); ?></span>
    <span class="text-muted"><?php echo e(APP_ORG); ?> &middot; Final Year Project</span>
  </div>
</footer>

<script src="<?php echo e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script src="<?php echo e(asset('js/app.js')); ?>"></script>
</body>
</html>
