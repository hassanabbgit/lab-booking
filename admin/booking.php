<?php
/**
 * Computer Laboratory Booking System - one booking, in full (administrator).
 *
 * The counterpart to user/booking.php. A student may only open their own
 * booking; an administrator can open any, because deciding a request and
 * auditing history are both part of the job.
 *
 * Every status change on this page goes through booking_decide(), so the
 * lifecycle rules are the same whether the decision is made from the queue, the
 * all-bookings list or here.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$adminId = current_user_id();
$id = query_int('id', 0);

/* ------------------------------------------------------------------ decide */
if (is_post()) {
    csrf_guard();

    $targets = array(
        'approve'  => 'approved',
        'reject'   => 'rejected',
        'cancel'   => 'cancelled',
        'complete' => 'completed',
    );

    $action = post('decision', '');

    if (!isset($targets[$action])) {
        flash('danger', 'That decision is not available from this page.');
        redirect('admin/bookings.php');
    }

    $result = booking_decide($id, $targets[$action], $adminId, post('note', ''));

    if ($result['ok']) {
        flash('success', $result['message'] . ' The student has been notified.');
    } else {
        flash('danger', $result['error']);
    }

    redirect('admin/booking.php?id=' . $id);
}

$booking = $id > 0
    ? db_one(
        'SELECT b.*, l.name AS lab_name, l.location AS lab_location, l.status AS lab_status,
                l.capacity, l.computer_count,
                u.name AS user_name, u.email AS user_email, u.student_no,
                r.name AS reviewer_name
           FROM bookings b
           JOIN laboratories l ON l.id = b.laboratory_id
           JOIN users u ON u.id = b.user_id
           LEFT JOIN users r ON r.id = b.reviewed_by
          WHERE b.id = ?',
        array($id)
    )
    : null;

if ($booking === null) {
    http_response_code(404);
    layout_start(array('title' => 'Booking not found', 'active' => 'bookings'));

    echo empty_state(
        'bi-question-circle',
        'Booking not found',
        'That booking does not exist. It may have been an old reference.'
    );

    echo '<div class="text-center"><a class="btn btn-primary" href="'
        . e(url('admin/bookings.php')) . '">Back to all bookings</a></div>';

    layout_end();
    exit;
}

$status = $booking['status'];
$next = booking_next_statuses($status);
$terminal = booking_is_terminal($status);

$isPast = $booking['booking_date'] < booking_today();
$isFuture = $booking['booking_date'] > booking_today();

$canApprove = in_array('approved', $next, true) && $booking['lab_status'] === 'available' && !$isPast;
$canReject = in_array('rejected', $next, true);
$canCancel = in_array('cancelled', $next, true) && !$isPast;
$canComplete = in_array('completed', $next, true) && !$isFuture;

/* Other requests in this laboratory on this date, so the overlap picture is
   visible without leaving the page. */
$sameDay = db_all(
    'SELECT b.id, b.booking_ref, b.start_time, b.end_time, b.status, u.name AS user_name
       FROM bookings b
       JOIN users u ON u.id = b.user_id
      WHERE b.laboratory_id = ?
        AND b.booking_date = ?
        AND b.id <> ?
        AND b.status IN (' . booking_blocking_status_sql() . ')
      ORDER BY b.start_time',
    array((int) $booking['laboratory_id'], $booking['booking_date'], (int) $booking['id'])
);

$length = duration_minutes($booking['start_time'], $booking['end_time']);

layout_start(array('title' => $booking['booking_ref'], 'active' => 'bookings'));
?>

<?php
page_heading(
    $booking['booking_ref'],
    booking_status_label($status) . ' · ' . $booking['lab_name'] . ' · '
        . format_date($booking['booking_date']),
    '<a class="btn btn-outline-secondary" href="' . e(url('admin/bookings.php')) . '">All bookings</a>'
);

render_flashes();
?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-info-circle me-1"></i> Request
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Student</dt>
                    <dd class="col-sm-8">
                        <?php echo e($booking['user_name']); ?>
                        <div class="small text-muted">
                            <?php echo e($booking['student_no']); ?> · <?php echo e($booking['user_email']); ?>
                        </div>
                    </dd>

                    <dt class="col-sm-4">Laboratory</dt>
                    <dd class="col-sm-8">
                        <a href="<?php echo e(url('admin/laboratory.php?id=' . (int) $booking['laboratory_id'])); ?>">
                            <?php echo e($booking['lab_name']); ?>
                        </a>
                        <span class="badge <?php echo e(lab_status_badge_class($booking['lab_status'])); ?>">
                            <?php echo e(laboratory_status_label($booking['lab_status'])); ?>
                        </span>
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
                        <?php if ($terminal): ?>
                            <span class="text-muted small">This is a final state and is kept for the record.</span>
                        <?php else: ?>
                            <?php
                            $parts = array();
                            foreach ($next as $s) {
                                $parts[] = strtolower(booking_status_label($s));
                            }
                            ?>
                            <div class="small text-muted">Can still be: <?php echo e(implode(', ', $parts)); ?></div>
                        <?php endif; ?>
                    </dd>

                    <?php if ($booking['admin_note'] !== null && $booking['admin_note'] !== ''): ?>
                        <dt class="col-sm-4">Reason given</dt>
                        <dd class="col-sm-8"><?php echo nl2br(e($booking['admin_note'])); ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if (!$terminal): ?>
            <div class="card mb-4">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-pencil-square me-1"></i> Record a decision
                </div>
                <div class="card-body">
                    <?php if ($status === 'pending' && !$isPast && $booking['lab_status'] !== 'available'): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-cone-striped me-1"></i>
                            <?php echo e($booking['lab_name']); ?> is currently
                            <?php echo e(lcfirst(laboratory_status_label($booking['lab_status']))); ?>,
                            so this request cannot be approved until the room is back in service.
                        </div>
                    <?php endif; ?>

                    <?php if ($status === 'approved' && $isFuture): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-1"></i>
                            It can be marked completed once the session has taken place.
                        </div>
                    <?php endif; ?>

                    <?php if ($isPast && $status === 'pending'): ?>
                        <div class="alert alert-secondary">
                            <i class="bi bi-clock-history me-1"></i>
                            The date on this request has passed, so it can no longer be approved.
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <?php echo csrf_field(); ?>

                        <label class="form-label" for="note">
                            Reason, shown to the student
                        </label>
                        <input class="form-control mb-3" type="text" id="note" name="note"
                               maxlength="255"
                               placeholder="Required to reject. Optional elsewhere.">

                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($canApprove): ?>
                                <button class="btn btn-primary" type="submit" name="decision" value="approve">
                                    <i class="bi bi-check-lg me-1"></i> Approve
                                </button>
                            <?php endif; ?>

                            <?php if ($canReject): ?>
                                <button class="btn btn-outline-danger" type="submit" name="decision" value="reject"
                                        data-require-reason="Why are you rejecting this booking? This is shown to the student.">
                                    <i class="bi bi-x-lg me-1"></i> Reject
                                </button>
                            <?php endif; ?>

                            <?php if ($canComplete): ?>
                                <button class="btn btn-outline-info" type="submit" name="decision" value="complete">
                                    <i class="bi bi-calendar-check me-1"></i> Mark completed
                                </button>
                            <?php endif; ?>

                            <?php if ($canCancel): ?>
                                <button class="btn btn-outline-secondary" type="submit" name="decision" value="cancel"
                                        data-require-reason="Why is this booking being cancelled? This is shown to the student.">
                                    <i class="bi bi-slash-circle me-1"></i> Cancel
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($sameDay)): ?>
            <div class="card mb-4">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-calendar-week me-1"></i> Other bookings in this room that day
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Ref.</th>
                                <th>Time</th>
                                <th>Student</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sameDay as $other): ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <a href="<?php echo e(url('admin/booking.php?id=' . (int) $other['id'])); ?>">
                                            <?php echo e($other['booking_ref']); ?>
                                        </a>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php echo e(format_range($other['start_time'], $other['end_time'])); ?>
                                    </td>
                                    <td><?php echo e($other['user_name']); ?></td>
                                    <td>
                                        <span class="badge <?php echo e(status_badge_class($other['status'])); ?>">
                                            <?php echo e(booking_status_label($other['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-body text-muted small">
                    Only pending and approved bookings hold a laboratory, so rejected, cancelled and
                    completed sessions above are not occupying the room.
                </div>
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
                        <i class="bi bi-check-circle-fill text-success me-2"></i> Requested
                        <div class="small text-muted ms-4">
                            <?php echo e(format_date($booking['created_at'], true)); ?>
                            <span class="text-muted">(<?php echo e(time_ago($booking['created_at'])); ?>)</span>
                        </div>
                    </li>
                    <li class="mb-0">
                        <?php if ($booking['reviewed_at'] === null): ?>
                            <i class="bi bi-circle text-muted me-2"></i> Not yet reviewed
                        <?php else: ?>
                            <i class="bi bi-check-circle-fill text-success me-2"></i> Reviewed
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

        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-activity me-1"></i> Actions on this booking
            </div>
            <div class="card-body">
                <?php
                $trail = db_all(
                    "SELECT * FROM activity_logs
                      WHERE entity = 'booking' AND entity_id = ?
                      ORDER BY created_at DESC, id DESC
                      LIMIT 20",
                    array((int) $booking['id'])
                );
                ?>

                <?php if (empty($trail)): ?>
                    <p class="text-muted mb-0">Nothing has happened to this booking yet.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($trail as $entry): ?>
                            <li class="mb-2">
                                <span class="badge bg-secondary"><?php echo e($entry['action']); ?></span>
                                <div class="small"><?php echo e($entry['description']); ?></div>
                                <div class="small text-muted">
                                    <?php echo e(format_date($entry['created_at'], true)); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="btn btn-sm btn-outline-secondary mt-2"
                       href="<?php echo e(url('admin/activity.php?entity=booking&entity_id=' . (int) $booking['id'])); ?>">
                        Full audit trail
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/bookings.php')); ?>">
                <i class="bi bi-arrow-left me-1"></i> All bookings
            </a>
        </div>
    </div>
</div>

<?php layout_end(); ?>
