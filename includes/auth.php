<?php
/**
 * Computer Laboratory Booking System
 * -----------------------------------
 * Session handling, authentication and route guards.
 *
 * Target runtime: PHP 5.6.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/bootstrap.php';
}

/* =========================================================================
 | Timing-equalisation hash
 |
 | bcrypt hashes are always exactly 60 characters. This is a real one, of a
 | random string that is not recorded anywhere, used only to keep the "no such
 | account" login path as slow as the "wrong password" path. Regenerating it is
 | harmless; its only property that matters is that it is well formed.
 | ====================================================================== */
define('AUTH_DUMMY_HASH', '$2y$10$npr/8WZeMqoJsEuIfBG3PeVZCHuYtrWecyCVmsfsqpAn5DHsH9HcK');

/* =========================================================================
 | Brute-force throttling
 |
 | Failed attempts are counted per client IP using the existing
 | activity_logs table, so no extra table is needed. After too many failures in
 | a rolling window, sign-in from that address is refused for a cooling-off
 | period. A busy shared network (a whole class behind one router) is a known
 | limitation of counting by IP and is called out in the README.
 | ====================================================================== */
define('AUTH_MAX_ATTEMPTS', 5);              // failures allowed per window
define('AUTH_WINDOW_MINUTES', 15);           // rolling window
define('AUTH_LOCKOUT_MINUTES', 15);          // cooling-off period
define('AUTH_IDLE_TIMEOUT', 7200);           // 2 hours without a request

/**
 * Number of failed sign-ins from this client inside the rolling window.
 *
 * The counter is cleared outright on a successful sign-in, so it always means
 * "failures since the last time this client got in".
 *
 * @return int
 */
function login_failure_count()
{
    $window = (int) AUTH_WINDOW_MINUTES;

    try {
        return (int) db_value(
            'SELECT COUNT(*)
               FROM activity_logs
              WHERE action = \'login_failed\'
                AND ip_address = ?
                AND created_at > (NOW() - INTERVAL ' . $window . ' MINUTE)',
            array(client_ip())
        );
    } catch (Exception $e) {
        // If the log table is unreachable, fail open rather than locking
        // everyone out of their own system.
        error_log('CLBS login_failure_count failed: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Forget this client's failed attempts after a successful sign-in.
 *
 * This used to be derived by comparing against the timestamp of the last
 * successful login, but activity_logs.created_at only has one-second
 * resolution. A failure recorded in the same second as the success was
 * therefore excluded by the strict ">" comparison, which let a handful of
 * guesses slip past the limit. Deleting the rows has no such edge case.
 *
 * The trade-off is that individual failure rows are no longer kept once the
 * account owner signs in successfully. The successful "login" event remains as
 * the record that access was granted.
 */
function clear_login_failures()
{
    $window = (int) AUTH_WINDOW_MINUTES;

    try {
        db_exec(
            'DELETE FROM activity_logs
              WHERE action = \'login_failed\'
                AND ip_address = ?
                AND created_at > (NOW() - INTERVAL ' . $window . ' MINUTE)',
            array(client_ip())
        );
    } catch (Exception $e) {
        error_log('CLBS clear_login_failures failed: ' . $e->getMessage());
    }
}

/**
 * Seconds remaining in a lockout, or 0 when sign-in is allowed.
 *
 * @return int
 */
function login_lockout_seconds()
{
    $failures = login_failure_count();
    if ($failures < AUTH_MAX_ATTEMPTS) {
        return 0;
    }

    $lastFailure = db_value(
        'SELECT UNIX_TIMESTAMP(MAX(created_at))
           FROM activity_logs
          WHERE action = \'login_failed\'
            AND ip_address = ?
            AND created_at > (NOW() - INTERVAL ' . (int) AUTH_WINDOW_MINUTES . ' MINUTE)',
        array(client_ip())
    );

    if ($lastFailure === null) {
        return 0;
    }

    $elapsed = time() - (int) $lastFailure;
    $lockout = AUTH_LOCKOUT_MINUTES * 60;

    return $elapsed >= $lockout ? 0 : $lockout - $elapsed;
}

/* =========================================================================
 | Session
 | ====================================================================== */

/**
 * Start the session once, with hardened cookie settings.
 */
function start_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Only send the cookie over HTTPS when the page actually is HTTPS.
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    session_name(SESSION_NAME);
    session_set_cookie_params(0, '/', '', $secure, true);
    session_start();

    // Idle timeout: sign a user out after a period of inactivity.
    if (isset($_SESSION['_last_activity'])
        && (time() - $_SESSION['_last_activity']) > AUTH_IDLE_TIMEOUT
    ) {
        // The session id is replaced as well as emptied, so a token that was
        // captured before the timeout cannot be used afterwards.
        $_SESSION = array();
        session_regenerate_id(true);
        flash('warning', 'Your session expired after a period of inactivity. Please sign in again.');
    }

    $_SESSION['_last_activity'] = time();
}

/**
 * Destroy the current session, then begin a fresh one so a message can still be
 * shown on the next page.
 *
 * session_destroy() leaves no active session, so a flash() call made straight
 * after destroy_session() is written into a session that no longer exists and is
 * silently lost. Starting a replacement session first keeps the notice visible.
 * A new id is forced so the pre-logout token cannot be reused.
 *
 * @param string $type
 * @param string $message
 */
function destroy_session_with_notice($type, $message)
{
    destroy_session();

    session_start();
    session_regenerate_id(true);

    flash($type, $message);
}

/**
 * Invalidate the session completely. Used by logout.
 */
function destroy_session()
{
    $_SESSION = array();

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/* =========================================================================
 | State helpers
 | ====================================================================== */

/**
 * @return array|null The signed-in user, or null.
 */
function current_user()
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

/**
 * @return int|null
 */
function current_user_id()
{
    $user = current_user();
    return $user === null ? null : (int) $user['id'];
}

/**
 * @return bool
 */
function is_logged_in()
{
    return current_user() !== null;
}

/**
 * @return bool
 */
function is_admin()
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

/**
 * @return int Number of unread notifications for the signed-in user.
 */
function unread_notification_count()
{
    $id = current_user_id();
    if ($id === null) {
        return 0;
    }
    return (int) db_value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', array($id));
}

/* =========================================================================
 | Login / logout
 | ====================================================================== */

/**
 * Put an authenticated user into the session and refresh their last_login_at.
 *
 * @param array $user
 */
function login_user(array $user)
{
    // New session id on privilege change -> defeats session fixation.
    session_regenerate_id(true);

    $_SESSION['user'] = array(
        'id'         => (int) $user['id'],
        'name'       => $user['name'],
        'email'      => $user['email'],
        'role'       => $user['role'],
        'student_no' => $user['student_no'],
    );

    try {
        db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', array((int) $user['id']));
    } catch (Exception $e) {
        error_log('CLBS last_login update failed: ' . $e->getMessage());
    }

    log_activity('login', 'user', (int) $user['id'], 'Signed in');

    // Getting in clears the strike count for this device.
    clear_login_failures();
}

/**
 * Verify credentials.
 *
 * @param  string $email
 * @param  string $password
 * @return array{ok:bool, user:array|null, error:string}
 */
function attempt_login($email, $password)
{
    // Throttle before doing any password work.
    $locked = login_lockout_seconds();
    if ($locked > 0) {
        $minutes = (int) ceil($locked / 60);
        return array(
            'ok'    => false,
            'user'  => null,
            'error' => 'Too many failed sign-in attempts. Please wait '
                     . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' and try again.',
        );
    }

    $user = db_one('SELECT * FROM users WHERE email = ? LIMIT 1', array($email));

    if ($user === null) {
        // Run a real bcrypt comparison even though there is no account, so that
        // a missing email and a wrong password take the same time. Without this
        // an attacker can time responses to discover which emails are registered.
        //
        // AUTH_DUMMY_HASH is a genuine 60-character bcrypt hash (of a random
        // string nobody knows) so that password_verify() performs the full
        // computation. A malformed hash would be silently cheaper on some
        // builds, which would defeat the point of it.
        password_verify($password, AUTH_DUMMY_HASH);
        log_activity('login_failed', 'user', null, 'No account for ' . $email);
        return array('ok' => false, 'user' => null, 'error' => 'Incorrect email or password.');
    }

    if (!password_verify($password, $user['password'])) {
        log_activity('login_failed', 'user', (int) $user['id'], 'Wrong password for ' . $email);
        return array('ok' => false, 'user' => null, 'error' => 'Incorrect email or password.');
    }

    if ($user['status'] !== 'active') {
        // A correct password on a disabled account is not a brute-force attempt,
        // so it must not feed the throttle counter. It is still worth recording.
        log_activity(
            'login_blocked',
            'user',
            (int) $user['id'],
            'Correct password but account status is ' . $user['status']
        );

        return array(
            'ok'    => false,
            'user'  => null,
            'error' => 'This account has been deactivated. Please contact the administrator.',
        );
    }

    // Transparently upgrade the hash if the cost factor has changed.
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        try {
            db_exec('UPDATE users SET password = ? WHERE id = ?', array(
                password_hash($password, PASSWORD_DEFAULT),
                (int) $user['id'],
            ));
        } catch (Exception $e) {
            error_log('CLBS password rehash failed: ' . $e->getMessage());
        }
    }

    return array('ok' => true, 'user' => $user, 'error' => '');
}

/**
 * Sign the current user out.
 */
function logout_user()
{
    $id = current_user_id();
    if ($id !== null) {
        log_activity('logout', 'user', $id, 'Signed out');
    }
    destroy_session();
}

/* =========================================================================
 | Route guards
 | ====================================================================== */

/**
 * Re-read the account from the database and confirm it may still be used.
 *
 * Called on every guarded request. This closes the window where an
 * administrator deactivates or demotes a user but that user's existing session
 * keeps working until it happens to expire.
 *
 * @return bool False when the session should be dropped.
 */
function sync_user_state()
{
    $user = current_user();
    if ($user === null) {
        return false;
    }

    try {
        $fresh = db_one('SELECT id, name, email, role, status, student_no FROM users WHERE id = ?', array((int) $user['id']));
    } catch (Exception $e) {
        // Database trouble must not silently sign everyone out.
        error_log('CLBS sync_user_state failed: ' . $e->getMessage());
        return true;
    }

    if ($fresh === null) {
        return false;
    }

    if ($fresh['status'] !== 'active') {
        return false;
    }

    // Refresh role and name so a promotion or a rename takes effect at once.
    $_SESSION['user'] = array(
        'id'         => (int) $fresh['id'],
        'name'       => $fresh['name'],
        'email'      => $fresh['email'],
        'role'       => $fresh['role'],
        'student_no' => $fresh['student_no'],
    );

    return true;
}

/**
 * Send anonymous visitors to the login page.
 */
function require_login()
{
    if (!is_logged_in()) {
        flash('warning', 'Please sign in to continue.');
        $_SESSION['_intended'] = current_url_path();
        redirect('auth/login.php');
    }

    if (!sync_user_state()) {
        // The account was deleted, or deactivated while the session was open.
        $accountId = current_user_id();

        // destroy_session_with_notice() is used instead of logout_user() +
        // flash() because the session has to be replaced before the notice can
        // be stored, otherwise the message is thrown away with the old session.
        destroy_session_with_notice(
            'danger',
            'Your account is no longer active. Please contact the administrator.'
        );

        // Logged after the session is gone, so the row has no user_id. That is
        // deliberate: if the account was deleted, a user_id pointing at it would
        // violate the foreign key on activity_logs.
        log_activity(
            'session_revoked',
            'user',
            $accountId,
            'Session ended: account deleted or deactivated'
        );

        redirect('auth/login.php');
    }}

/**
 * Only administrators may continue; everyone else goes to their dashboard.
 */
function require_admin()
{
    require_login();

    if (!is_admin()) {
        http_response_code(403);
        flash('danger', 'You do not have permission to view that page.');
        redirect('user/index.php');
    }
}

/**
 * Only students may continue; administrators go to their dashboard.
 */
function require_user()
{
    require_login();

    if (is_admin()) {
        redirect('admin/index.php');
    }
}

/**
 * Signed-in users skip the login and registration pages.
 */
function require_guest()
{
    if (is_logged_in()) {
        redirect(home_path());
    }
}

/**
 * The dashboard belonging to the current role.
 *
 * @return string
 */
function home_path()
{
    return is_admin() ? 'admin/index.php' : 'user/index.php';
}

/**
 * Best-effort path of the current request, used to send users back after login.
 *
 * @return string
 */
function current_url_path()
{
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $rel = str_replace('\\', '/', $script);
    if (BASE_URL !== '' && strpos($rel, BASE_URL) === 0) {
        $rel = substr($rel, strlen(BASE_URL));
    }
    return '/' . ltrim($rel, '/');
}

/**
 * Where to send the user after a successful sign-in.
 *
 * @return string
 */
function intended_path()
{
    if (!empty($_SESSION['_intended'])) {
        $path = $_SESSION['_intended'];
        unset($_SESSION['_intended']);
        if (strpos($path, '/') === 0 && !contains($path, '..')) {
            return ltrim($path, '/');
        }
    }
    return home_path();
}
