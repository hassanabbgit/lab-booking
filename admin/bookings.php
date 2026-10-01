<?php
/**
 * Computer Laboratory Booking System - all bookings (administrator).
 *
 * A searchable list of every booking the system has ever recorded. Unlike the
 * approvals queue, this page can cancel approved bookings, mark sessions as
 * completed after they have happened, and look into history without changing it.
 *
 * All status changes run through booking_decide(), so the lifecycle cannot be
 * bypassed. A session that is still in the future cannot be marked completed,
 * and a session that has already passed cannot be cancelled; the rules are the
 * same for every administrator.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$adminId = current_user_id();

/* ------------------------------------------------------------------ decide */

if (is_post()) {
    csrf_guard();

    $bookingId = (int) post('booking_id', 0);
    $action = post('decision', '');
    $note = post('note', '');

    $targets = array(
        'approve'  => 'approved',
        'reject'   => 'rejected',
        'cancel'   => 'cancelled',
        'complete' => 'completed',
    );

    if (!isset($targets[$action])) {
        flash('danger', 'That decision is not available from this page.');
        redirect('admin/bookings.php');
    }

    $result = booking_decide($bookingId, $targets[$action], $adminId, $note);

    if ($result['ok']) {
        flash('success', $result['message'] . ' The student has been notified.');
    } else {
        flash('danger', $result['error']);
    }

    $back = post('return', 'admin/bookings.php');

    if ($back === 'admin/booking.php') {
        redirect('admin/booking.php?id=' . $bookingId);
    }

    redirect('admin/bookings.php');
}

/* -------------------------------------------------------------------- list */

$search = query('q', '');
$labFilter = query_int('lab', 0);
$statusFilter = query('status', '');
$dateFrom = query('from', '');
$dateTo = query('to', '');

$where = array();
$params = array();

if ($search !== '') {
    $where[] = '(b.booking_ref LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR u.student_no LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($labFilter > 0) {
    $where[] = 'b.laboratory_id = ?';
    $params[] = $labFilter;
}

if ($statusFilter !== '' && in_array($statusFilter, booking_statuses(), true)) {
    $where[] = 'b.status = ?';
    $params[] = $statusFilter;
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = 'b.booking_date >= ?';
    $params[] = $dateFrom;
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = 'b.booking_date <= ?';
    $params[] = $dateTo;
}

$sql =
    'SELECT b.*, u.name AS user_name, u.email AS user_email, u.student_no,
            l.name AS lab_name, l.location, l.status AS lab_status
       FROM bookings b
       JOIN users u ON u.id = b.user_id
       JOIN laboratories l ON l.id = b.laboratory_id';

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY b.booking_date DESC, b.start_time DESC, b.id DESC';

$bookings = db_all($sql, $params);

// Totals, so the administrator can see the workload at a glance. Only the
// statuses that actually exist appear, so counts never drift if the lifecycle
// changes.
$statusCounts = array();
foreach (booking_statuses() as $status) {
    $statusCounts[$status] = 0;
}

foreach (db_all('SELECT status, COUNT(*) AS total FROM bookings GROUP BY status') as $row) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = (int) $row['total'];
    }
}

$totalAll = array_sum($statusCounts);

$today = booking_today();

layout_start(array('title' => 'All Bookings', 'active' => 'bookings'));

page_heading(
    'All bookings',
    'See every request, approval, rejection, cancellation and completed session.',
    '<a class="btn btn-outline-secondary" href="' . e(url('admin/approvals.php')) . '">Pending requests</a>'
);

// Refusals from booking_decide() arrive here as flash messages, so they have to
// be rendered or the page would give no feedback at all.
render_flashes();

// At-a-glance status cards. These are read-only — they help administrators
// focus on the area that needs attention without distracting from the table.
echo '<div class="row g-3 mb-4">';
foreach (array(
    'pending'   => 'bi-hourglass-split',
    'approved'  => 'bi-check-circle',
    'rejected'  => 'bi-x-circle',
    'cancelled' => 'bi-slash-circle',
    'completed' => 'bi-calendar-check',
) as $status => $icon) {
    stat_card(
        booking_status_label($status),
        (string) $statusCounts[$status],
        $icon,
        $status === 'pending' ? 'warning' : ($status === 'approved' ? 'success' : 'info'),
        url('admin/bookings.php?status=' . $status)
    );
}
stat_card('Total', (string) $totalAll, 'bi-collection', 'primary');
echo '</div>';

echo '<div class="card"><div class="card-header">';

echo '<form method="get" class="row g-2 align-items-end mb-0">';

echo '<div class="col-md-3"><label class="form-label" for="q">Find</label>'
   . '<input class="form-control" type="search" id="q" name="q" value="' . e($search) . '"'
   . ' placeholder="Reference, student or email"></div>';

echo '<div class="col-md-2"><label class="form-label" for="status">Status</label>'
   . '<select class="form-control" id="status" name="status"><option value="">All statuses</option>';

foreach (booking_statuses() as $status) {
    echo '<option value="' . e($status) . '"'
       . ($statusFilter === $status ? ' selected' : '') . '>'
       . e(booking_status_label($status)) . '</option>';
}

echo '</select></div>';

echo '<div class="col-md-3"><label class="form-label" for="lab">Laboratory</label>'
   . '<select class="form-control" id="lab" name="lab"><option value="0">All laboratories</option>';

foreach (db_all('SELECT id, name FROM laboratories ORDER BY name') as $lab) {
    echo '<option value="' . (int) $lab['id'] . '"'
       . ((int) $lab['id'] === $labFilter ? ' selected' : '') . '>'
       . e($lab['name']) . '</option>';
}

echo '</select></div>';

echo '<div class="col-md-2"><label class="form-label" for="from">From</label>'
   . '<input class="form-control" type="date" id="from" name="from" value="' . e($dateFrom) . '"></div>';

echo '<div class="col-md-2"><label class="form-label" for="to">To</label>'
   . '<input class="form-control" type="date" id="to" name="to" value="' . e($dateTo) . '"></div>';

echo '<div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Filter</button>';

$filtered = ($search !== '' || $labFilter > 0 || $statusFilter !== '' || $dateFrom !== '' || $dateTo !== '');

if ($filtered) {
    echo '<a class="btn btn-outline-secondary" href="' . e(url('admin/bookings.php')) . '">Clear</a>';
}

echo '</div></form></div>';

if (empty($bookings)) {
    empty_state(
        'bi-calendar-x',
        $filtered ? 'No bookings match' : 'No bookings yet',
        $filtered
            ? 'No bookings match these filters. Try widening the range or a different status.'
            : 'Bookings will appear here once students begin making requests.'
    );
} else {
    echo '<div class="table-responsive"><table class="table align-middle">';
    echo '<thead><tr>'
       . '<th>Ref.</th><th>Student</th><th>Laboratory</th><th>When</th><th>Status</th><th>Review</th>'
       . '</tr></thead><tbody>';

    foreach ($bookings as $booking) {
        $isPast = $booking['booking_date'] < $today;
        $isFuture = $booking['booking_date'] > $today;
        $isToday = $booking['booking_date'] === $today;
        $next = booking_next_statuses($booking['status']);
        $terminal = booking_is_terminal($booking['status']);

        echo '<tr>';

        echo '<td class="text-nowrap">';
        echo '<a href="' . e(url('admin/booking.php?id=' . (int) $booking['id'])) . '">'
           . e($booking['booking_ref']) . '</a>';
        echo '</td>';

        echo '<td>';
        echo '<div>' . e($booking['user_name']) . '</div>';
        echo '<div class="text-muted small">' . e($booking['student_no']) . '</div>';
        echo '<div class="text-muted small">' . e($booking['user_email']) . '</div>';
        echo '</td>';

        echo '<td>';
        echo '<div>' . e($booking['lab_name']) . '</div>';
        echo '<div class="text-muted small">' . e($booking['location']) . '</div>';
        echo '<div class="text-muted small">' . e(laboratory_status_label($booking['lab_status'])) . '</div>';
        echo '</td>';

        echo '<td class="text-nowrap">';
        echo '<div>' . e(format_date($booking['booking_date'])) . '</div>';
        echo '<div>' . e(format_range($booking['start_time'], $booking['end_time'])) . '</div>';
        echo '<div class="text-muted small">'
           . e(format_duration(duration_minutes($booking['start_time'], $booking['end_time']))) . '</div>';
        echo '</td>';

        echo '<td>';
        echo '<span class="badge ' . e(status_badge_class($booking['status'])) . '">'
           . e(booking_status_label($booking['status'])) . '</span>';

        if ($terminal) {
            echo '<div class="text-muted small mt-1">Final state</div>';
        } else {
            $parts = array();
            foreach ($next as $status) {
                $parts[] = strtolower(booking_status_label($status));
            }
            echo '<div class="text-muted small mt-1">Can be: '
               . ($parts === array() ? 'none' : implode(', ', $parts)) . '</div>';
        }

        if ($booking['admin_note'] !== null && $booking['admin_note'] !== '') {
            echo '<div class="text-muted small mt-1">Note: ' . e($booking['admin_note']) . '</div>';
        }

        echo '</td>';

        echo '<td class="text-nowrap">';
        echo '<div class="d-flex flex-column gap-2 align-items-end">';

        // Quick decisions, only where the transition is valid. The buttons are
        // intentionally minimal: complex cases should be handled from the full
        // booking record, which is the link at the end of the row.
        $canApprove = in_array('approved', $next, true) && $booking['lab_status'] === 'available' && !$isPast;
        $canReject  = in_array('rejected', $next, true);
        $canCancel  = in_array('cancelled', $next, true) && !$isPast;
        $canComplete = in_array('completed', $next, true) && !$isFuture;

        if ($canApprove) {
            echo '<form method="post" class="d-flex gap-1">';
            echo csrf_field();
            echo '<input type="hidden" name="booking_id" value="' . (int) $booking['id'] . '">';
            echo '<input type="hidden" name="return" value="admin/bookings.php">';
            echo '<button class="btn btn-sm btn-primary" type="submit" name="decision" value="approve">Approve</button>';
            echo '</form>';
        }

        if ($canReject) {
            echo '<form method="post" class="d-flex gap-1">';
            echo csrf_field();
            echo '<input type="hidden" name="booking_id" value="' . (int) $booking['id'] . '">';
            echo '<input type="hidden" name="return" value="admin/bookings.php">';
            /* Carries the reason the prompt collects, so the one-click route
               can still reach the server with one. */
            echo '<input type="hidden" name="note" value="">';
            echo '<button class="btn btn-sm btn-outline-danger" type="submit" name="decision" value="reject"'
               . ' data-require-reason="Why are you rejecting this booking? This is shown to the student.">Reject</button>';
            echo '</form>';
        }

        if ($canComplete) {
            echo '<form method="post" class="d-flex gap-1">';
            echo csrf_field();
            echo '<input type="hidden" name="booking_id" value="' . (int) $booking['id'] . '">';
            echo '<input type="hidden" name="return" value="admin/bookings.php">';
            echo '<button class="btn btn-sm btn-outline-info" type="submit" name="decision" value="complete">Mark completed</button>';
            echo '</form>';
        }

        if ($canCancel) {
            echo '<form method="post" class="d-flex gap-1">';
            echo csrf_field();
            echo '<input type="hidden" name="booking_id" value="' . (int) $booking['id'] . '">';
            echo '<input type="hidden" name="return" value="admin/bookings.php">';
            echo '<input type="hidden" name="note" value="">';
            echo '<button class="btn btn-sm btn-outline-secondary" type="submit" name="decision" value="cancel"'
               . ' data-require-reason="Why is this booking being cancelled? This is shown to the student.">Cancel</button>';
            echo '</form>';
        }

        /* Only offered when there is a decision left to make. Otherwise the
           button would carry an empty decision and post a request the server
           can only refuse. */
        if ($canReject || $canCancel) {
            echo '<form method="post" class="d-flex gap-1 align-items-center">';
            echo csrf_field();
            echo '<input type="hidden" name="booking_id" value="' . (int) $booking['id'] . '">';
            echo '<input type="hidden" name="return" value="admin/bookings.php">';
            echo '<input class="form-control form-control-sm" type="text" name="note" maxlength="255"'
               . ' style="max-width:160px" placeholder="Reason (required to reject)">';
            echo '<button class="btn btn-sm btn-outline-secondary" type="submit" name="decision" value="'
               . ($canReject ? 'reject' : 'cancel') . '"'
               . ' data-require-reason="A reason is required for this decision. It is shown to the student.">With reason</button>';
            echo '</form>';
        }

        echo '<a class="btn btn-sm btn-link px-0" href="' . e(url('admin/booking.php?id=' . (int) $booking['id'])) . '">Full details</a>';

        echo '</div>';
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

echo '</div>';

layout_end();
