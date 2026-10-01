<?php
/**
 * Computer Laboratory Booking System - user management rules.
 *
 * Loaded by includes/bootstrap.php, so it is available to every page. The
 * administrator pages use it to add accounts, edit them, switch them on and off,
 * change their role and reset a forgotten password.
 *
 * Nothing here ever deletes a user. Bookings, notifications and the activity log
 * all point at a user, and the log is deliberately kept after the account is
 * gone, so removing the row would either destroy a student's history or leave
 * the trail describing something that no longer exists. Deactivating an account
 * is the removal, and it is reversible.
 *
 * The two rules that matter most here are about not locking yourself out:
 *
 *   1. An administrator cannot change their own role or status. A demotion
 *      would silently turn them into a student on their next click, and a
 *      deactivation would end their session mid-form.
 *   2. The last active administrator cannot be demoted or deactivated, because
 *      that would leave the system with nobody able to undo it.
 *
 * Both are enforced inside the same transaction that performs the change, with
 * the rows involved locked, so two administrators working at once cannot each
 * assume the other one still exists.
 *
 * PHP 5.6 only: no scalar type hints, no ??, no arrow functions.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    exit('Direct access is not allowed.');
}

if (!defined('USER_NAME_MAX')) {
    define('USER_NAME_MAX', 120);
    define('USER_EMAIL_MAX', 191);
    define('USER_STUDENT_NO_MAX', 50);
    define('USER_PHONE_MAX', 30);
    define('USER_PASSWORD_MIN', 8);
}

/* =========================================================================
 | Roles and status
 | ====================================================================== */

/**
 * The roles an account can hold.
 *
 * 'admin' sees the administrator pages; 'user' is a student. The role is named
 * 'user' rather than 'student' because that is the column's own ENUM value, and
 * the two have to be spelled the same way in every query.
 *
 * @return array
 */
function user_roles()
{
    return array('admin', 'user');
}

/**
 * @param  string $role
 * @return string
 */
function user_role_label($role)
{
    $map = array(
        'admin' => 'Administrator',
        'user'  => 'Student',
    );
    return isset($map[$role]) ? $map[$role] : ucfirst($role);
}

/**
 * Only students can hold bookings and be sent to the student pages.
 *
 * @param  string $role
 * @return bool
 */
function user_is_student($role)
{
    return $role === 'user';
}

/**
 * The two states an account can be in. A deactivated account keeps all of its
 * data and its history; it simply cannot sign in.
 *
 * @return array
 */
function user_statuses()
{
    return array('active', 'inactive');
}

/**
 * @param  string $status
 * @return string
 */
function user_status_label($status)
{
    $map = array(
        'active'   => 'Active',
        'inactive' => 'Deactivated',
    );
    return isset($map[$status]) ? $map[$status] : ucfirst($status);
}

/**
 * @param  string $role
 * @return string A Bootstrap badge class, matching the rest of the app.
 */
function user_role_badge_class($role)
{
    return $role === 'admin' ? 'bg-primary' : 'bg-secondary';
}

/**
 * @param  string $status
 * @return string A Bootstrap badge class.
 *
 * Kept apart from status_badge_class() in functions.php, which only knows the
 * five booking statuses. Reusing it would render an active and a deactivated
 * account in the same grey, which is exactly the distinction this column exists
 * to show. This mirrors how lab_status_badge_class() is kept separate.
 */
function user_status_badge_class($status)
{
    $map = array(
        'active'   => 'bg-success',
        'inactive' => 'bg-secondary',
    );
    return isset($map[$status]) ? $map[$status] : 'bg-secondary';
}

/* =========================================================================
 | Validation
 | ====================================================================== */

/**
 * Check a set of submitted user fields.
 *
 * $ignoreId is the account being edited, so saving a record without changing
 * its email is not reported as a clash with itself. Pass 0 when adding.
 *
 * @param  array $input  keys: name, email, student_no, phone, role, status
 * @param  int   $ignoreId
 * @return array field name => message, empty when everything is acceptable
 */
function user_validate(array $input, $ignoreId = 0)
{
    $errors = array();

    $name      = trim(isset($input['name']) ? $input['name'] : '');
    $email     = user_normalise_email(isset($input['email']) ? $input['email'] : '');
    $studentNo = user_normalise_student_no(isset($input['student_no']) ? $input['student_no'] : '');
    $phone     = trim(isset($input['phone']) ? $input['phone'] : '');
    $role      = trim(isset($input['role']) ? $input['role'] : '');
    $status    = trim(isset($input['status']) ? $input['status'] : '');

    /* ----------------------------------------------------------------- name */
    if ($name === '') {
        $errors['name'] = 'Give the account a name.';
    } elseif (mb_strlen($name) < 3) {
        $errors['name'] = 'The name needs to be at least 3 characters.';
    } elseif (mb_strlen($name) > USER_NAME_MAX) {
        $errors['name'] = 'Keep the name under ' . USER_NAME_MAX . ' characters.';
    }

    /* ---------------------------------------------------------------- email */
    if ($email === '') {
        $errors['email'] = 'An email address is required.';
    } elseif (mb_strlen($email) > USER_EMAIL_MAX) {
        $errors['email'] = 'Keep the email address under ' . USER_EMAIL_MAX . ' characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That does not look like a valid email address.';
    } else {
        $clash = db_one(
            'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1',
            array($email, (int) $ignoreId)
        );
        if ($clash !== null) {
            $errors['email'] = 'Another account already uses that email address.';
        }
    }

    /* ----------------------------------------------------------- student no */
    // Blank means "not a student", and is stored as NULL rather than an empty
    // string: the column is UNIQUE, and MySQL treats every NULL as distinct but
    // only allows one ''. Storing '' would make the second staff account with no
    // student number collide with the first.
    if ($studentNo !== '') {
        if (mb_strlen($studentNo) > USER_STUDENT_NO_MAX) {
            $errors['student_no'] = 'Keep the student number under '
                . USER_STUDENT_NO_MAX . ' characters.';
        } else {
            $clash = db_one(
                'SELECT id FROM users WHERE student_no = ? AND id <> ? LIMIT 1',
                array($studentNo, (int) $ignoreId)
            );
            if ($clash !== null) {
                $errors['student_no'] = 'That student number is already registered.';
            }
        }
    }

    /* ---------------------------------------------------------------- phone */
    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $errors['phone'] = 'Enter a valid phone number, or leave it blank.';
    } elseif (mb_strlen($phone) > USER_PHONE_MAX) {
        $errors['phone'] = 'Keep the phone number under ' . USER_PHONE_MAX . ' characters.';
    }

    /* ----------------------------------------------------------------- role */
    if (!in_array($role, user_roles(), true)) {
        $errors['role'] = 'Choose whether this is a student or an administrator.';
    }

    /* --------------------------------------------------------------- status */
    if (!in_array($status, user_statuses(), true)) {
        $errors['status'] = 'Choose whether the account is active or deactivated.';
    }

    return $errors;
}

/**
 * The password rule, kept in one place because both the add form and the reset
 * form use it, and so does registration.
 *
 * @param  string $password
 * @return string An error message, or '' when the password is acceptable.
 */
function user_validate_password($password)
{
    if ($password === '') {
        return 'Enter a password.';
    }

    if (strlen($password) < USER_PASSWORD_MIN) {
        return 'Use at least ' . USER_PASSWORD_MIN . ' characters.';
    }

    return '';
}

/**
 * Emails are stored lower-case.
 *
 * The column is utf8mb4_unicode_ci, so the UNIQUE index already treats
 * "Ann@x.com" and "ann@x.com" as the same address, but the pre-checks are plain
 * comparisons and would only agree with the index if the stored value were
 * already in one case. Normalising on the way in keeps the two in step, and
 * means a lookup by email cannot miss a row.
 *
 * @param  string $email
 * @return string
 */
function user_normalise_email($email)
{
    return strtolower(trim($email));
}

/**
 * Student numbers are stored upper-case, so "eng-21-0144" and "ENG-21-0144" are
 * recognised as the same student rather than as two accounts.
 *
 * @param  string $studentNo
 * @return string
 */
function user_normalise_student_no($studentNo)
{
    return strtoupper(trim($studentNo));
}

/**
 * Pull the fields out of a request and normalise them, so the same code serves
 * both the add and the edit form.
 *
 * @return array
 */
function user_input_from_post()
{
    return array(
        'name'       => post('name'),
        'email'      => post('email'),
        'student_no' => post('student_no'),
        'phone'      => post('phone'),
        'role'       => post('role'),
        'status'     => post('status'),
    );
}

/**
 * The fields the "edit details" form collects.
 *
 * That form has no role or status on it, because those two are changed through
 * their own guarded forms. user_validate() still insists on both being valid, so
 * they are filled in from the record being edited. That is not a way around the
 * guards: user_update() never writes role or status at all, and the values are
 * only present so validation has something to check.
 *
 * @param  array $user the record as returned by user_find()
 * @return array keys as user_validate()
 */
function user_details_input_from_post(array $user)
{
    $input = user_input_from_post();

    $input['role']   = $user['role'];
    $input['status'] = $user['status'];

    return $input;
}

/* =========================================================================
 | Create and update
 | ====================================================================== */

/**
 * Add an account.
 *
 * The duplicates are checked before the insert as well, but the UNIQUE indexes
 * are what actually guarantee them: two administrators saving the same email
 * at the same moment would both pass the check.
 *
 * @param  array  $input    keys as user_validate()
 * @param  string $password the initial password, already checked by the caller
 * @return array array('ok' => bool, 'id' => int, 'error' => string, 'field' => string)
 */
function user_create(array $input, $password)
{
    $email     = user_normalise_email($input['email']);
    $studentNo = user_normalise_student_no($input['student_no']);

    try {
        $id = db_insert(
            'INSERT INTO users (name, email, password, role, status, student_no, phone)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            array(
                trim($input['name']),
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $input['role'],
                $input['status'],
                $studentNo === '' ? null : $studentNo,
                trim($input['phone']) === '' ? null : trim($input['phone']),
            )
        );

        return array('ok' => true, 'id' => $id, 'error' => '', 'field' => '');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            // Say which column actually clashed rather than assuming it was the
            // email: the email and the student number each have a UNIQUE index,
            // and blaming the email for a duplicate student number sends the
            // administrator looking in the wrong place.
            if ($studentNo !== ''
                && db_one('SELECT id FROM users WHERE student_no = ? LIMIT 1', array($studentNo)) !== null
            ) {
                return array('ok' => false, 'id' => 0, 'field' => 'student_no',
                    'error' => 'That student number is already registered.');
            }

            return array('ok' => false, 'id' => 0, 'field' => 'email',
                'error' => 'Another account already uses that email address.');
        }

        error_log('CLBS user create failed: ' . $e->getMessage());
        return array('ok' => false, 'id' => 0, 'field' => '',
            'error' => 'The account could not be created. Please try again.');
    }
}

/**
 * Save changes to an existing account.
 *
 * Deliberately does not touch the password, the last sign-in, or the role and
 * status: the password has its own form, and role and status each have their
 * own guard in user_change_access() that this function would otherwise bypass.
 *
 * @param  int   $id
 * @param  array $input keys as user_validate()
 * @return array array('ok' => bool, 'error' => string, 'field' => string)
 */
function user_update($id, array $input)
{
    $id = (int) $id;

    if (db_one('SELECT id FROM users WHERE id = ?', array($id)) === null) {
        return array('ok' => false, 'field' => '', 'error' => 'That account no longer exists.');
    }

    $email     = user_normalise_email($input['email']);
    $studentNo = user_normalise_student_no($input['student_no']);

    try {
        db_exec(
            'UPDATE users
                SET name = ?, email = ?, student_no = ?, phone = ?
              WHERE id = ?',
            array(
                trim($input['name']),
                $email,
                $studentNo === '' ? null : $studentNo,
                trim($input['phone']) === '' ? null : trim($input['phone']),
                $id,
            )
        );

        return array('ok' => true, 'error' => '', 'field' => '');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            if ($studentNo !== ''
                && db_one(
                    'SELECT id FROM users WHERE student_no = ? AND id <> ? LIMIT 1',
                    array($studentNo, $id)
                ) !== null
            ) {
                return array('ok' => false, 'field' => 'student_no',
                    'error' => 'That student number is already registered.');
            }

            return array('ok' => false, 'field' => 'email',
                'error' => 'Another account already uses that email address.');
        }

        error_log('CLBS user update failed: ' . $e->getMessage());
        return array('ok' => false, 'field' => '',
            'error' => 'The changes could not be saved. Please try again.');
    }
}

/* =========================================================================
 | Role and status
 | ====================================================================== */

/**
 * How many active administrators there are, optionally ignoring one account.
 *
 * @param  int $ignoreId
 * @return int
 */
function active_admin_count($ignoreId = 0)
{
    return (int) db_value(
        'SELECT COUNT(*) FROM users WHERE role = \'admin\' AND status = \'active\' AND id <> ?',
        array((int) $ignoreId)
    );
}

/**
 * Change a user's role or status, refusing anything that would lock the system.
 *
 * Both changes are handled together because their guards are the same shape: a
 * value has to be real, the account has to exist, it has to be a change, it must
 * not be the administrator making it, and it must not remove the last way into
 * the system.
 *
 * A role or status change takes effect on that account's very next request
 * without any session hunting: require_login() calls sync_user_state() on every
 * guarded page, which re-reads the row and drops the session if the account is
 * no longer active.
 *
 * @param  int    $id      the account being changed
 * @param  string $field   'role' or 'status'
 * @param  string $value   the new value
 * @param  int    $actorId the administrator making the change
 * @return array array('ok' => bool, 'error' => string)
 */
function user_change_access($id, $field, $value, $actorId)
{
    $id      = (int) $id;
    $actorId = (int) $actorId;

    if ($field !== 'role' && $field !== 'status') {
        return array('ok' => false, 'error' => 'That is not something an account can change.');
    }

    $allowed = $field === 'role' ? user_roles() : user_statuses();

    if (!in_array($value, $allowed, true)) {
        return array('ok' => false, 'error' => $field === 'role'
            ? 'That is not a role this system has.'
            : 'That is not a status this system has.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        /* Lock the row first: without it two administrators could both read
           "there is another admin" and both demote themselves. */
        $current = db_one('SELECT id, name, role, status FROM users WHERE id = ? FOR UPDATE', array($id));

        if ($current === null) {
            $pdo->rollBack();
            return array('ok' => false, 'error' => 'That account no longer exists.');
        }

        if ($current[$field] === $value) {
            $pdo->rollBack();
            return array('ok' => false, 'error' => $field === 'role'
                ? $current['name'] . ' is already ' . lcfirst(user_role_label($value)) . '.'
                : $current['name'] . ' is already ' . lcfirst(user_status_label($value)) . '.');
        }

        /* An administrator changing their own access would either be signed out
           mid-save or quietly become a student on the next click, with no way
           back through this page. Refuse it and say so. */
        if ($id === $actorId) {
            $pdo->rollBack();
            return array('ok' => false, 'error' => $field === 'role'
                ? 'You cannot change your own role. Ask another administrator to do it.'
                : 'You cannot deactivate your own account. Ask another administrator to do it.');
        }

        /* Does this change remove an active administrator? */
        $losesAdmin = ($current['role'] === 'admin' && $current['status'] === 'active')
            && (($field === 'role'    && $value !== 'admin')
             || ($field === 'status' && $value !== 'active'));

        if ($losesAdmin) {
            /* A locking read rather than a COUNT, so a concurrent change to
               another administrator cannot commit between the test and the
               update below. */
            $others = db_all(
                'SELECT id FROM users
                  WHERE role = \'admin\' AND status = \'active\' AND id <> ?
                  FOR UPDATE',
                array($id)
            );

            if (empty($others)) {
                $pdo->rollBack();
                return array('ok' => false, 'error' => $current['name'] . ' is the only active '
                    . 'administrator, so they cannot be '
                    . ($field === 'role' ? 'demoted' : 'deactivated')
                    . '. Promote somebody else first, otherwise nobody could undo it.');
            }
        }

        db_exec('UPDATE users SET ' . $field . ' = ? WHERE id = ?', array($value, $id));

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('CLBS user access change failed: ' . $e->getMessage());
        return array('ok' => false, 'error' => 'The change could not be saved. Please try again.');
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Switch an account on or off.
 *
 * @param  int    $id
 * @param  string $status
 * @param  int    $actorId
 * @return array array('ok' => bool, 'error' => string)
 */
function user_set_status($id, $status, $actorId)
{
    return user_change_access($id, 'status', $status, $actorId);
}

/**
 * Promote a student to administrator, or the other way round.
 *
 * @param  int    $id
 * @param  string $role
 * @param  int    $actorId
 * @return array array('ok' => bool, 'error' => string)
 */
function user_set_role($id, $role, $actorId)
{
    return user_change_access($id, 'role', $role, $actorId);
}

/* =========================================================================
 | Password
 | ====================================================================== */

/**
 * Set a new password for an account.
 *
 * This is the recovery route when somebody has forgotten theirs: there is no
 * self-service password change yet, so without this an account can only be
 * recovered by an administrator. The password is hashed with the same
 * password_hash() as everywhere else, and the old hash is overwritten rather
 * than kept, so a forgotten password is genuinely gone.
 *
 * @param  int    $id
 * @param  string $password
 * @return array array('ok' => bool, 'error' => string)
 */
function user_set_password($id, $password)
{
    $id = (int) $id;

    $user = db_one('SELECT id, name FROM users WHERE id = ?', array($id));
    if ($user === null) {
        return array('ok' => false, 'error' => 'That account no longer exists.');
    }

    $problem = user_validate_password($password);
    if ($problem !== '') {
        return array('ok' => false, 'error' => $problem);
    }

    db_exec(
        'UPDATE users SET password = ? WHERE id = ?',
        array(password_hash($password, PASSWORD_DEFAULT), $id)
    );

    return array('ok' => true, 'error' => '');
}

/* =========================================================================
 | Figures and summaries
 | ====================================================================== */

/**
 * How many bookings a user has, split by the ones that still matter.
 *
 * @param  int $userId
 * @return array
 */
function user_booking_summary($userId)
{
    $row = db_one(
        'SELECT COUNT(*) AS total,
                SUM(status = \'pending\')   AS pending,
                SUM(status = \'approved\')  AS approved,
                SUM(status = \'rejected\')  AS rejected,
                SUM(status = \'cancelled\') AS cancelled,
                SUM(status = \'completed\') AS completed
           FROM bookings
          WHERE user_id = ?',
        array((int) $userId)
    );

    $out = array('total' => 0, 'active' => 0, 'pending' => 0, 'approved' => 0,
        'rejected' => 0, 'cancelled' => 0, 'completed' => 0);

    if ($row !== null) {
        foreach ($out as $key => $ignored) {
            if (array_key_exists($key, $row)) {
                $out[$key] = (int) $row[$key];
            }
        }
    }

    $out['active'] = $out['pending'] + $out['approved'];

    return $out;
}

/**
 * Totals for the user list strip.
 *
 * @return array
 */
function user_totals()
{
    $row = db_one(
        'SELECT COUNT(*) AS total,
                SUM(status = \'active\')   AS active,
                SUM(status = \'inactive\') AS inactive,
                SUM(role = \'admin\')      AS admins,
                SUM(role = \'user\')       AS students,
                SUM(last_login_at IS NULL) AS never_signed_in
           FROM users'
    );

    $out = array('total' => 0, 'active' => 0, 'inactive' => 0, 'admins' => 0,
        'students' => 0, 'never_signed_in' => 0);

    if ($row !== null) {
        foreach ($out as $key => $ignored) {
            if (array_key_exists($key, $row)) {
                $out[$key] = (int) $row[$key];
            }
        }
    }

    return $out;
}

/**
 * The full record for one account, with the figures the details page shows.
 *
 * @param  int $id
 * @return array|null
 */
function user_find($id)
{
    return db_one(
        'SELECT u.*,
                (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS booking_count,
                (SELECT COUNT(*) FROM activity_logs a
                  WHERE a.user_id = u.id) AS activity_count
           FROM users u
          WHERE u.id = ?',
        array((int) $id)
    );
}

/**
 * Everything needed to describe a role or status change in a sentence, for the
 * activity log.
 *
 * @param  array  $user
 * @param  string $field
 * @param  string $value
 * @return string
 */
function user_access_change_summary(array $user, $field, $value)
{
    if ($field === 'role') {
        return 'Changed ' . $user['name'] . ' from '
            . lcfirst(user_role_label($user['role'])) . ' to '
            . lcfirst(user_role_label($value));
    }

    return ($value === 'active' ? 'Reactivated ' : 'Deactivated ') . $user['name'];
}
