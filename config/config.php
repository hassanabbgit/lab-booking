<?php
/**
 * Computer Laboratory Booking System
 * -----------------------------------
 * Application-wide configuration.
 *
 * Loaded once by includes/bootstrap.php. This file must never be requested
 * directly over HTTP - it is protected by config/.htaccess.
 *
 * Target runtime: PHP 5.6 (XAMPP). Do not use PHP 7+ syntax in this
 * project (no ??, no scalar/return types, no list() short destructuring).
 */

if (defined('APP_CONFIG_LOADED')) {
    return;
}
define('APP_CONFIG_LOADED', true);

/* -------------------------------------------------------------------------
 | Database
 | XAMPP ships with a passwordless root account on localhost.
 | ---------------------------------------------------------------------- */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'clbs');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/* -------------------------------------------------------------------------
 | Application identity
 | ---------------------------------------------------------------------- */
define('APP_NAME', 'Computer Laboratory Booking System');
define('APP_SHORT_NAME', 'Lab Booking');
define('APP_ORG', 'Networking & Cloud Computing');
define('APP_DEBUG', true); // set to false before exposing the server to a LAN

/* -------------------------------------------------------------------------
 | Filesystem paths
 | ---------------------------------------------------------------------- */
define('APP_ROOT', dirname(dirname(__FILE__)));
define('APP_ASSETS', APP_ROOT . '/assets');

/* -------------------------------------------------------------------------
 | Base URL
 |
 | Auto-detected rather than hard-coded so the project folder can be moved,
 | renamed or served from a different document root without editing files.
 | ---------------------------------------------------------------------- */
$__scriptFs = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
$__scriptUrl = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
$__baseUrl = '';

if ($__scriptFs !== false && strpos($__scriptFs, APP_ROOT) === 0) {
    // e.g. "/admin/index.php"
    $__rel = str_replace('\\', '/', substr($__scriptFs, strlen(APP_ROOT)));
    if ($__rel !== '' && substr($__scriptUrl, -strlen($__rel)) === $__rel) {
        $__baseUrl = rtrim(substr($__scriptUrl, 0, strlen($__scriptUrl) - strlen($__rel)), '/');
    }
}
unset($__scriptFs, $__scriptUrl, $__rel);

// Served from the document root itself -> base URL is empty.
define('BASE_URL', $__baseUrl);
unset($__baseUrl);

/* -------------------------------------------------------------------------
 | Domain rules
 | ---------------------------------------------------------------------- */
define('BOOKING_MAX_ADVANCE_DAYS', 30); // how far ahead a booking may be made
define('BOOKING_MIN_DURATION_MIN', 30);  // shortest bookable session
define('BOOKING_MAX_DURATION_MIN', 480); // longest bookable session (8h)

/* -------------------------------------------------------------------------
 | Session / locale
 | ---------------------------------------------------------------------- */
define('SESSION_NAME', 'CLBS_SESSION');
define('APP_TIMEZONE', 'Africa/Lusaka');

date_default_timezone_set(APP_TIMEZONE);
