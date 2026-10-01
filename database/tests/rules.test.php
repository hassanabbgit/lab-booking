<?php
/**
 * Unit tests for includes/booking_rules.php. Run from the command line:
 *   C:\xampp\php\php.exe database\tests\rules.test.php
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$pass = 0;
$fail = 0;

function check($label, $cond)
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  PASS  $label\n";
    } else {
        $fail++;
        echo "  FAIL  $label\n";
    }
}

function section($t) { echo "\n=== $t ===\n"; }

/**
 * First bookable date that has no bookings at all for a laboratory.
 *
 * Using a genuinely empty date means the test never has to delete a seeded row
 * to get a clean slate, so it cannot damage real data even if it dies halfway
 * through.
 *
 * @param  int $labId
 * @return string 'Y-m-d', or '' when the whole window is already booked.
 */
function booking_test_free_date($labId)
{
    for ($i = 1; $i <= 30; $i++) {
        $date = date('Y-m-d', strtotime('+' . $i . ' days'));
        $n = (int) db_value(
            'SELECT COUNT(*) FROM bookings WHERE laboratory_id = ? AND booking_date = ?',
            array((int) $labId, $date)
        );
        if ($n === 0) {
            return $date;
        }
    }
    return '';
}

/**
 * Remove a booking this test created, by reference only.
 *
 * @param string $ref
 */
function booking_test_drop($ref)
{
    db_exec('DELETE FROM bookings WHERE booking_ref = ?', array($ref));
}

/* -------------------------------------------------------------- time maths */
section('Time conversion');
check('time_to_minutes 08:00 -> 480', time_to_minutes('08:00') === 480);
check('time_to_minutes 08:00:00 -> 480', time_to_minutes('08:00:00') === 480);
check('time_to_minutes 20:00 -> 1200', time_to_minutes('20:00') === 1200);
check('time_to_minutes 00:00 -> 0', time_to_minutes('00:00') === 0);
check('time_to_minutes rejects 25:00', time_to_minutes('25:00') === null);
check('time_to_minutes rejects 08:99', time_to_minutes('08:99') === null);
check('time_to_minutes rejects empty', time_to_minutes('') === null);
check('time_to_minutes rejects junk', time_to_minutes('abc') === null);
check('minutes_to_time 480 -> 08:00:00', minutes_to_time(480) === '08:00:00');
check('minutes_to_time 1439 -> 23:59:00', minutes_to_time(1439) === '23:59:00');
check('normalise_time 08:05 -> 08:05:00', normalise_time('08:05') === '08:05:00');
check('normalise_time rejects an unpadded 8:5 (inputs are zero-padded)', normalise_time('8:5') === null);
check('round trip 09:45 survives', time_to_minutes(normalise_time('09:45')) === 585);

/* ------------------------------------------------------------ date window */
section('Booking window');
$today = booking_today();
check('today is allowed', booking_date_is_allowed($today));
check('tomorrow is allowed', booking_date_is_allowed(date('Y-m-d', strtotime('+1 day'))));
check('+30 days is allowed', booking_date_is_allowed(booking_last_date()));
check('+31 days is refused', !booking_date_is_allowed(date('Y-m-d', strtotime('+31 days'))));
check('yesterday is refused', !booking_date_is_allowed(date('Y-m-d', strtotime('-1 day'))));
check('31 Feb is refused', !booking_date_is_allowed('2026-02-31'));
check('29 Feb in a leap year is a real date', booking_date_is_calendar_valid('2028-02-29'));
check('29 Feb in a common year is not', !booking_date_is_calendar_valid('2027-02-29'));
check('a real far-future date is refused by the window, not the calendar',
    booking_date_is_calendar_valid('2028-02-29') && !booking_date_is_allowed('2028-02-29'));
check('garbage is refused', !booking_date_is_allowed('tomorrow'));
check('empty is refused', !booking_date_is_allowed(''));

/* ---------------------------------------------------------- slot matching */
section('Slot containment');
check('08:00-12:00 sits in the morning slot', slot_containing(480, 720) !== null);
check('13:00-17:00 sits in the afternoon slot', slot_containing(780, 1020) !== null);
check('17:00-20:00 sits in the evening slot', slot_containing(1020, 1200) !== null);
check('12:00-13:00 is in no slot (lunch break)', slot_containing(720, 780) === null);
check('12:30-13:30 straddles two slots so is refused', slot_containing(750, 810) === null);
check('07:00-09:00 starts before opening', slot_containing(420, 540) === null);
check('19:00-21:00 ends after closing', slot_containing(1140, 1260) === null);
check('the whole morning fits', slot_containing(480, 720) !== null);

/* ------------------------------------------------------------- conflicts */
section('Conflict detection');
// Laboratory 1 is available in the seed data.
$lab = db_one("SELECT * FROM laboratories WHERE status = 'available' ORDER BY id LIMIT 1");
$labId = (int) $lab['id'];
check('found an available laboratory', $lab !== null);

// How many bookings exist before this script starts. Compared again at the end
// so the test asserts "it added nothing of its own" rather than a hard-coded
// seed count, which would fail as soon as anyone made a real booking.
$countBefore = (int) db_value('SELECT COUNT(*) FROM bookings');

// Work on a laboratory and a date the seed data does not touch, and delete only
// the rows this script created. A blanket "DELETE WHERE date AND laboratory"
// would quietly destroy real bookings, which is exactly what an earlier version
// of this test did to BK-2026-0009.
$fresh = booking_test_free_date($labId);
check('found a date with no seeded bookings for laboratory ' . $labId, $fresh !== '');

$mine = db_insert(
    'INSERT INTO bookings (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time, purpose, status)
     VALUES (?, 3, ?, ?, \'10:00:00\', \'12:00:00\', \'rules test\', \'approved\')',
    array('ZZ-RULES-1', $labId, $fresh)
);

check('identical period conflicts', booking_find_conflict($labId, $fresh, '10:00:00', '12:00:00') !== null);
check('partial overlap at the start conflicts', booking_find_conflict($labId, $fresh, '09:00:00', '10:30:00') !== null);
check('partial overlap at the end conflicts', booking_find_conflict($labId, $fresh, '11:00:00', '13:00:00') !== null);
check('fully containing it conflicts', booking_find_conflict($labId, $fresh, '08:00:00', '12:00:00') !== null);
check('fully inside it conflicts', booking_find_conflict($labId, $fresh, '10:30:00', '11:30:00') !== null);
check('back to back before it is free', booking_find_conflict($labId, $fresh, '08:00:00', '10:00:00') === null);
check('back to back after it is free', booking_find_conflict($labId, $fresh, '12:00:00', '14:00:00') === null);
check('a different day is free', booking_find_conflict($labId, date('Y-m-d', strtotime('+3 days')), '10:00:00', '12:00:00') === null);
check('excluding itself is free', booking_find_conflict($labId, $fresh, '10:00:00', '12:00:00', $mine) === null);

// Rejected and cancelled bookings must not hold the time.
db_exec('UPDATE bookings SET status = \'rejected\' WHERE id = ?', array($mine));
check('a rejected booking does not block', booking_find_conflict($labId, $fresh, '10:00:00', '12:00:00') === null);
db_exec('UPDATE bookings SET status = \'cancelled\' WHERE id = ?', array($mine));
check('a cancelled booking does not block', booking_find_conflict($labId, $fresh, '10:00:00', '12:00:00') === null);
db_exec('UPDATE bookings SET status = \'pending\' WHERE id = ?', array($mine));
check('a pending booking does block', booking_find_conflict($labId, $fresh, '10:00:00', '12:00:00') !== null);
// A completed booking is in the past, but the rule stays "pending or approved".
db_exec('UPDATE bookings SET status = \'completed\' WHERE id = ?', array($mine));
check('a completed booking does not block', booking_find_conflict($labId, $fresh, '10:00:00', '12:00:00') === null);
db_exec('UPDATE bookings SET status = \'approved\' WHERE id = ?', array($mine));

/* --------------------------------------------------------- free windows */
section('Free windows');
// Clear the booking left over from the conflict section, so the day really is
// free before the first measurement.
booking_test_drop('ZZ-RULES-1');
booking_test_drop('ZZ-RULES-2');
booking_test_drop('ZZ-RULES-3');
booking_test_drop('ZZ-RULES-4');
$w = booking_free_windows($labId, $fresh);
$total = 0;
foreach ($w as $win) { $total += $win['minutes']; }
// Morning 08:00-12:00 = 240, afternoon 13:00-17:00 = 240, evening 17:00-20:00 = 180.
check('a free day offers all three active sessions (660 min)', $total === 660);

db_insert(
    'INSERT INTO bookings (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time, purpose, status)
     VALUES (?, 3, ?, ?, \'09:00:00\', \'11:00:00\', \'rules test\', \'approved\')',
    array('ZZ-RULES-2', $labId, $fresh)
);
$w = booking_free_windows($labId, $fresh);
$total = 0;
foreach ($w as $win) { $total += $win['minutes']; }
check('a 2 hour booking removes exactly 120 minutes', $total === 540);

$labels = array();
foreach ($w as $win) { $labels[] = $win['label']; }
check('the morning slot is split into 08:00-09:00 and 11:00-12:00',
    in_array('08:00 - 09:00', $labels, true) && in_array('11:00 - 12:00', $labels, true));
check('the afternoon session is untouched', in_array('13:00 - 17:00', $labels, true));
check('the evening session is untouched', in_array('17:00 - 20:00', $labels, true));

// A booking that swallows a slot whole must leave no slivers behind.
booking_test_drop('ZZ-RULES-3'); booking_test_drop('ZZ-RULES-4');
db_insert(
    'INSERT INTO bookings (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time, purpose, status)
     VALUES (?, 3, ?, ?, \'08:00:00\', \'12:00:00\', \'rules test\', \'approved\')',
    array('ZZ-RULES-3', $labId, $fresh)
);
$w = booking_free_windows($labId, $fresh);
$total = 0;
foreach ($w as $win) { $total += $win['minutes']; }
check('a fully booked morning leaves 420 minutes elsewhere', $total === 420);
$labels = array();
foreach ($w as $win) { $labels[] = $win['label']; }
check('no 08:00-12:00 window is offered', !in_array('08:00 - 12:00', $labels, true));

// A 20 minute gap is below the 30 minute minimum and must be discarded.
booking_test_drop('ZZ-RULES-3'); booking_test_drop('ZZ-RULES-4');
db_insert(
    'INSERT INTO bookings (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time, purpose, status)
     VALUES (?, 3, ?, ?, \'08:20:00\', \'12:00:00\', \'rules test\', \'approved\')',
    array('ZZ-RULES-4', $labId, $fresh)
);
$w = booking_free_windows($labId, $fresh);
$labels = array();
foreach ($w as $win) { $labels[] = $win['label']; }
check('a 20 minute sliver is not offered', !in_array('08:00 - 08:20', $labels, true));

// Every booking this script made has to go before the next section, or it will
// block the very request the validation tests expect to succeed.
booking_test_drop('ZZ-RULES-1');
booking_test_drop('ZZ-RULES-2');
booking_test_drop('ZZ-RULES-3');
booking_test_drop('ZZ-RULES-4');

/* ------------------------------------------------------------ validation */
section('Validation');
// Reuse the date already proven empty for this laboratory, so the "a valid
// request passes" case is not accidentally testing a seeded booking.
$future = $fresh;

$errors = booking_validate(array(
    'laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '09:00', 'end_time' => '11:00', 'purpose' => 'Practical session',
));
check('a valid request passes', empty($errors));

$errors = booking_validate(array('laboratory_id' => 0, 'booking_date' => $future,
    'start_time' => '09:00', 'end_time' => '11:00', 'purpose' => 'x'));
check('no laboratory is refused', isset($errors['laboratory_id']));

$brokenLab = db_one("SELECT id FROM laboratories WHERE status <> 'available' LIMIT 1");
if ($brokenLab !== null) {
    $errors = booking_validate(array('laboratory_id' => (int) $brokenLab['id'], 'booking_date' => $future,
        'start_time' => '09:00', 'end_time' => '11:00', 'purpose' => 'x'));
    check('a laboratory under maintenance is refused', isset($errors['laboratory_id']));
} else {
    echo "  SKIP  no unavailable laboratory in the seed data\n";
}

$errors = booking_validate(array('laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '11:00', 'end_time' => '09:00', 'purpose' => 'x'));
check('end before start is refused', isset($errors['end_time']));

$errors = booking_validate(array('laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '09:00', 'end_time' => '09:00', 'purpose' => 'x'));
check('zero length is refused', isset($errors['end_time']));

$errors = booking_validate(array('laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '09:00', 'end_time' => '09:15', 'purpose' => 'x'));
check('under 30 minutes is refused', isset($errors['end_time']));

$errors = booking_validate(array('laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '08:00', 'end_time' => '20:00', 'purpose' => 'x'));
check('longer than 8 hours is refused', isset($errors['end_time']));

$errors = booking_validate(array('laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '12:00', 'end_time' => '13:00', 'purpose' => 'x'));
check('the lunch break is refused', isset($errors['start_time']));

$errors = booking_validate(array('laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '12:30', 'end_time' => '13:30', 'purpose' => 'x'));
check('straddling two sessions is refused', isset($errors['start_time']));

$errors = booking_validate(array('laboratory_id' => $labId, 'booking_date' => '2020-01-01',
    'start_time' => '09:00', 'end_time' => '11:00', 'purpose' => 'x'));
check('a past date is refused', isset($errors['booking_date']));

$errors = booking_validate(array('laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '09:00', 'end_time' => '11:00', 'purpose' => ''));
check('an empty purpose is refused', isset($errors['purpose']));

$errors = booking_validate(array('laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '09:00', 'end_time' => '11:00', 'purpose' => str_repeat('x', 256)));
check('an over-long purpose is refused', isset($errors['purpose']));

$errors = booking_validate(array('laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '25:00', 'end_time' => '11:00', 'purpose' => 'x'));
check('an impossible start time is refused', isset($errors['start_time']));

// A live conflict must be caught by validation too.
db_insert(
    'INSERT INTO bookings (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time, purpose, status)
     VALUES (?, 3, ?, ?, \'09:00:00\', \'11:00:00\', \'rules test\', \'pending\')',
    array('ZZ-RULES-5', $labId, $future)
);
$errors = booking_validate(array('laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '10:00', 'end_time' => '12:00', 'purpose' => 'x'));
check('validation rejects an overlapping request', isset($errors['start_time']));
db_exec('DELETE FROM bookings WHERE booking_ref = \'ZZ-RULES-5\'');

/* --------------------------------------------------------------- create */
section('Creation');
$ref = booking_next_ref();
check('a new reference looks like BK-YYYY-NNNN', preg_match('/^BK-\d{4}-\d{4}$/', $ref) === 1);

$made = booking_create(3, array(
    'laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '09:00', 'end_time' => '11:00', 'purpose' => 'Created by the rules test',
));
check('creation succeeds', $made['ok'] === true);
check('creation returns the new id', (int) $made['id'] > 0);

$row = db_one('SELECT * FROM bookings WHERE id = ?', array($made['id']));
check('the new row starts as pending', $row['status'] === 'pending');
check('the times were stored as TIME values', $row['start_time'] === '09:00:00' && $row['end_time'] === '11:00:00');
check('the purpose was stored', $row['purpose'] === 'Created by the rules test');
check('the reference matches', $row['booking_ref'] === $made['ref']);

// A second, overlapping request must be refused by the transactional check.
$again = booking_create(4, array(
    'laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '10:00', 'end_time' => '12:00', 'purpose' => 'Should be refused',
));
check('an overlapping second request is refused', $again['ok'] === false);
check('the refusal explains why', $again['error'] !== '');

// A different period in the same laboratory must still work. The seed data
// already holds 13:00-15:00 on this date, so use the time directly after it:
// back-to-back periods do not overlap.
$ok = booking_create(4, array(
    'laboratory_id' => $labId, 'booking_date' => $future,
    'start_time' => '15:00', 'end_time' => '16:30', 'purpose' => 'Should be allowed',
));
check('a different period in the same laboratory is allowed', $ok['ok'] === true);
check('each booking gets a unique reference', $ok['ref'] !== $made['ref']);

db_exec('DELETE FROM bookings WHERE id IN (?, ?)', array($made['id'], $ok['id']));

/* --------------------------------------------------------------- cleanup */
db_exec('DELETE FROM bookings WHERE booking_ref LIKE \'ZZ-RULES-%\'');
$left = (int) db_value('SELECT COUNT(*) FROM bookings');
check('this test left no rows behind (' . $countBefore . ' before, ' . $left . ' after)', $left === $countBefore);
check('no rules test reference survives', (int) db_value(
    'SELECT COUNT(*) FROM bookings WHERE booking_ref LIKE \'ZZ-RULES-%\'') === 0);

echo "\nPASS: $pass   FAIL: $fail\n";
exit($fail === 0 ? 0 : 1);

