<?php
/**
 * Computer Laboratory Booking System - administrator dashboard.
 *
 * Read-only overview for Phase 1. The approval actions shown here are wired up
 * once the Booking Management module is implemented.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

/* -------------------------------------------------------------------------
 | Headline statistics
 | ---------------------------------------------------------------------- */

$totalLabs      = (int) db_value('SELECT COUNT(*) FROM laboratories');
$availableLabs  = (int) db_value("SELECT COUNT(*) FROM laboratories WHERE status = 'available'");
$totalUsers     = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'user'");
$activeUsers    = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'active'");
$totalBookings  = (int) db_value('SELECT COUNT(*) FROM bookings');
$pendingCount   = (int) db_value("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
$approvedCount  = (int) db_value("SELECT COUNT(*) FROM bookings WHERE status = 'approved'");
$totalComputers = (int) db_value('SELECT COALESCE(SUM(computer_count), 0) FROM laboratories');

/* -------------------------------------------------------------------------
 | Requests awaiting a decision
 | ---------------------------------------------------------------------- */

$pendingBookings = db_all(
    'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.purpose,
            b.created_at, u.name AS user_name, u.student_no, l.name AS lab_name, l.location
     FROM bookings b
     JOIN users u       ON u.id = b.user_id
     JOIN laboratories l ON l.id = b.laboratory_id
     WHERE b.status = \'pending\'
     ORDER BY b.booking_date ASC, b.start_time ASC
     LIMIT 6'
);

/* -------------------------------------------------------------------------
 | Laboratory usage
 | ---------------------------------------------------------------------- */

$usage = db_all(
    'SELECT l.id, l.name, l.status, l.computer_count,
            COUNT(b.id) AS total_bookings,
            COALESCE(SUM(CASE WHEN b.status = \'approved\' THEN 1 ELSE 0 END), 0) AS approved_bookings
     FROM laboratories l
     LEFT JOIN bookings b ON b.laboratory_id = l.id
     GROUP BY l.id, l.name, l.status, l.computer_count
     ORDER BY total_bookings DESC, l.name ASC'
);

$maxUsage = 0;
foreach ($usage as $row) {
    if ((int) $row['total_bookings'] > $maxUsage) {
        $maxUsage = (int) $row['total_bookings'];
    }
}

/* -------------------------------------------------------------------------
 | Recent activity
 | ---------------------------------------------------------------------- */

$activity = db_all(
    'SELECT a.action, a.description, a.created_at, u.name AS user_name
     FROM activity_logs a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC, a.id DESC
     LIMIT 6'
);

/* -------------------------------------------------------------------------
 | Status breakdown for the reports module
 | ---------------------------------------------------------------------- */

$byStatus = db_all(
    'SELECT status, COUNT(*) AS total FROM bookings GROUP BY status'
);

layout_start(array('title' => 'Dashboard', 'active' => 'dashboard'));
?>

<?php page_heading(
    'Welcome back, ' . current_user()['name'],
    'Overview of laboratory bookings as at ' . format_date(date('Y-m-d H:i:s'), true),
    '<a class="btn btn-primary" href="' . e(url('admin/laboratories.php')) . '">'
    . '<i class="bi bi-plus-lg me-1"></i> Add laboratory</a>'
); ?>

<?php render_flashes(); ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <?php stat_card('Laboratories', $totalLabs, 'bi-pc-display', 'primary', url('admin/laboratories.php')); ?>
    </div>
    <div class="col-6 col-xl-3">
        <?php stat_card('Active users', $activeUsers, 'bi-people', 'info', url('admin/users.php')); ?>
    </div>
    <div class="col-6 col-xl-3">
        <?php stat_card('Pending approvals', $pendingCount, 'bi-inbox', 'warning', url('admin/approvals.php')); ?>
    </div>
    <div class="col-6 col-xl-3">
        <?php stat_card('Approved bookings', $approvedCount, 'bi-check-circle', 'success', url('admin/bookings.php')); ?>
    </div>
</div>

<div class="row g-3">

    <!-- Awaiting approval -->
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-inbox me-2 text-warning"></i>Awaiting approval</span>
                <a class="btn btn-sm btn-outline-primary" href="<?php echo e(url('admin/approvals.php')); ?>">View all</a>
            </div>

            <?php if (empty($pendingBookings)): ?>
                <?php empty_state('bi-check2-all', 'Nothing to approve', 'Every booking request has been dealt with.'); ?>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Student</th>
                            <th>Laboratory</th>
                            <th>When</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pendingBookings as $row): ?>
                        <tr>
                            <td class="ref"><?php echo e($row['booking_ref']); ?></td>
                            <td>
                                <?php echo e($row['user_name']); ?>
                                <?php if (!empty($row['student_no'])): ?>
                                    <br><small class="text-muted"><?php echo e($row['student_no']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo e($row['lab_name']); ?>
                                <br><small class="text-muted"><?php echo e($row['location']); ?></small>
                            </td>
                            <td class="text-nowrap">
                                <?php echo e(format_date($row['booking_date'])); ?>
                                <br><small class="text-muted"><?php echo e(format_range($row['start_time'], $row['end_time'])); ?></small>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-primary" href="<?php echo e(url('admin/approvals.php')); ?>">Review</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Booking status breakdown -->
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart me-2 text-primary"></i>Bookings by status</div>
            <div class="card-body">
                <?php if (empty($byStatus)): ?>
                    <?php empty_state('bi-clipboard-data', 'No bookings yet', 'Statistics appear once students start booking.'); ?>
                <?php else: ?>
                    <?php foreach ($byStatus as $row):
                        $pct = $totalBookings > 0 ? round(($row['total'] / $totalBookings) * 100) : 0; ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge <?php echo e(status_badge_class($row['status'])); ?>">
                                    <?php echo e(ucfirst($row['status'])); ?>
                                </span>
                                <span class="small text-muted"><?php echo e($row['total']); ?> booking<?php echo $row['total'] == 1 ? '' : 's'; ?></span>
                            </div>
                            <div class="progress" style="height:6px">
                                <div class="progress-bar <?php echo $row['status'] === 'pending' ? 'bg-warning' : (strpos(status_badge_class($row['status']), 'success') !== false ? 'bg-success' : 'bg-secondary'); ?>"
                                     role="progressbar" style="width: <?php echo e($pct); ?>%"
                                     aria-valuenow="<?php echo e($pct); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <hr class="my-3">
                <div class="d-flex justify-content-between small">
                    <span class="text-muted">Total bookings</span>
                    <strong><?php echo e($totalBookings); ?></strong>
                </div>
                <div class="d-flex justify-content-between small mt-1">
                    <span class="text-muted">Computers managed</span>
                    <strong><?php echo e($totalComputers); ?></strong>
                </div>
                <div class="d-flex justify-content-between small mt-1">
                    <span class="text-muted">Laboratories available</span>
                    <strong><?php echo $availableLabs . ' of ' . $totalLabs; ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Laboratory usage -->
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart-line me-2 text-success"></i>Laboratory usage</div>
            <div class="card-body">
                <?php foreach ($usage as $row):
                    $total = (int) $row['total_bookings'];
                    $pct   = $maxUsage > 0 ? (int) round(($total / $maxUsage) * 100) : 0; ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>
                                <i class="bi bi-pc-display me-1 text-muted"></i><?php echo e($row['name']); ?>
                                <span class="badge <?php echo e(lab_status_badge_class($row['status'])); ?> ms-1">
                                    <?php echo e(ucfirst($row['status'])); ?>
                                </span>
                            </span>
                            <span class="small text-muted"><?php echo e($total); ?> total</span>
                        </div>
                        <div class="progress" style="height:6px">
                            <div class="progress-bar bg-primary" role="progressbar"
                                 style="width: <?php echo e($pct); ?>%"
                                 aria-valuenow="<?php echo e($pct); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-activity me-2 text-info"></i>Recent activity</div>
            <div class="card-body">
                <?php if (empty($activity)): ?>
                    <?php empty_state('bi-clock-history', 'No activity recorded', 'Sign-ins and changes will be listed here.'); ?>
                <?php else: ?>
                    <ul class="timeline">
                    <?php foreach ($activity as $row): ?>
                        <li>
                            <div class="t-when"><?php echo e(format_date($row['created_at'], true)); ?></div>
                            <div class="t-what">
                                <?php if (!empty($row['user_name'])): ?>
                                    <strong><?php echo e($row['user_name']); ?></strong>
                                <?php endif; ?>
                                <?php echo e($row['description'] !== null && $row['description'] !== '' ? $row['description'] : $row['action']); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php layout_end(); ?>
