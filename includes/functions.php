<?php
/**
 * Computer Laboratory Booking System
 * -----------------------------------
 * Shared helper functions: escaping, URLs, database shortcuts, flash
 * messages, CSRF protection and display formatting.
 *
 * Target runtime: PHP 5.6.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/bootstrap.php';
}

/* =========================================================================
 | Output escaping
 | ====================================================================== */

/**
 * Escape a value for safe output in HTML. Always use this for anything that
 * came from a user; never print $_GET/$_POST/DB values raw.
 *
 * @param  mixed $value
 * @return string
 */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape a value for use inside a JavaScript string or a data-* attribute.
 *
 * @param  mixed $value
 * @return string
 */
function ejs($value)
{
    return htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Echo an escaped value.
 *
 * @param mixed $value
 */
function echo_e($value)
{
    echo e($value);
}

/* =========================================================================
 | URLs
 | ====================================================================== */

/**
 * Build an absolute application URL.
 *
 * @param  string $path Path relative to the project root, e.g. 'admin/index.php'
 * @return string
 */
function url($path = '')
{
    $path = ltrim($path, '/');
    if ($path === '') {
        return BASE_URL === '' ? '/' : BASE_URL . '/';
    }
    return BASE_URL . '/' . $path;
}

/**
 * URL for a file inside assets/.
 *
 * @param  string $path
 * @return string
 */
function asset($path)
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Send a Location header and stop. Always exits.
 *
 * @param string $path Path relative to the project root.
 */
function redirect($path)
{
    if (!headers_sent()) {
        header('Location: ' . url($path));
    }
    exit;
}

/* =========================================================================
 | Request input
 | ====================================================================== */

/**
 * @return bool
 */
function is_post()
{
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Read and trim a POST value.
 *
 * @param  string $key
 * @param  string $default
 * @return string
 */
function post($key, $default = '')
{
    if (!isset($_POST[$key]) || is_array($_POST[$key])) {
        return $default;
    }
    $value = trim((string) $_POST[$key]);
    return $value === '' ? $default : $value;
}

/**
 * Read and trim a GET value.
 *
 * @param  string $key
 * @param  string $default
 * @return string
 */
function query($key, $default = '')
{
    if (!isset($_GET[$key]) || is_array($_GET[$key])) {
        return $default;
    }
    $value = trim((string) $_GET[$key]);
    return $value === '' ? $default : $value;
}

/**
 * Read an integer from the request.
 *
 * @param  string $key
 * @param  int    $default
 * @return int
 */
function query_int($key, $default = 0)
{
    $value = query($key, '');
    return $value === '' ? $default : (int) $value;
}

/**
 * Repopulate a form field after a failed validation.
 *
 * @param  string $key
 * @param  string $default
 * @return string
 */
function old($key, $default = '')
{
    return isset($_SESSION['_old'][$key]) ? $_SESSION['_old'][$key] : $default;
}

/**
 * The session's CSRF token, generated on first use.
 *
 * @return string
 */
function csrf_token()
{
    if (empty($_SESSION['_csrf'])) {
        // 32 random bytes -> 64 hex characters.
        if (function_exists('random_bytes')) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $_SESSION['_csrf'] = bin2hex(openssl_random_pseudo_bytes(32));
        } else {
            $_SESSION['_csrf'] = sha1(uniqid(mt_rand(), true));
        }
    }
    return $_SESSION['_csrf'];
}

/**
 * Hidden input carrying the CSRF token. Put this inside every POST form.
 *
 * @return string
 */
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Read and validate a CSRF token from POST.
 *
 * @return bool
 */
function csrf_is_valid()
{
    if (empty($_POST['csrf_token']) || empty($_SESSION['_csrf'])) {
        return false;
    }
    return hash_equals($_SESSION['_csrf'], $_POST['csrf_token']);
}

/**
 * Stop the request when the CSRF token is missing or wrong.
 */
function csrf_guard()
{
    if (!csrf_is_valid()) {
        // 403, not 419: http_response_code() only accepts standard codes, and
        // an unrecognised one makes PHP emit a 500 instead.
        http_response_code(403);
        if (is_ajax()) {
            header('Content-Type: application/json');
            exit('{"ok":false,"error":"Your session expired. Please reload the page and try again."}');
        }
        exit('Your session expired or the form was tampered with. Please go back, reload the page and try again.');
    }
}

/**
 * @return bool True when the caller asked for JSON rather than a page.
 */
function is_ajax()
{
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    return false;
}

/* =========================================================================
 | Flash messages
 | ====================================================================== */

/**
 * Queue a one-time message for the next rendered page.
 *
 * @param string $type    success|info|warning|danger
 * @param string $message
 */
function flash($type, $message)
{
    if (!isset($_SESSION['_flash'])) {
        $_SESSION['_flash'] = array();
    }
    $_SESSION['_flash'][] = array('type' => $type, 'message' => $message);
}

/**
 * Drain and return queued messages.
 *
 * @return array
 */
function take_flashes()
{
    if (empty($_SESSION['_flash'])) {
        return array();
    }
    $flashes = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $flashes;
}

/**
 * Remember submitted values so a redirect can refill the form.
 *
 * @param array $values
 */
function keep_old(array $values)
{
    $_SESSION['_old'] = $values;
}

/**
 * Forget remembered form values.
 */
function clear_old()
{
    unset($_SESSION['_old']);
}

/* =========================================================================
 | Database shortcuts
 | ====================================================================== */

/**
 * Prepare, execute and return the statement.
 *
 * @param  string $sql
 * @param  array  $params
 * @return PDOStatement
 */
function db_query($sql, array $params = array())
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch all rows.
 *
 * @param  string $sql
 * @param  array  $params
 * @return array
 */
function db_all($sql, array $params = array())
{
    return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch a single row, or null.
 *
 * @param  string $sql
 * @param  array  $params
 * @return array|null
 */
function db_one($sql, array $params = array())
{
    $row = db_query($sql, $params)->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/**
 * Fetch the first column of the first row, or null.
 *
 * @param  string $sql
 * @param  array  $params
 * @return mixed
 */
function db_value($sql, array $params = array())
{
    $row = db_query($sql, $params)->fetch(PDO::FETCH_NUM);
    return $row === false ? null : $row[0];
}

/**
 * Execute a statement and return the affected row count.
 *
 * @param  string $sql
 * @param  array  $params
 * @return int
 */
function db_exec($sql, array $params = array())
{
    return db_query($sql, $params)->rowCount();
}

/**
 * Execute an INSERT and return the new primary key.
 *
 * @param  string $sql
 * @param  array  $params
 * @return int
 */
function db_insert($sql, array $params = array())
{
    db_query($sql, $params);
    return (int) db()->lastInsertId();
}

/* =========================================================================
 | Activity log
 | ====================================================================== */

/**
 * Record an auditable action. Never let logging break the page.
 *
 * @param string      $action
 * @param string|null $entity
 * @param int|null    $entityId
 * @param string|null $description
 */
function log_activity($action, $entity = null, $entityId = null, $description = null)
{
    try {
        $userId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (user_id, action, entity, entity_id, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array($userId, $action, $entity, $entityId, $description, client_ip()));
    } catch (Exception $e) {
        error_log('CLBS log_activity failed: ' . $e->getMessage());
    }
}

/**
 * @return string
 */
function client_ip()
{
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }
    return '0.0.0.0';
}

/* =========================================================================
 | Formatting
 | ====================================================================== */

/**
 * '2026-09-30' -> '30 Sep 2026'
 *
 * @param  string $date
 * @param  bool   $withTime
 * @return string
 */
function format_date($date, $withTime = false)
{
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '-';
    }
    $parsed = date_create_from_format('Y-m-d', $date);
    if ($parsed !== false && $parsed->format('Y-m-d') === $date) {
        return $withTime ? $parsed->format('d M Y, H:i') : $parsed->format('d M Y');
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '-';
    }
    return date($withTime ? 'd M Y, H:i' : 'd M Y', $ts);
}

/**
 * '08:30:00' -> '08:30'
 *
 * @param  string $time
 * @return string
 */
function format_time($time)
{
    if (empty($time)) {
        return '-';
    }
    $ts = strtotime($time);
    return $ts === false ? $time : date('H:i', $ts);
}

/**
 * '08:30:00' + '10:00:00' -> '08:30 - 10:00'
 *
 * @param  string $start
 * @param  string $end
 * @return string
 */
function format_range($start, $end)
{
    return format_time($start) . ' - ' . format_time($end);
}

/**
 * Length of a booking period in minutes.
 *
 * @param  string $start
 * @param  string $end
 * @return int
 */
function duration_minutes($start, $end)
{
    $s = strtotime($start);
    $e = strtotime($end);
    if ($s === false || $e === false) {
        return 0;
    }
    return (int) round(($e - $s) / 60);
}

/**
 * How long ago something happened, in words.
 *
 * Used to tell an administrator whether a request has been sitting in the queue
 * long enough to be worth worrying about.
 *
 * @param  string $datetime
 * @return string
 */
function time_ago($datetime)
{
    $ts = strtotime($datetime);

    if ($ts === false) {
        return 'at an unknown time';
    }

    $seconds = time() - $ts;

    if ($seconds < 0) {
        return 'just now';
    }

    if ($seconds < 60) {
        return 'moments ago';
    }

    $minutes = (int) floor($seconds / 60);

    if ($minutes < 60) {
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }

    $hours = (int) floor($minutes / 60);

    if ($hours < 24) {
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    $days = (int) floor($hours / 24);

    if ($days < 30) {
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }

    return format_date($datetime);
}

/**
 * '2h 30m' style duration label.
 *
 * @param  int $minutes
 * @return string
 */
function format_duration($minutes)
{
    $hours = (int) floor($minutes / 60);
    $mins = $minutes % 60;
    if ($hours > 0 && $mins > 0) {
        return $hours . 'h ' . $mins . 'm';
    }
    if ($hours > 0) {
        return $hours . 'h';
    }
    return $mins . 'm';
}

/**
 * 'Ada Lovelace' -> 'AL'
 *
 * @param  string $name
 * @return string
 */
function initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $out .= strtoupper($part[0]);
        }
    }
    return $out === '' ? '?' : substr($out, 0, 2);
}

/**
 * Bootstrap badge class for a booking status.
 *
 * @param  string $status
 * @return string
 */
function status_badge_class($status)
{
    $map = array(
        'pending'   => 'bg-warning text-dark',
        'approved'  => 'bg-success',
        'rejected'  => 'bg-danger',
        'cancelled' => 'bg-secondary',
        'completed' => 'bg-info text-dark',
    );
    return isset($map[$status]) ? $map[$status] : 'bg-secondary';
}

/**
 * Bootstrap badge class for a laboratory status.
 *
 * @param  string $status
 * @return string
 */
function lab_status_badge_class($status)
{
    $map = array(
        'available'   => 'bg-success',
        'maintenance' => 'bg-warning text-dark',
        'inactive'    => 'bg-secondary',
    );
    return isset($map[$status]) ? $map[$status] : 'bg-secondary';
}

/**
 * PHP 5.6 has no str_contains(); this is the safe local equivalent.
 *
 * @param  string $haystack
 * @param  string $needle
 * @return bool
 */
function contains($haystack, $needle)
{
    if ($needle === '') {
        return true;
    }
    return strpos((string) $haystack, (string) $needle) !== false;
}

/**
 * Active-page marker for sidebar/navbar highlighting.
 *
 * @param  string $current
 * @param  string $candidate
 * @return string
 */
function nav_active($current, $candidate)
{
    return $current === $candidate ? ' active' : '';
}
