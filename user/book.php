<?php
/**
 * Computer Laboratory Booking System - new booking request (student).
 *
 * The form follows the same order as the specification:
 *
 *     laboratory -> date -> start time -> end time -> purpose -> submit
 *
 * Every step is rendered by the server from query parameters, so the page works
 * with JavaScript switched off, and the same validation in
 * includes/booking_rules.php runs again on submission. The browser is never the
 * only thing standing between a student and an overlapping booking.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_user();

$userId = current_user_id();
$errors = array();

$labs = db_all("SELECT * FROM laboratories WHERE status = 'available' ORDER BY name ASC");

// Pre-selection: a link from the laboratory list passes ?lab=, and the
// student's own choices come back from the session after a failed submit.
$labId = query_int('lab', (int) old('laboratory_id', 0));
$date = query('date', old('booking_date', ''));

if ($date === '') {
    $date = booking_today();
}

$slots = active_time_slots();

/* ------------------------------------------------------------------ submit */
if (is_post()) {
    csrf_guard();

    $labId = (int) post('laboratory_id', '0');
    $date = post('booking_date');
    $start = post('start_time');
    $end = post('end_time');
    $purpose = post('purpose');

    $input = array(
        'laboratory_id' => $labId,
        'booking_date'  => $date,
        'start_time'    => $start,
        'end_time'      => $end,
        'purpose'       => $purpose,
    );

    $errors = booking_validate($input);

    if (empty($errors)) {
        $result = booking_create($userId, $input);

        if ($result['ok']) {
            log_activity('create', 'booking', $result['id'], 'Requested ' . $result['ref']);

            clear_old();
            // render_flashes() escapes on output, so the message is stored raw.
            flash('success', 'Request ' . $result['ref'] . ' was sent for approval. '
                . 'You will be notified when an administrator responds.');
            redirect('user/booking.php?id=' . (int) $result['id']);
        }

        // The period was taken between rendering the form and submitting it.
        $errors['start_time'] = $result['error'];

        keep_old($input);
    } else {
        keep_old($input);
    }
}

/* ------------------------------------------------------- what is on offer */
$selectedLab = null;
foreach ($labs as $candidate) {
    if ((int) $candidate['id'] === $labId) {
        $selectedLab = $candidate;
        break;
    }
}

$dayValid = booking_date_is_allowed($date);
$windows = ($selectedLab !== null && $dayValid)
    ? booking_free_windows($labId, $date)
    : array();
$dayBookings = ($selectedLab !== null && $dayValid)
    ? bookings_on($labId, $date)
    : array();

/**
 * Every BOOKING_STEP_MIN boundary inside the active sessions, for the time
 * pickers. The server still validates whatever comes back.
 *
 * @return array
 */
function time_grid()
{
    $grid = array();
    foreach (active_time_slots() as $slot) {
        $start = time_to_minutes($slot['start_time']);
        $end = time_to_minutes($slot['end_time']);
        if ($start === null || $end === null) {
            continue;
        }
        for ($m = $start; $m <= $end; $m += BOOKING_STEP_MIN) {
            $grid[] = array('value' => $m, 'label' => format_time(minutes_to_time($m)));
        }
    }
    return $grid;
}

$grid = time_grid();

layout_start(array('title' => 'New Booking', 'active' => 'new-booking'));
?>

<?php page_heading('New booking request', 'Laboratory, date, time and purpose'); ?>

<?php render_flashes(); ?>

<?php if (empty($labs)): ?>
    <?php echo empty_state(
        'bi-pc-display',
        'No laboratories are open for booking',
        'An administrator has to mark a laboratory as available before it can be booked.'
    ); ?>
<?php else: ?>

    <?php render_errors($errors); ?>

    <form method="post" action="" novalidate>
        <?php echo csrf_field(); ?>

        <div class="row g-4">
            <!-- ------------------------------------------------ the steps -->
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">

                        <!-- 1. laboratory -->
                        <div class="mb-4">
                            <label class="form-label fw-bold" for="laboratory_id">
                                <span class="badge bg-primary me-2">1</span>Laboratory
                            </label>
                            <select class="form-select" id="laboratory_id" name="laboratory_id"
                                    data-reload="1" required>
                                <option value="">Choose a laboratory...</option>
                                <?php foreach ($labs as $lab): ?>
                                    <option value="<?php echo (int) $lab['id']; ?>"
                                        <?php echo $labId === (int) $lab['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($lab['name']); ?> &mdash; <?php echo e($lab['location']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php echo field_error($errors, 'laboratory_id'); ?>
                        </div>

                        <!-- 2. date -->
                        <div class="mb-4">
                            <label class="form-label fw-bold" for="booking_date">
                                <span class="badge bg-primary me-2">2</span>Date
                            </label>
                            <input type="date" class="form-control" id="booking_date" name="booking_date"
                                   value="<?php echo e($date); ?>"
                                   min="<?php echo e(booking_today()); ?>"
                                   max="<?php echo e(booking_last_date()); ?>"
                                   data-reload="1" required>
                            <div class="form-text">
                                Bookings can be made up to
                                <?php echo e(format_date(booking_last_date())); ?>.
                            </div>
                            <?php echo field_error($errors, 'booking_date'); ?>
                        </div>

                        <!-- 3 + 4. times -->
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="form-label fw-bold" for="start_time">
                                    <span class="badge bg-primary me-2">3</span>Start time
                                </label>
                                <select class="form-select" id="start_time" name="start_time"
                                        data-end-filter="1" required <?php echo $selectedLab === null ? 'disabled' : ''; ?>>
                                    <option value="">Choose...</option>
                                    <?php foreach ($grid as $t): ?>
                                        <option value="<?php echo e($t['label']); ?>"
                                            data-minutes="<?php echo (int) $t['value']; ?>"
                                            <?php echo old('start_time') === $t['label'] ? 'selected' : ''; ?>>
                                            <?php echo e($t['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php echo field_error($errors, 'start_time'); ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-bold" for="end_time">
                                    <span class="badge bg-primary me-2">4</span>End time
                                </label>
                                <select class="form-select" id="end_time" name="end_time" required
                                        <?php echo $selectedLab === null ? 'disabled' : ''; ?>>
                                    <option value="">Choose...</option>
                                    <?php foreach ($grid as $t): ?>
                                        <option value="<?php echo e($t['label']); ?>"
                                            data-minutes="<?php echo (int) $t['value']; ?>"
                                            <?php echo old('end_time') === $t['label'] ? 'selected' : ''; ?>>
                                            <?php echo e($t['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php echo field_error($errors, 'end_time'); ?>
                            </div>
                        </div>

                        <div class="alert alert-light border small mb-4">
                            <i class="bi bi-info-circle me-1"></i>
                            Sessions run <?php echo e(format_range('08:00:00', '20:00:00')); ?>
                            and must sit inside one of them.
                            The shortest booking is
                            <?php echo e(format_duration(BOOKING_MIN_DURATION_MIN)); ?>,
                            the longest <?php echo e(format_duration(BOOKING_MAX_DURATION_MIN)); ?>.
                        </div>

                        <!-- 5. purpose -->
                        <div class="mb-4">
                            <label class="form-label fw-bold" for="purpose">
                                <span class="badge bg-primary me-2">5</span>Purpose
                            </label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="3"
                                      maxlength="255" required
                                      placeholder="What will you use the laboratory for?"><?php
                                echo e(old('purpose'));
                            ?></textarea>
                            <div class="form-text">The administrator reads this when deciding.</div>
                            <?php echo field_error($errors, 'purpose'); ?>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i> Submit request
                        </button>
                    </div>
                </div>
            </div>

            <!-- ------------------------------------------- availability -->
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-calendar-week me-1"></i> Availability
                    </div>
                    <div class="card-body">
                        <?php if ($selectedLab === null): ?>
                            <p class="text-muted small mb-0">
                                Choose a laboratory to see what is free.
                            </p>
                        <?php elseif (!$dayValid): ?>
                            <div class="alert alert-warning small mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Pick a date between today and
                                <?php echo e(format_date(booking_last_date())); ?>.
                            </div>
                        <?php else: ?>
                            <p class="small text-muted">
                                <?php echo e($selectedLab['name']); ?>
                                on <strong><?php echo e(format_date($date)); ?></strong>
                            </p>

                            <?php if (empty($windows)): ?>
                                <div class="alert alert-warning small mb-0">
                                    <i class="bi bi-x-circle me-1"></i>
                                    Nothing is free on this date. Try another day.
                                </div>
                            <?php else: ?>
                                <h3 class="h6 small text-uppercase text-muted">Free periods</h3>
                                <ul class="list-group list-group-flush mb-3">
                                    <?php foreach ($windows as $win): ?>
                                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="bi bi-clock me-1"></i>
                                                <?php echo e($win['label']); ?>
                                                <span class="text-muted small">
                                                    (<?php echo e($win['slot_label']); ?>)
                                                </span>
                                            </span>
                                            <span class="badge bg-light text-dark">
                                                <?php echo e(format_duration($win['minutes'])); ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($dayBookings)): ?>
                                <h3 class="h6 small text-uppercase text-muted">Already taken</h3>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($dayBookings as $taken): ?>
                                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="bi bi-lock me-1"></i>
                                                <?php echo e(format_range($taken['start_time'], $taken['end_time'])); ?>
                                            </span>
                                            <span class="badge <?php echo e(status_badge_class($taken['status'])); ?>">
                                                <?php echo e(ucfirst($taken['status'])); ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="small text-muted mb-0 mt-2">
                                    Only the times are shown. Other students' names and
                                    purposes are not shown.
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </form>

<?php endif; ?>

<script>
// Reload the page when the laboratory or the date changes, so the availability
// panel and the time pickers match the choice. Plain GET, so this is only a
// convenience: the form still submits and validates without it.
(function () {
    var form = document.querySelector('form[method="post"]');
    if (!form) { return; }

    Array.prototype.forEach.call(form.querySelectorAll('[data-reload]'), function (field) {
        field.addEventListener('change', function () {
            // Fall back to a plain GET submit where URLSearchParams is missing,
            // rather than silently doing nothing.
            if (!window.URLSearchParams) {
                var plain = document.createElement('form');
                plain.method = 'GET';
                plain.action = window.location.pathname;
                ['laboratory_id', 'booking_date'].forEach(function (name) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name === 'laboratory_id' ? 'lab' : 'date';
                    input.value = form[name].value;
                    plain.appendChild(input);
                });
                document.body.appendChild(plain);
                plain.submit();
                return;
            }

            var params = new URLSearchParams();
            params.set('lab', form.laboratory_id.value);
            params.set('date', form.booking_date.value);
            window.location.search = params.toString();
        });
    });

    // Hide end times that cannot follow the chosen start. This only narrows the
    // list; booking_validate() still has the final say on submit.
    var start = document.getElementById('start_time');
    var end = document.getElementById('end_time');
    if (!start || !end) { return; }

    function filterEnds() {
        var from = start.options[start.selectedIndex];
        if (!from || !from.dataset.minutes) {
            Array.prototype.forEach.call(end.options, function (o) { o.hidden = false; });
            return;
        }
        var min = parseInt(from.dataset.minutes, 10);
        Array.prototype.forEach.call(end.options, function (o) {
            if (!o.dataset.minutes) { o.hidden = false; return; }
            o.hidden = parseInt(o.dataset.minutes, 10) <= min;
        });
    }

    start.addEventListener('change', filterEnds);
    filterEnds();
})();
</script>

<?php layout_end(); ?>
