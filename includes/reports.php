<?php
/**
 * Shared reporting queries for admin/reports.php.
 *
 * Kept out of the page so the numbers can be tested without rendering HTML,
 * and so the date range is applied in one place: every report on the page is
 * built from the same $from/$to pair, otherwise the cards and the tables
 * would silently disagree at the edges.
 *
 * Target runtime: PHP 5.6.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    exit('Direct access is not allowed.');
}

if (!defined('REPORT_MIN_RANGE_DAYS')) {
    define('REPORT_MIN_RANGE_DAYS', 1);
}

/**
 * Read a date from a query string, falling back to something sensible.
 *
 * @param  string $key
 * @param  string $default
 * @return string 'Y-m-d'
 */
function report_date($key, $default)
{
    $raw = trim(query($key, ''));

    if ($raw === '') {
        return $default;
    }

    $parsed = date_create_from_format('Y-m-d', $raw);
    // date_create_from_format('Y-m-d', '2026-02-31') rolls over rather than
    // failing, so the formatted result has to be compared back.
    if ($parsed === false || $parsed->format('Y-m-d') !== $raw) {
        return $default;
    }

    return $raw;
}

/**
 * Resolve the requested period, keeping from <= to.
 *
 * @param  string $fromRaw
 * @param  string $toRaw
 * @return array array('from'=>string,'to'=>string,'days'=>int)
 */
function report_range($fromRaw, $toRaw)
{
    $today = date('Y-m-d');

    $from = $fromRaw !== '' ? $fromRaw : date('Y-m-d', strtotime('-29 days'));
    $to   = $toRaw !== '' ? $toRaw : $today;

    if ($from > $to) {
        // A reversed range is almost always a typo; swap it rather than
        // returning nothing at all.
        $swap = $from;
        $from = $to;
        $to   = $swap;
    }

    $days = (int) floor((strtotime($to) - strtotime($from)) / 86400) + 1;
    if ($days < REPORT_MIN_RANGE_DAYS) {
        $days = REPORT_MIN_RANGE_DAYS;
    }

    return array('from' => $from, 'to' => $to, 'days' => $days);
}

/**
 * Booking counts by status inside the period.
 *
 * @param  string $from 'Y-m-d'
 * @param  string $to
 * @return array
 */
function report_status_counts($from, $to)
{
    $row = db_one(
        "SELECT COUNT(*) AS total,
                SUM(status = 'pending')   AS pending,
                SUM(status = 'approved')  AS approved,
                SUM(status = 'rejected')  AS rejected,
                SUM(status = 'cancelled') AS cancelled,
                SUM(status = 'completed') AS completed
           FROM bookings
          WHERE booking_date BETWEEN ? AND ?",
        array($from, $to)
    );

    $out = array('total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0,
        'cancelled' => 0, 'completed' => 0);

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
 * How many of the decided requests were approved.
 *
 * Only requests that reached a decision count: a pending booking has not been
 * turned down, so including it here would flatter the rate.
 *
 * @param  array $counts
 * @return float Percentage 0-100.
 */
function report_approval_rate(array $counts)
{
    $decided = $counts['approved'] + $counts['rejected'];

    if ($decided === 0) {
        return 0.0;
    }

    return round($counts['approved'] / $decided * 100, 1);
}

/**
 * Average hours between asking and being decided.
 *
 * @param  string $from
 * @param  string $to
 * @return float|null Null when nothing has been decided yet.
 */
function report_average_lead_hours($from, $to)
{
    $value = db_value(
        "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, reviewed_at))
           FROM bookings
          WHERE booking_date BETWEEN ? AND ?
            AND reviewed_at IS NOT NULL",
        array($from, $to)
    );

    if ($value === null || $value === false) {
        return null;
    }

    return round(((float) $value) / 60, 1);
}

/**
 * Per-laboratory usage, busiest first.
 *
 * @param  string $from
 * @param  string $to
 * @return array
 */
function report_laboratory_usage($from, $to)
{
    return db_all(
        "SELECT l.id, l.name, l.location,
                COUNT(b.id) AS total,
                SUM(b.status = 'approved')  AS approved,
                SUM(b.status = 'completed') AS completed
           FROM laboratories l
           LEFT JOIN bookings b
                  ON b.laboratory_id = l.id
                 AND b.booking_date BETWEEN ? AND ?
          GROUP BY l.id, l.name, l.location
          ORDER BY total DESC, l.name ASC",
        array($from, $to)
    );
}

/**
 * Which windows students book into most.
 *
 * Joined against the slot table so a window that has since been renamed still
 * shows its current label, with the raw times as a fallback.
 *
 * @param  string $from
 * @param  string $to
 * @return array
 */
function report_slot_usage($from, $to)
{
    return db_all(
        "SELECT b.start_time, b.end_time, COUNT(*) AS total
           FROM bookings b
          WHERE b.booking_date BETWEEN ? AND ?
          GROUP BY b.start_time, b.end_time
          ORDER BY total DESC, b.start_time ASC",
        array($from, $to)
    );
}

/**
 * How many different people booked in the period.
 *
 * Counted separately from report_student_activity() because that function is
 * deliberately capped, and a cap would otherwise be reported as the real total
 * once more than $limit people have booked.
 *
 * @param  string $from
 * @param  string $to
 * @return int
 */
function report_distinct_students($from, $to)
{
    return (int) db_value(
        'SELECT COUNT(DISTINCT user_id)
           FROM bookings
          WHERE booking_date BETWEEN ? AND ?',
        array($from, $to)
    );
}

/**
 * Per-student booking history, most active first.
 *
 * Everyone who has booked is listed, whatever their role. The column is called
 * "student" because in practice only students book, but filtering on
 * u.role = 'student' would silently drop any booking made by somebody else,
 * and the totals on the page would then stop adding up.
 *
 * The list is capped at $limit rows so a long period cannot produce an
 * unbounded page. The cap is deliberate and reported as such; the true number
 * of people is report_distinct_students().
 *
 * @param  string $from
 * @param  string $to
 * @param  int    $limit
 * @return array
 */
function report_student_activity($from, $to, $limit = 25)
{
    $limit = (int) $limit;
    if ($limit < 1) {
        $limit = 25;
    }

    return db_all(
        "SELECT u.id, u.name, u.student_no, u.email, u.role,
                COUNT(b.id) AS total,
                SUM(b.status = 'approved')  AS approved,
                SUM(b.status = 'pending')   AS pending,
                SUM(b.status = 'completed') AS completed,
                SUM(b.status = 'cancelled') AS cancelled,
                SUM(b.status = 'rejected')  AS rejected
           FROM users u
           JOIN bookings b ON b.user_id = u.id
          WHERE b.booking_date BETWEEN ? AND ?
          GROUP BY u.id, u.name, u.student_no, u.email, u.role
          ORDER BY total DESC, u.name ASC
          LIMIT " . $limit,
        array($from, $to)
    );
}

/**
 * Requests per day, filling the gaps.
 *
 * Days with no bookings are returned as zero rather than being skipped, so a
 * chart or table does not have to guess which dates are missing.
 *
 * @param  string $from
 * @param  string $to
 * @return array
 */
function report_daily_counts($from, $to)
{
    $rows = array();
    foreach (db_all(
        "SELECT booking_date, COUNT(*) AS n
           FROM bookings
          WHERE booking_date BETWEEN ? AND ?
          GROUP BY booking_date
          ORDER BY booking_date ASC",
        array($from, $to)
    ) as $row) {
        $rows[$row['booking_date']] = (int) $row['n'];
    }

    $out  = array();
    $days = (int) floor((strtotime($to) - strtotime($from)) / 86400) + 1;

    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime($from) + $i * 86400);
        $out[] = array('date' => $date, 'count' => isset($rows[$date]) ? $rows[$date] : 0);
    }

    return $out;
}

/**
 * Every figure the page needs, in one place.
 *
 * @param  array $range array('from','to','days')
 * @return array
 */
function report_all(array $range)
{
    $from = $range['from'];
    $to   = $range['to'];

    $counts = report_status_counts($from, $to);

    return array(
        'counts'    => $counts,
        'rate'      => report_approval_rate($counts),
        'lead'      => report_average_lead_hours($from, $to),
        'labs'      => report_laboratory_usage($from, $to),
        'slots'     => report_slot_usage($from, $to),
        'students'  => report_student_activity($from, $to),
        'studentCount' => report_distinct_students($from, $to),
        'daily'     => report_daily_counts($from, $to),
    );
}

/**
 * Turn a report table into CSV text.
 *
 * @param  array  $headings
 * @param  array  $rows
 * @return string
 */
function report_csv(array $headings, array $rows)
{
    $out = fopen('php://temp', 'r+');

    // A BOM makes Excel read the file as UTF-8 instead of guessing the
    // encoding, which matters for names outside ASCII.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headings);

    foreach ($rows as $row) {
        fputcsv($out, $row);
    }

    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);

    return $csv;
}
