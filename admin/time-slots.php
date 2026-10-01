<?php
/**
 * Computer Laboratory Booking System - time slot management (administrator).
 *
 * Lists every bookable window and lets an administrator add, edit, enable or
 * disable one. Deleting is offered only while a window has never been used,
 * so a period that appears in somebody's history is kept and disabled instead.
 *
 * Adding happens here rather than on a separate details page because a window
 * that does not exist yet has no details page to live on. Each row carries its
 * own inline edit and status controls, which keeps the whole list manageable
 * from one screen.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$editId   = query_int('edit', 0);
$errors   = array();
$addErrors = array();

/* ================================================================= actions */

if (is_post()) {
    csrf_guard();

    $action = post('action');

    /* ------------------------------------------------------------- create */
    if ($action === 'create') {
        $input     = time_slot_input_from_post();
        $addErrors = time_slot_validate($input, 0);

        if (empty($addErrors)) {
            $result = time_slot_create($input);

            if ($result['ok']) {
                log_activity('create', 'time_slot', $result['id'], 'Added ' . $input['label']);
                flash('success', trim($input['label']) . ' was added as '
                    . lcfirst(time_slot_active_label($input['is_active'])) . '.');
                redirect('admin/time-slots.php');
            }

            $addErrors['label'] = $result['error'];
        }

        keep_old($input);
    }

    /* ------------------------------------------------------------- update */
    elseif ($action === 'update') {
        /* The id comes from the body of the form, not the query string: the edit
           row posts back to this same URL, and query_int() would read the
           ?edit= parameter instead and update the wrong row, or none. */
        $slotId = post('id') !== '' ? (int) post('id') : 0;
        $input  = time_slot_input_from_post();
        $errors = time_slot_validate($input, $slotId);

        if (empty($errors)) {
            $result = time_slot_update($slotId, $input);

            if ($result['ok']) {
                log_activity('update', 'time_slot', $slotId, 'Updated ' . trim($input['label']));
                flash('success', 'Changes to ' . trim($input['label']) . ' were saved.');
                redirect('admin/time-slots.php');
            }

            $errors['label'] = $result['error'];
        }

        keep_old($input);
    }

    /* -------------------------------------------------------- enable/disable */
    elseif ($action === 'toggle') {
        $slotId = post('id') !== '' ? (int) post('id') : 0;
        $wanted = post('is_active') === '0' ? 0 : 1;
        $result = time_slot_set_active($slotId, $wanted);

        if ($result['ok']) {
            $slot = time_slot_find($slotId);
            log_activity('update', 'time_slot', $slotId,
                ($wanted === 1 ? 'Enabled ' : 'Disabled ') . ($slot ? $slot['label'] : 'slot'));
            flash('success', ($slot ? $slot['label'] : 'The slot') . ' is now '
                . lcfirst(time_slot_active_label($wanted)) . '.');
        } else {
            flash('warning', $result['error']);
        }

        redirect('admin/time-slots.php');
    }

    /* ------------------------------------------------------------- delete */
    elseif ($action === 'delete') {
        $slotId = (int) post('id');
        $slot   = time_slot_find($slotId);
        $result = time_slot_delete($slotId);

        if ($result['ok']) {
            log_activity('delete', 'time_slot', $slotId, 'Deleted ' . ($slot ? $slot['label'] : 'slot'));
            flash('success', ($slot ? $slot['label'] : 'The slot') . ' was deleted.');
        } else {
            flash('danger', $result['error']);
        }

        redirect('admin/time-slots.php');
    }
}

if (!empty($errors) || !empty($addErrors)) {
    // Old values are kept by the action handlers above; nothing to clear.
} else {
    clear_old();
}

/* ================================================================ read data */

$slots = time_slot_list();

/* How many bookings sit inside each window, so the list can show which are in
   use and offer deletion only for the ones that are not. */
$usage = time_slot_booking_counts();

$totalSlots   = count($slots);
$activeSlots  = 0;
$inUseSlots   = 0;
foreach ($slots as $s) {
    if ((int) $s['is_active'] === 1) {
        $activeSlots++;
    }
    $slotId = (int) $s['id'];
    if (isset($usage[$slotId]) && $usage[$slotId] > 0) {
        $inUseSlots++;
    }
}

$editing = $editId > 0 ? time_slot_find($editId) : null;

layout_start(array('title' => 'Time Slots', 'active' => 'slots'));
?>

<?php page_heading('Time Slots',
    'Define the periods of the day that can be booked'); ?>

<?php render_flashes(); ?>

<?php if (!empty($errors)): ?>
    <?php render_errors($errors); ?>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php stat_card('Time slots', $totalSlots, 'bi-clock-history', 'primary'); ?>
    <?php stat_card('Bookable', $activeSlots, 'bi-check-circle', 'success'); ?>
    <?php stat_card('Disabled', $totalSlots - $activeSlots, 'bi-pause-circle', 'secondary'); ?>
    <?php stat_card('In use', $inUseSlots, 'bi-calendar-check', 'info'); ?>
</div>

<div class="row g-4">
    <!-- -------------------------------------------------------- the list -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-list-ul me-1"></i> Configured windows
                <span class="badge bg-light text-dark ms-1"><?php echo (int) $totalSlots; ?></span>
            </div>

            <?php if (empty($slots)): ?>
                <?php
                echo empty_state(
                    'bi-clock-history',
                    'No time slots yet',
                    'Add the first bookable window with the form alongside.'
                );
                ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Label</th>
                                <th>Start</th>
                                <th>End</th>
                                <th class="text-center">Length</th>
                                <th class="text-center">Bookings</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($slots as $slot):
                            $id       = (int) $slot['id'];
                            $isActive = (int) $slot['is_active'] === 1;
                            $inUse    = isset($usage[$id]) ? $usage[$id] : 0;
                            $sm       = time_to_minutes($slot['start_time']);
                            $em       = time_to_minutes($slot['end_time']);
                            $length   = ($sm !== null && $em !== null) ? $em - $sm : 0;
                            $isEditing = $editing !== null && (int) $editing['id'] === $id;
                        ?>
                            <tr>
                                <td class="fw-bold"><?php echo e($slot['label']); ?></td>
                                <td><?php echo e(format_time($slot['start_time'])); ?></td>
                                <td><?php echo e(format_time($slot['end_time'])); ?></td>
                                <td class="text-center small text-muted">
                                    <?php echo e(format_duration($length)); ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($inUse > 0): ?>
                                        <span class="badge bg-info"><?php echo (int) $inUse; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo e(time_slot_active_badge($isActive)); ?>">
                                        <?php echo e(time_slot_active_label($isActive)); ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex gap-1">
                                        <?php if ($isEditing): ?>
                                            <a class="btn btn-sm btn-outline-secondary"
                                               href="<?php echo e(url('admin/time-slots.php')); ?>"
                                               title="Cancel edit">
                                                <i class="bi bi-x-lg"></i>
                                            </a>
                                        <?php else: ?>
                                            <a class="btn btn-sm btn-outline-primary"
                                               href="<?php echo e(url('admin/time-slots.php?edit=' . $id)); ?>"
                                               title="Edit this slot">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>

                                         <form method="post" action="" class="d-inline">
                                             <?php echo csrf_field(); ?>
                                             <input type="hidden" name="action" value="toggle">
                                             <input type="hidden" name="id" value="<?php echo $id; ?>">
                                             <input type="hidden" name="is_active" value="<?php echo $isActive ? '0' : '1'; ?>">
                                             <button type="submit"
                                                     class="btn btn-sm <?php echo $isActive ? 'btn-outline-secondary' : 'btn-outline-success'; ?>"
                                                     title="<?php echo $isActive ? 'Disable this slot' : 'Enable this slot'; ?>">
                                                 <i class="bi <?php echo $isActive ? 'bi-pause' : 'bi-play'; ?>"></i>
                                             </button>
                                         </form>

                                         <?php if ($inUse === 0): ?>
                                             <form method="post" action="" class="d-inline"
                                                   onsubmit="return confirm('Delete <?php echo e(addslashes($slot['label'])); ?>? This cannot be undone.');">
                                                 <?php echo csrf_field(); ?>
                                                 <input type="hidden" name="action" value="delete">
                                                 <input type="hidden" name="id" value="<?php echo $id; ?>">
                                                 <button type="submit" class="btn btn-sm btn-outline-danger"
                                                         title="Delete this slot">
                                                     <i class="bi bi-trash"></i>
                                                 </button>
                                             </form>
                                         <?php else: ?>
                                             <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                                     title="Cannot delete a slot that is already in use">
                                                 <i class="bi bi-lock"></i>
                                             </button>
                                         <?php endif; ?>
                                     </div>
                                 </td>
                             </tr>

                            <?php if ($isEditing): ?>
                                <tr class="table-light">
                                    <td colspan="7">
                                        <form method="post" action="" novalidate
                                              class="row g-2 align-items-end">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id" value="<?php echo $id; ?>">

                                            <div class="col-md-3">
                                                <label class="form-label small fw-bold" for="edit_label_<?php echo $id; ?>">
                                                    Label
                                                </label>
                                                <input type="text" class="form-control form-control-sm"
                                                       id="edit_label_<?php echo $id; ?>" name="label"
                                                       maxlength="<?php echo TIME_SLOT_LABEL_MAX; ?>"
                                                       value="<?php echo e(old('label', $slot['label'])); ?>"
                                                       required>
                                                <?php echo field_error($errors, 'label'); ?>
                                            </div>

                                            <div class="col-md-2">
                                                <label class="form-label small fw-bold" for="edit_start_<?php echo $id; ?>">
                                                    Start
                                                </label>
                                                <input type="time" class="form-control form-control-sm"
                                                       id="edit_start_<?php echo $id; ?>" name="start_time"
                                                       value="<?php echo e(old('start_time', substr($slot['start_time'], 0, 5))); ?>"
                                                       required>
                                                <?php echo field_error($errors, 'start_time'); ?>
                                            </div>

                                            <div class="col-md-2">
                                                <label class="form-label small fw-bold" for="edit_end_<?php echo $id; ?>">
                                                    End
                                                </label>
                                                <input type="time" class="form-control form-control-sm"
                                                       id="edit_end_<?php echo $id; ?>" name="end_time"
                                                       value="<?php echo e(old('end_time', substr($slot['end_time'], 0, 5))); ?>"
                                                       required>
                                                <?php echo field_error($errors, 'end_time'); ?>
                                            </div>

                                            <div class="col-md-2">
                                                <label class="form-label small fw-bold" for="edit_active_<?php echo $id; ?>">
                                                    Bookable
                                                </label>
                                                <select class="form-select form-select-sm"
                                                        id="edit_active_<?php echo $id; ?>" name="is_active">
                                                    <option value="1" <?php echo old('is_active', (string) $isActive) === '1' ? 'selected' : ''; ?>>
                                                        Yes
                                                    </option>
                                                    <option value="0" <?php echo old('is_active', (string) $isActive) === '0' ? 'selected' : ''; ?>>
                                                        No
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="col-md-3 d-grid">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-check-lg me-1"></i> Save
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card mt-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-shield-check me-1"></i> How these windows are used
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">
                    A booking has to sit entirely inside one bookable window. Windows
                    that merely touch, such as 13:00&ndash;17:00 and 17:00&ndash;20:00,
                    stay separate, so a booking cannot straddle two of them.
                </p>
                <p class="small text-muted mb-2">
                    The bookings column counts anything sitting inside a window, not
                    only sessions whose times match it exactly. Where two windows
                    overlap, a booking in the shared part is counted by both, so the
                    column does not have to add up to the total number of bookings.
                </p>
                <p class="small text-muted mb-0">
                    Disabling a window stops students choosing it straight away. Any
                    bookings already made inside it are left alone, so turning a
                    window off never rewrites somebody's history.
                </p>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------------- the add form -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-plus-circle me-1"></i> Add a time slot
            </div>
            <div class="card-body">
                <?php if (!empty($addErrors)): ?>
                    <?php render_errors($addErrors); ?>
                <?php endif; ?>

                <form method="post" action="" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="new_label">Label</label>
                        <input type="text" class="form-control" id="new_label" name="label"
                               maxlength="<?php echo TIME_SLOT_LABEL_MAX; ?>"
                               value="<?php echo e(old('label')); ?>"
                               placeholder="Morning Session" required>
                        <?php echo field_error($addErrors, 'label'); ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold" for="new_start">Start</label>
                            <input type="time" class="form-control" id="new_start" name="start_time"
                                   value="<?php echo e(old('start_time')); ?>" required>
                            <?php echo field_error($addErrors, 'start_time'); ?>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-bold" for="new_end">End</label>
                            <input type="time" class="form-control" id="new_end" name="end_time"
                                   value="<?php echo e(old('end_time')); ?>" required>
                            <?php echo field_error($addErrors, 'end_time'); ?>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-bold" for="new_active">Bookable</label>
                        <select class="form-select" id="new_active" name="is_active">
                            <option value="1" <?php echo old('is_active', '1') === '1' ? 'selected' : ''; ?>>
                                Yes &mdash; students can book it
                            </option>
                            <option value="0" <?php echo old('is_active', '1') === '0' ? 'selected' : ''; ?>>
                                No &mdash; keep it on record but stop bookings
                            </option>
                        </select>
                        <div class="form-text">
                            A window must run for at least
                            <?php echo TIME_SLOT_MIN_LENGTH_MINUTES; ?> minutes, and the
                            end time has to be after the start.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-4">
                        <i class="bi bi-plus-lg me-1"></i> Add time slot
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php layout_end(); ?>
