<?php
/**
 * Computer Laboratory Booking System - time slot management rules.
 *
 * Loaded via includes/bootstrap.php so it is available everywhere. The
 * administrator pages use these helpers to add, edit, disable and delete
 * the bookable windows. The booking form and validation rely on the same
 * data, so the rules live here to keep them consistent.
 *
 * PHP 5.6 only.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    exit('Direct access is not allowed.');
}

if (!defined('TIME_SLOT_LABEL_MAX')) {
    define('TIME_SLOT_LABEL_MAX', 60);
}

if (!defined('TIME_SLOT_MIN_LENGTH_MINUTES')) {
    define('TIME_SLOT_MIN_LENGTH_MINUTES', 30);
}

/**
 * All slots ordered by start time.
 *
 * @return array
 */
function time_slot_list()
{
    return db_all(
        'SELECT * FROM time_slots
          ORDER BY start_time ASC, id ASC'
    );
}

/**
 * Active (bookable) slots ordered by start time.
 *
 * @return array
 */
function time_slot_active_list()
{
    return db_all(
        'SELECT * FROM time_slots
          WHERE is_active = 1
          ORDER BY start_time ASC, id ASC'
    );
}

/**
 * Fetch a single slot.
 *
 * @param  int $id
 * @return array|null
 */
function time_slot_find($id)
{
    $id = (int) $id;
    return db_one('SELECT * FROM time_slots WHERE id = ?', array($id));
}

/**
 * Validate a slot submission.
 *
 * $ignoreId is used when editing so we do not clash with ourselves.
 *
 * @param  array $input keys: label, start_time, end_time, is_active
 * @param  int   $ignoreId
 * @return array errors keyed by field name
 */
function time_slot_validate(array $input, $ignoreId = 0)
{
    $errors = array();

    $label     = trim(isset($input['label']) ? $input['label'] : '');
    $startRaw  = trim(isset($input['start_time']) ? $input['start_time'] : '');
    $endRaw    = trim(isset($input['end_time']) ? $input['end_time'] : '');
    $isActive  = trim(isset($input['is_active']) ? $input['is_active'] : '');

    if ($isActive === '') {
        $isActive = '1';
    }

    /* -------------------------------------------------------------- label */
    if ($label === '') {
        $errors['label'] = 'Give this slot a short label.';
    } elseif (mb_strlen($label) > TIME_SLOT_LABEL_MAX) {
        $errors['label'] = 'Keep the label under ' . TIME_SLOT_LABEL_MAX . ' characters.';
    }

    /* ------------------------------------------------------------ start time */
    $startMinutes = null;
    if ($startRaw === '') {
        $errors['start_time'] = 'Choose a start time.';
    } else {
        $startMinutes = time_to_minutes($startRaw);
        if ($startMinutes === null) {
            $errors['start_time'] = 'Enter a valid time in HH:MM format.';
        }
    }

    /* -------------------------------------------------------------- end time */
    $endMinutes = null;
    if ($endRaw === '') {
        $errors['end_time'] = 'Choose an end time.';
    } else {
        $endMinutes = time_to_minutes($endRaw);
        if ($endMinutes === null) {
            $errors['end_time'] = 'Enter a valid time in HH:MM format.';
        }
    }

    /* ------------------------------------------------------------ order/length */
    if ($startMinutes !== null && $endMinutes !== null) {
        if ($endMinutes <= $startMinutes) {
            $errors['end_time'] = 'End time must be after start time.';
        } elseif (($endMinutes - $startMinutes) < TIME_SLOT_MIN_LENGTH_MINUTES) {
            $errors['end_time'] = 'A slot must run for at least '
                . TIME_SLOT_MIN_LENGTH_MINUTES . ' minutes.';
        }

        if (empty($errors)) {
            $ignoreId = (int) $ignoreId;
            $exists = db_one(
                'SELECT id
                   FROM time_slots
                  WHERE start_time = ? AND end_time = ?
                    AND id <> ?
                  LIMIT 1',
                array(minutes_to_time($startMinutes), minutes_to_time($endMinutes), $ignoreId)
            );
            if ($exists !== null) {
                $errors['start_time'] = 'A slot with that exact start and end time already exists.';
                $errors['end_time']   = $errors['start_time'];
            }
        }
    }

    /* ------------------------------------------------------------- is_active */
    if ($isActive !== '0' && $isActive !== '1') {
        $isActive = '1';
    }

    return $errors;
}

/**
 * Extract slot fields from POST.
 *
 * @return array
 */
function time_slot_input_from_post()
{
    $isActive = post('is_active', '1');
    if ($isActive !== '0' && $isActive !== '1') {
        $isActive = '1';
    }

    return array(
        'label'      => post('label'),
        'start_time' => post('start_time'),
        'end_time'   => post('end_time'),
        'is_active'  => $isActive,
    );
}

/**
 * Create a slot.
 *
 * @param  array $input
 * @return array array('ok'=>bool,'id'=>int,'error'=>string)
 */
function time_slot_create(array $input)
{
    $label      = trim($input['label']);
    $start      = normalise_time(isset($input['start_time']) ? $input['start_time'] : '');
    $end        = normalise_time(isset($input['end_time']) ? $input['end_time'] : '');
    $isActive   = isset($input['is_active']) && $input['is_active'] === '0' ? 0 : 1;

    try {
        $id = db_insert(
            'INSERT INTO time_slots (label, start_time, end_time, is_active)
             VALUES (?, ?, ?, ?)',
            array($label, $start, $end, $isActive)
        );
        return array('ok' => true, 'id' => $id, 'error' => '');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return array('ok' => false, 'id' => 0,
                'error' => 'A slot with that exact time range already exists.');
        }
        error_log('CLBS time_slot create failed: ' . $e->getMessage());
        return array('ok' => false, 'id' => 0,
            'error' => 'The time slot could not be created. Please try again.');
    }
}

/**
 * Update a slot.
 *
 * @param  int   $id
 * @param  array $input
 * @return array
 */
function time_slot_update($id, array $input)
{
    $id = (int) $id;
    $slot = time_slot_find($id);
    if ($slot === null) {
        return array('ok' => false, 'id' => $id, 'error' => 'That time slot no longer exists.');
    }

    $label    = trim($input['label']);
    $start    = normalise_time(isset($input['start_time']) ? $input['start_time'] : '');
    $end      = normalise_time(isset($input['end_time']) ? $input['end_time'] : '');
    $isActive = isset($input['is_active']) && $input['is_active'] === '0' ? 0 : 1;

    try {
        db_exec(
            'UPDATE time_slots
                SET label = ?, start_time = ?, end_time = ?, is_active = ?
              WHERE id = ?',
            array($label, $start, $end, $isActive, $id)
        );
        return array('ok' => true, 'id' => $id, 'error' => '');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return array('ok' => false, 'id' => $id,
                'error' => 'A slot with that exact time range already exists.');
        }
        error_log('CLBS time_slot update failed: ' . $e->getMessage());
        return array('ok' => false, 'id' => $id,
            'error' => 'The changes could not be saved. Please try again.');
    }
}

/**
 * Set active/disabled.
 *
 * @param  int $id
 * @param  int $active 1 or 0
 * @return array
 */
function time_slot_set_active($id, $active)
{
    $id = (int) $id;
    $slot = time_slot_find($id);
    if ($slot === null) {
        return array('ok' => false, 'error' => 'That time slot no longer exists.');
    }

    $active = ($active === 0) ? 0 : 1;
    if ((int) $slot['is_active'] === $active) {
        $state = $active === 1 ? 'already enabled' : 'already disabled';
        return array('ok' => false, 'error' => 'This slot is ' . $state . '.');
    }

    db_exec('UPDATE time_slots SET is_active = ? WHERE id = ?', array($active, $id));
    return array('ok' => true, 'error' => '');
}

/**
 * How many bookings sit inside each slot, keyed by slot id.
 *
 * A booking uses a window when it lies entirely within it, so the test is
 * containment rather than an exact match on the two times. Booking 08:00-10:00
 * belongs to the 08:00-12:00 window even though the times are not identical,
 * and counting only identical pairs would report that window as unused and
 * then let an administrator delete the window out from under the booking.
 *
 * @return array slot id => booking count
 */
function time_slot_booking_counts()
{
    $counts = array();

    foreach (db_all(
        'SELECT ts.id AS slot_id, COUNT(b.id) AS n
           FROM time_slots ts
           LEFT JOIN bookings b
             ON b.start_time >= ts.start_time
            AND b.end_time   <= ts.end_time
          GROUP BY ts.id'
    ) as $row) {
        $counts[(int) $row['slot_id']] = (int) $row['n'];
    }

    return $counts;
}

/**
 * Delete a slot.
 *
 * Refused once the window has been used, so a period that appears in somebody's
 * history is kept and disabled instead. The bookings table has no slot column
 * to lean on, so the check is made here on the window's own times and repeated
 * in the page; the interface offers the action only for an unused window, and
 * this is what stops a hand-crafted request from skipping that.
 *
 * @param  int $id
 * @return array
 */
function time_slot_delete($id)
{
    $id = (int) $id;
    $slot = time_slot_find($id);
    if ($slot === null) {
        return array('ok' => false, 'error' => 'That time slot no longer exists.');
    }

    $counts = time_slot_booking_counts();
    $used   = isset($counts[$id]) ? $counts[$id] : 0;

    if ($used > 0) {
        return array(
            'ok'    => false,
            'error' => $slot['label'] . ' has ' . $used . ' booking'
                . ($used === 1 ? '' : 's') . ' inside it, so it cannot be deleted'
                . ' without losing that history. Disable it instead.',
        );
    }

    try {
        db_exec('DELETE FROM time_slots WHERE id = ?', array($id));
        return array('ok' => true, 'error' => '');
    } catch (PDOException $e) {
        $errno = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
        if ($e->getCode() === '23000' || $errno === 1451 || $errno === 1452) {
            return array('ok' => false,
                'error' => 'Something still refers to this slot, so it could not be deleted.');
        }
        error_log('CLBS time_slot delete failed: ' . $e->getMessage());
        return array('ok' => false, 'error' => 'The time slot could not be deleted.');
    }
}

/**
 * Label for active state.
 *
 * @param  int $active
 * @return string
 */
function time_slot_active_label($active)
{
    $active = (int) $active;
    return $active === 1 ? 'Bookable' : 'Disabled';
}

/**
 * Badge class for active state.
 *
 * @param  int $active
 * @return string
 */
function time_slot_active_badge($active)
{
    $active = (int) $active;
    return $active === 1 ? 'bg-success' : 'bg-secondary';
}
