<?php
/**
 * Exercises the time slot helpers the same way the administrator pages do,
 * covering both the accept and the reject side of every rule.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$pass = 0;
$fail = 0;
$failures = array();

function ok($cond, $label)
{
    global $pass, $fail, $failures;
    if ($cond) {
        $pass++;
    } else {
        $fail++;
        $failures[] = $label;
        echo "  FAIL  $label\n";
    }
}

function section($name)
{
    echo "\n=== $name ===\n";
}

/* ------------------------------------------------------------------ setup */

$before = db_all('SELECT * FROM time_slots ORDER BY id');

/* ============================================================ validation */

section('label is required and bounded');

$e = time_slot_validate(array('label' => '', 'start_time' => '08:00', 'end_time' => '10:00', 'is_active' => '1'));
ok(isset($e['label']), 'empty label is rejected');

$e = time_slot_validate(array('label' => '   ', 'start_time' => '08:00', 'end_time' => '10:00', 'is_active' => '1'));
ok(isset($e['label']), 'whitespace-only label is rejected');

$e = time_slot_validate(array('label' => str_repeat('x', TIME_SLOT_LABEL_MAX + 1),
    'start_time' => '08:00', 'end_time' => '10:00', 'is_active' => '1'));
ok(isset($e['label']), 'over-long label is rejected');

$e = time_slot_validate(array('label' => 'Ok', 'start_time' => '08:00', 'end_time' => '10:00', 'is_active' => '1'));
ok(!isset($e['label']), 'normal label is accepted');

/* ============================================================ start/end */

section('start and end must be real times');

$e = time_slot_validate(array('label' => 'A', 'start_time' => '', 'end_time' => '10:00', 'is_active' => '1'));
ok(isset($e['start_time']), 'missing start is rejected');

$e = time_slot_validate(array('label' => 'A', 'start_time' => '08:00', 'end_time' => '', 'is_active' => '1'));
ok(isset($e['end_time']), 'missing end is rejected');

$e = time_slot_validate(array('label' => 'A', 'start_time' => '25:00', 'end_time' => '10:00', 'is_active' => '1'));
ok(isset($e['start_time']), 'hour 25 is rejected');

$e = time_slot_validate(array('label' => 'A', 'start_time' => '08:99', 'end_time' => '10:00', 'is_active' => '1'));
ok(isset($e['start_time']), 'minute 99 is rejected');

$e = time_slot_validate(array('label' => 'A', 'start_time' => 'morning', 'end_time' => '10:00', 'is_active' => '1'));
ok(isset($e['start_time']), 'free text start is rejected');

$e = time_slot_validate(array('label' => 'A', 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'is_active' => '1'));
ok(empty($e), 'HH:MM:SS is accepted as well as HH:MM');

/* =============================================================== ordering */

section('end must come after start, with a minimum length');

$e = time_slot_validate(array('label' => 'A', 'start_time' => '10:00', 'end_time' => '09:00', 'is_active' => '1'));
ok(isset($e['end_time']), 'end before start is rejected');

$e = time_slot_validate(array('label' => 'A', 'start_time' => '10:00', 'end_time' => '10:00', 'is_active' => '1'));
ok(isset($e['end_time']), 'end equal to start is rejected');

$short = TIME_SLOT_MIN_LENGTH_MINUTES - 10;
$e = time_slot_validate(array('label' => 'A', 'start_time' => '10:00',
    'end_time' => minutes_to_time(time_to_minutes('10:00') + $short), 'is_active' => '1'));
ok(isset($e['end_time']), 'window shorter than the minimum is rejected');

$e = time_slot_validate(array('label' => 'A', 'start_time' => '10:00',
    'end_time' => minutes_to_time(time_to_minutes('10:00') + TIME_SLOT_MIN_LENGTH_MINUTES),
    'is_active' => '1'));
ok(empty($e), 'window exactly the minimum length is accepted');

/* ============================================================ uniqueness */

section('an identical range cannot be added twice');

$existing = db_one('SELECT * FROM time_slots WHERE is_active = 1 ORDER BY id LIMIT 1');
$e = time_slot_validate(array(
    'label' => 'Clash', 'start_time' => $existing['start_time'], 'end_time' => $existing['end_time'],
    'is_active' => '1',
));
ok(isset($e['start_time']), 'duplicate of an existing slot is rejected');

$e = time_slot_validate(array(
    'label' => 'Same', 'start_time' => $existing['start_time'], 'end_time' => $existing['end_time'],
    'is_active' => '1',
), (int) $existing['id']);
ok(empty($e), 'the same slot does not clash with itself when editing');

/* ============================================================== is_active */

section('is_active is coerced to 0 or 1');

$created0 = time_slot_create(array(
    'label' => 'INACTIVE PROBE', 'start_time' => '06:15', 'end_time' => '07:45', 'is_active' => '0',
));
if ($created0['ok']) {
    $probeInactive = (int) $created0['id'];
    $row = time_slot_find($probeInactive);
    ok((int) $row['is_active'] === 0, 'a slot created with is_active=0 is stored disabled');

    $r = time_slot_set_active($probeInactive, 1);
    ok($r['ok'] === true, 'it can be enabled afterwards');
    ok((int) time_slot_find($probeInactive)['is_active'] === 1, 'and it is then bookable');

    ok(time_slot_delete($probeInactive)['ok'] === true, 'the inactive probe is cleaned up');
}

/* ========================================================= create/update */

section('create, update, toggle and delete');

$created = time_slot_create(array(
    'label' => 'TEST SLOT', 'start_time' => '06:15', 'end_time' => '07:45', 'is_active' => '1',
));
ok($created['ok'] === true, 'a valid slot is created');
$newId = (int) $created['id'];

if ($created['ok']) {
    $row = time_slot_find($newId);
    ok($row !== null, 'the new slot can be read back');
    ok($row['label'] === 'TEST SLOT', 'the label was stored');
    ok(substr($row['start_time'], 0, 5) === '06:15', 'the start time was stored');
    ok(substr($row['end_time'], 0, 5) === '07:45', 'the end time was stored');
    ok((int) $row['is_active'] === 1, 'it was created as bookable');

    // seconds are zeroed on the way in
    ok(substr($row['start_time'], 6, 2) === '00', 'start seconds are normalised to 00');

    $r = time_slot_update($newId, array(
        'label' => 'TEST RENAMED', 'start_time' => '06:30', 'end_time' => '08:00', 'is_active' => '0',
    ));
    ok($r['ok'] === true, 'the slot is updated');
    $row = time_slot_find($newId);
    ok($row['label'] === 'TEST RENAMED', 'the new label was saved');
    ok(substr($row['start_time'], 0, 5) === '06:30', 'the new start was saved');
    ok((int) $row['is_active'] === 0, 'it was disabled by the update');

    $r = time_slot_set_active($newId, 0);
    ok($r['ok'] === false, 'disabling an already disabled slot is refused');
    $r = time_slot_set_active($newId, 1);
    ok($r['ok'] === true, 'it can be enabled again');
    $r = time_slot_set_active($newId, 1);
    ok($r['ok'] === false, 'enabling an already enabled slot is refused');

    $r = time_slot_delete($newId);
    ok($r['ok'] === true, 'an unused slot is deleted');
    ok(time_slot_find($newId) === null, 'it is really gone');
}

/* error paths */
$r = time_slot_update(999999, array('label' => 'X', 'start_time' => '08:00', 'end_time' => '09:00', 'is_active' => '1'));
ok($r['ok'] === false, 'updating a missing slot is refused');
$r = time_slot_set_active(999999, 1);
ok($r['ok'] === false, 'toggling a missing slot is refused');
$r = time_slot_delete(999999);
ok($r['ok'] === false, 'deleting a missing slot is refused');

/* ================================================= integration with booking */

/* ============================================ a created slot is bookable */

section('a new bookable slot is offered to the booking form');

$probe = time_slot_create(array(
    'label' => 'PROBE SLOT', 'start_time' => '06:00', 'end_time' => '07:30', 'is_active' => '1',
));
if ($probe['ok']) {
    $probeId = (int) $probe['id'];

    $active = active_time_slots();
    $found  = false;
    foreach ($active as $s) {
        if ((int) $s['id'] === $probeId) { $found = true; }
    }
    ok($found, 'the new slot appears in active_time_slots()');

    $start = time_to_minutes('06:30');
    $end   = time_to_minutes('07:00');
    $holder = slot_containing($start, $end);
    ok($holder !== null, 'a period inside the new slot is accepted by slot_containing()');
    if ($holder !== null) {
        ok((int) $holder['id'] === $probeId, 'and it resolves to the new slot');
    }

    // A period outside it must not resolve to it.
    $outside = slot_containing(time_to_minutes('08:00'), time_to_minutes('09:00'));
    ok($outside === null || (int) $outside['id'] !== $probeId,
        'a period outside the new slot does not resolve to it');

    // Disabling removes it from the offer.
    ok(time_slot_set_active($probeId, 0)['ok'] === true, 'the probe slot is disabled');
    $holder = slot_containing(time_to_minutes('06:30'), time_to_minutes('07:00'));
    ok($holder === null || (int) $holder['id'] !== $probeId,
        'a disabled slot is no longer accepted by slot_containing()');

    ok(time_slot_delete($probeId)['ok'] === true, 'the probe slot is cleaned up');
}

/* ================================== a used window is kept, not deleted */

section('a window that is in use cannot be deleted');

$used = time_slot_create(array(
    'label' => 'USED SLOT', 'start_time' => '04:00', 'end_time' => '05:00', 'is_active' => '1',
));
if ($used['ok']) {
    $usedId = (int) $used['id'];

    // A booking sitting inside the window, but not matching its two times
    // exactly. Counting identical pairs alone would call the window unused.
    $labId = (int) db_value("SELECT id FROM laboratories WHERE status = 'available' ORDER BY id LIMIT 1");
    $userId = (int) db_value("SELECT id FROM users WHERE role = 'user' ORDER BY id LIMIT 1");
    $today = booking_today();

    db_insert(
        'INSERT INTO bookings (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time, purpose, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        array('BK-USED-' . $usedId, $userId, $labId, $today, '04:15:00', '04:45:00', 'used slot probe', 'pending')
    );
    $probeBooking = (int) db_value('SELECT id FROM bookings WHERE booking_ref = ?', array('BK-USED-' . $usedId));

    $counts = time_slot_booking_counts();
    ok(isset($counts[$usedId]) && $counts[$usedId] === 1,
        'a booking inside the window is counted even when the times differ');

    $r = time_slot_delete($usedId);
    ok($r['ok'] === false, 'deleting a window that is in use is refused');
    ok(time_slot_find($usedId) !== null, 'the window is still there');
    ok(strpos($r['error'], 'inside it') !== false, 'the refusal says why');

    /* The row is removed by hand below, because the helper now refuses to. */
    db_exec('DELETE FROM bookings WHERE id = ?', array($probeBooking));

    $counts = time_slot_booking_counts();
    ok(isset($counts[$usedId]) && $counts[$usedId] === 0,
        'once the booking is gone the window reads as unused');

    ok(time_slot_delete($usedId)['ok'] === true, 'and only then can it be deleted');
    ok(time_slot_find($usedId) === null, 'it is really gone');
}

/* ================================================ straddling is refused */

section('a booking may not straddle two windows');

$touching = time_slot_create(array(
    'label' => 'STRADDLE A', 'start_time' => '05:00', 'end_time' => '06:00', 'is_active' => '1',
));
$touching2 = time_slot_create(array(
    'label' => 'STRADDLE B', 'start_time' => '06:00', 'end_time' => '07:00', 'is_active' => '1',
));
if ($touching['ok'] && $touching2['ok']) {
    $a = (int) $touching['id'];
    $b = (int) $touching2['id'];
    ok(slot_containing(time_to_minutes('05:30'), time_to_minutes('05:45')) !== null,
        'a period inside the first window is fine');
    ok(slot_containing(time_to_minutes('05:30'), time_to_minutes('06:30')) === null,
        'a period spanning both windows is refused');

    time_slot_delete($a);
    time_slot_delete($b);
}

/* ================================================================ totals */

section('list helpers');

$all = time_slot_list();
ok(is_array($all), 'time_slot_list returns an array');
ok(count($all) === count(db_all('SELECT * FROM time_slots')), 'it returns every row');

$ordered = true;
$prev = -1;
foreach ($all as $s) {
    $m = time_to_minutes($s['start_time']);
    if ($m < $prev) { $ordered = false; }
    $prev = $m;
}
ok($ordered, 'the list is ordered by start time');

$activeOnly = time_slot_active_list();
$allActive = true;
foreach ($activeOnly as $s) {
    if ((int) $s['is_active'] !== 1) { $allActive = false; }
}
ok($allActive, 'time_slot_active_list returns only bookable slots');

/* labels and badges */
ok(time_slot_active_label(1) === 'Bookable', 'label for an enabled slot');
ok(time_slot_active_label(0) === 'Disabled', 'label for a disabled slot');
ok(time_slot_active_badge(1) === 'bg-success', 'badge for an enabled slot');
ok(time_slot_active_badge(0) === 'bg-secondary', 'badge for a disabled slot');

/* ================================================================ cleanup */

$after = db_all('SELECT * FROM time_slots ORDER BY id');
ok(count($after) === count($before), 'the slot count is back to where it started');

foreach (db_all('SELECT id, label FROM time_slots
                  WHERE label LIKE \'TEST%\' OR label LIKE \'PROBE%\'
                     OR label LIKE \'STRADDLE%\' OR label LIKE \'USED%\'') as $row) {
    db_exec('DELETE FROM time_slots WHERE id = ?', array((int) $row['id']));
}

$final = db_all('SELECT * FROM time_slots ORDER BY id');
ok(count($final) === count($before), 'no probe rows were left behind');

/* ================================================================ report */

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
if ($fail > 0) {
    echo "\nFailures:\n";
    foreach ($failures as $f) {
        echo "  - $f\n";
    }
}
exit($fail === 0 ? 0 : 1);
