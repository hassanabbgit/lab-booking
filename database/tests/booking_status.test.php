<?php
/**
 * Unit tests for the booking status lifecycle in includes/booking_rules.php.
 * Run from the command line:
 *   C:\xampp\php\php.exe database\tests\booking_status.test.php
 *
 * Every booking this file creates is marked with the purpose ZZ-STATUS-PROBE, so
 * cleanup cannot touch a real booking however the test ends. It counts rows and
 * notifications before and after rather than comparing against fixed numbers, so
 * real activity happening while the test runs does not make it fail.
 *
 * The tests drive the state machine directly, with no web server involved. The
 * HTTP layer is covered separately by the phase 6 end-to-end script, because
 * these checks are about the rules, not about the pages.
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

const PROBE_PURPOSE = 'ZZ-STATUS-PROBE';

/** Remove everything this test created, including its log and notification rows. */
function probe_cleanup()
{
    $ids = db_all('SELECT id FROM bookings WHERE purpose = ?', array(PROBE_PURPOSE));

    foreach ($ids as $row) {
        $id = (int) $row['id'];
        db_exec('DELETE FROM notifications WHERE link = ?', array('user/booking.php?id=' . $id));
        db_exec('DELETE FROM activity_logs WHERE entity = \'booking\' AND entity_id = ?', array($id));
    }

    db_exec('DELETE FROM bookings WHERE purpose = ?', array(PROBE_PURPOSE));
    probe_free_lab_drop();

    return count($ids);
}

/**
 * A laboratory reserved for the test, so probing the lifecycle can never
 * interfere with a seeded room or its bookings.
 */
function probe_free_lab()
{
    $lab = db_one("SELECT * FROM laboratories WHERE name LIKE 'ZZ-LAB-%' LIMIT 1");

    if ($lab === null) {
        $result = laboratory_create(array(
            'name'           => 'ZZ-LAB-Status',
            'location'       => 'Block Z - Room 999',
            'capacity'       => '40',
            'computer_count' => '40',
            'description'    => 'Created by booking_status.test.php',
            'status'         => 'available',
        ));

        if (!$result['ok']) {
            echo "  FATAL  could not create the probe laboratory: " . $result['error'] . "\n";
            exit(1);
        }

        $lab = db_one('SELECT * FROM laboratories WHERE id = ?', array($result['id']));
    }

    // Make sure it is in service, in case a previous run left it offline.
    laboratory_set_status((int) $lab['id'], 'available');

    return $lab;
}

function probe_drop_lab($labId)
{
    $count = (int) db_value('SELECT COUNT(*) FROM bookings WHERE laboratory_id = ?', array((int) $labId));
    if ($count > 0) {
        return false;
    }
    laboratory_delete((int) $labId);
    return true;
}

function probe_free_lab_drop()
{
    $lab = db_one("SELECT id FROM laboratories WHERE name LIKE 'ZZ-LAB-%' LIMIT 1");
    if ($lab !== null) {
        probe_drop_lab((int) $lab['id']);
    }
}

/**
 * Insert a booking straight into the table, bypassing booking_validate() so the
 * lifecycle can be tested with combinations the booking form would refuse.
 */
function probe_booking($userId, $labId, $date, $start, $end, $status = 'pending')
{
    return db_insert(
        'INSERT INTO bookings
            (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time, purpose, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        array(booking_next_ref(), (int) $userId, (int) $labId, $date, $start, $end, PROBE_PURPOSE, $status)
    );
}

function probe_status($id)
{
    return db_value('SELECT status FROM bookings WHERE id = ?', array((int) $id));
}

function probe_exists($id)
{
    return db_one('SELECT id FROM bookings WHERE id = ?', array((int) $id)) !== null;
}

/**
 * A window inside a bookable slot on a date with no probe bookings yet.
 * Slots are 08:00-12:00, 13:00-17:00 and 17:00-20:00, so each hour-long
 * candidate is inside exactly one of them.
 */
function probe_window($labId, $date, $hour, $excludeId = 0)
{
    $start = sprintf('%02d:00:00', $hour);
    $end = sprintf('%02d:00:00', $hour + 1);
    return array($start, $end);
}

/* ------------------------------------------------------------------- set-up */

$admin = db_one("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id LIMIT 1");
$student = db_one("SELECT id FROM users WHERE role = 'user' AND status = 'active' ORDER BY id LIMIT 1");
$other = db_one("SELECT id FROM users WHERE role = 'user' AND status = 'active' AND id <> ? ORDER BY id LIMIT 1", array((int) $student['id']));

if ($admin === null || $student === null || $other === null) {
    echo "FATAL  needs an active administrator and at least two active students. Run database/seed.php first.\n";
    exit(1);
}

$adminId = (int) $admin['id'];
$studentId = (int) $student['id'];
$otherId = (int) $other['id'];

probe_cleanup();

// Captured before the probe laboratory is created, so the final check can prove
// the test gave the database back exactly as many laboratories as it took.
$labsBefore = (int) db_value('SELECT COUNT(*) FROM laboratories');

$lab = probe_free_lab();
$labId = (int) $lab['id'];

// Well inside the 30-day booking window, so date rules never interfere with a
// test that is about the status machine. Each section gets its own date, so
// probe bookings made by one section cannot be mistaken for a clash by another.
$date = date('Y-m-d', strtotime('+3 days'));
$pastDate = date('Y-m-d', strtotime('-3 days'));
$overlapDate = date('Y-m-d', strtotime('+4 days'));
$shapeDate = date('Y-m-d', strtotime('+5 days'));
$competeDate = date('Y-m-d', strtotime('+6 days'));

echo "Probe laboratory #{$labId}, date {$date}\n";

/* ------------------------------------------------------------------ the graph */

section('The lifecycle graph');

check('there are exactly five statuses', count(booking_statuses()) === 5);

foreach (array('pending', 'approved', 'rejected', 'cancelled', 'completed') as $s) {
    check("$s is a status", in_array($s, booking_statuses(), true));
}

check('pending can be approved', booking_can_transition('pending', 'approved'));
check('pending can be rejected', booking_can_transition('pending', 'rejected'));
check('pending can be cancelled', booking_can_transition('pending', 'cancelled'));
check('pending cannot skip straight to completed', !booking_can_transition('pending', 'completed'));

check('approved can be completed', booking_can_transition('approved', 'completed'));
check('approved can be cancelled', booking_can_transition('approved', 'cancelled'));
check('approved cannot be un-approved into rejected', !booking_can_transition('approved', 'rejected'));
check('approved cannot go back to pending', !booking_can_transition('approved', 'pending'));

check('rejected is final', booking_is_terminal('rejected'));
check('cancelled is final', booking_is_terminal('cancelled'));
check('completed is final', booking_is_terminal('completed'));
check('pending is not final', !booking_is_terminal('pending'));
check('approved is not final', !booking_is_terminal('approved'));

check('every status has a label', booking_status_label('banished') === 'Banished');
check('pending is labelled for a person', booking_status_label('pending') === 'Awaiting approval');

check('only pending and approved hold a laboratory', booking_blocking_status_sql() === "'pending','approved'");

/* ------------------------------------------------------------- the transition */

section('One decision at a time');

$a = probe_booking($studentId, $labId, $date, '08:00:00', '09:00:00');

$r = booking_decide($a, 'approved', $adminId, 'Room is free.');
check('pending -> approved is allowed', $r['ok'] === true);
check('the status was written', probe_status($a) === 'approved');
check('the administrator is recorded', (int) db_value('SELECT reviewed_by FROM bookings WHERE id = ?', array($a)) === $adminId);
check('the decision was timestamped', db_value('SELECT reviewed_at FROM bookings WHERE id = ?', array($a)) !== null);
check('the note was stored', db_value('SELECT admin_note FROM bookings WHERE id = ?', array($a)) === 'Room is free.');

$r = booking_decide($a, 'completed', $adminId);
check('a future session cannot be completed', $r['ok'] === false);
check('the refusal explains itself', strpos($r['error'], 'not happened yet') !== false);
check('and nothing changed', probe_status($a) === 'approved');

$r = booking_decide($a, 'cancelled', $adminId, 'The room is being rewired.');
check('approved -> cancelled is allowed', $r['ok'] === true);
check('the status was written', probe_status($a) === 'cancelled');

$r = booking_decide($a, 'approved', $adminId);
check('a cancelled booking cannot be re-approved', $r['ok'] === false);
check('the refusal says it is final', strpos($r['error'], 'final state') !== false);

/* ------------------------------------------------------------------ refusals */

section('Refusals');

$b = probe_booking($studentId, $labId, $date, '13:00:00', '14:00:00');

$r = booking_decide($b, 'completed', $adminId);
check('pending -> completed is refused', $r['ok'] === false);
check('the refusal names the legal targets', strpos($r['error'], 'can only be approved, rejected or cancelled') !== false);

$r = booking_decide($b, 'rejected', $adminId, '');
check('rejecting without a reason is refused', $r['ok'] === false);
check('the refusal asks for one', strpos($r['error'], 'why') !== false);

$r = booking_decide($b, 'rejected', $adminId, str_repeat('x', 256));
check('an over-long reason is refused', $r['ok'] === false);

$r = booking_decide($b, 'banished', $adminId);
check('an invented status is refused', $r['ok'] === false);

$r = booking_decide(999999999, 'approved', $adminId);
check('a booking that does not exist is refused', $r['ok'] === false);

$r = booking_decide($b, 'pending', $adminId);
check('setting the status it already has is refused', $r['ok'] === false);

check('none of the refusals changed the booking', probe_status($b) === 'pending');

$r = booking_decide($b, 'rejected', $adminId, 'Computer Laboratory 1 is fully booked that day.');
check('pending -> rejected with a reason is allowed', $r['ok'] === true);
check('the student can read the reason', db_value('SELECT admin_note FROM bookings WHERE id = ?', array($b)) === 'Computer Laboratory 1 is fully booked that day.');

$r = booking_decide($b, 'cancelled', $adminId);
check('a rejected booking cannot be cancelled afterwards', $r['ok'] === false);

/* --------------------------------------------------------- the overlap example */

section('Overlapping bookings cannot both be approved');

/*
 * The example from the specification, exactly:
 *
 *   Lab 1  10:00 - 12:00   already booked
 *   Lab 1  11:00 - 13:00   another student tries to book it too
 *
 * 11:00 falls inside 10:00-12:00, so the second request must be refused. This is
 * checked twice, because there are two places the rule can be enforced and both
 * have to hold: when the request is made, and when an administrator decides it.
 */

$x = probe_booking($studentId, $labId, $overlapDate, '10:00:00', '12:00:00');
check('the 10:00-12:00 request exists', $x > 0);

$clash = booking_find_conflict($labId, $overlapDate, '11:00:00', '13:00:00');
check('the 11:00-13:00 period clashes with it', $clash !== null);
check('and the clash names the right period', $clash !== null
    && $clash['start_time'] === '10:00:00'
    && $clash['end_time'] === '12:00:00');

// At creation time, through the real booking path.
$before = (int) db_value('SELECT COUNT(*) FROM bookings');
$attempt = booking_create($otherId, array(
    'laboratory_id' => $labId,
    'booking_date'  => $overlapDate,
    'start_time'    => '11:00',
    'end_time'      => '13:00',
    'purpose'       => PROBE_PURPOSE,
));

check('a second request for the overlapping period is refused', $attempt['ok'] === false);
check('the refusal quotes the period that is taken', strpos($attempt['error'], '10:00 - 12:00') !== false);
check('and no row was created', (int) db_value('SELECT COUNT(*) FROM bookings') === $before);

$rx = booking_decide($x, 'approved', $adminId);
check('the first request is approved while the period is free', $rx['ok'] === true);

// Now the second request slips in as a probe row, standing in for one that was
// made while the period was still free and is only being decided afterwards.
$y = probe_booking($otherId, $labId, $overlapDate, '11:00:00', '13:00:00');

$r = booking_decide($y, 'approved', $adminId);
check('the overlapping request cannot be approved as well', $r['ok'] === false);
check('the refusal names the booking that holds the period', strpos($r['error'], '10:00 - 12:00') !== false);
check('the refusal explains the options', strpos($r['error'], 'Reject it') !== false);
check('and the overlap is still only pending', probe_status($y) === 'pending');

/* ------------------------------------------------- what counts as an overlap */

section('Exactly which periods count as an overlap');

/*
 * The rule is strict: a period clashes when it starts before the other ends AND
 * ends after the other starts. So back-to-back sessions are fine, and anything
 * that shares even a minute is not.
 *
 * All of these run on a date with no bookings in it yet, so the only booking in
 * play is the one inserted below.
 */
check('the date starts out free', booking_find_conflict($labId, $shapeDate, '16:00:00', '17:00:00') === null);

$held = probe_booking($studentId, $labId, $shapeDate, '16:00:00', '17:00:00');
check('a booking is made for 16:00-17:00', $held > 0);

check('a period ending exactly when it starts is free', booking_find_conflict($labId, $shapeDate, '15:00:00', '16:00:00') === null);
check('a period starting exactly when it ends is free', booking_find_conflict($labId, $shapeDate, '17:00:00', '18:00:00') === null);
check('a period one minute into it is a clash', booking_find_conflict($labId, $shapeDate, '16:01:00', '16:30:00') !== null);
check('a period ending one minute into it is a clash', booking_find_conflict($labId, $shapeDate, '15:30:00', '16:01:00') !== null);
check('a period wholly inside it is a clash', booking_find_conflict($labId, $shapeDate, '16:15:00', '16:45:00') !== null);
check('an identical period is a clash', booking_find_conflict($labId, $shapeDate, '16:00:00', '17:00:00') !== null);
check('a period that swallows it is a clash', booking_find_conflict($labId, $shapeDate, '15:00:00', '18:00:00') !== null);
check('a period overlapping only the start is a clash', booking_find_conflict($labId, $shapeDate, '15:30:00', '16:30:00') !== null);
check('a period overlapping only the end is a clash', booking_find_conflict($labId, $shapeDate, '16:30:00', '17:30:00') !== null);
check('a period on another day is not a clash', booking_find_conflict($labId, date('Y-m-d', strtotime($shapeDate . ' +1 day')), '16:00:00', '17:00:00') === null);

$otherLab = db_one('SELECT id FROM laboratories WHERE id <> ? ORDER BY id LIMIT 1', array($labId));
check('a period in another laboratory is not a clash', $otherLab !== null
    && booking_find_conflict((int) $otherLab['id'], $shapeDate, '16:00:00', '17:00:00') === null);

// Cancelling frees the period, which is the whole point of allowing it.
check('the held booking can be cancelled', booking_decide($held, 'cancelled', $adminId)['ok'] === true);
check('and the period is free again', booking_find_conflict($labId, $shapeDate, '16:00:00', '17:00:00') === null);

/* ------------------------------------------- two requests competing for one slot */

section('Two requests competing for the same period');

$p = probe_booking($studentId, $labId, $competeDate, '16:00:00', '17:00:00');
$q = probe_booking($otherId, $labId, $competeDate, '16:30:00', '17:30:00');

$r = booking_decide($p, 'approved', $adminId);
check('the first of two overlapping requests cannot be approved', $r['ok'] === false);
check('and the administrator is told what to do', strpos($r['error'], 'Reject it') !== false);

check('rejecting one of them is allowed', booking_decide($q, 'rejected', $adminId, 'Second of two requests for the same period.')['ok'] === true);
check('now the other can be approved', booking_decide($p, 'approved', $adminId)['ok'] === true);

/* ------------------------------------------------------- a room out of service */

section('A room out of service cannot be approved');

$offline = db_one("SELECT * FROM laboratories WHERE status <> 'available' LIMIT 1");

if ($offline === null) {
    echo "  SKIP  no out-of-service laboratory in the database\n";
} else {
    $offlineId = (int) $offline['id'];
    $z = probe_booking($studentId, $offlineId, $date, '15:00:00', '15:30:00');

    $r = booking_decide($z, 'approved', $adminId);
    check('approving into an out-of-service room is refused', $r['ok'] === false);
    check('the refusal names the room status', strpos($r['error'], 'back into service') !== false);
    check('and it can still be rejected', booking_decide($z, 'rejected', $adminId, 'The room is closed for repairs.')['ok'] === true);
}

/* ---------------------------------------------------- dates versus transitions */

section('Dates versus transitions');

$past = probe_booking($studentId, $labId, $pastDate, '08:00:00', '09:00:00');
$r = booking_decide($past, 'approved', $adminId);
check('a request for a date that has passed cannot be approved', $r['ok'] === false);
check('the refusal mentions the date', strpos($r['error'], 'passed') !== false);
check('and it can be rejected', booking_decide($past, 'rejected', $adminId, 'This date has already gone.')['ok'] === true);

$future = probe_booking($studentId, $labId, $date, '14:00:00', '15:00:00');
check('a future request is approved normally', booking_decide($future, 'approved', $adminId)['ok'] === true);
$r = booking_decide($future, 'cancelled', $adminId);
check('a future approved booking can be cancelled by an administrator', $r['ok'] === true);

$pastApproved = probe_booking($studentId, $labId, $pastDate, '09:00:00', '10:00:00', 'approved');
$r = booking_decide($pastApproved, 'cancelled', $adminId);
check('a session that has already happened cannot be cancelled', $r['ok'] === false);
$r = booking_decide($pastApproved, 'completed', $adminId);
check('but it can be marked completed', $r['ok'] === true);
check('completed is recorded', probe_status($pastApproved) === 'completed');

/* --------------------------------------------------- the student's own cancel */

section('A student cancelling their own booking');

$mine = probe_booking($studentId, $labId, $date, '17:00:00', '18:00:00');

$r = booking_cancel_by_owner($mine, $studentId);
check('a student can cancel their own pending booking', $r['ok'] === true);
check('the status is cancelled', probe_status($mine) === 'cancelled');
check('no administrator is recorded as the reviewer', db_value('SELECT reviewed_by FROM bookings WHERE id = ?', array($mine)) === null);

$r = booking_cancel_by_owner($mine, $studentId);
check('cancelling it twice is refused', $r['ok'] === false);
check('and the refusal explains why', strpos($r['error'], 'cancelled') !== false);

$theirs = probe_booking($otherId, $labId, $date, '18:00:00', '19:00:00');
$r = booking_cancel_by_owner($theirs, $studentId);
check("a student cannot cancel somebody else's booking", $r['ok'] === false);
check('and it is left alone', probe_status($theirs) === 'pending');

$r = booking_cancel_by_owner($theirs, $otherId);
check('its owner can cancel it', $r['ok'] === true);

$mineApproved = probe_booking($studentId, $labId, $date, '19:00:00', '20:00:00', 'approved');
$r = booking_cancel_by_owner($mineApproved, $studentId);
check('a student can cancel their own approved booking', $r['ok'] === true);

$mineDone = probe_booking($studentId, $labId, $pastDate, '10:00:00', '11:00:00', 'approved');
$r = booking_cancel_by_owner($mineDone, $studentId);
check('but not one that has already happened', $r['ok'] === false);

/* ------------------------------------------------------------------- the trail */

section('The trail left behind');

$logged = db_all(
    "SELECT * FROM activity_logs WHERE entity = 'booking' AND entity_id = ? AND action = 'approved'",
    array($x)
);
check('an approval is in the activity log', count($logged) === 1);
check('the entry describes what happened', $logged !== array() && strpos($logged[0]['description'], 'was approved') !== false);

$notified = db_all('SELECT * FROM notifications WHERE link = ?', array('user/booking.php?id=' . $x));
check('the student was notified', count($notified) === 1);
check('the notification is titled clearly', $notified !== array() && $notified[0]['title'] === 'Booking approved');
check('the notification carries the reference', $notified !== array() && strpos($notified[0]['message'], '10:00 - 12:00') !== false);

$rejectedNotified = db_all('SELECT * FROM notifications WHERE link = ?', array('user/booking.php?id=' . $b));
check('a rejection notifies the student', count($rejectedNotified) === 1);
check('the rejection notification carries the reason', $rejectedNotified !== array() && strpos($rejectedNotified[0]['message'], 'fully booked') !== false);

$cancelled = db_all("SELECT * FROM activity_logs WHERE entity = 'booking' AND entity_id = ? AND action = 'cancel'", array($mine));
check("a student's own cancellation is logged", count($cancelled) === 1);

/* --------------------------------------------------------------------- cleanup */

section('The database is left as it was found');

$remainingBefore = (int) db_value('SELECT COUNT(*) FROM bookings');
$bookingsBaseline = (int) db_value('SELECT COUNT(*) FROM bookings WHERE purpose <> ?', array(PROBE_PURPOSE));

$removed = probe_cleanup();

echo "\n  removed $removed probe bookings\n";

$bookingsAfter = (int) db_value('SELECT COUNT(*) FROM bookings');
$labsAfter = (int) db_value('SELECT COUNT(*) FROM laboratories');

check('no probe booking survives', (int) db_value('SELECT COUNT(*) FROM bookings WHERE purpose = ?', array(PROBE_PURPOSE)) === 0);
check('the booking count returned to its starting figure', $bookingsAfter === $remainingBefore - $removed);
check('the seeded bookings are all still there', $bookingsAfter === $bookingsBaseline);
check('no probe notification survives', (int) db_value('SELECT COUNT(*) FROM notifications WHERE message LIKE ?', array('%' . PROBE_PURPOSE . '%')) === 0);
check('no probe log entry survives', (int) db_value("SELECT COUNT(*) FROM activity_logs WHERE entity = 'booking' AND description LIKE ?", array('%' . PROBE_PURPOSE . '%')) === 0);
check('the probe laboratory is gone', (int) db_value("SELECT COUNT(*) FROM laboratories WHERE name LIKE 'ZZ-LAB-%'") === 0);
check('the laboratory count is unchanged', $labsAfter === $labsBefore);

echo "\n" . str_repeat('=', 50) . "\n";
echo "PASS: $pass   FAIL: $fail\n";
echo str_repeat('=', 50) . "\n";

exit($fail === 0 ? 0 : 1);
