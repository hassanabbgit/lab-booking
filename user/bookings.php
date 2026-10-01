<?php
/**
 * Computer Laboratory Booking System - the signed-in student's bookings.
 *
 * Scoped to the current user in the WHERE clause on every query, so there is no
 * way to reach another student's booking by changing a parameter. Status is a
 * filter, which the dashboard links to directly.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_user();

$userId = current_user_id();

$allStatuses = booking_statuses();
$status = query('status', '');

if ($status !== '' && !in_array($status, $allStatuses, true)) {
    $status = '';
}

$where = 'WHERE b.user_id = ?';
$params = array($userId);

if ($status !== '') {
    $where .= ' AND b.status = ?';
    $params[] = $status;
}

$bookings = db_all(
    'SELECT b.*, l.name AS lab_name, l.location AS lab_location
       FROM bookings b
       JOIN laboratories l ON l.id = b.laboratory_id
       ' . $where . '
      ORDER BY b.booking_date DESC, b.start_time DESC',
    $params
);

// Counts for the filter chips, always across the whole set. Every status is
// seeded to zero first: a status the student has never had would otherwise be
// missing here and read as an undefined index by the chip loop below. The
// wording comes from booking_status_label() so these chips and the
// administrator's queue cannot drift apart.
$labels = array('all' => 'All');

foreach (booking_statuses() as $status) {
    $labels[$status] = booking_status_label($status);
}

$counts = array();
foreach ($labels as $key => $label) {
    $counts[$key] = 0;
}

foreach (db_all(
    'SELECT status, COUNT(*) AS total FROM bookings WHERE user_id = ? GROUP BY status',
    array($userId)
) as $row) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int) $row['total'];
    }
}
$counts['all'] = array_sum($counts);

layout_start(array('title' => 'My Bookings', 'active' => 'bookings'));
?>

<?php page_heading('My bookings', 'Every session you have requested', '<a class="btn btn-primary" href="'
    . e(url('user/book.php')) . '"><i class="bi bi-plus-circle me-1"></i> New booking</a>'); ?>

<?php render_flashes(); ?>

<ul class="nav nav-pills mb-3 flex-wrap">
    <?php foreach ($labels as $key => $label): ?>
        <?php $isAll = ($key === 'all'); ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $status === $key || ($isAll && $status === '') ? 'active' : ''; ?>"
               href="<?php echo e(url('user/bookings.php' . ($isAll ? '' : '?status=' . $key))); ?>">
                <?php echo e($label); ?>
                <span class="badge bg-light text-dark ms-1"><?php echo (int) $counts[$key]; ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (empty($bookings)): ?>
    <?php echo empty_state(
        'bi-calendar-x',
        $status === '' ? 'You have not made any bookings yet' : 'Nothing here',
        $status === ''
            ? 'Pick a laboratory and a time, and the request goes to an administrator for approval.'
            : 'You have no bookings with that status.'
    ); ?>
    <?php if ($status === ''): ?>
        <div class="text-center">
            <a class="btn btn-primary" href="<?php echo e(url('user/book.php')); ?>">
                <i class="bi bi-calendar-plus me-1"></i> Make your first booking
            </a>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reference</th>
                        <th>Laboratory</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td class="font-monospace small"><?php echo e($b['booking_ref']); ?></td>
                            <td>
                                <?php echo e($b['lab_name']); ?>
                                <div class="small text-muted"><?php echo e($b['lab_location']); ?></div>
                            </td>
                            <td class="text-nowrap"><?php echo e(format_date($b['booking_date'])); ?></td>
                            <td class="text-nowrap small">
                                <?php echo e(format_range($b['start_time'], $b['end_time'])); ?>
                                <div class="text-muted">
                                    <?php echo e(format_duration(duration_minutes($b['start_time'], $b['end_time']))); ?>
                                </div>
                            </td>
                            <td class="small text-muted">
                                <?php echo e($b['purpose']); ?>
                                <?php if ($b['status'] === 'rejected' && $b['admin_note'] !== null): ?>
                                    <div class="text-danger">
                                        <i class="bi bi-chat-left-text me-1"></i><?php echo e($b['admin_note']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo e(status_badge_class($b['status'])); ?>">
                                    <?php echo e(ucfirst($b['status'])); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="<?php echo e(url('user/booking.php?id=' . (int) $b['id'])); ?>">
                                    Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php layout_end(); ?>
