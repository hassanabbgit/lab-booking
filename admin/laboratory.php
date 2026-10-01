<?php
/**
 * Computer Laboratory Booking System - one laboratory (administrator).
 *
 * Shows the full record, the booking history attached to it, and the three
 * things an administrator can do to it: edit the details, change its status,
 * and delete it while that is still safe.
 *
 * Every action re-reads the row before acting rather than trusting the form,
 * so a stale page cannot overwrite a change made in another tab.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$id = query_int('id', 0);

$lab = $id > 0
    ? db_one('SELECT * FROM laboratories WHERE id = ?', array($id))
    : null;

if ($lab === null) {
    http_response_code(404);
    layout_start(array('title' => 'Laboratory not found', 'active' => 'laboratories'));
    echo empty_state(
        'bi-question-circle',
        'Laboratory not found',
        'That laboratory does not exist, or it has been deleted.'
    );
    echo '<div class="text-center"><a class="btn btn-primary" href="'
        . e(url('admin/laboratories.php')) . '">Back to laboratories</a></div>';
    layout_end();
    exit;
}

$bookings = laboratory_booking_summary($id);
$errors   = array();

/**
 * "Computer Laboratory 1" plus a verb, so the confirmation message reads like a
 * sentence rather than a status code.
 *
 * @param  string $name
 * @param  string $status
 * @return string
 */
function lab_name_after_status($name, $status)
{
    $verbs = array(
        'available'   => 'Back in service:',
        'maintenance' => 'Out for maintenance:',
        'inactive'    => 'Taken out of service:',
    );
    $verb = isset($verbs[$status]) ? $verbs[$status] : 'Updated:';
    return $verb . ' ' . $name;
}

/* ================================================================= actions */

if (is_post()) {
    csrf_guard();

    $action = post('action');

    /* ------------------------------------------------------------- update */
    if ($action === 'update') {
        $input  = laboratory_input_from_post();
        $errors = laboratory_validate($input, $id);

        if (empty($errors)) {
            $result = laboratory_update($id, $input);

            if ($result['ok']) {
                log_activity('update', 'laboratory', $id, 'Updated ' . $input['name']);
                flash('success', 'Changes to ' . $input['name'] . ' were saved.');
                redirect('admin/laboratory.php?id=' . $id);
            }

            $errors['name'] = $result['error'];
        }

        keep_old($input);
    }

    /* ------------------------------------------------------------- status */
    elseif ($action === 'status') {
        $wanted = post('status');
        $result = laboratory_set_status($id, $wanted);

        if ($result['ok']) {
            log_activity('update', 'laboratory', $id,
                'Marked ' . $lab['name'] . ' as ' . laboratory_status_label($wanted));
            flash('success', lab_name_after_status($lab['name'], $wanted)
                . ' is now ' . lcfirst(laboratory_status_label($wanted)) . '.');
            redirect('admin/laboratory.php?id=' . $id);
        }

        flash('warning', $result['error']);
        redirect('admin/laboratory.php?id=' . $id);
    }

    /* ------------------------------------------------------------- delete */
    elseif ($action === 'delete') {
        $result = laboratory_delete($id);

        if ($result['ok']) {
            log_activity('delete', 'laboratory', $id, 'Deleted ' . $lab['name']);
            flash('success', $lab['name'] . ' was deleted.');
            redirect('admin/laboratories.php');
        }

        flash('danger', $result['error']);
        redirect('admin/laboratory.php?id=' . $id);
    }
}

/* Re-read after a failed update so the form shows what was typed, not what is
   stored. */
if (!empty($errors)) {
    $lab['name']           = old('name', $lab['name']);
    $lab['location']       = old('location', $lab['location']);
    $lab['capacity']       = old('capacity', $lab['capacity']);
    $lab['computer_count'] = old('computer_count', $lab['computer_count']);
    $lab['description']    = old('description', $lab['description']);
    $lab['status']         = old('status', $lab['status']);
} else {
    clear_old();
}

$recent = db_all(
    'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.status, b.purpose,
            u.name AS student_name
       FROM bookings b
       JOIN users u ON u.id = b.user_id
      WHERE b.laboratory_id = ?
      ORDER BY b.booking_date DESC, b.start_time DESC
      LIMIT 15',
    array($id)
);

$deletable = $bookings['total'] === 0;

layout_start(array('title' => $lab['name'], 'active' => 'laboratories'));
?>

<?php
page_heading(
    $lab['name'],
    $lab['location'],
    '<a class="btn btn-outline-secondary" href="' . e(url('admin/laboratories.php')) . '">'
        . '<i class="bi bi-arrow-left me-1"></i> All laboratories</a>'
);
?>

<?php render_flashes(); ?>

<?php if (!empty($errors)): ?>
    <?php render_errors($errors); ?>
<?php endif; ?>

<div class="row g-4">
    <!-- ------------------------------------------------- details + actions -->
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-info-circle me-1"></i> Details
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Name</dt>
                    <dd class="col-7"><?php echo e($lab['name']); ?></dd>

                    <dt class="col-5">Location</dt>
                    <dd class="col-7"><?php echo e($lab['location']); ?></dd>

                    <dt class="col-5">Capacity</dt>
                    <dd class="col-7"><?php echo (int) $lab['capacity']; ?> students</dd>

                    <dt class="col-5">Computers</dt>
                    <dd class="col-7"><?php echo (int) $lab['computer_count']; ?></dd>

                    <dt class="col-5">Status</dt>
                    <dd class="col-7">
                        <span class="badge <?php echo e(lab_status_badge_class($lab['status'])); ?>">
                            <?php echo e(laboratory_status_label($lab['status'])); ?>
                        </span>
                    </dd>

                    <dt class="col-5">Description</dt>
                    <dd class="col-7">
                        <?php echo $lab['description'] === null || $lab['description'] === ''
                            ? '<span class="text-muted">None</span>'
                            : nl2br(e($lab['description'])); ?>
                    </dd>

                    <dt class="col-5">Added</dt>
                    <dd class="col-7 small text-muted">
                        <?php echo e(format_date($lab['created_at'], true)); ?>
                    </dd>
                </dl>
            </div>
        </div>

        <!-- status switches -->
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-toggle-on me-1"></i> Availability
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Changing the status never touches bookings that already exist.
                    A laboratory that is not available simply stops being offered
                    to students.
                </p>

                <div class="d-grid gap-2">
                    <?php foreach (laboratory_statuses() as $option):
                        $isCurrent = $lab['status'] === $option;
                    ?>
                        <form method="post" action="">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="status" value="<?php echo e($option); ?>">
                            <button type="submit"
                                    class="btn w-100 text-start <?php echo $isCurrent ? 'btn-secondary' : 'btn-outline-primary'; ?>"
                                    <?php echo $isCurrent ? 'disabled' : ''; ?>>
                                <i class="bi <?php echo $isCurrent ? 'bi-check-circle' : 'bi-arrow-right-circle'; ?> me-2"></i>
                                <?php echo e(laboratory_status_label($option)); ?>
                                <?php if ($isCurrent): ?>
                                    <span class="badge bg-light text-dark ms-1">current</span>
                                <?php endif; ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- delete, only offered when it is safe -->
        <div class="card border-danger">
            <div class="card-header bg-white fw-bold text-danger">
                <i class="bi bi-trash me-1"></i> Delete
            </div>
            <div class="card-body">
                <?php if ($deletable): ?>
                    <p class="small text-muted">
                        This laboratory has never been booked, so deleting it leaves
                        no history behind.
                    </p>
                    <form method="post" action=""
                          onsubmit="return confirm('Delete <?php echo e(addslashes($lab['name'])); ?>? This cannot be undone.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-trash me-1"></i> Delete this laboratory
                        </button>
                    </form>
                <?php else: ?>
                    <p class="small text-muted mb-2">
                        <strong><?php echo (int) $bookings['total']; ?></strong>
                        booking<?php echo $bookings['total'] === 1 ? ' has' : 's have' ?>
                        been recorded against this laboratory, so it cannot be deleted
                        without losing that history. Mark it inactive or in maintenance
                        instead; it stops appearing as bookable straight away and every
                        record stays intact.
                    </p>
                    <button class="btn btn-outline-danger" disabled>
                        <i class="bi bi-lock me-1"></i> Delete not possible
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------------------- edit -->
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-pencil me-1"></i> Edit details
            </div>
            <div class="card-body">
                <form method="post" action="" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update">

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="name">Name</label>
                        <input type="text" class="form-control" id="name" name="name"
                               maxlength="<?php echo LABORATORY_NAME_MAX; ?>"
                               value="<?php echo e($lab['name']); ?>" required>
                        <?php echo field_error($errors, 'name'); ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="location">Location</label>
                        <input type="text" class="form-control" id="location" name="location"
                               maxlength="<?php echo LABORATORY_LOCATION_MAX; ?>"
                               value="<?php echo e($lab['location']); ?>" required>
                        <?php echo field_error($errors, 'location'); ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-bold" for="capacity">Capacity</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="capacity" name="capacity"
                                       min="<?php echo LABORATORY_MIN_CAPACITY; ?>"
                                       max="<?php echo LABORATORY_MAX_CAPACITY; ?>"
                                       value="<?php echo e($lab['capacity']); ?>" required>
                                <span class="input-group-text">seats</span>
                            </div>
                            <?php echo field_error($errors, 'capacity'); ?>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label fw-bold" for="computer_count">Computers</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="computer_count" name="computer_count"
                                       min="0" max="<?php echo LABORATORY_MAX_COMPUTERS; ?>"
                                       value="<?php echo e($lab['computer_count']); ?>" required>
                                <span class="input-group-text">machines</span>
                            </div>
                            <?php echo field_error($errors, 'computer_count'); ?>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-bold" for="status">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <?php foreach (laboratory_statuses() as $option): ?>
                                <option value="<?php echo e($option); ?>"
                                    <?php echo $lab['status'] === $option ? 'selected' : ''; ?>>
                                    <?php echo e(laboratory_status_label($option)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php echo field_error($errors, 'status'); ?>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-bold" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description"
                                  rows="4"
                                  maxlength="<?php echo LABORATORY_DESCRIPTION_MAX; ?>"><?php echo e($lab['description']); ?></textarea>
                        <div class="form-text">Optional. Shown to students on the laboratory page.</div>
                        <?php echo field_error($errors, 'description'); ?>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Save changes
                        </button>
                        <a class="btn btn-outline-secondary"
                           href="<?php echo e(url('admin/laboratory.php?id=' . $id)); ?>">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- booking history -->
        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-calendar-check me-1"></i> Booking history
                <span class="badge bg-light text-dark ms-1"><?php echo (int) $bookings['total']; ?></span>
            </div>

            <div class="card-body border-bottom">
                <div class="row g-2 text-center">
                    <div class="col-6 col-md-3">
                        <div class="fs-5 fw-bold text-warning"><?php echo (int) $bookings['pending']; ?></div>
                        <div class="small text-muted">pending</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fs-5 fw-bold text-success"><?php echo (int) $bookings['approved']; ?></div>
                        <div class="small text-muted">approved</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fs-5 fw-bold text-secondary"><?php echo (int) $bookings['cancelled']; ?></div>
                        <div class="small text-muted">cancelled</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fs-5 fw-bold text-info"><?php echo (int) $bookings['completed']; ?></div>
                        <div class="small text-muted">completed</div>
                    </div>
                </div>
            </div>

            <?php if (empty($recent)): ?>
                <?php empty_state('bi-calendar-x', 'No bookings yet',
                    'Nothing has ever been requested for this laboratory.'); ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Reference</th>
                                <th>Student</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $row): ?>
                                <tr>
                                    <td class="font-monospace small"><?php echo e($row['booking_ref']); ?></td>
                                    <td class="small"><?php echo e($row['student_name']); ?></td>
                                    <td class="text-nowrap small"><?php echo e(format_date($row['booking_date'])); ?></td>
                                    <td class="text-nowrap small">
                                        <?php echo e(format_range($row['start_time'], $row['end_time'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo e(status_badge_class($row['status'])); ?>">
                                            <?php echo e(ucfirst($row['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($bookings['total'] > count($recent)): ?>
                    <div class="card-footer bg-white small text-muted">
                        Showing the most recent <?php echo count($recent); ?> of
                        <?php echo (int) $bookings['total']; ?> bookings.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php layout_end(); ?>
