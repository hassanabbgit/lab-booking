<?php
/**
 * Computer Laboratory Booking System - student dashboard.
 *
 * Read-only overview for Phase 1. The booking form is wired up once the
 * Booking Management module is implemented.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_user();

$userId = current_user_id();

/* -------------------------------------------------------------------------
 | The student's own statistics
 | ---------------------------------------------------------------------- */

$counts = array();
foreach (db_all('SELECT status, COUNT(*) AS total FROM bookings WHERE user_id = ? GROUP BY status', array($userId)) as $row) {
    $counts[$row['status']] = (int) $row['total'];
}
$myPending  = isset($counts['pending'])  ? $counts['pending']  : 0;
$myApproved = isset($counts['approved']) ? $counts['approved'] : 0;
$myTotal    = array_sum($counts);
$myRejected = isset($counts['rejected']) ? $counts['rejected'] : 0;

/* -------------------------------------------------------------------------
 | Upcoming bookings
 | ---------------------------------------------------------------------- */

$upcoming = db_all(
    'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.status, b.purpose,
            l.name AS lab_name, l.location
     FROM bookings b
     JOIN laboratories l ON l.id = b.laboratory_id
     WHERE b.user_id = ? AND b.booking_date >= CURDATE() AND b.status IN (\'pending\', \'approved\')
     ORDER BY b.booking_date ASC, b.start_time ASC
     LIMIT 5',
    array($userId)
);

/* -------------------------------------------------------------------------
 | Bookable laboratories
 | ---------------------------------------------------------------------- */

$labs = db_all(
    "SELECT id, name, location, capacity, computer_count, description
     FROM laboratories
     WHERE status = 'available'
     ORDER BY name ASC"
);

/* -------------------------------------------------------------------------
 | Recent decisions
 | ---------------------------------------------------------------------- */

$recent = db_all(
    'SELECT b.booking_ref, b.booking_date, b.status, b.admin_note, b.reviewed_at, l.name AS lab_name
     FROM bookings b
     JOIN laboratories l ON l.id = b.laboratory_id
     WHERE b.user_id = ? AND b.status IN (\'approved\', \'rejected\')
     ORDER BY b.reviewed_at DESC, b.id DESC
     LIMIT 4',
    array($userId)
);

layout_start(array('title' => 'My dashboard', 'active' => 'dashboard'));
?>

<?php page_heading(
    'Hello, ' . current_user()['name'],
    'Track your laboratory requests and see what is free',
    '<a class="btn btn-primary" href="' . e(url('user/book.php')) . '">'
    . '<i class="bi bi-plus-lg me-1"></i> New booking</a>'
); ?>

<?php render_flashes(); ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <?php stat_card('My bookings', $myTotal, 'bi-calendar-check', 'primary', url('user/bookings.php')); ?>
    </div>
    <div class="col-6 col-xl-3">
        <?php stat_card('Awaiting approval', $myPending, 'bi-hourglass-split', 'warning', url('user/bookings.php?status=pending')); ?>
    </div>
    <div class="col-6 col-xl-3">
        <?php stat_card('Approved', $myApproved, 'bi-check-circle', 'success', url('user/bookings.php?status=approved')); ?>
    </div>
    <div class="col-6 col-xl-3">
        <?php stat_card('Available labs', count($labs), 'bi-pc-display', 'info', url('user/laboratories.php')); ?>
    </div>
</div>

<div class="row g-3">

    <!-- Upcoming -->
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-event me-2 text-primary"></i>My upcoming bookings</span>
                <a class="btn btn-sm btn-outline-primary" href="<?php echo e(url('user/bookings.php')); ?>">View all</a>
            </div>

            <?php if (empty($upcoming)): ?>
                <?php empty_state(
                    'bi-calendar-plus',
                    'No upcoming bookings',
                    'Request a laboratory and time slot to get started.'
                ); ?>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Laboratory</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($upcoming as $row): ?>
                        <tr>
                            <td class="ref"><?php echo e($row['booking_ref']); ?></td>
                            <td>
                                <?php echo e($row['lab_name']); ?>
                                <br><small class="text-muted"><?php echo e($row['location']); ?></small>
                            </td>
                            <td class="text-nowrap"><?php echo e(format_date($row['booking_date'])); ?></td>
                            <td class="text-nowrap small"><?php echo e(format_range($row['start_time'], $row['end_time'])); ?></td>
                            <td>
                                <span class="badge <?php echo e(status_badge_class($row['status'])); ?>">
                                    <?php echo e(ucfirst($row['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Available laboratories -->
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-pc-display me-2 text-success"></i>Available laboratories</span>
                <a class="btn btn-sm btn-outline-primary" href="<?php echo e(url('user/laboratories.php')); ?>">Details</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($labs)): ?>
                    <?php empty_state('bi-pc-display', 'No laboratories available', 'Check back later.'); ?>
                <?php else: ?>
                    <?php foreach ($labs as $lab): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo e($lab['name']); ?></strong>
                                <br><small class="text-muted"><?php echo e($lab['location']); ?></small>
                            </div>
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-people me-1"></i><?php echo e($lab['capacity']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent decisions -->
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bell me-2 text-warning"></i>Recent decisions</div>
            <div class="card-body">
                <?php if (empty($recent)): ?>
                    <?php empty_state('bi-hourglass', 'No decisions yet', 'Administrator decisions will appear here.'); ?>
                <?php else: ?>
                    <ul class="timeline">
                    <?php foreach ($recent as $row): ?>
                        <li>
                            <div class="t-when"><?php echo e(format_date($row['reviewed_at'], true)); ?></div>
                            <div class="t-what">
                                <span class="badge <?php echo e(status_badge_class($row['status'])); ?> me-1">
                                    <?php echo e(ucfirst($row['status'])); ?>
                                </span>
                                <span class="ref"><?php echo e($row['booking_ref']); ?></span>
                                &mdash; <?php echo e($row['lab_name']); ?>
                                <?php if (!empty($row['admin_note'])): ?>
                                    <br><small class="text-muted">Note: <?php echo e($row['admin_note']); ?></small>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Next step -->
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-list-check me-2 text-primary"></i>How to book</div>
            <div class="card-body">
                <ul class="timeline">
                    <li><div class="t-what">Choose an <strong>available laboratory</strong>.</div></li>
                    <li><div class="t-what">Pick a <strong>date and time slot</strong> that suits you.</div></li>
                    <li><div class="t-what">State the <strong>purpose</strong> of the session.</div></li>
                    <li><div class="t-what">Submit and <strong>wait for approval</strong>.</div></li>
                    <li><div class="t-what">Check the <strong>status</strong> on your dashboard.</div></li>
                </ul>
                <?php if ($myRejected > 0): ?>
                    <div class="alert alert-light border small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        <?php echo e($myRejected); ?> of your requests were declined.
                        See the reason on <a href="<?php echo e(url('user/history.php')); ?>">your history</a>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php layout_end(); ?>
