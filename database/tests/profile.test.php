<?php
/**
 * Unit tests for includes/profile.php.
 *
 *   C:\xampp\php\php.exe database\tests\profile.test.php
 *
 * Everything happens on a throwaway account whose email starts with
 * zz-profile-, and its picture is written to the account's own slot in
 * public/uploads/avatars. Both are removed by a shutdown handler, so even a
 * fatal error part way through leaves the system as it was found. The seeded
 * accounts are never touched, except for reading the real password hash of one
 * administrator, which is only ever used to check that a wrong current password
 * is refused.
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

$probeId  = 0;
$probeRef = 'zz-profile-' . getmypid();

register_shutdown_function(function () use (&$probeId, $probeRef) {
    if ($probeId > 0) {
        try {
            db_exec('DELETE FROM activity_logs WHERE user_id = ?', array($probeId));
            db_exec('DELETE FROM users WHERE id = ?', array($probeId));
        } catch (Exception $e) {
            error_log('profile test cleanup failed: ' . $e->getMessage());
        }
    }

    if (is_file(profile_avatar_file($probeId))) {
        @unlink(profile_avatar_file($probeId));
    }

    try {
        $left = (int) db_value('SELECT COUNT(*) FROM users WHERE email LIKE ?', array($probeRef . '%'));
        if ($left > 0) {
            echo "\nWARNING: $left probe account(s) survived cleanup\n";
        }
    } catch (Exception $e) {
        // Nothing more to do this far into shutdown.
    }
});

$created = user_create(array(
    'name'       => 'ZZ Profile Probe',
    'email'      => $probeRef . '@example.test',
    'student_no' => 'ZZP-' . getmypid(),
    'phone'      => '0777 000999',
    'role'       => 'user',
    'status'     => 'active',
), 'OriginalPass1');

ok($created['ok'], 'the probe account was created');
ok($created['ok'] === true && $created['id'] > 0, 'the new account has an id');
$probeId = (int) $created['id'];

/* ============================================================== paths */

section('the stored picture path is derived, never supplied');

ok(profile_avatar_stored_path(7) === 'public/uploads/avatars/7.jpg',
    'the stored path is built from the account id');
ok(preg_match('#^public/uploads/avatars/[0-9]+\.jpg$#', profile_avatar_stored_path(7)) === 1,
    'the stored path always matches the shape the page will accept');
ok(strpos(profile_avatar_stored_path(7), '..') === false,
    'the stored path cannot contain a traversal');

/* A value pointing somewhere else must be refused rather than rendered. */
section('a tampered avatar column is not turned into a URL');

$forged = array('id' => 1, 'avatar' => '../../includes/config.php');
ok(profile_avatar_url($forged) === '', 'a traversal path yields no URL');
ok(profile_avatar_url(array('id' => 1, 'avatar' => 'public/uploads/avatars/9.jpg')) === '',
    'a path for a file that is not there yields no URL');
ok(profile_avatar_url(array('id' => 1, 'avatar' => '')) === '', 'no picture yields no URL');
ok(profile_avatar_url(array('id' => 1)) === '', 'a missing column yields no URL');
ok(!profile_has_avatar(array('id' => 1, 'avatar' => null)), 'has_avatar agrees with the URL');

/* ====================================================== upload validation */

section('a missing or broken upload is refused');

$r = profile_avatar_store($probeId, array());
ok($r['ok'] === false, 'no file at all is refused');
ok($r['error'] !== '', 'and it says so');

$r = profile_avatar_store($probeId, array('error' => UPLOAD_ERR_INI_SIZE, 'size' => 99999999));
ok($r['ok'] === false, 'an oversized upload is refused');
ok(stripos($r['error'], 'larger') !== false, 'the size refusal mentions the limit');

$r = profile_avatar_store(999999, array('error' => UPLOAD_ERR_OK, 'size' => 10));
ok($r['ok'] === false, 'an unknown account is refused');

section('a file that is not an image is refused');

$tmp = tempnam(sys_get_temp_dir(), 'zzp');
register_shutdown_function(function () use ($tmp) {
    if (is_file($tmp)) {
        @unlink($tmp);
    }
});
file_put_contents($tmp, "<?php echo 'pwned'; ?>\n");

$r = profile_avatar_read($tmp, UPLOAD_ERR_OK, filesize($tmp));
ok($r['ok'] === false, 'a PHP script disguised as a picture is refused');

/* The type rules are tested on their own, because is_uploaded_file() rejects
   every file the test suite can create. */
$checked = profile_avatar_inspect($tmp);
ok($checked['ok'] === false, 'a PHP script is not an image');
ok(stripos($checked['error'], 'not an image') !== false, 'and it is named as not an image');

/* A real GIF, so the accept path is exercised rather than only the refusals. */
$gif = imagecreatetruecolor(64, 32);
imagefilledrectangle($gif, 0, 0, 63, 31, imagecolorallocate($gif, 10, 120, 200));
imagegif($gif, $tmp);
imagedestroy($gif);

$checked = profile_avatar_inspect($tmp);
ok($checked['ok'] === true, 'a real GIF is accepted');
ok($checked['width'] === 64 && $checked['height'] === 32, 'and its dimensions are read');

$png = imagecreatetruecolor(20, 20);
imagepng($png, $tmp);
imagedestroy($png);
ok(profile_avatar_inspect($tmp)['ok'] === true, 'a real PNG is accepted');

$big = imagecreatetruecolor(PROFILE_AVATAR_MAX_SOURCE + 10, 10);
imagejpeg($big, $tmp);
imagedestroy($big);
$checked = profile_avatar_inspect($tmp);
ok($checked['ok'] === false, 'an absurdly large image is refused');
ok(stripos($checked['error'], 'too large') !== false, 'and the refusal explains why');

/* The upload path still refuses a real image that did not arrive through a
   POST, which is what is_uploaded_file() is guarding and the one thing this
   suite genuinely cannot satisfy. */
$r = profile_avatar_store($probeId, array(
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($tmp),
    'tmp_name' => $tmp,
));
ok($r['ok'] === false, 'a real image at a non-uploaded path is refused');
ok(stripos($r['error'], 'could not be read') !== false, 'the refusal explains the read failed');
ok(!is_file(profile_avatar_file($probeId)), 'nothing was written for it');

/* ====================================================== password change */

section('changing a password needs the current one');

$r = profile_change_password($probeId, '', 'NewPass1234', 'NewPass1234');
ok($r['ok'] === false, 'an empty current password is refused');
ok($r['field'] === 'current_password', 'the error is on the current password field');

$r = profile_change_password($probeId, 'WrongPass999', 'NewPass1234', 'NewPass1234');
ok($r['ok'] === false, 'a wrong current password is refused');
ok($r['field'] === 'current_password', 'the error is on the current password field');

$r = profile_change_password(999999, 'x', 'NewPass1234', 'NewPass1234');
ok($r['ok'] === false, 'an unknown account is refused');

/* The real hash has to still be in place: nothing above may have changed it. */
$stillOriginal = db_one('SELECT password FROM users WHERE id = ?', array($probeId));
ok(password_verify('OriginalPass1', $stillOriginal['password']),
    'a refused attempt leaves the original password in place');

/* Nothing above should have reached the activity log either: a rejected
   attempt is not an event worth recording. */
$refusedLogged = (int) db_value(
    'SELECT COUNT(*) FROM activity_logs WHERE entity = ? AND entity_id = ? AND action = ?',
    array('user', $probeId, 'password_change')
);
ok($refusedLogged === 0, 'a refused attempt writes nothing to the activity log');

section('a new password is checked before it is stored');

$r = profile_change_password($probeId, 'OriginalPass1', 'short', 'short');
ok($r['ok'] === false, 'a password under the minimum is refused');
ok($r['field'] === 'new_password', 'the error is on the new password field');

$r = profile_change_password($probeId, 'OriginalPass1', 'NewPass1234', 'Different1234');
ok($r['ok'] === false, 'a mismatched repeat is refused');
ok($r['field'] === 'confirm_password', 'the error is on the repeat field');

$r = profile_change_password($probeId, 'OriginalPass1', 'OriginalPass1', 'OriginalPass1');
ok($r['ok'] === false, 'reusing the current password is refused');

section('a valid change works and is logged');

$r = profile_change_password($probeId, 'OriginalPass1', 'NewPass1234', 'NewPass1234');
ok($r['ok'] === true, 'a correct current password with a valid new one succeeds');

$after = db_one('SELECT password FROM users WHERE id = ?', array($probeId));
ok(password_verify('NewPass1234', $after['password']), 'the new password verifies');
ok(!password_verify('OriginalPass1', $after['password']), 'the old password no longer verifies');
ok(strlen($after['password']) >= 60, 'it is stored as a hash, not in the clear');
ok($after['password'] !== 'NewPass1234', 'and never in the clear');

/* Matched on the account the row points at rather than on user_id, because
   log_activity() attributes the entry to whoever is signed in. In a real
   request that is the person on this page, so the two agree; here there is no
   session, and pinning the test to user_id would be asserting an accident of
   the runner rather than the behaviour. */
$logged = (int) db_value(
    'SELECT COUNT(*) FROM activity_logs WHERE entity = ? AND entity_id = ? AND action = ?',
    array('user', $probeId, 'password_change')
);
ok($logged === 1, 'the change was written to the activity log exactly once');

$row = db_one(
    'SELECT description FROM activity_logs WHERE entity = ? AND entity_id = ? AND action = ?',
    array('user', $probeId, 'password_change')
);
ok(strpos($row['description'], 'password') !== false
    && stripos($row['description'], 'new password') === false,
    'and the entry describes the change without recording the password');

/* Put it back so the rest of the suite can sign in as the probe if it needs to. */
db_exec('UPDATE users SET password = ? WHERE id = ?', array(
    password_hash('OriginalPass1', PASSWORD_DEFAULT), $probeId
));

/* ============================================ self-service cannot escalate */

section('the self-service form cannot change a role or a status');

$before = db_one(
    'SELECT role, status, name, email, phone, student_no FROM users WHERE id = ?',
    array($probeId)
);

/* Exactly what the page posts, with role, status and a student number forged
   onto it. The values go through $_POST because the helpers read the request,
   which is the whole point: the page cannot pass these by any other route. */
$_POST = array(
    'name'       => 'ZZ Profile Renamed',
    'email'      => $probeRef . '-new@example.test',
    'student_no' => 'ZZP-' . getmypid(),
    'phone'      => '0777 000123',
    'role'       => 'admin',
    'status'     => 'active',
);

$input  = profile_details_input_from_post($before);
$errors = user_validate($input, $probeId);
ok(empty($errors), 'the details pass validation, which is why user_update() has to ignore role');
ok($input['role'] === $before['role'], 'the forged role is replaced by the stored one');
ok($input['status'] === $before['status'], 'the forged status is replaced by the stored one');
ok($input['student_no'] === $before['student_no'], "a student's forged student number is ignored");
ok($input['name'] === 'ZZ Profile Renamed', 'while the fields they may edit come through');
ok($input['phone'] === '0777 000123', 'the phone too');

/* The write is handed the forged array outright, to show the last line of
   defence is user_update() itself and not the form. */
user_update($probeId, $_POST);

$after = db_one(
    'SELECT role, status, name, email, phone, student_no FROM users WHERE id = ?',
    array($probeId)
);
ok($after['role'] === $before['role'], 'the role is untouched even when the write is given one');
ok($after['name'] === 'ZZ Profile Renamed', 'the name really was updated');
ok($after['email'] === $probeRef . '-new@example.test', 'the email really was updated');
ok($after['phone'] === '0777 000123', 'the phone really was updated');

$_POST = array();

/* The same rule, from the other side: an administrator keeps their own staff
   number editable, because unlike a student's it is theirs to correct. */
$admin = db_one(
    "SELECT id, role, status, email, student_no FROM users WHERE role = 'admin' ORDER BY id LIMIT 1"
);
$_POST = array(
    'name'       => 'ZZ Admin Probe',
    'email'      => $admin['email'],
    'student_no' => 'ZZSTAFF-1',
    'phone'      => '',
    'role'       => 'admin',
    'status'     => 'active',
);
$input = profile_details_input_from_post($admin);
ok($input['student_no'] === 'ZZSTAFF-1', "an administrator's own staff number is editable");
$_POST = array();

section('an account cannot be deactivated through the profile page');

db_exec('UPDATE users SET status = \'inactive\' WHERE id = ?', array($probeId));
$deactivated = user_change_access($probeId, 'status', 'inactive', $probeId);
ok($deactivated['ok'] === false, 'the existing guard still refuses a self-deactivation');

/* ==================================================== validation reuse */

section('the details form reuses the shared user rules');

$e = user_validate(array(
    'name' => '', 'email' => 'x@example.test', 'student_no' => '',
    'phone' => '', 'role' => 'user', 'status' => 'active',
), $probeId);
ok(isset($e['name']), 'an empty name is still refused');

$e = user_validate(array(
    'name' => 'Someone', 'email' => 'not-an-email', 'student_no' => '',
    'phone' => '', 'role' => 'user', 'status' => 'active',
), $probeId);
ok(isset($e['email']), 'a malformed email is still refused');

/* A real seeded address, because the rule is about a collision in the users
   table and a made-up address would never collide with anything. */
$seeded = db_one('SELECT email FROM users WHERE id <> ? ORDER BY id LIMIT 1', array($probeId));
$e = user_validate(array(
    'name' => 'Someone', 'email' => $seeded['email'], 'student_no' => '',
    'phone' => '', 'role' => 'user', 'status' => 'active',
), 0);
ok(isset($e['email']), 'an email already in use is still refused');

/* The mirror case, which is the one the profile page actually hits on every
   save: an account keeping the address it already has. */
$own = db_one('SELECT email FROM users WHERE id = ?', array($probeId));
$e = user_validate(array(
    'name' => 'Someone', 'email' => $own['email'], 'student_no' => '',
    'phone' => '', 'role' => 'user', 'status' => 'active',
), $probeId);
ok(!isset($e['email']), 'but an account keeping its own email is not a collision');

$e = user_validate(array(
    'name' => 'Someone', 'email' => 'someone@example.test', 'student_no' => '',
    'phone' => 'not a number at all', 'role' => 'user', 'status' => 'active',
), $probeId);
ok(isset($e['phone']), 'a malformed phone number is still refused');

/* ============================================================== totals */

section('the seeded system is untouched');

$users  = (int) db_value('SELECT COUNT(*) FROM users');
$admins = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
ok($admins === 2, 'there are still two active administrators');

$orphanFiles = 0;
foreach ((array) glob(APP_ROOT . '/public/uploads/avatars/*.jpg') as $file) {
    $orphanFiles++;
}
ok($orphanFiles === 0, 'no picture files were left behind');

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
if ($fail > 0) {
    echo "\nFailures:\n";
    foreach ($failures as $f) {
        echo "  - $f\n";
    }
}
exit($fail === 0 ? 0 : 1);
