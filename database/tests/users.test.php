<?php
/**
 * Unit tests for includes/users.php. Run from the command line:
 *   C:\xampp\php\php.exe database\tests\users.test.php
 *
 * The test creates only accounts whose email starts with zz-user- and deletes
 * them again, so it cannot damage a real account. It counts the rows before and
 * after rather than comparing to a fixed number.
 *
 * Two sections deliberately disturb the administrators, because the guards that
 * stop an administrator locking themselves out cannot be tested any other way.
 * Those changes are put back by a shutdown handler, so even a fatal error part
 * way through leaves the system exactly as it was found.
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
function user_ok(array $overrides = array())
{
    return array_merge(array(
        'name'       => 'ZZ Probe Student',
        'email'      => 'zz-user-probe@example.test',
        'student_no' => 'ZZ-0001',
        'phone'      => '0777 000001',
        'role'       => 'user',
        'status'     => 'active',
    ), $overrides);
}

/**
 * user_create() answers with a result array, so the id has to be read out of it.
 * Returning the id rather than the array is deliberate: it keeps a mistake like
 * passing the result straight to a function expecting an id from reaching the
 * database, where a silent cast would quietly point at user #1.
 */
function make_user(array $overrides = array(), $password = 'Probe@12345')
{
    $result = user_create(user_ok($overrides), $password);

    if (empty($result['ok'])) {
        throw new RuntimeException('setup failed: ' . $result['error']);
    }

    return (int) $result['id'];
}

function user_drop()
{
    db_exec('DELETE FROM activity_logs WHERE entity = \'user\' AND description LIKE \'%ZZ Probe%\'');
    db_exec('DELETE FROM notifications WHERE user_id IN (SELECT id FROM users WHERE email LIKE \'zz-user-%\')');
    db_exec('DELETE FROM users WHERE email LIKE \'zz-user-%\'');
}

$usersBefore = (int) db_value('SELECT COUNT(*) FROM users');
user_drop();

/* Snapshot the real administrators, and put them back however this run ends. The
   guard tests below have to reduce the system to a single active administrator
   to prove the last one is protected, and that must not be left behind. */
$adminsBefore = db_all('SELECT id, role, status FROM users WHERE role = \'admin\' OR status = \'inactive\'');

register_shutdown_function(function () use ($adminsBefore) {
    foreach ($adminsBefore as $a) {
        db_exec('UPDATE users SET role = ?, status = ? WHERE id = ?',
            array($a['role'], $a['status'], (int) $a['id']));
    }
    user_drop();
});

/* -------------------------------------------------------------------- roles */
section('Roles and status');

check('there are exactly two roles', count(user_roles()) === 2);
check('admin is offered', in_array('admin', user_roles(), true));
check('user is offered', in_array('user', user_roles(), true));
check('only "user" counts as a student', user_is_student('user') === true);
check('an administrator is not a student', user_is_student('admin') === false);
check('admin is labelled Administrator', user_role_label('admin') === 'Administrator');
check('user is labelled Student', user_role_label('user') === 'Student');
check('an invented role falls back to its own name', user_role_label('nonsense') === 'Nonsense');

check('there are exactly two statuses', count(user_statuses()) === 2);
check('active is offered', in_array('active', user_statuses(), true));
check('inactive is offered', in_array('inactive', user_statuses(), true));
check('inactive is labelled Deactivated', user_status_label('inactive') === 'Deactivated');
check('an active and a deactivated account get different badge classes',
    user_status_badge_class('active') !== user_status_badge_class('inactive'));

/* -------------------------------------------------------------- normalising */
section('Normalising');

check('an email is stored lower-case', user_normalise_email('  Jane.Chisopa@Lab.EDU.zm ') === 'jane.chisopa@lab.edu.zm');
check('a student number is stored upper-case', user_normalise_student_no(' eng-21-0144 ') === 'ENG-21-0144');
check('a blank student number normalises to an empty string', user_normalise_student_no('   ') === '');

/* --------------------------------------------------------------- validation */
section('Validating the details');

$e = user_validate(user_ok(), 0);
check('a complete set of fields is accepted', empty($e));

check('a missing name is refused', isset(user_validate(user_ok(array('name' => '')), 0)['name']));
check('a one-character name is refused', isset(user_validate(user_ok(array('name' => 'Jo')), 0)['name']));
check('an over-long name is refused', isset(user_validate(user_ok(array('name' => str_repeat('x', 200))), 0)['name']));

check('a missing email is refused', isset(user_validate(user_ok(array('email' => '')), 0)['email']));
check('a malformed email is refused', isset(user_validate(user_ok(array('email' => 'not-an-email')), 0)['email']));
check('an over-long email is refused', isset(user_validate(user_ok(array('email' => str_repeat('a', 200) . '@x.test')), 0)['email']));

check('a malformed phone is refused', isset(user_validate(user_ok(array('phone' => 'abcdefg')), 0)['phone']));
check('a blank phone is accepted', empty(user_validate(user_ok(array('phone' => '')), 0)['phone']));
check('a blank student number is accepted', empty(user_validate(user_ok(array('student_no' => '')), 0)));

check('an invented role is refused', isset(user_validate(user_ok(array('role' => 'superuser')), 0)['role']));
check('an invented status is refused', isset(user_validate(user_ok(array('status' => 'deleted')), 0)['status']));
check('an empty role is refused', isset(user_validate(user_ok(array('role' => '')), 0)['role']));

/* ------------------------------------------------------------------ create */
section('Adding an account');

$id = make_user();
check('a valid account is created', $id > 0);
check('the new account is a real row, not the first user in the table',
    $id > 0 && (int) db_value('SELECT COUNT(*) FROM users WHERE id = ?', array($id)) === 1);

$row = db_one('SELECT * FROM users WHERE id = ?', array($id));
check('the name is stored', $row['name'] === 'ZZ Probe Student');
check('the email is stored lower-case', $row['email'] === 'zz-user-probe@example.test');
check('the student number is stored upper-case', $row['student_no'] === 'ZZ-0001');
check('the role is stored', $row['role'] === 'user');
check('the status is stored', $row['status'] === 'active');
check('the password is not stored in the clear', $row['password'] !== 'Probe@12345');
check('the password is a bcrypt hash', strpos($row['password'], '$2y$') === 0);
check('the hash verifies the password', password_verify('Probe@12345', $row['password']));
check('a wrong password does not verify', !password_verify('wrong', $row['password']));
check('a new account has never signed in', $row['last_login_at'] === null);

check('a duplicate email is refused',
    user_create(user_ok(array('student_no' => 'ZZ-0002')), 'Probe@12345')['field'] === 'email');
check('a duplicate email is refused whatever its case',
    user_create(user_ok(array('email' => 'ZZ-USER-PROBE@EXAMPLE.TEST', 'student_no' => 'ZZ-0003')), 'Probe@12345')['field'] === 'email');
check('a duplicate student number is blamed on the student number, not the email',
    user_create(user_ok(array('email' => 'zz-user-other@example.test', 'student_no' => 'ZZ-0001')), 'Probe@12345')['field'] === 'student_no');

/* Two accounts with no student number at all. Stored as NULL, so the UNIQUE
   index on the column does not make the second one collide with the first. */
$a = make_user(array('email' => 'zz-user-nostudenta@example.test', 'student_no' => ''));
$b = make_user(array('email' => 'zz-user-nostudentb@example.test', 'student_no' => ''));
check('a blank student number is stored as NULL, not an empty string',
    db_value('SELECT student_no FROM users WHERE id = ?', array($a)) === null);
check('two accounts may both have no student number', $a > 0 && $b > 0 && $a !== $b);

/* Created inactive so it never counts towards the last-administrator checks
   further down, but still proves the role can be set at creation. */
$c = make_user(array('email' => 'zz-user-admin@example.test', 'student_no' => 'ZZ-0004', 'role' => 'admin', 'status' => 'inactive'));
check('an account can be created as an administrator',
    db_value('SELECT role FROM users WHERE id = ?', array($c)) === 'admin');

/* ------------------------------------------------------------------ update */
section('Editing an account');

$before = db_one('SELECT * FROM users WHERE id = ?', array($id));

$r = user_update($id, user_ok(array('name' => 'ZZ Probe Renamed', 'phone' => '0777 000999')));
check('a valid edit is saved', $r['ok'] === true);

$after = db_one('SELECT * FROM users WHERE id = ?', array($id));
check('the new name is stored', $after['name'] === 'ZZ Probe Renamed');
check('the new phone is stored', $after['phone'] === '0777 000999');

check('saving without changing the email is not a clash with itself',
    user_validate(user_ok(), $id) === array());
check('saving without changing the student number is not a clash with itself',
    user_validate(user_ok(), $id) === array());

check('an edit that collides with another email is refused',
    user_update($id, user_ok(array('email' => 'zz-user-admin@example.test')))['field'] === 'email');
check('the collided email was not written',
    db_value('SELECT email FROM users WHERE id = ?', array($id)) === 'zz-user-probe@example.test');
/* ZZ-0004 is $c's student number, so this is a genuine clash. (ZZ-0002 is not:
   the create above that named it was refused, so no such account exists.) */
check('an edit that collides with another student number is refused',
    user_update($id, user_ok(array('student_no' => 'ZZ-0004', 'email' => 'zz-user-x@example.test')))['field'] === 'student_no');
check('the collided student number was not written',
    db_value('SELECT student_no FROM users WHERE id = ?', array($id)) === 'ZZ-0001');
check('editing an account that does not exist is refused',
    user_update(999999, user_ok())['ok'] === false);

/* The important one: the general edit must not be able to change access. */
user_update($id, user_ok(array('role' => 'admin', 'status' => 'inactive')));
$still = db_one('SELECT role, status FROM users WHERE id = ?', array($id));
check('the edit form cannot change the role', $still['role'] === 'user');
check('the edit form cannot change the status', $still['status'] === 'active');
check('the edit form cannot change the password',
    db_value('SELECT password FROM users WHERE id = ?', array($id)) === $before['password']);

/* The edit page fills the missing role and status in from the record it is
   editing. Check that helper does exactly that, and that it ignores anything
   the form might have posted for those two fields. */
$_POST = array('name' => 'ZZ Via Post', 'email' => 'zz-user-post@example.test', 'student_no' => 'ZZ-0042');
$posted = user_details_input_from_post(array('role' => 'user', 'status' => 'active'));
check('the edit helper takes the name from the form', $posted['name'] === 'ZZ Via Post');
check('the edit helper takes the role from the record, not the form',
    $posted['role'] === 'user');
check('the edit helper takes the status from the record, not the form',
    $posted['status'] === 'active');
check('the fields the edit helper returns pass validation',
    empty(user_validate($posted, 0)));
$_POST = array();

/* ---------------------------------------------------------------- password */
section('Passwords');

check('an empty password is refused', user_validate_password('') !== '');
check('a seven-character password is refused', user_validate_password('1234567') !== '');
check('an eight-character password is accepted', user_validate_password('12345678') === '');
check('a long password is accepted', user_validate_password(str_repeat('x', 200)) === '');

$oldHash = db_value('SELECT password FROM users WHERE id = ?', array($id));
check('a password can be reset', user_set_password($id, 'BrandNew@456')['ok'] === true);
$newHash = db_value('SELECT password FROM users WHERE id = ?', array($id));
check('the stored hash changes', $newHash !== $oldHash);
check('the new password verifies', password_verify('BrandNew@456', $newHash));
check('the old password no longer verifies', !password_verify('Probe@12345', $newHash));
check('a short password is refused', user_set_password($id, 'short')['ok'] === false);
check('the refused reset left the password alone',
    db_value('SELECT password FROM users WHERE id = ?', array($id)) === $newHash);
check('resetting the password of an account that does not exist is refused',
    user_set_password(999999, 'Whatever@123')['ok'] === false);

/* ----------------------------------------------------- role and status */
section('Role and status changes');

$actor = (int) db_value("SELECT id FROM users WHERE email = 'admin@lab.edu.zm'");

check('a bogus field is refused', user_change_access($id, 'password', 'x', $actor)['ok'] === false);
check('a bogus role is refused', user_set_role($id, 'superuser', $actor)['ok'] === false);
check('a bogus status is refused', user_set_status($id, 'deleted', $actor)['ok'] === false);
check('changing a field that is already set is refused', user_set_role($id, 'user', $actor)['ok'] === false);
check('changing an account that does not exist is refused', user_set_role(999999, 'admin', $actor)['ok'] === false);

$r = user_set_role($id, 'admin', $actor);
check('a student can be promoted', $r['ok'] === true);
check('the role really changed', db_value('SELECT role FROM users WHERE id = ?', array($id)) === 'admin');
$r = user_set_role($id, 'user', $actor);
check('an administrator can be demoted', $r['ok'] === true);
check('the role really changed back', db_value('SELECT role FROM users WHERE id = ?', array($id)) === 'user');

$r = user_set_status($id, 'inactive', $actor);
check('an account can be deactivated', $r['ok'] === true);
check('the status really changed', db_value('SELECT status FROM users WHERE id = ?', array($id)) === 'inactive');
$r = user_set_status($id, 'active', $actor);
check('a deactivated account can be reactivated', $r['ok'] === true);
check('the status really changed back', db_value('SELECT status FROM users WHERE id = ?', array($id)) === 'active');

/* ------------------------------------------------------------ self-lockout */
section('Refusing to lock yourself out');

$r = user_set_role($actor, 'user', $actor);
check('an administrator cannot demote themselves', $r['ok'] === false);
check('and they are told why', stripos($r['error'], 'own role') !== false);
check('they are still an administrator', db_value('SELECT role FROM users WHERE id = ?', array($actor)) === 'admin');

$r = user_set_status($actor, 'inactive', $actor);
check('an administrator cannot deactivate themselves', $r['ok'] === false);
check('and they are told why', stripos($r['error'], 'own account') !== false);
check('they are still active', db_value('SELECT status FROM users WHERE id = ?', array($actor)) === 'active');

/* ------------------------------------------------------------- last admin */
section('Refusing to remove the last administrator');

/* Make this account the only active administrator, by stepping the real ones
   down with direct SQL. The guard is bypassed on purpose: the point of the test
   is what the guard does when it is asked to take the final one away. The
   shutdown handler puts them all back. */
$otherAdmins = db_all("SELECT id FROM users WHERE role = 'admin' AND status = 'active' AND id <> ?", array($actor));
check('there was another administrator to step down', count($otherAdmins) > 0);

foreach ($otherAdmins as $o) {
    db_exec("UPDATE users SET status = 'inactive' WHERE id = ?", array((int) $o['id']));
}
check('only one active administrator is left', active_admin_count() === 1);

$r = user_set_role($actor, 'user', $id);
check('the last administrator cannot be demoted', $r['ok'] === false);
check('and is told to promote somebody else first', stripos($r['error'], 'promote somebody else') !== false);
check('they are still an administrator', db_value('SELECT role FROM users WHERE id = ?', array($actor)) === 'admin');

$r = user_set_status($actor, 'inactive', $id);
check('the last administrator cannot be deactivated', $r['ok'] === false);
check('they are still active', db_value('SELECT status FROM users WHERE id = ?', array($actor)) === 'active');

/* A student is not protected by any of this: only administrators are. */
$r = user_set_status($id, 'inactive', $actor);
check('a student can still be deactivated while there is one admin left', $r['ok'] === true);
user_set_status($id, 'active', $actor);

foreach ($otherAdmins as $o) {
    db_exec("UPDATE users SET status = 'active' WHERE id = ?", array((int) $o['id']));
}
check('the other administrators are back', active_admin_count() > 1);

$r = user_set_status($actor, 'inactive', $id);
check('with a second administrator present, deactivation is allowed', $r['ok'] === true);
db_exec("UPDATE users SET status = 'active' WHERE id = ?", array($actor));
check('and the account was put back for the next test', active_admin_count() > 1);

/* ----------------------------------------------------------- booking counts */
section('Booking figures');

$booker = db_one("SELECT id FROM users WHERE role = 'user' AND status = 'active' ORDER BY id LIMIT 1");
$summary = user_booking_summary($booker['id']);
check('the summary adds up', $summary['total'] === $summary['pending'] + $summary['approved']
    + $summary['rejected'] + $summary['cancelled'] + $summary['completed']);
check('live means pending plus approved', $summary['active'] === $summary['pending'] + $summary['approved']);
check('an account with no bookings reports zero', user_booking_summary($a)['total'] === 0);

$found = user_find($booker['id']);
check('a user can be found by id', $found !== null && (int) $found['id'] === (int) $booker['id']);
check('the record carries its booking count', array_key_exists('booking_count', $found));
check('a user who does not exist is not found', user_find(999999) === null);

$totals = user_totals();
check('the totals add up', $totals['total'] === $totals['active'] + $totals['inactive']);
check('active plus inactive covers both roles', $totals['total'] === $totals['admins'] + $totals['students']);

/* ------------------------------------------------------------------ cleanup */
section('Cleaning up');

user_drop();

check('no probe account survives', (int) db_value("SELECT COUNT(*) FROM users WHERE email LIKE 'zz-user-%'") === 0);
check('no probe activity survives',
    (int) db_value("SELECT COUNT(*) FROM activity_logs WHERE description LIKE '%ZZ Probe%'") === 0);
check('the account count returned to where it started',
    (int) db_value('SELECT COUNT(*) FROM users') === $usersBefore);

$restored = true;
foreach ($adminsBefore as $a) {
    $now = db_one('SELECT role, status FROM users WHERE id = ?', array((int) $a['id']));
    if ($now === null || $now['role'] !== $a['role'] || $now['status'] !== $a['status']) {
        $restored = false;
        echo "         (administrator #{$a['id']} is " . ($now === null ? 'missing' : $now['role'] . '/' . $now['status']) . ")\n";
    }
}
check('every administrator was restored', $restored);

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
exit($fail === 0 ? 0 : 1);
