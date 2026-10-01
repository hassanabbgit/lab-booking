<?php
/**
 * Computer Laboratory Booking System - one booking, in full (student).
 *
 * The lookup is "WHERE id = ? AND user_id = ?", so another student's booking
 * returns the same not-found page as a booking that does not exist. That avoids
 * confirming that a reference belongs to somebody else.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_user();

$userId = current_user_id();
$id = query_int('id', 0);

$booking = $id > 0
    ? db_one(
        'SELECT b.*, l.name AS lab_name, l.location AS lab_location,
                l.capacity, l.computer_count,
                r.name AS reviewer_name
           FROM bookings b
           JOIN laboratories l ON l.id = b.laboratory_id
           LEFT JOIN users r ON r.id = b.reviewed_by
          WHERE b.id = ? AND b.user_id = ?',
        array($id, $userId)
    )
    : null;

if ($booking === null) {
    http_response_code(404);
    layout_start(array('title' => 'Booking not found', 'active' => 'bookings'));
    echo empty_state(
        'bi-question-circle',
        'Booking not found',
        'That booking does not exist, or it belongs to another student.'
    );
    echo '<div class="text-center"><a class="btn btn-primary" href="'
        . e(url('user/bookings.php')) . '">Back to my bookings</a></div>';
    layout_end();
    exit;
}

/* ------------------------------------------------------------------ cancel */
if (is_post() && post('action') === 'cancel') {
    csrf_guard();

    // booking_cancel_by_owner() re-reads the row under a lock and applies the
    // same lifecycle rules the administrator's decisions use, so a student
    // cannot cancel a booking that has already been rejected or completed.
    $result = booking_cancel_by_owner($id, $userId);

    if ($result['ok']) {
        flash('success', 'Booking ' . $booking['booking_ref'] . ' was cancelled.');
    } else {
        flash('danger', $result['error']);
    }

    redirect('user/booking.php?id=' . $id);
}

$status = $booking['status'];
$canCancel = in_array('cancelled', booking_next_statuses($status), true)
    && $booking['booking_date'] >= booking_today();

$length = duration_minutes($booking['start_time'], $booking['end_time']);

layout_start(array('title' => $booking['booking_ref'], 'active' => 'bookings'));
?>

<?php page_heading($booking['booking_ref'], booking_status_label($status) . ' request'); ?>

<?php render_flashes(); ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-info-circle me-1"></i> Request
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Laboratory</dt>
                    <dd class="col-sm-8">
                        <a href="<?php echo e(url('user/laboratory.php?id=' . (int) $booking['laboratory_id'])); ?>">
                            <?php echo e($booking['lab_name']); ?>
                        </a>
                        <div class="small text-muted"><?php echo e($booking['lab_location']); ?></div>
                    </dd>

                    <dt class="col-sm-4">Date</dt>
                    <dd class="col-sm-8"><?php echo e(format_date($booking['booking_date'])); ?></dd>

                    <dt class="col-sm-4">Time</dt>
                    <dd class="col-sm-8">
                        <?php echo e(format_range($booking['start_time'], $booking['end_time'])); ?>
                        <span class="text-muted">(<?php echo e(format_duration($length)); ?>)</span>
                    </dd>

                    <dt class="col-sm-4">Purpose</dt>
                    <dd class="col-sm-8"><?php echo nl2br(e($booking['purpose'])); ?></dd>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">
                        <span class="badge <?php echo e(status_badge_class($status)); ?>">
                            <?php echo e(booking_status_label($status)); ?>
                        </span>
                    </dd>
                </dl>
            </div>
        </div>

        <?php if ($status === 'rejected' || $status === 'cancelled'): ?>
            <div class="alert alert-<?php echo $status === 'rejected' ? 'danger' : 'secondary'; ?>">
                <i class="bi bi-<?php echo $status === 'rejected' ? 'x-octagon' : 'slash-circle'; ?> me-1"></i>
                <strong>
                    <?php echo $status === 'rejected' ? 'This request was rejected.' : 'This request was cancelled.'; ?>
                </strong>
                <?php if ($booking['admin_note'] !== null && $booking['admin_note'] !== ''): ?>
                    <div class="mt-1"><?php echo nl2br(e($booking['admin_note'])); ?></div>
                <?php endif; ?>
            </div>
        <?php elseif ($status === 'pending'): ?>
            <div class="alert alert-warning">
                <i class="bi bi-hourglass-split me-1"></i>
                Waiting for an administrator to review this request.
            </div>
        <?php elseif ($status === 'approved'): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-1"></i>
                Approved. Please arrive a few minutes early.
                <?php if ($booking['admin_note'] !== null && $booking['admin_note'] !== ''): ?>
                    <div class="mt-1">
                        <strong>Note from the administrator:</strong>
                        <?php echo nl2br(e($booking['admin_note'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($status === 'completed'): ?>
            <div class="alert alert-info">
                <i class="bi bi-calendar-check me-1"></i>
                This session has been marked as completed.
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-clock-history me-1"></i> Progress
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        Requested
                        <div class="small text-muted ms-4">
                            <?php echo e(format_date($booking['created_at'], true)); ?>
                        </div>
                    </li>
                    <li class="mb-0">
                        <?php if ($booking['reviewed_at'] === null): ?>
                            <i class="bi bi-circle text-muted me-2"></i>
                            Not yet reviewed
                        <?php else: ?>
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            Reviewed
                            <div class="small text-muted ms-4">
                                <?php echo e(format_date($booking['reviewed_at'], true)); ?>
                                <?php if ($booking['reviewer_name'] !== null): ?>
                                    by <?php echo e($booking['reviewer_name']); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>

        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo e(url('user/bookings.php')); ?>">
                <i class="bi bi-arrow-left me-1"></i> All my bookings
            </a>

            <?php if ($canCancel): ?>
                <form method="post" action="" onsubmit="return confirm('Cancel this booking? The time becomes free for others.');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-x-circle me-1"></i> Cancel this booking
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php layout_end(); ?>
