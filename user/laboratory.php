<?php
/**
 * Computer Laboratory Booking System - one laboratory, in full (student).
 *
 * Shows the specification, the sessions it can be booked in, and a day-by-day
 * availability strip for the next two weeks. Occupied periods are shown as
 * times only: other students' names and purposes are never revealed.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_user();

$userId = current_user_id();
$id = query_int('id', 0);

$lab = $id > 0
    ? db_one('SELECT * FROM laboratories WHERE id = ?', array($id))
    : null;

if ($lab === null) {
    http_response_code(404);
    layout_start(array('title' => 'Laboratory not found', 'active' => 'laboratories'));
    echo empty_state(
        'bi-question-circle',
        'Laboratory not found',
        'That laboratory does not exist.'
    );
    echo '<div class="text-center"><a class="btn btn-primary" href="'
        . e(url('user/laboratories.php')) . '">Back to laboratories</a></div>';
    layout_end();
    exit;
}

$slots = active_time_slots();
$bookable = $lab['status'] === 'available';

/* -------------------------------------------------- the student's own use */
$mine = db_all(
    'SELECT * FROM bookings
      WHERE user_id = ? AND laboratory_id = ? AND booking_date >= CURDATE()
      ORDER BY booking_date ASC, start_time ASC',
    array($userId, $id)
);

/* --------------------------------------------------- next fortnight's use */
// Only one query for the whole window rather than a loop per day.
$horizonEnd = date('Y-m-d', strtotime('+14 days'));
$taken = db_all(
    'SELECT booking_date, start_time, end_time, status
       FROM bookings
      WHERE laboratory_id = ?
        AND booking_date BETWEEN CURDATE() AND ?
        AND status IN (\'pending\', \'approved\')
      ORDER BY booking_date ASC, start_time ASC',
    array($id, $horizonEnd)
);

$byDate = array();
foreach ($taken as $row) {
    $byDate[$row['booking_date']][] = $row;
}

layout_start(array('title' => $lab['name'], 'active' => 'laboratories'));
?>

<?php
page_heading(
    $lab['name'],
    $lab['location'],
    $bookable
        ? '<a class="btn btn-primary" href="' . e(url('user/book.php?lab=' . (int) $lab['id'])) . '">'
            . '<i class="bi bi-calendar-plus me-1"></i> Book this laboratory</a>'
        : '<button class="btn btn-secondary" disabled><i class="bi bi-lock me-1"></i> Not bookable</button>'
);
?>

<?php render_flashes(); ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-pc-display me-1"></i> About this laboratory
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="badge <?php echo e(lab_status_badge_class($lab['status'])); ?>">
                        <?php echo e(laboratory_status_label($lab['status'])); ?>
                    </span>
                </div>

                <p><?php echo nl2br(e($lab['description'])); ?></p>

                <dl class="row mb-0 border-top pt-3">
                    <dt class="col-6">Location</dt>
                    <dd class="col-6"><?php echo e($lab['location']); ?></dd>

                    <dt class="col-6">Seats</dt>
                    <dd class="col-6"><?php echo (int) $lab['capacity']; ?></dd>

                    <dt class="col-6">Computers</dt>
                    <dd class="col-6"><?php echo (int) $lab['computer_count']; ?></dd>
                </dl>

                <?php if (!$bookable): ?>
                    <div class="alert alert-warning small mt-3 mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        This laboratory is
                        <strong><?php echo e(laboratory_status_label($lab['status'])); ?></strong>
                        and cannot be booked
                        at the moment.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-clock me-1"></i> Sessions
            </div>
            <div class="card-body">
                <?php if (empty($slots)): ?>
                    <p class="text-muted small mb-0">No sessions are open for booking.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($slots as $slot): ?>
                            <li class="list-group-item px-0 d-flex justify-content-between">
                                <span><?php echo e($slot['label']); ?></span>
                                <span class="text-muted small">
                                    <?php echo e(format_range($slot['start_time'], $slot['end_time'])); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <?php if (!empty($mine)): ?>
            <div class="card mb-4">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-calendar-check me-1"></i> Your upcoming sessions here
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mine as $b): ?>
                                <tr>
                                    <td class="text-nowrap"><?php echo e(format_date($b['booking_date'])); ?></td>
                                    <td class="text-nowrap small">
                                        <?php echo e(format_range($b['start_time'], $b['end_time'])); ?>
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

        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-calendar-week me-1"></i> Next 14 days
            </div>
            <div class="card-body">
                <?php if (empty($slots)): ?>
                    <p class="text-muted small mb-0">
                        Sessions have to be switched on before availability can be shown.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <?php foreach ($slots as $slot): ?>
                                        <th class="small"><?php echo e($slot['label']); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                for ($i = 0; $i < 14; $i++) {
                                    $day = date('Y-m-d', strtotime('+' . $i . ' days'));
                                    $dayTaken = isset($byDate[$day]) ? $byDate[$day] : array();
                                ?>
                                    <tr>
                                        <td class="text-nowrap small">
                                            <?php echo e(format_date($day)); ?>
                                            <?php if ($i === 0): ?>
                                                <span class="badge bg-light text-dark">today</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php foreach ($slots as $slot):
                                            $sStart = time_to_minutes($slot['start_time']);
                                            $sEnd = time_to_minutes($slot['end_time']);
                                            $free = $sEnd - $sStart;

                                            foreach ($dayTaken as $row) {
                                                $bStart = time_to_minutes($row['start_time']);
                                                $bEnd = time_to_minutes($row['end_time']);
                                                // Only the overlapping part is lost.
                                                $overlap = min($sEnd, $bEnd) - max($sStart, $bStart);
                                                if ($overlap > 0) {
                                                    $free -= $overlap;
                                                }
                                            }
                                        ?>
                                            <td class="small">
                                                <?php if ($day > booking_last_date()): ?>
                                                    <span class="text-muted">&mdash;</span>
                                                <?php elseif ($free <= 0): ?>
                                                    <span class="badge bg-secondary">Full</span>
                                                <?php elseif ($free < $sEnd - $sStart): ?>
                                                    <span class="badge bg-warning text-dark" title="Partly booked">
                                                        <?php echo (int) round($free / 60); ?>h free
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Free</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mt-3 mb-0">
                        Taken periods show only their times. Other students' names and
                        purposes stay private.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php layout_end(); ?>
