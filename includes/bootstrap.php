<?php
/**
 * Computer Laboratory Booking System
 * -----------------------------------
 * Single entry point for the whole application.
 *
 * Every page starts with:
 *
 *     require_once __DIR__ . '/../includes/bootstrap.php';
 *
 * which loads configuration, opens the database connection lazily, starts the
 * session and makes the helper functions available.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);

    // Fail loudly during development rather than emitting a blank page.
    if (ini_get('display_errors')) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    } else {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        ini_set('display_errors', '0');
    }

    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/functions.php';
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/booking_rules.php';
    require_once __DIR__ . '/laboratories.php';
    require_once __DIR__ . '/users.php';
    require_once __DIR__ . '/time_slots.php';
    require_once __DIR__ . '/reports.php';
    require_once __DIR__ . '/profile.php';
    require_once __DIR__ . '/alerts.php';
    require_once __DIR__ . '/layout.php';

    start_session();
}
