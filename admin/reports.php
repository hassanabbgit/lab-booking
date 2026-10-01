<?php
/**
 * Computer Laboratory Booking System - reports (administrator).
 *
 * Counts and comparisons for a chosen period, with every figure downloadable as
 * CSV. The queries live in includes/reports.php so they can be tested without
 * rendering this page; this file is only the presentation.
 *
 * The date range is read from the query string and applied to every report on
 * the page, so the cards and the tables always agree with each other.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$range = report_range(report_date('from', ''), report_date('to', ''));
$from  = $range['from'];
$to    = $range['to'];

/* ==================================================================== CSV */

if (query('export', '') !== '') {
    $which = query('export', 'summary');
    $data  = report_all($range);

    switch ($which) {
        case 'laboratories':
            $headings = array('Laboratory', 'Location', 'Bookings', 'Approved', 'Completed');
            $rows = array();
            foreach ($data['labs'] as $row) {
                $rows[] = array($row['name'], $row['location'], (int) $row['total'],
                    (int) $row['approved'], (int) $row['completed']);
            }
            $stem = 'laboratory-usage';
            break;

        case 'students':
            $headings = array('Student', 'Student number', 'Email', 'Bookings',
                'Approved', 'Pending', 'Completed', 'Cancelled', 'Rejected');
            $rows = array();
            foreach ($data['students'] as $row) {
                $rows[] = array($row['name'], $row['student_no'], $row['email'],
                    (int) $row['total'], (int) $row['approved'], (int) $row['pending'],
                    (int) $row['completed'], (int) $row['cancelled'], (int) $row['rejected']);
            }
            $stem = 'student-activity';
            break;

        case 'daily':
            $headings = array('Date', 'Bookings');
            $rows = array();
            foreach ($data['daily'] as $row) {
                $rows[] = array($row['date'], (int) $row['count']);
            }
            $stem = 'daily-requests';
            break;

        case 'summary':
        default:
            $c = $data['counts'];
            $headings = array('Metric', 'Value');
            $rows = array(
                array('From', $from),
                array('To', $to),
                array('Days in period', $range['days']),
                array('Total bookings', (int) $c['total']),
                array('Pending', (int) $c['pending']),
                array('Approved', (int) $c['approved']),
                array('Rejected', (int) $c['rejected']),
                array('Cancelled', (int) $c['cancelled']),
                array('Completed', (int) $c['completed']),
                array('Approval rate (%)', $data['rate']),
                array('Average lead time (hours)',
                    $data['lead'] === null ? 'no decisions yet' : $data['lead']),
                array('Distinct students', $data['studentCount']),
            );
            $stem = 'summary';
            break;
    }

    $filename = $stem . '-' . $from . '-to-' . $to . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($csv = report_csv($headings, $rows)));
    header('Pragma: no-cache');
    header('Expires: 0');

    log_activity('export', 'report', null, 'Exported ' . $which . ' report for ' . $from . ' to ' . $to);

    echo $csv;
    exit;
}

/* ================================================================== read */

$data = report_all($range);

$counts = $data['counts'];
$rate   = $data['rate'];
$lead   = $data['lead'];

/* Busiest and quietest room, ignoring rooms nobody booked at all. */
$used = array();
foreach ($data['labs'] as $lab) {
    if ((int) $lab['total'] > 0) {
        $used[] = $lab;
    }
}
$busiest = empty($used) ? null : $used[0];
$quietest = null;
if (count($used) > 1) {
    $quietest = $used[count($used) - 1];
}

/* Tallest day in the period, for the sparkline caption. */
$peakDay = null;
foreach ($data['daily'] as $day) {
    if ($peakDay === null || $day['count'] > $peakDay['count']) {
        $peakDay = $day;
    }
}
$peakHeight = 0;
foreach ($data['daily'] as $day) {
    if ($day['count'] > $peakHeight) {
        $peakHeight = $day['count'];
    }
}

$exportQuery = 'from=' . urlencode($from) . '&to=' . urlencode($to);

layout_start(array('title' => 'Reports', 'active' => 'reports'));
?>

<?php page_heading('Reports',
    'Booking statistics and usage summaries for a chosen period'); ?>

<?php render_flashes(); ?>

<form method="get" action="" class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-sm-4 col-lg-3">
                <label class="form-label fw-bold" for="from">From</label>
                <input type="date" class="form-control" id="from" name="from"
                       value="<?php echo e($from); ?>">
            </div>

            <div class="col-sm-4 col-lg-3">
                <label class="form-label fw-bold" for="to">To</label>
                <input type="date" class="form-control" id="to" name="to"
                       value="<?php echo e($to); ?>">
            </div>

            <div class="col-lg-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i> Apply period
                </button>
            </div>

            <div class="col-lg-3">
                <div class="d-grid gap-2">
                    <a class="btn btn-outline-secondary"
                       href="<?php echo e(url('admin/reports.php?from='
                           . urlencode(date('Y-m-d', strtotime('-6 days')))
                           . '&to=' . urlencode(date('Y-m-d')))); ?>">
                        Last 7 days
                    </a>
                    <a class="btn btn-outline-secondary"
                       href="<?php echo e(url('admin/reports.php?from='
                           . urlencode(date('Y-m-d', strtotime('-29 days')))
                           . '&to=' . urlencode(date('Y-m-d')))); ?>">
                        Last 30 days
                    </a>
                </div>
            </div>
        </div>

        <div class="small text-muted mt-3">
            Showing <strong><?php echo e(format_date($from)); ?></strong>
            to <strong><?php echo e(format_date($to)); ?></strong>,
            which is <?php echo (int) $range['days']; ?>
            day<?php echo $range['days'] === 1 ? '' : 's'; ?>.
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <?php stat_card('Total requests', $counts['total'], 'bi-journal-text', 'primary'); ?>
    <?php stat_card('Pending', $counts['pending'], 'bi-hourglass-split', 'warning'); ?>
    <?php stat_card('Approved', $counts['approved'], 'bi-check-circle', 'success'); ?>
    <?php stat_card('Rejected', $counts['rejected'], 'bi-x-circle', 'danger'); ?>
    <?php stat_card('Cancelled', $counts['cancelled'], 'bi-slash-circle', 'secondary'); ?>
    <?php stat_card('Completed', $counts['completed'], 'bi-check2-all', 'info'); ?>
</div>

<div class="row g-4">
    <!-- ------------------------------------------------------ headline figures -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-speedometer2 me-1"></i> At a glance
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-7">Approval rate</dt>
                    <dd class="col-5 text-end">
                        <?php if ($counts['approved'] + $counts['rejected'] === 0): ?>
                            <span class="text-muted">nothing decided yet</span>
                        <?php else: ?>
                            <strong><?php echo e($rate); ?>%</strong>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-7">Average lead time</dt>
                    <dd class="col-5 text-end">
                        <?php if ($lead === null): ?>
                            <span class="text-muted">no decisions yet</span>
                        <?php else: ?>
                            <strong><?php echo e($lead); ?> h</strong>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-7">Students who booked</dt>
                    <dd class="col-5 text-end">
                        <strong><?php echo (int) $data['studentCount']; ?></strong>
                    </dd>

                    <dt class="col-7">Busiest laboratory</dt>
                    <dd class="col-5 text-end">
                        <?php echo $busiest === null
                            ? '<span class="text-muted">none</span>'
                            : '<strong>' . e($busiest['name']) . '</strong>'
                                . '<div class="small text-muted">'
                                . (int) $busiest['total'] . ' request'
                                . ((int) $busiest['total'] === 1 ? '' : 's')
                                . '</div>'; ?>
                    </dd>

                    <dt class="col-7">Quietest laboratory</dt>
                    <dd class="col-5 text-end">
                        <?php echo $quietest === null
                            ? '<span class="text-muted">not enough data</span>'
                            : e($quietest['name'])
                                . '<div class="small text-muted">'
                                . (int) $quietest['total'] . ' request'
                                . ((int) $quietest['total'] === 1 ? '' : 's')
                                . '</div>'; ?>
                    </dd>
                </dl>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-bar-chart-line me-1"></i> Requests per day
            </div>
            <div class="card-body">
                <?php if ($counts['total'] === 0): ?>
                    <?php empty_state('bi-calendar-range', 'No requests in this period',
                        'Widen the dates above to see activity.'); ?>
                <?php else: ?>
                    <?php if ($peakDay !== null && $peakHeight > 0): ?>
                        <div class="small text-muted mb-2">
                            Busiest day:
                            <strong><?php echo e(format_date($peakDay['date'])); ?></strong>
                            with <?php echo (int) $peakDay['count']; ?> request<?php
                                echo $peakDay['count'] === 1 ? '' : 's'; ?>.
                        </div>
                    <?php endif; ?>

                    <?php
                    /* Plain CSS bars rather than a charting library: the data is a
                       single series and a dependency would outweigh the benefit. */
                    $shown = array_slice($data['daily'], -31);
                    ?>
                    <div class="d-flex align-items-end gap-1"
                         style="height: 120px;" role="img"
                         aria-label="Requests per day for the period">
                        <?php foreach ($shown as $day):
                            /* A day with no requests still gets a 2px mark, faded,
                               so a gap in the period reads as a gap instead of
                               disappearing. The bar height and the opacity are
                               built into one style attribute: a second one on the
                               same element would be ignored by the browser. */
                            $h    = $peakHeight > 0
                                ? max(2, (int) round($day['count'] / $peakHeight * 100))
                                : 2;
                            $zero = $day['count'] === 0;
                            $style = 'height: ' . $h . '%;'
                                . ($zero ? ' opacity:.3;' : '');
                        ?>
                            <div class="flex-fill bg-primary rounded-top"
                                 style="<?php echo e($style); ?>"
                                 title="<?php echo e(format_date($day['date'])) . ': '
                                     . (int) $day['count']; ?> request<?php
                                     echo $day['count'] === 1 ? '' : 's'; ?>"></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-between small text-muted mt-1">
                        <span><?php echo e(format_date($shown[0]['date'])); ?></span>
                        <span><?php echo e(format_date($shown[count($shown) - 1]['date'])); ?></span>
                    </div>

                    <?php if (count($data['daily']) > count($shown)): ?>
                        <div class="small text-muted mt-1">
                            Showing the last <?php echo count($shown); ?> of
                            <?php echo count($data['daily']); ?> days.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-download me-1"></i> Download as CSV
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Each file holds the figures for the period shown above, with one
                    row per line and a heading row.
                </p>
                <div class="d-grid gap-2">
                    <a class="btn btn-outline-primary"
                       href="<?php echo e(url('admin/reports.php?' . $exportQuery . '&export=summary')); ?>">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Summary
                    </a>
                    <a class="btn btn-outline-primary"
                       href="<?php echo e(url('admin/reports.php?' . $exportQuery . '&export=laboratories')); ?>">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Laboratory usage
                    </a>
                    <a class="btn btn-outline-primary"
                       href="<?php echo e(url('admin/reports.php?' . $exportQuery . '&export=students')); ?>">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Student activity
                    </a>
                    <a class="btn btn-outline-primary"
                       href="<?php echo e(url('admin/reports.php?' . $exportQuery . '&export=daily')); ?>">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Daily requests
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------------------- tables -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-pc-display me-1"></i> Laboratory usage
            </div>

            <?php if (empty($data['labs'])): ?>
                <?php empty_state('bi-pc-display', 'No laboratories', 'None configured yet.'); ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Laboratory</th>
                                <th>Location</th>
                                <th class="text-center">Requests</th>
                                <th class="text-center">Approved</th>
                                <th class="text-center">Completed</th>
                                <th style="width: 130px;">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $widest = 0;
                        foreach ($data['labs'] as $lab) {
                            if ((int) $lab['total'] > $widest) {
                                $widest = (int) $lab['total'];
                            }
                        }
                        foreach ($data['labs'] as $lab):
                            $total = (int) $lab['total'];
                            $pct   = ($counts['total'] > 0)
                                ? round($total / $counts['total'] * 100, 1) : 0;
                        ?>
                            <tr>
                                <td>
                                    <a class="fw-bold"
                                       href="<?php echo e(url('admin/laboratory.php?id=' . (int) $lab['id'])); ?>">
                                        <?php echo e($lab['name']); ?>
                                    </a>
                                </td>
                                <td class="small"><?php echo e($lab['location']); ?></td>
                                <td class="text-center">
                                    <?php if ($total === 0): ?>
                                        <span class="text-muted">0</span>
                                    <?php else: ?>
                                        <strong><?php echo $total; ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?php echo (int) $lab['approved']; ?></td>
                                <td class="text-center"><?php echo (int) $lab['completed']; ?></td>
                                <td>
                                    <?php if ($total === 0): ?>
                                        <span class="small text-muted">&mdash;</span>
                                    <?php else: ?>
                                        <div class="progress" style="height: 8px;"
                                             role="img"
                                             aria-label="<?php echo e($pct); ?> percent of all requests">
                                            <div class="progress-bar bg-primary"
                                                 style="width: <?php echo $widest > 0
                                                     ? round($total / $widest * 100) : 0; ?>%;"></div>
                                        </div>
                                        <div class="small text-muted"><?php echo e($pct); ?>%</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-clock-history me-1"></i> Busiest windows
            </div>
            <?php if (empty($data['slots'])): ?>
                <?php empty_state('bi-clock', 'No bookings in this period',
                    'No student picked a window in these dates.'); ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Window</th>
                                <th class="text-center">Requests</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        /* Match the raw times back to the slot table for its label,
                           falling back to the times alone when the window has since
                           been edited or removed. */
                        $known = array();
                        foreach (time_slot_list() as $slot) {
                            $known[$slot['start_time'] . '|' . $slot['end_time']] = $slot['label'];
                        }
                        ?>
                        <?php foreach ($data['slots'] as $row):
                            $key = $row['start_time'] . '|' . $row['end_time'];
                            $label = isset($known[$key]) ? $known[$key] : null;
                        ?>
                            <tr>
                                <td>
                                    <?php if ($label !== null): ?>
                                        <?php echo e($label); ?>
                                        <div class="small text-muted">
                                            <?php echo e(format_range($row['start_time'], $row['end_time'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">no matching window</span>
                                        <div class="small text-muted">
                                            <?php echo e(format_range($row['start_time'], $row['end_time'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?php echo (int) $row['total']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-people me-1"></i> Student activity
                <span class="badge bg-light text-dark ms-1"><?php echo (int) $data['studentCount']; ?></span>
                <?php if (count($data['students']) < (int) $data['studentCount']): ?>
                    <span class="text-muted small fw-normal ms-1">
                        showing the <?php echo count($data['students']); ?> most active
                    </span>
                <?php endif; ?>
            </div>

            <?php if (empty($data['students'])): ?>
                <?php empty_state('bi-people', 'No student activity',
                    'Nobody has booked a laboratory in this period.'); ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th class="text-center">Requests</th>
                                <th class="text-center">Approved</th>
                                <th class="text-center">Completed</th>
                                <th class="text-center">Pending</th>
                                <th class="text-center">Cancelled</th>
                                <th class="text-center">Rejected</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['students'] as $row): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo e($row['name']); ?></span>
                                    <div class="small text-muted">
                                        <?php echo e($row['student_no'] === null || $row['student_no'] === ''
                                            ? $row['email'] : $row['student_no']); ?>
                                    </div>
                                </td>
                                <td class="text-center"><strong><?php echo (int) $row['total']; ?></strong></td>
                                <td class="text-center text-success"><?php echo (int) $row['approved']; ?></td>
                                <td class="text-center text-info"><?php echo (int) $row['completed']; ?></td>
                                <td class="text-center text-warning"><?php echo (int) $row['pending']; ?></td>
                                <td class="text-center text-secondary"><?php echo (int) $row['cancelled']; ?></td>
                                <td class="text-center text-danger"><?php echo (int) $row['rejected']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php layout_end(); ?>
