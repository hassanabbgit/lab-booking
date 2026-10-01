<?php
/**
 * Computer Laboratory Booking System
 * -----------------------------------
 * Booking rules: availability, validation and creation.
 *
 * Every page that shows or accepts a booking period goes through this file, so
 * that the form, the availability display and the database insert can never
 * disagree with each other.
 *
 * Why the validation lives here rather than in the database: MariaDB 10.1
 * parses but ignores CHECK constraints, so the database cannot enforce these
 * rules for us. They are applied in PHP on every submission, and the conflict
 * check is repeated inside the transaction that inserts the row.
 *
 * Target runtime: PHP 5.6.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/bootstrap.php';
}

/* =========================================================================
 | Tunables
 | ====================================================================== */

/** Offered start/end times land on this many minute boundaries. */
define('BOOKING_STEP_MIN', 30);

/** Statuses that hold a laboratory's time and therefore block a new request. */
define('BOOKING_BLOCKING_STATUSES', 'pending,approved');

/**
 * BOOKING_BLOCKING_STATUSES as a quoted SQL list.
 *
 * The constant holds bare words so that it reads well where it is defined, but
 * it has to reach the query as a quoted list: IN (pending,approved) is read by
 * MySQL as a list of column names, not values, and fails with
 * "Unknown column 'pending' in 'where clause'".
 *
 * @return string e.g. "'pending','approved'"
 */
function booking_blocking_status_sql()
{
    $parts = array();
    foreach (explode(',', BOOKING_BLOCKING_STATUSES) as $status) {
        $status = trim($status);
        if ($status !== '') {
            $parts[] = "'" . $status . "'";
        }
    }
    return $parts === array() ? "''" : implode(',', $parts);
}

/* =========================================================================
 | Small time helpers
 | ====================================================================== */

/**
 * 'HH:MM' or 'HH:MM:SS' -> minutes since midnight.
 *
 * @param  string $time
 * @return int|null Null when the value is not a real time.
 */
function time_to_minutes($time)
{
    $time = trim((string) $time);
    if ($time === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m) !== 1) {
        return null;
    }

    $h = (int) $m[1];
    $i = (int) $m[2];
    $s = isset($m[3]) ? (int) $m[3] : 0;

    if ($h > 23 || $i > 59 || $s > 59) {
        return null;
    }

    return $h * 60 + $i;
}

/**
 * Minutes since midnight -> 'HH:MM:SS' for storing in a TIME column.
 *
 * @param  int $minutes
 * @return string
 */
function minutes_to_time($minutes)
{
    $minutes = (int) $minutes;
    $h = (int) floor($minutes / 60);
    $m = $minutes % 60;
    return sprintf('%02d:%02d:00', $h, $m);
}

/**
 * Normalise user input to a storable TIME value.
 *
 * @param  string $time
 * @return string|null
 */
function normalise_time($time)
{
    $minutes = time_to_minutes($time);
    return $minutes === null ? null : minutes_to_time($minutes);
}

/**
 * Today's date as 'Y-m-d'.
 *
 * @return string
 */
function booking_today()
{
    return date('Y-m-d');
}

/**
 * The last date a booking may be made for.
 *
 * @return string
 */
function booking_last_date()
{
    return date('Y-m-d', strtotime('+' . (int) BOOKING_MAX_ADVANCE_DAYS . ' days'));
}

/**
 * A date string that names a real day on the calendar.
 *
 * Kept separate from the booking window so that calendar validity can be checked
 * on its own: 2026-02-31 is not a date, whatever the window says.
 *
 * @param  string $date
 * @return bool
 */
function booking_date_is_calendar_valid($date)
{
    $date = trim((string) $date);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
        return false;
    }

    list($y, $m, $d) = array_map('intval', explode('-', $date));

    // checkdate rejects 31 February and 29 February outside a leap year, which
    // strtotime() would instead silently roll forward into March.
    return checkdate($m, $d, $y);
}

/**
 * A date string that is real and inside the booking window.
 *
 * @param  string $date
 * @return bool
 */
function booking_date_is_allowed($date)
{
    if (!booking_date_is_calendar_valid($date)) {
        return false;
    }

    $date = trim($date);
    return $date >= booking_today() && $date <= booking_last_date();
}

/* =========================================================================
 | Time slots
 | ====================================================================== */

/**
 * The bookable sessions an administrator has enabled.
 *
 * @return array Rows from time_slots, ordered by start time.
 */
function active_time_slots()
{
    return db_all(
        'SELECT * FROM time_slots
          WHERE is_active = 1
          ORDER BY start_time ASC'
    );
}

/**
 * The active slot that fully contains a period, or null.
 *
 * A period may not straddle two slots. 13:00-17:00 and 17:00-20:00 are separate
 * slots that merely touch, so a booking has to sit inside exactly one of them.
 *
 * @param  int $startMinutes
 * @param  int $endMinutes
 * @return array|null
 */
function slot_containing($startMinutes, $endMinutes)
{
    foreach (active_time_slots() as $slot) {
        $s = time_to_minutes($slot['start_time']);
        $e = time_to_minutes($slot['end_time']);
        if ($s === null || $e === null) {
            continue;
        }
        if ($startMinutes >= $s && $endMinutes <= $e) {
            return $slot;
        }
    }
    return null;
}

/* =========================================================================
 | Conflict detection
 | ====================================================================== */

/**
 * Find a booking that overlaps a proposed period in the same laboratory.
 *
 * Two periods overlap when each starts before the other ends. That test also
 * allows back-to-back bookings: 08:00-10:00 and 10:00-12:00 do not overlap,
 * because neither starts before the other ends.
 *
 * @param  int    $laboratoryId
 * @param  string $date       'Y-m-d'
 * @param  string $start      'HH:MM:SS'
 * @param  string $end        'HH:MM:SS'
 * @param  int    $excludeId  Ignore this booking, so a booking can be re-saved.
 * @param  bool   $forUpdate  Lock the rows while a transaction is open.
 * @return array|null The conflicting booking, or null.
 */
function booking_find_conflict($laboratoryId, $date, $start, $end, $excludeId = 0, $forUpdate = false)
{
    $sql =
        'SELECT b.*, u.name AS user_name, u.email AS user_email
           FROM bookings b
           JOIN users u ON u.id = b.user_id
          WHERE b.laboratory_id = ?
            AND b.booking_date = ?
            AND b.status IN (' . booking_blocking_status_sql() . ')
            AND b.start_time < ?
            AND b.end_time > ?
            AND b.id <> ?
          ORDER BY b.start_time ASC
          LIMIT 1'
        . ($forUpdate ? ' FOR UPDATE' : '');

    return db_one($sql, array(
        (int) $laboratoryId,
        $date,
        $end,
        $start,
        (int) $excludeId,
    ));
}

/**
 * Bookings that occupy a laboratory on a date.
 *
 * @param  int    $laboratoryId
 * @param  string $date
 * @return array
 */
function bookings_on($laboratoryId, $date)
{
    return db_all(
        'SELECT * FROM bookings
          WHERE laboratory_id = ?
            AND booking_date = ?
            AND status IN (' . booking_blocking_status_sql() . ')
          ORDER BY start_time ASC',
        array((int) $laboratoryId, $date)
    );
}

/**
 * The periods still free inside each active slot on a date.
 *
 * Existing bookings are cut out of the slots, and any fragment shorter than the
 * minimum booking length is discarded because it cannot be booked anyway.
 *
 * @param  int    $laboratoryId
 * @param  string $date 'Y-m-d'
 * @return array Each entry: label, start, end, minutes, slot_label, exact
 */
function booking_free_windows($laboratoryId, $date)
{
    $windows = array();

    $existing = array();
    foreach (bookings_on($laboratoryId, $date) as $row) {
        $s = time_to_minutes($row['start_time']);
        $e = time_to_minutes($row['end_time']);
        if ($s !== null && $e !== null) {
            $existing[] = array($s, $e);
        }
    }

    // A booking for today cannot start in the past.
    $earliest = 0;
    if ($date === booking_today()) {
        $now = (int) date('H') * 60 + (int) date('i');
        // Round up to the next step so an offered time is always in the future.
        $earliest = (int) (ceil($now / BOOKING_STEP_MIN) * BOOKING_STEP_MIN);
    }

    foreach (active_time_slots() as $slot) {
        $slotStart = time_to_minutes($slot['start_time']);
        $slotEnd = time_to_minutes($slot['end_time']);
        if ($slotStart === null || $slotEnd === null || $slotEnd <= $slotStart) {
            continue;
        }

        // Start with the whole slot, then carve out the bookings.
        $free = array(array(max($slotStart, $earliest), $slotEnd));

        foreach ($existing as $busy) {
            $next = array();
            foreach ($free as $span) {
                // No overlap: keep the span untouched.
                if ($busy[1] <= $span[0] || $busy[0] >= $span[1]) {
                    $next[] = $span;
                    continue;
                }
                // Busy period starts inside this span: keep the part before it.
                if ($busy[0] > $span[0]) {
                    $next[] = array($span[0], $busy[0]);
                }
                // Busy period ends inside this span: keep the part after it.
                if ($busy[1] < $span[1]) {
                    $next[] = array($busy[1], $span[1]);
                }
            }
            $free = $next;
        }

        foreach ($free as $span) {
            $minutes = $span[1] - $span[0];
            if ($minutes < (int) BOOKING_MIN_DURATION_MIN) {
                continue;
            }
            $windows[] = array(
                'label'      => format_time(minutes_to_time($span[0])) . ' - ' . format_time(minutes_to_time($span[1])),
                'start'      => minutes_to_time($span[0]),
                'end'        => minutes_to_time($span[1]),
                'minutes'    => $minutes,
                'slot_label' => $slot['label'],
                'exact'      => ($minutes === $span[1] - $span[0]) && $minutes <= (int) BOOKING_MAX_DURATION_MIN,
            );
        }
    }

    return $windows;
}

/* =========================================================================
 | Validation
 | ====================================================================== */

/**
 * Check a proposed booking.
 *
 * @param  array $input Keys: laboratory_id, booking_date, start_time,
 *                      end_time, purpose
 * @return array Field name => message. Empty means valid.
 */
function booking_validate(array $input)
{
    $errors = array();

    $labId = isset($input['laboratory_id']) ? (int) $input['laboratory_id'] : 0;
    $date = isset($input['booking_date']) ? trim($input['booking_date']) : '';
    $startRaw = isset($input['start_time']) ? trim($input['start_time']) : '';
    $endRaw = isset($input['end_time']) ? trim($input['end_time']) : '';
    $purpose = isset($input['purpose']) ? trim($input['purpose']) : '';

    /* --- laboratory --- */
    $lab = $labId > 0 ? db_one('SELECT * FROM laboratories WHERE id = ?', array($labId)) : null;
    if ($lab === null) {
        $errors['laboratory_id'] = 'Choose a laboratory.';
    } elseif ($lab['status'] !== 'available') {
        $errors['laboratory_id'] = $lab['name'] . ' is not open for booking (' . $lab['status'] . ').';
    }

    /* --- date --- */
    if ($date === '') {
        $errors['booking_date'] = 'Choose a date.';
    } elseif (!booking_date_is_allowed($date)) {
        $errors['booking_date'] = 'Choose a date between today and '
            . format_date(booking_last_date()) . '.';
    }

    /* --- times --- */
    $startMinutes = time_to_minutes($startRaw);
    $endMinutes = time_to_minutes($endRaw);

    if ($startRaw === '' || $startMinutes === null) {
        $errors['start_time'] = 'Choose a start time.';
    }
    if ($endRaw === '' || $endMinutes === null) {
        $errors['end_time'] = 'Choose an end time.';
    }

    if ($startMinutes !== null && $endMinutes !== null) {
        if ($endMinutes <= $startMinutes) {
            $errors['end_time'] = 'The end time must be after the start time.';
        } else {
            $length = $endMinutes - $startMinutes;
            if ($length < (int) BOOKING_MIN_DURATION_MIN) {
                $errors['end_time'] = 'A session must be at least '
                    . format_duration(BOOKING_MIN_DURATION_MIN) . '.';
            } elseif ($length > (int) BOOKING_MAX_DURATION_MIN) {
                $errors['end_time'] = 'A session cannot be longer than '
                    . format_duration(BOOKING_MAX_DURATION_MIN) . '.';
            } elseif (slot_containing($startMinutes, $endMinutes) === null) {
                $errors['start_time'] = 'That period is outside the bookable sessions.';
            } elseif ($date === booking_today() && $startMinutes <= (int) date('H') * 60 + (int) date('i')) {
                $errors['start_time'] = 'That time has already passed today.';
            } elseif (booking_date_is_allowed($date) && $lab !== null) {
                $conflict = booking_find_conflict(
                    $labId,
                    $date,
                    minutes_to_time($startMinutes),
                    minutes_to_time($endMinutes)
                );
                if ($conflict !== null) {
                    $errors['start_time'] = format_range($conflict['start_time'], $conflict['end_time'])
                        . ' is already taken on that date.';
                }
            }
        }
    }

    /* --- purpose --- */
    if ($purpose === '') {
        $errors['purpose'] = 'Say what the session is for.';
    } elseif (mb_strlen($purpose) > 255) {
        // The column is VARCHAR(255); let the user trim it rather than 500.
        $errors['purpose'] = 'Keep the purpose under 255 characters.';
    }

    return $errors;
}

/* =========================================================================
 | Creating a booking
 | ====================================================================== */

/**
 * The next reference in the BK-YYYY-NNNN series.
 *
 * @return string
 */
function booking_next_ref()
{
    $prefix = 'BK-' . date('Y') . '-';
    $used = (int) db_value(
        'SELECT COUNT(*) FROM bookings WHERE booking_ref LIKE ?',
        array($prefix . '%')
    );

    // Sequential, but never collide with a reference left by a seeded year.
    do {
        $used++;
        $ref = $prefix . str_pad((string) $used, 4, '0', STR_PAD_LEFT);
    } while (db_value('SELECT id FROM bookings WHERE booking_ref = ?', array($ref)) !== null);

    return $ref;
}

/**
 * Insert a booking request.
 *
 * The conflict check runs again inside the transaction, with the laboratory's
 * rows locked, because two students can pass the check on the form at the same
 * moment. Approval re-checks as well, so a double request can never turn into
 * two approved bookings for the same period.
 *
 * @param  int   $userId
 * @param  array $data  Keys: laboratory_id, booking_date, start_time, end_time, purpose
 * @return array array('ok' => bool, 'id' => int, 'ref' => string, 'error' => string)
 */
function booking_create($userId, array $data)
{
    $date = $data['booking_date'];
    $start = normalise_time($data['start_time']);
    $end = normalise_time($data['end_time']);
    $labId = (int) $data['laboratory_id'];

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // Lock this laboratory's bookings for the date, so a concurrent
        // request has to wait rather than slip through the gap.
        $conflict = booking_find_conflict($labId, $date, $start, $end, 0, true);

        if ($conflict !== null) {
            $pdo->rollBack();
            return array(
                'ok'    => false,
                'id'    => 0,
                'ref'   => '',
                'error' => format_range($conflict['start_time'], $conflict['end_time'])
                        . ' was booked a moment ago. Please pick another time.',
            );
        }

        $ref = booking_next_ref();

        $id = db_insert(
            'INSERT INTO bookings
                (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time, purpose, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\')',
            array($ref, (int) $userId, $labId, $date, $start, $end, trim($data['purpose']))
        );

        $pdo->commit();

        return array('ok' => true, 'id' => $id, 'ref' => $ref, 'error' => '');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('CLBS booking_create failed: ' . $e->getMessage());
        return array(
            'ok'    => false,
            'id'    => 0,
            'ref'   => '',
            'error' => 'The booking could not be saved. Please try again.',
        );
    }
}

/**
 * Tell the owner that an administrator has acted on a request.
 *
 * @param int    $userId
 * @param int    $bookingId
 * @param string $title
 * @param string $message
 * @param string $link
 */
function notify_user($userId, $bookingId, $title, $message, $link = '')
{
    try {
        db_insert(
            'INSERT INTO notifications (user_id, title, message, link)
             VALUES (?, ?, ?, ?)',
            array((int) $userId, $title, $message, $link === '' ? null : $link)
        );
    } catch (Exception $e) {
        error_log('CLBS notify_user failed: ' . $e->getMessage());
    }
}

/* =========================================================================
   The booking status lifecycle
   ========================================================================= */

/**
 * Every state a booking can be in.
 *
 * @return array
 */
function booking_statuses()
{
    return array('pending', 'approved', 'rejected', 'cancelled', 'completed');
}

/**
 * The states a booking is allowed to move to from a given state.
 *
 *                     +---------------> rejected   (final)
 *                     |
 *    [new] --> pending +---------------> cancelled (final, by student or admin)
 *                     |
 *                     +---------------> approved ---> completed
 *
 * This graph is the single source of truth. The buttons the interface renders,
 * the explanations it gives and booking_decide() all read from here, so a
 * transition cannot be relaxed in one place and forgotten in another.
 *
 * @param  string $from
 * @return array  Allowed target states; empty when the state is final.
 */
function booking_next_statuses($from)
{
    $graph = array(
        'pending'   => array('approved', 'rejected', 'cancelled'),
        'approved'  => array('completed', 'cancelled'),
        'rejected'  => array(),
        'cancelled' => array(),
        'completed' => array(),
    );

    return isset($graph[$from]) ? $graph[$from] : array();
}

/**
 * Whether a booking may move between two states.
 *
 * @param  string $from
 * @param  string $to
 * @return bool
 */
function booking_can_transition($from, $to)
{
    return in_array($to, booking_next_statuses($from), true);
}

/**
 * Whether a state can never change again.
 *
 * Final states are what the history is made of, so they are deliberately not
 * editable: a mistake is put right with a new booking, not by rewriting the
 * record.
 *
 * @param  string $status
 * @return bool
 */
function booking_is_terminal($status)
{
    return booking_next_statuses($status) === array();
}

/**
 * A booking status as a person would say it.
 *
 * @param  string $status
 * @return string
 */
function booking_status_label($status)
{
    $labels = array(
        'pending'   => 'Awaiting approval',
        'approved'  => 'Approved',
        'rejected'  => 'Rejected',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
    );

    return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
}

/**
 * A student cancelling their own booking.
 *
 * Kept separate from booking_decide() because a self-cancellation is not a
 * review: there is no administrator and no decision to attribute, so
 * `reviewed_by` and `admin_note` are cleared rather than filled in. The
 * ownership check and the same lifecycle rules still apply, and the row is
 * locked first so a student cannot cancel one that an administrator has just
 * acted on.
 *
 * @param  int $bookingId
 * @param  int $userId
 * @return array array('ok' => bool, 'error' => string)
 */
function booking_cancel_by_owner($bookingId, $userId)
{
    $bookingId = (int) $bookingId;
    $userId = (int) $userId;

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $current = db_one(
            'SELECT * FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE',
            array($bookingId, $userId)
        );

        if ($current === null) {
            $pdo->rollBack();
            return array(
                'ok'    => false,
                'error' => 'That booking does not exist, or it belongs to another student.',
            );
        }

        if (!booking_can_transition($current['status'], 'cancelled')) {
            $pdo->rollBack();
            return array(
                'ok'    => false,
                'error' => 'Booking ' . $current['booking_ref'] . ' is '
                        . lcfirst(booking_status_label($current['status'])) . ', so it cannot be cancelled.',
            );
        }

        if ($current['booking_date'] < booking_today()) {
            $pdo->rollBack();
            return array(
                'ok'    => false,
                'error' => 'Booking ' . $current['booking_ref'] . ' was on '
                        . format_date($current['booking_date']) . ', which has already passed.',
            );
        }

        db_exec(
            "UPDATE bookings
                SET status      = 'cancelled',
                    reviewed_by = NULL,
                    reviewed_at = NULL,
                    admin_note  = NULL
              WHERE id = ? AND user_id = ? AND status = ?",
            array($bookingId, $userId, $current['status'])
        );

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('CLBS booking_cancel_by_owner failed: ' . $e->getMessage());
        return array('ok' => false, 'error' => 'The booking could not be cancelled. Please try again.');
    }

    log_activity(
        'cancel',
        'booking',
        $bookingId,
        'Cancelled ' . $current['booking_ref'] . ' by its owner'
    );

    return array('ok' => true, 'error' => '');
}

/**
 * Build the failure shape returned by booking_decide().
 *
 * @param  string $error
 * @return array
 */
function booking_decision_failure($error)
{
    return array(
        'ok'      => false,
        'error'   => $error,
        'message' => '',
        'booking' => null,
    );
}

/**
 * Act on a booking request: approve, reject, cancel or complete it.
 *
 * This is the only place a booking status is allowed to change. It refuses
 * anything the lifecycle does not permit, re-checks availability when
 * approving, and tells the student what happened.
 *
 * @param  int    $bookingId
 * @param  string $toStatus  One of booking_statuses().
 * @param  int    $adminId   The administrator deciding.
 * @param  string $note      Reason shown to the student; required to reject.
 * @return array array('ok' => bool, 'error' => string, 'message' => string, 'booking' => array|null)
 */
function booking_decide($bookingId, $toStatus, $adminId, $note = '')
{
    $bookingId = (int) $bookingId;
    $note = trim($note);

    if (!in_array($toStatus, booking_statuses(), true)) {
        return booking_decision_failure('"' . $toStatus . '" is not a status a booking can have.');
    }

    if (strlen($note) > 255) {
        return booking_decision_failure('The reason must be 255 characters or fewer.');
    }

    if ($toStatus === 'rejected' && $note === '') {
        return booking_decision_failure('A rejection has to say why, so the student knows what to change.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // Lock the row so a second administrator clicking at the same moment
        // waits here instead of both decisions landing.
        $current = db_one(
            'SELECT b.*,
                    l.name    AS lab_name,
                    l.status  AS lab_status,
                    u.name    AS user_name,
                    u.email   AS user_email
               FROM bookings b
               JOIN laboratories l ON l.id = b.laboratory_id
               JOIN users u ON u.id = b.user_id
              WHERE b.id = ?
              FOR UPDATE',
            array($bookingId)
        );

        if ($current === null) {
            $pdo->rollBack();
            return booking_decision_failure('That booking no longer exists.');
        }

        $from = $current['status'];

        if ($from === $toStatus) {
            $pdo->rollBack();
            return booking_decision_failure(
                'Booking ' . $current['booking_ref'] . ' is already ' . lcfirst(booking_status_label($toStatus)) . '.'
            );
        }

        if (!booking_can_transition($from, $toStatus)) {
            $pdo->rollBack();

            $message = 'This booking is ' . lcfirst(booking_status_label($from)) . ', so it cannot be '
                     . lcfirst(booking_status_label($toStatus)) . '.';

            $allowed = booking_next_statuses($from);

            if ($allowed === array()) {
                $message .= ' That is a final state, and it is kept as it is for the record.';
            } else {
                $parts = array();
                foreach ($allowed as $status) {
                    $parts[] = strtolower(booking_status_label($status));
                }
                $last = array_pop($parts);
                $message .= ' From here it can only be '
                         . (count($parts) === 0 ? $last : implode(', ', $parts) . ' or ' . $last) . '.';
            }

            return booking_decision_failure($message);
        }

        $today = booking_today();
        $ref = $current['booking_ref'];
        $when = format_date($current['booking_date']) . ', '
              . format_range($current['start_time'], $current['end_time']);

        // A session that has already happened cannot be approved or cancelled,
        // and one that has not happened yet cannot be completed. These live here
        // rather than only in the interface, so a hand-crafted request cannot
        // slip past a button that the page simply did not draw.
        if (($toStatus === 'approved' || $toStatus === 'cancelled')
            && $current['booking_date'] < $today
        ) {
            $pdo->rollBack();
            return booking_decision_failure(
                'Booking ' . $ref . ' was for ' . $when . ', which has already passed, so it cannot be '
                . ($toStatus === 'approved' ? 'approved' : 'cancelled') . '.'
            );
        }

        if ($toStatus === 'completed' && $current['booking_date'] > $today) {
            $pdo->rollBack();
            return booking_decision_failure(
                'Booking ' . $ref . ' is for ' . $when . ', which has not happened yet, so it cannot be marked completed.'
            );
        }

        if ($toStatus === 'approved') {
            // The room may have been taken offline after the request was made.
            if ($current['lab_status'] !== 'available') {
                $pdo->rollBack();
                return booking_decision_failure(
                    $current['lab_name'] . ' is currently ' . lcfirst(laboratory_status_label($current['lab_status']))
                    . ', so ' . $ref . ' cannot be approved. Reject it, or put the room back into service first.'
                );
            }

            // The period was free when the student asked, but a competing
            // request may have been approved since. Re-check with the room's
            // rows locked, so approving can never create a double booking.
            $conflict = booking_find_conflict(
                (int) $current['laboratory_id'],
                $current['booking_date'],
                $current['start_time'],
                $current['end_time'],
                $bookingId,
                true
            );

            if ($conflict !== null) {
                $pdo->rollBack();
                return booking_decision_failure(
                    $conflict['booking_ref'] . ' (' . format_range($conflict['start_time'], $conflict['end_time'])
                    . ') now holds that period in ' . $current['lab_name'] . ' for ' . $conflict['user_name']
                    . ', so ' . $ref . ' cannot be approved as well. Reject it, or ask the student to move it.'
                );
            }
        }

        // The status is repeated in the WHERE clause as well: the row is
        // already locked, so this can never fail here, but it keeps the write
        // safe if this function is ever called from somewhere without a lock.
        $changed = db_exec(
            'UPDATE bookings
                SET status      = ?,
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    admin_note  = ?
              WHERE id = ? AND status = ?',
            array($toStatus, (int) $adminId, $note === '' ? null : $note, $bookingId, $from)
        );

        if ($changed !== 1) {
            $pdo->rollBack();
            return booking_decision_failure('Booking ' . $ref . ' changed while you were deciding. Reload and try again.');
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('CLBS booking_decide failed: ' . $e->getMessage());
        return booking_decision_failure('The booking could not be updated. Please try again.');
    }

    // Logged and announced only after the decision is committed, so a failure
    // in either can never lose or undo the decision itself.
    $current['status'] = $toStatus;
    $current['admin_note'] = $note === '' ? null : $note;

    $verbs = array(
        'approved'  => 'approved',
        'rejected'  => 'rejected',
        'cancelled' => 'cancelled',
        'completed' => 'marked completed',
    );

    $label = lcfirst(booking_status_label($toStatus));
    $message = 'Booking ' . $current['booking_ref'] . ' (' . $current['user_name'] . ', '
             . $current['lab_name'] . ', ' . $when . ') was ' . $verbs[$toStatus] . '.';

    log_activity($toStatus, 'booking', $bookingId, $message);

    $titles = array(
        'approved'  => 'Booking approved',
        'rejected'  => 'Booking rejected',
        'cancelled' => 'Booking cancelled by an administrator',
        'completed' => 'Session completed',
    );

    $body = $current['lab_name'] . ' on ' . $when . '. Reference ' . $current['booking_ref'] . '.';

    if ($note !== '') {
        $body .= ' Reason: ' . $note;
    }

    notify_user(
        (int) $current['user_id'],
        $bookingId,
        $titles[$toStatus],
        $body,
        'user/booking.php?id=' . $bookingId
    );

    return array(
        'ok'      => true,
        'error'   => '',
        'message' => 'Booking ' . $current['booking_ref'] . ' is now ' . $label . '.',
        'booking' => $current,
    );
}
