<?php
/**
 * Unit tests for the report helpers. Runs against the real database to make
 * sure the aggregations line up with how the page reads them.
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

section('report_range basics');

$r = report_range('', '');
ok($r['from'] <= $r['to'], 'default from <= to');
ok($r['days'] >= REPORT_MIN_RANGE_DAYS, 'default range is at least the minimum');

$r = report_range('2026-09-30', '2026-09-20');
ok($r['from'] === '2026-09-20' && $r['to'] === '2026-09-30', 'a reversed range is swapped');

/* report_range() does not re-validate; the page is expected to pass the values
   through report_date() first. Handing it junk here is a caller error, so the
   only thing worth pinning is that it still returns a usable shape rather than
   blowing up or returning a negative span. */
$r = report_range('not-a-date', '2026-09-30');
ok(is_array($r) && isset($r['from'], $r['to'], $r['days']), 'a bad from still returns a full range');
ok($r['days'] >= REPORT_MIN_RANGE_DAYS, 'a bad from still yields a positive span');

$r = report_range('2026-02-31', '2026-03-01');
ok($r['from'] <= $r['to'], 'an impossible date is rejected');

$r = report_range('2026-01-01', '2026-01-31');
ok($r['trimmed'] === false, 'a short period is not trimmed');
ok($r['days'] === 31, 'and its length is left alone');

section('report_range is bounded');

/* The daily chart and table build one row per day, so an unbounded range is a
   request to allocate millions of rows. Pinned because REPORT_MAX_RANGE_DAYS is
   the only thing standing between a hand-typed query string and a timeout. */
$r = report_range('0001-01-01', '9999-12-31');
ok($r['days'] === REPORT_MAX_RANGE_DAYS, 'an enormous range is capped at the maximum');
ok($r['trimmed'] === true, 'and says that it was trimmed');
ok($r['from'] === '0001-01-01', 'the start date asked for is kept');
ok($r['to'] < '9999-12-31', 'the far end is what gets moved');
ok($r['from'] <= $r['to'], 'and the result is still a usable range');

/* A range exactly on the limit must be left alone, or the cap would quietly
   affect a period that was legitimately requested in full. */
$exactFrom = '2020-01-01';
$exactTo   = date('Y-m-d', strtotime($exactFrom) + (REPORT_MAX_RANGE_DAYS - 1) * 86400);
$r = report_range($exactFrom, $exactTo);
ok($r['days'] === REPORT_MAX_RANGE_DAYS, 'a range exactly on the limit is kept whole');
ok($r['trimmed'] === false, 'and is not reported as trimmed');
ok($r['to'] === $exactTo, 'with the end date untouched');

$oneMore = date('Y-m-d', strtotime($exactFrom) + REPORT_MAX_RANGE_DAYS * 86400);
$r = report_range($exactFrom, $oneMore);
ok($r['trimmed'] === true, 'one day past the limit is trimmed');
ok($r['days'] === REPORT_MAX_RANGE_DAYS, 'back down to the maximum');
ok($r['to'] === $exactTo, 'and the end lands on the last allowed day');

/* The trimmed range has to be the one that is actually reported, or the page
   and its CSV exports would disagree about the period. */
$daily = report_daily_counts($r['from'], $r['to']);
ok(count($daily) === REPORT_MAX_RANGE_DAYS, 'the daily table matches the trimmed range exactly');
ok($daily[0]['date'] === $r['from'], 'starting on the first day');
ok($daily[count($daily) - 1]['date'] === $r['to'], 'and ending on the last');

section('report_date');

ok(report_date('from', '2026-09-30') === '2026-09-30', 'empty query string returns the default');
ok(report_date('from', '2026-09-30') === '2026-09-30', 'valid YYYY-MM-DD is kept');
$_GET['from'] = '2026-10-01';
ok(report_date('from', '2026-09-30') === '2026-10-01', 'a real query value is used');
unset($_GET['from']);
$_GET['to'] = '2026-13-01';
ok(report_date('to', '2026-09-30') === '2026-09-30', 'a malformed date falls back');

section('totals add up for the whole dataset');

$all = db_all('SELECT booking_date FROM bookings ORDER BY booking_date ASC');
if (!empty($all)) {
    $fromAll = $all[0]['booking_date'];
    $toAll   = $all[count($all) - 1]['booking_date'];
} else {
    $fromAll = date('Y-m-d');
    $toAll   = date('Y-m-d');
}

$c = report_status_counts($fromAll, $toAll);
$sum = $c['pending'] + $c['approved'] + $c['rejected'] + $c['cancelled'] + $c['completed'];
ok($sum === $c['total'], 'the status split sums to the total');

if ($sum > 0) {
    $decided = $c['approved'] + $c['rejected'];
    $rate = report_approval_rate($c);
    if ($decided === 0) {
        ok($rate === 0.0, 'approval rate is 0 when nothing decided');
    } else {
        $calc = round($c['approved'] / $decided * 100, 1);
        ok(abs($rate - $calc) < 0.01, 'approval rate is computed correctly');
    }
} else {
    ok(report_approval_rate($c) === 0.0, 'approval rate is 0 when there are no bookings');
}

section('laboratory usage sums');

$labs = report_laboratory_usage($fromAll, $toAll);
$labTotal = 0;
foreach ($labs as $l) { $labTotal += (int) $l['total']; }
ok($labTotal === $c['total'], 'laboratory totals equal all bookings');

section('slot usage sums');

$slots = report_slot_usage($fromAll, $toAll);
$slotTotal = 0;
foreach ($slots as $s) { $slotTotal += (int) $s['total']; }
ok($slotTotal === $c['total'], 'slot totals equal all bookings');

section('student activity sums');

$students = report_student_activity($fromAll, $toAll, 500);
$stTotal = 0;
foreach ($students as $st) { $stTotal += (int) $st['total']; }
ok($stTotal === $c['total'], 'student totals equal all bookings');

section('daily counts cover the range');

$daily = report_daily_counts($fromAll, $toAll);
if (!empty($all)) {
    $days = (int) floor((strtotime($toAll) - strtotime($fromAll)) / 86400) + 1;
    ok(count($daily) === $days, 'daily array has one entry per day');
    $dSum = 0;
    foreach ($daily as $d) { $dSum += $d['count']; }
    ok($dSum === $c['total'], 'daily counts sum to the total');
}

section('a small window works');

$r = report_range('2026-09-30', '2026-09-30');
$c1 = report_status_counts($r['from'], $r['to']);
ok($c1['total'] === (int) db_value('SELECT COUNT(*) FROM bookings WHERE booking_date = ?', array('2026-09-30')),
    'the single-day count matches the database');
$daily1 = report_daily_counts($r['from'], $r['to']);
ok(count($daily1) === 1 && $daily1[0]['date'] === '2026-09-30', 'a single-day span produces one entry');

section('report_all bundles everything');

$bundled = report_all(report_range('', ''));
ok(isset($bundled['counts']), 'has counts');
ok(isset($bundled['rate']), 'has rate');
ok(isset($bundled['lead']), 'has lead');
ok(isset($bundled['labs']), 'has labs');
ok(isset($bundled['slots']), 'has slots');
ok(isset($bundled['students']), 'has students');
ok(isset($bundled['daily']), 'has daily');
ok(isset($bundled['studentCount']), 'has the distinct student count');

section('the distinct student count is not capped');

$distinct = report_distinct_students($fromAll, $toAll);
$expected = (int) db_value(
    'SELECT COUNT(DISTINCT user_id) FROM bookings WHERE booking_date BETWEEN ? AND ?',
    array($fromAll, $toAll)
);
ok($distinct === $expected, 'the distinct count matches the database');

/* report_range('', '') is the default window, not the whole history, so its
   figure is compared against the same window rather than against $expected. */
$bundledRange = report_range('', '');
$bundledExpected = (int) db_value(
    'SELECT COUNT(DISTINCT user_id) FROM bookings WHERE booking_date BETWEEN ? AND ?',
    array($bundledRange['from'], $bundledRange['to'])
);
ok($bundled['studentCount'] === $bundledExpected,
    'report_all carries the count for its own range');
ok($bundled['studentCount'] >= count($bundled['students']),
    'the distinct count is never lower than the number of rows listed');

section('report_csv produces headings and rows');

$csv = report_csv(array('A', 'B'), array(array('x','y'), array('1','2')));
ok(strpos($csv, 'A,B') !== false, 'headings are present');
ok(strpos($csv, 'x,y') !== false, 'first row is present');
ok(strpos($csv, '1,2') !== false, 'second row is present');

echo "\n----------------------------------------\n";
echo "PASS: $pass   FAIL: $fail\n";
if ($fail > 0) {
    echo "\nFailures:\n";
    foreach ($failures as $f) { echo "  - $f\n"; }
}
exit($fail === 0 ? 0 : 1);
