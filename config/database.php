<?php
/**
 * Computer Laboratory Booking System
 * -----------------------------------
 * PDO database connection.
 *
 * A single lazily-created connection is shared for the whole request.
 * Loaded once by includes/bootstrap.php. Never requested directly over
 * HTTP - protected by config/.htaccess.
 */

if (!defined('APP_CONFIG_LOADED')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Returns the shared PDO handle, connecting on first use.
 *
 * @return PDO
 * @throws PDOException when the database is unreachable
 */
function db()
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use real prepared statements so user input is never
            // interpolated into the SQL string.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ));

        // Strict mode.
        //
        // XAMPP's MariaDB runs with sql_mode = NO_AUTO_CREATE_USER,
        // NO_ENGINE_SUBSTITUTION, which is NOT strict. Without strict mode an
        // out-of-range ENUM value is silently coerced instead of rejected, so
        // writing status = 'banana' stores an empty string. That matters more
        // than it looks: the conflict check ignores any row whose status is not
        // 'pending' or 'approved', so a corrupted status would quietly stop a
        // booking from blocking a conflicting one. Strict mode turns that class
        // of silent corruption into a hard error.
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
    } catch (PDOException $e) {
        if (APP_DEBUG) {
            die(
                '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;'
                . 'border:1px solid #dc3545;border-radius:8px;color:#842029">'
                . '<h2 style="margin-top:0">Database connection failed</h2>'
                . '<p>Could not connect to <code>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</code></p>'
                . '<p>Check that:</p><ul>'
                . '<li>MySQL is running in the XAMPP Control Panel</li>'
                . '<li>The <code>' . DB_NAME . '</code> database has been imported '
                . '(<code>database/schema.sql</code>)</li>'
                . '<li>The credentials in <code>config/config.php</code> are correct</li>'
                . '</ul></div>'
            );
        }
        error_log('CLBS DB connection failed: ' . $e->getMessage());
        http_response_code(503);
        die('Service temporarily unavailable. Please try again later.');
    }

    return $pdo;
}
