<?php
/**
 * Computer Laboratory Booking System - booking history (student).
 *
 * Everything that has already happened or been closed off, newest first: past
 * sessions and anything rejected, cancelled or completed. Open requests live on
 * the dashboard and on "My bookings".
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_user();

$userId = current_user_id();

$history = db_all(
    'SELECT b.*, l.name AS lab_name, l.location AS lab_location
       FROM bookings b
       JOIN laboratories l ON l.id = b.laboratory_id
      WHERE b.user_id = ?
        AND (b.booking_date < CURDATE()
             OR b.status IN (\'rejected\', \'cancelled\', \'completed\'))
      ORDER BY b.booking_date DESC, b.start_time DESC',
    array($userId)
);

// Figures for the summary strip.
$totals = array('sessions' => 0, 'hours' => 0, 'completed' => 0, 'rejected' => 0, 'cancelled' => 0);
foreach ($history as $b) {
    if ($b['status'] === 'completed' || ($b['booking_date'] < booking_today() && $b['status'] === 'approved')) {
        $totals['sessions']++;
        $totals['hours'] += duration_minutes($b['start_time'], $b['end_time']);
    }
    if (isset($totals[$b['status']])) {
        $totals[$b['status']]++;
    }
}

layout_start(array('title' => 'History', 'active' => 'history'));
?>

<?php page_heading('Booking history', 'Sessions that have happened, or were closed off'); ?>

<?php render_flashes(); ?>

<div class="row g-3 mb-4">
    <?php stat_card('Sessions attended', $totals['sessions'], 'bi-calendar-check', 'primary'); ?>
    <?php stat_card('Time booked', format_duration($totals['hours']), 'bi-hourglass', 'info'); ?>
    <?php stat_card('Completed', $totals['completed'], 'bi-check-circle', 'success'); ?>
    <?php stat_card('Rejected', $totals['rejected'], 'bi-x-circle', 'danger'); ?>
    <?php stat_card('Cancelled', $totals['cancelled'], 'bi-slash-circle', 'secondary'); ?>
</div>

<?php if (empty($history)): ?>
    <?php echo empty_state(
        'bi-clock-history',
        'Nothing in your history yet',
        'Once a session has taken place, or a request has been closed off, it appears here.'
    ); ?>
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
                        <th>Length</th>
                        <th>Outcome</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $b): ?>
                        <tr>
                            <td class="font-monospace small"><?php echo e($b['booking_ref']); ?></td>
                            <td>
                                <?php echo e($b['lab_name']); ?>
                                <div class="small text-muted"><?php echo e($b['lab_location']); ?></div>
                            </td>
                            <td class="text-nowrap"><?php echo e(format_date($b['booking_date'])); ?></td>
                            <td class="text-nowrap small">
                                <?php echo e(format_range($b['start_time'], $b['end_time'])); ?>
                            </td>
                            <td class="small text-muted">
                                <?php echo e(format_duration(duration_minutes($b['start_time'], $b['end_time']))); ?>
                            </td>
                            <td>
                                <span class="badge <?php echo e(status_badge_class($b['status'])); ?>">
                                    <?php echo e(ucfirst($b['status'])); ?>
                                </span>
                                <?php if ($b['admin_note'] !== null && $b['admin_note'] !== ''): ?>
                                    <div class="small text-muted mt-1">
                                        <?php echo e($b['admin_note']); ?>
                                    </div>
                                <?php endif; ?>
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
