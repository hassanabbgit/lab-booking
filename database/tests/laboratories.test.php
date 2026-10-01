<?php
/**
 * Unit tests for includes/laboratories.php. Run from the command line:
 *   C:\xampp\php\php.exe database\tests\laboratories.test.php
 *
 * The test only ever touches laboratories whose name starts with ZZ-LAB-, so it
 * cannot damage the seeded facility. It counts the rows before and after rather
 * than comparing to a fixed number, so a real laboratory being added while the
 * test runs does not make it fail.
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

/** A valid set of fields, so each test can change exactly one thing. */
function lab_ok(array $overrides = array())
{
    return array_merge(array(
        'name'           => 'ZZ-LAB-Probe',
        'location'       => 'Block Z - Room 999',
        'capacity'       => '40',
        'computer_count' => '40',
        'description'    => 'Created by laboratories.test.php',
        'status'         => 'available',
    ), $overrides);
}

function lab_drop()
{
    db_exec('DELETE FROM laboratories WHERE name LIKE \'ZZ-LAB-%\'');
}

$labsBefore = (int) db_value('SELECT COUNT(*) FROM laboratories');
lab_drop();

/* ----------------------------------------------------------------- statuses */
section('Statuses');

check('there are exactly three statuses', count(laboratory_statuses()) === 3);
check('available is offered', in_array('available', laboratory_statuses(), true));
check('maintenance is offered', in_array('maintenance', laboratory_statuses(), true));
check('inactive is offered', in_array('inactive', laboratory_statuses(), true));
check('only available counts as bookable', laboratory_is_bookable('available') === true);
check('maintenance is not bookable', laboratory_is_bookable('maintenance') === false);
check('inactive is not bookable', laboratory_is_bookable('inactive') === false);
check('a nonsense status is not bookable', laboratory_is_bookable('nonsense') === false);
check('the label reads as a sentence', laboratory_status_label('maintenance') === 'In maintenance');
check('an unknown label falls back rather than blanking', laboratory_status_label('zzz') === 'Zzz');

/* ----------------------------------------------------------------- creation */
section('Adding');

check('a complete set of fields validates', laboratory_validate(lab_ok(), 0) === array());

$made = laboratory_create(lab_ok());
check('a laboratory is created', $made['ok'] === true, $made['error']);
$newId = (int) $made['id'];

if ($newId > 0) {
    $row = db_one('SELECT * FROM laboratories WHERE id = ?', array($newId));
    check('it is readable afterwards', $row !== null);
    check('the name is stored', $row['name'] === 'ZZ-LAB-Probe');
    check('the location is stored', $row['location'] === 'Block Z - Room 999');
    check('capacity is stored as a number', (int) $row['capacity'] === 40);
    check('the computer count is stored', (int) $row['computer_count'] === 40);
    check('the status is stored', $row['status'] === 'available');
    check('it is immediately bookable', laboratory_is_bookable($row['status']));
}

check('the description is stored as given',
    db_value('SELECT description FROM laboratories WHERE id = ?', array($newId))
        === 'Created by laboratories.test.php');

$noDesc = laboratory_create(lab_ok(array(
    'name' => 'ZZ-LAB-NoDesc', 'description' => '   ')));
check('whitespace-only description is treated as none', $noDesc['ok'] === true, $noDesc['error']);
if ($noDesc['ok']) {
    check('and it is stored as NULL',
        db_value('SELECT description FROM laboratories WHERE id = ?', array($noDesc['id'])) === null);
}

/* --------------------------------------------------------------- validation */
section('Validation');

check('a missing name is refused', isset(laboratory_validate(lab_ok(array('name' => '')), 0)['name']));
check('a one-character name is refused',
    isset(laboratory_validate(lab_ok(array('name' => 'A')), 0)['name']));
check('an over-long name is refused',
    isset(laboratory_validate(lab_ok(array('name' => str_repeat('x', LABORATORY_NAME_MAX + 1))), 0)['name']));
check('a name at the limit is allowed',
    laboratory_validate(lab_ok(array('name' => str_repeat('x', LABORATORY_NAME_MAX))), 0) === array());

check('a duplicate name is refused',
    isset(laboratory_validate(lab_ok(array('name' => 'ZZ-LAB-Probe')), 0)['name']));
check('a real seeded name is refused',
    isset(laboratory_validate(lab_ok(array('name' => 'Computer Laboratory 1')), 0)['name']));
check('saving a laboratory under its own name is allowed',
    laboratory_validate(lab_ok(), $newId) === array());

check('a missing location is refused', isset(laboratory_validate(lab_ok(array('location' => '')), 0)['location']));
check('an over-long location is refused',
    isset(laboratory_validate(lab_ok(array('location' => str_repeat('y', LABORATORY_LOCATION_MAX + 1))), 0)['location']));

check('a blank capacity is refused', isset(laboratory_validate(lab_ok(array('capacity' => '')), 0)['capacity']));
check('a capacity of zero is refused', isset(laboratory_validate(lab_ok(array('capacity' => '0')), 0)['capacity']));
check('a negative capacity is refused', isset(laboratory_validate(lab_ok(array('capacity' => '-5')), 0)['capacity']));
check('a decimal capacity is refused', isset(laboratory_validate(lab_ok(array('capacity' => '40.5')), 0)['capacity']));
check('a non-numeric capacity is refused',
    isset(laboratory_validate(lab_ok(array('capacity' => 'forty')), 0)['capacity']));
check('a capacity with a leading plus is refused',
    isset(laboratory_validate(lab_ok(array('capacity' => '+40')), 0)['capacity']));
check('a capacity beyond the maximum is refused',
    isset(laboratory_validate(lab_ok(array('capacity' => (string) (LABORATORY_MAX_CAPACITY + 1))), 0)['capacity']));
// Each "allowed" case needs its own name: the probe above already holds
// ZZ-LAB-Probe, and a clash would be reported as a name error and mask the
// capacity rule being tested.
check('a capacity of one is allowed',
    laboratory_validate(lab_ok(array('name' => 'ZZ-LAB-Seat1', 'capacity' => '1', 'computer_count' => '1')), 0) === array());

check('a missing computer count is refused',
    isset(laboratory_validate(lab_ok(array('computer_count' => '')), 0)['computer_count']));
check('more computers than seats is refused',
    isset(laboratory_validate(lab_ok(array('name' => 'ZZ-LAB-Over', 'capacity' => '10', 'computer_count' => '11')), 0)['computer_count']));
check('as many computers as seats is allowed',
    laboratory_validate(lab_ok(array('name' => 'ZZ-LAB-Equal', 'capacity' => '10', 'computer_count' => '10')), 0) === array());
check('fewer computers than seats is allowed',
    laboratory_validate(lab_ok(array('name' => 'ZZ-LAB-Fewer', 'capacity' => '40', 'computer_count' => '0')), 0) === array());

check('an over-long description is refused',
    isset(laboratory_validate(lab_ok(array(
        'description' => str_repeat('d', LABORATORY_DESCRIPTION_MAX + 1))), 0)['description']));

check('a blank status is refused', isset(laboratory_validate(lab_ok(array('status' => '')), 0)['status']));
check('an invented status is refused', isset(laboratory_validate(lab_ok(array('status' => 'closed')), 0)['status']));

check('surrounding spaces are trimmed rather than stored',
    laboratory_validate(lab_ok(array('name' => '  ZZ-LAB-Probe  ')), $newId) === array());

/* ------------------------------------------------------------------ editing */
section('Editing');

$updated = laboratory_update($newId, lab_ok(array(
    'name' => 'ZZ-LAB-Renamed', 'location' => 'Block Z - Room 998',
    'capacity' => '25', 'computer_count' => '20', 'description' => 'Now different')));
check('a laboratory is updated', $updated['ok'] === true, $updated['error']);

$row = db_one('SELECT * FROM laboratories WHERE id = ?', array($newId));
check('the new name is stored', $row['name'] === 'ZZ-LAB-Renamed');
check('the new capacity is stored', (int) $row['capacity'] === 25);
check('the new description is stored', $row['description'] === 'Now different');

check('a blank description clears the column',
    laboratory_update($newId, lab_ok(array('name' => 'ZZ-LAB-Renamed', 'description' => '')))['ok'] === true
    && db_value('SELECT description FROM laboratories WHERE id = ?', array($newId)) === null);

check('updating a laboratory that does not exist is refused',
    laboratory_update(999999, lab_ok())['ok'] === false);
check('and it says so', laboratory_update(999999, lab_ok())['error'] !== '');

/* ------------------------------------------------------------------- status */
section('Status changes');

check('a laboratory can go to maintenance',
    laboratory_set_status($newId, 'maintenance')['ok'] === true);
check('the status is stored',
    db_value('SELECT status FROM laboratories WHERE id = ?', array($newId)) === 'maintenance');
check('and it is no longer bookable',
    laboratory_is_bookable(db_value('SELECT status FROM laboratories WHERE id = ?', array($newId))) === false);

check('a laboratory can come back into service',
    laboratory_set_status($newId, 'available')['ok'] === true);
check('a laboratory can be made inactive',
    laboratory_set_status($newId, 'inactive')['ok'] === true);
check('an unknown status is refused', laboratory_set_status($newId, 'closed')['ok'] === false);
check('setting the status it already has is refused',
    laboratory_set_status($newId, 'inactive')['ok'] === false);
check('changing the status of a missing laboratory is refused',
    laboratory_set_status(999999, 'available')['ok'] === false);

/* ---------------------------------------------------------------- deletion */
section('Deletion');

check('a laboratory with no bookings can be deleted',
    laboratory_delete($newId)['ok'] === true);
check('it is really gone',
    db_one('SELECT id FROM laboratories WHERE id = ?', array($newId)) === null);

$withBookings = (int) db_value('SELECT laboratory_id FROM bookings LIMIT 1');
$blocked = laboratory_delete($withBookings);
check('a laboratory that has been booked cannot be deleted', $blocked['ok'] === false);
check('the refusal explains why', strpos($blocked['error'], 'cannot be deleted') !== false);
check('and suggests taking it out of service',
    strpos($blocked['error'], 'inactive') !== false || strpos($blocked['error'], 'maintenance') !== false);
check('the laboratory survives the attempt',
    db_one('SELECT id FROM laboratories WHERE id = ?', array($withBookings)) !== null);
check('deleting a laboratory that does not exist is refused',
    laboratory_delete(999999)['ok'] === false);

/* ----------------------------------------------------------------- totals */
section('Totals');

$totals = laboratory_totals();
check('the total is a positive number', $totals['total'] > 0);
check('the three status counts add up to the total',
    $totals['available'] + $totals['maintenance'] + $totals['inactive'] === $totals['total']);
check('seats and computers are counted',
    $totals['seats'] > 0 && $totals['computers'] > 0);

$summary = laboratory_booking_summary(1);
check('a booking summary has every key it promises',
    array('total', 'active', 'pending', 'approved', 'rejected', 'cancelled', 'completed')
    === array_keys($summary));
check('active is pending plus approved',
    $summary['active'] === $summary['pending'] + $summary['approved']);
check('the status counts add up to the total',
    $summary['pending'] + $summary['approved'] + $summary['rejected']
        + $summary['cancelled'] + $summary['completed'] === $summary['total']);
check('a laboratory with no bookings summarises to zeroes',
    laboratory_booking_summary(999999)['total'] === 0);

/* ----------------------------------------------------------------- cleanup */
section('Cleanup');

lab_drop();
$labsAfter = (int) db_value('SELECT COUNT(*) FROM laboratories');
check('this test left no laboratories behind', $labsAfter === $labsBefore,
    "was $labsBefore, now $labsAfter");
check('no probe name survives',
    (int) db_value('SELECT COUNT(*) FROM laboratories WHERE name LIKE \'ZZ-LAB-%\'') === 0);

echo "\nPASS: $pass   FAIL: $fail\n";
exit($fail === 0 ? 0 : 1);
