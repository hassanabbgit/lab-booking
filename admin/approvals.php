<?php
/**
 * Computer Laboratory Booking System - pending requests (administrator).
 *
 * The queue of booking requests waiting for a decision. Each one is shown with
 * everything needed to decide it without opening anything else: who asked, for
 * which room, when, for what, and how that period is currently spoken for.
 *
 * Decisions go through booking_decide(), the only thing in the application
 * allowed to change a booking's status. In particular it re-checks the
 * laboratory when approving, because a period that was free when the student
 * asked may have been claimed by another request since.
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

    // The button that was pressed is the decision. The target status is not read
    // from a posted field, so a hand-crafted request cannot approve a booking by
    // submitting a value that is not on this list.
    $targets = array(
        'approve' => 'approved',
        'reject'  => 'rejected',
        'cancel'  => 'cancelled',
    );

    if (!isset($targets[$action])) {
        flash('danger', 'That is not a decision this page can make.');
        redirect('admin/approvals.php');
    }

    $result = booking_decide($bookingId, $targets[$action], $adminId, $note);

    if ($result['ok']) {
        flash('success', $result['message'] . ' The student has been notified.');
    } else {
        flash('danger', $result['error']);
    }

    // Whitelisted, so a posted value cannot be used to redirect anywhere.
    $back = post('return', 'admin/approvals.php');

    if ($back === 'admin/booking.php') {
        redirect('admin/booking.php?id=' . $bookingId);
    }

    redirect('admin/approvals.php');
}

/* -------------------------------------------------------------------- list */

$search = query('q', '');
$labFilter = query_int('lab', 0);
$dateFilter = query('date', '');

$where = array("b.status = 'pending'");
$params = array();

if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.student_no LIKE ? OR b.booking_ref LIKE ?)';
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

if ($dateFilter !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
    $where[] = 'b.booking_date = ?';
    $params[] = $dateFilter;
}

$requests = db_all(
    'SELECT b.*, u.name AS user_name, u.email AS user_email, u.student_no,
            l.name AS lab_name, l.location, l.status AS lab_status,
            l.capacity, l.computer_count
       FROM bookings b
       JOIN users u ON u.id = b.user_id
       JOIN laboratories l ON l.id = b.laboratory_id
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY b.booking_date ASC, b.start_time ASC, b.created_at ASC',
    $params
);

$totalPending = (int) db_value("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
$filtered = ($search !== '' || $labFilter > 0 || $dateFilter !== '');

layout_start(array('title' => 'Pending Requests', 'active' => 'approvals'));

page_heading(
    'Pending requests',
    $totalPending === 0
        ? 'Nothing is waiting for a decision.'
        : $totalPending . ' booking request' . ($totalPending === 1 ? ' is' : 's are') . ' waiting for a decision.',
    '<a class="btn btn-outline-secondary" href="' . e(url('admin/bookings.php')) . '">All bookings</a>'
);

// Every refusal this page produces is explained in a flash message, so it has
// to be rendered here. Without this an administrator who pressed Approve on a
// clashing request would be redirected to the queue with no idea why.
render_flashes();

echo '<div class="card"><div class="card-header">';

echo '<form method="get" class="row g-2 align-items-end mb-0">';

echo '<div class="col-md-4"><label class="form-label" for="q">Find</label>'
   . '<input class="form-control" type="search" id="q" name="q" value="' . e($search) . '"'
   . ' placeholder="Student, email or reference"></div>';

echo '<div class="col-md-3"><label class="form-label" for="lab">Laboratory</label>'
   . '<select class="form-control" id="lab" name="lab"><option value="0">All laboratories</option>';

foreach (db_all('SELECT id, name FROM laboratories ORDER BY name') as $lab) {
    echo '<option value="' . (int) $lab['id'] . '"'
       . ((int) $lab['id'] === $labFilter ? ' selected' : '') . '>'
       . e($lab['name']) . '</option>';
}

echo '</select></div>';

echo '<div class="col-md-3"><label class="form-label" for="date">Date</label>'
   . '<input class="form-control" type="date" id="date" name="date" value="' . e($dateFilter) . '"></div>';

echo '<div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Filter</button></div>';

echo '</form>';

if ($filtered) {
    echo '<a class="btn btn-sm btn-outline-secondary" href="' . e(url('admin/approvals.php')) . '">Clear filters</a>';
}

echo '</div>';

if (empty($requests)) {
    if ($filtered) {
        empty_state(
            'bi-search',
            'No requests match',
            'No pending booking matches these filters. Try a different search, laboratory or date.'
        );
    } else {
        empty_state(
            'bi-check2-circle',
            'Nothing waiting',
            'Every booking request has been dealt with. New requests appear here as students make them.'
        );
    }
} else {
    echo '<div class="table-responsive"><table class="table align-middle">';
    echo '<thead><tr>'
       . '<th>Student</th><th>Laboratory</th><th>When</th><th>Details</th>'
       . '<th class="text-end">Decision</th>'
       . '</tr></thead><tbody>';

    foreach ($requests as $booking) {
        $isPast = $booking['booking_date'] < booking_today();
        $labOpen = $booking['lab_status'] === 'available';

        // Anything else holding this period: a competing request still waiting,
        // or an approved booking that already has it. Both block approval, so
        // both are worth pointing out before the button is pressed. Pending is
        // not special here, and the query is the same one the engine uses.
        $competitors = db_all(
            'SELECT b.booking_ref, b.status, u.name AS user_name
               FROM bookings b
               JOIN users u ON u.id = b.user_id
              WHERE b.laboratory_id = ?
                AND b.booking_date = ?
                AND b.status IN (' . booking_blocking_status_sql() . ')
                AND b.id <> ?
                AND b.start_time < ?
                AND b.end_time > ?
              ORDER BY b.start_time',
            array(
                (int) $booking['laboratory_id'],
                $booking['booking_date'],
                (int) $booking['id'],
                $booking['end_time'],
                $booking['start_time'],
            )
        );

        echo '<tr>';

        /* who asked */
        echo '<td>';
        echo '<div>' . e($booking['user_name']) . '</div>';
        echo '<div class="text-muted small">' . e($booking['student_no']) . '</div>';
        echo '<div class="text-muted small">' . e(time_ago($booking['created_at'])) . '</div>';
        echo '</td>';

        /* where */
        echo '<td>';
        echo '<div>' . e($booking['lab_name']) . '</div>';
        echo '<div class="text-muted small">' . e($booking['location']) . '</div>';

        if (!$labOpen) {
            echo '<span class="badge bg-warning text-dark">' . e(laboratory_status_label($booking['lab_status'])) . '</span>';
        }

        echo '</td>';

        /* when */
        echo '<td class="text-nowrap">';
        echo '<div>' . e(format_date($booking['booking_date'])) . '</div>';
        echo '<div>' . e(format_range($booking['start_time'], $booking['end_time'])) . '</div>';
        echo '<div class="text-muted small">'
           . e(format_duration(duration_minutes($booking['start_time'], $booking['end_time']))) . '</div>';

        if ($isPast) {
            echo '<span class="badge bg-secondary">Date passed</span>';
        }

        echo '</td>';

        /* purpose, and anything that should be settled first */
        echo '<td>';
        echo '<div>' . e($booking['purpose']) . '</div>';
        echo '<div class="text-muted small">'
           . (int) $booking['computer_count'] . ' of ' . (int) $booking['capacity'] . ' computers</div>';

        if (!$labOpen) {
            echo '<div class="text-muted small">The room is out of service, so this cannot be approved'
               . ' until it is back in service.</div>';
        }

        if (!empty($competitors)) {
            $names = array();
            foreach ($competitors as $other) {
                $names[] = $other['booking_ref'] . ' (' . $other['user_name'] . ', '
                    . strtolower(booking_status_label($other['status'])) . ')';
            }

            echo '<div class="text-muted small">This period is already spoken for by '
               . e(implode(', ', $names)) . ', so it cannot be approved as well.</div>';
        }

        echo '</td>';

        /* decide */
        echo '<td class="text-end text-nowrap">';
        echo '<div class="d-flex gap-2 justify-content-end">';

        if (!$isPast && $labOpen) {
            echo '<form method="post" class="d-flex gap-1">';
            echo csrf_field();
            echo '<input type="hidden" name="booking_id" value="' . (int) $booking['id'] . '">';
            echo '<input type="hidden" name="return" value="admin/approvals.php">';
            echo '<button class="btn btn-sm btn-primary" type="submit" name="decision" value="approve"'
               . ' title="Approve ' . e($booking['booking_ref']) . '">Approve</button>';
            echo '</form>';
        }

        echo '<form method="post" class="d-flex gap-1">';
        echo csrf_field();
        echo '<input type="hidden" name="booking_id" value="' . (int) $booking['id'] . '">';
        echo '<input type="hidden" name="return" value="admin/approvals.php">';
        /* The prompt writes its answer here, so the form has to carry a note
           field of its own. Hidden, because this is the one-click route; the
           row below offers a visible field for typing a reason instead. */
        echo '<input type="hidden" name="note" value="">';
        echo '<button class="btn btn-sm btn-outline-danger" type="submit" name="decision" value="reject"'
           . ' data-require-reason="Why is this request being rejected? This is shown to the student."'
           . ' title="Reject ' . e($booking['booking_ref']) . '">Reject</button>';
        echo '</form>';

        echo '</div>';

        if (!$isPast && $labOpen && empty($competitors)) {
            echo '<div class="text-muted small mt-1">Room is free for this period</div>';
        }

        /* A reason can be typed in for any decision, including approving. A
           past request can only be rejected, and a rejection needs a reason, so
           the field is asked for in that case. Cancelling does not need one. */
        $isReject = $isPast;
        echo '<form method="post" class="mt-2 d-flex gap-1 justify-content-end">';
        echo csrf_field();
        echo '<input type="hidden" name="booking_id" value="' . (int) $booking['id'] . '">';
        echo '<input type="hidden" name="return" value="admin/approvals.php">';
        echo '<input class="form-control form-control-sm" type="text" name="note" maxlength="255"'
           . ' style="max-width:190px" placeholder="Reason (required to reject)">';
        echo '<button class="btn btn-sm btn-outline-secondary" type="submit" name="decision" value="'
           . ($isReject ? 'reject' : 'cancel') . '"'
           . ($isReject
               ? ' data-require-reason="This request can only be rejected, and a reason is required. It is shown to the student."'
               : '')
           . ' title="' . ($isReject ? 'Reject' : 'Cancel') . ' ' . e($booking['booking_ref'])
           . ' with the reason above">' . ($isReject ? 'Reject' : 'Cancel') . '</button>';
        echo '</form>';

        echo '<a class="btn btn-sm btn-link px-0" href="'
           . e(url('admin/booking.php?id=' . (int) $booking['id'])) . '">Full record</a>';

        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

echo '</div>';

layout_end();
