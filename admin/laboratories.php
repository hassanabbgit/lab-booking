<?php
/**
 * Computer Laboratory Booking System - laboratory management (administrator).
 *
 * The list every administrator works from, with search, a status filter, live
 * totals and the add form. Each row links through to admin/laboratory.php,
 * which is where editing, status changes and deletion happen.
 *
 * Adding is done here rather than on the details page because a laboratory that
 * does not exist yet has no details page to live on.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$search = query('q', '');
$filter = query('status', '');

if ($filter !== '' && !in_array($filter, laboratory_statuses(), true)) {
    $filter = '';
}

$where = array();
$params = array();

if ($search !== '') {
    $where[] = '(name LIKE ? OR location LIKE ? OR description LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($filter !== '') {
    $where[] = 'status = ?';
    $params[] = $filter;
}

$sql = 'SELECT * FROM laboratories';
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
// Problems first while managing, then whatever is in service, then the rest.
$sql .= " ORDER BY FIELD(status, 'maintenance', 'inactive', 'available'), name ASC";

$labs = db_all($sql, $params);

/* Booking counts for the whole facility, in one pass rather than per row. */
$counts = array();
foreach (db_all(
    'SELECT laboratory_id,
            COUNT(*) AS total,
            SUM(status = \'pending\' OR status = \'approved\') AS active
       FROM bookings GROUP BY laboratory_id'
) as $row) {
    $counts[(int) $row['laboratory_id']] = array(
        'total'  => (int) $row['total'],
        'active' => (int) $row['active'],
    );
}

$totals = laboratory_totals();

/* ================================================================== add */

$addErrors = array();

if (is_post() && post('action') === 'create') {
    csrf_guard();

    $input     = laboratory_input_from_post();
    $addErrors = laboratory_validate($input, 0);

    if (empty($addErrors)) {
        $result = laboratory_create($input);

        if ($result['ok']) {
            log_activity('create', 'laboratory', $result['id'], 'Added ' . $input['name']);
            flash('success', $input['name'] . ' was added as '
                . lcfirst(laboratory_status_label($input['status'])) . '.');
            redirect('admin/laboratory.php?id=' . $result['id']);
        }

        $addErrors['name'] = $result['error'];
    }

    // Keep what was typed so the form can be corrected rather than retyped.
    keep_old($input);
} else {
    clear_old();
}

layout_start(array('title' => 'Laboratories', 'active' => 'laboratories'));
?>

<?php page_heading('Laboratories',
    'Add, edit and take the computer laboratories in this facility in or out of service'); ?>

<?php render_flashes(); ?>

<div class="row g-3 mb-4">
    <?php stat_card('Laboratories', $totals['total'], 'bi-pc-display', 'primary'); ?>
    <?php stat_card('Available', $totals['available'], 'bi-check-circle', 'success'); ?>
    <?php stat_card('In maintenance', $totals['maintenance'], 'bi-tools', 'warning'); ?>
    <?php stat_card('Inactive', $totals['inactive'], 'bi-archive', 'secondary'); ?>
    <?php stat_card('Computers', $totals['computers'], 'bi-cpu', 'info'); ?>
</div>

<div class="row g-4">
    <!-- -------------------------------------------------------- the list -->
    <div class="col-lg-8">
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-sm-7">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="q" name="q"
                           value="<?php echo e($search); ?>"
                           placeholder="Search name, location or description">
                </div>
            </div>
            <div class="col-sm-5">
                <div class="d-flex gap-2">
                    <select class="form-select" name="status" aria-label="Filter by status">
                        <option value="">Any status</option>
                        <?php foreach (laboratory_statuses() as $option): ?>
                            <option value="<?php echo e($option); ?>"
                                <?php echo $filter === $option ? 'selected' : ''; ?>>
                                <?php echo e(laboratory_status_label($option)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-list-ul me-1"></i> All laboratories
                <span class="badge bg-light text-dark ms-1"><?php echo count($labs); ?></span>
            </div>

            <?php if (empty($labs)): ?>
                <?php
                echo empty_state(
                    'bi-pc-display',
                    $search === '' && $filter === '' ? 'No laboratories yet' : 'Nothing matches that filter',
                    $search === '' && $filter === ''
                        ? 'Add the first one with the form alongside.'
                        : 'Try a different search, or clear the filter.'
                );
                ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Laboratory</th>
                                <th>Location</th>
                                <th class="text-center">Seats</th>
                                <th class="text-center">PCs</th>
                                <th class="text-center">Bookings</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($labs as $lab):
                                $labId = (int) $lab['id'];
                                $c     = isset($counts[$labId])
                                    ? $counts[$labId] : array('total' => 0, 'active' => 0);
                            ?>
                                <tr>
                                    <td>
                                        <a class="fw-bold"
                                           href="<?php echo e(url('admin/laboratory.php?id=' . $labId)); ?>">
                                            <?php echo e($lab['name']); ?>
                                        </a>
                                        <div class="small text-muted">
                                            <?php echo (int) $lab['computer_count']; ?>
                                            computer<?php echo (int) $lab['computer_count'] === 1 ? '' : 's'; ?>,
                                            seats <?php echo (int) $lab['capacity']; ?>
                                        </div>
                                    </td>
                                    <td class="small"><?php echo e($lab['location']); ?></td>
                                    <td class="text-center"><?php echo (int) $lab['capacity']; ?></td>
                                    <td class="text-center"><?php echo (int) $lab['computer_count']; ?></td>
                                    <td class="text-center">
                                        <?php if ($c['total'] === 0): ?>
                                            <span class="text-muted">0</span>
                                        <?php else: ?>
                                            <?php echo (int) $c['total']; ?>
                                            <?php if ($c['active'] > 0): ?>
                                                <div class="small text-warning">
                                                    <?php echo (int) $c['active']; ?> live
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo e(lab_status_badge_class($lab['status'])); ?>">
                                            <?php echo e(laboratory_status_label($lab['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?php echo e(url('admin/laboratory.php?id=' . $labId)); ?>">
                                            Manage
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ------------------------------------------------------- the add form -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-plus-circle me-1"></i> Add a laboratory
            </div>
            <div class="card-body">
                <?php if (!empty($addErrors)): ?>
                    <?php render_errors($addErrors); ?>
                <?php endif; ?>

                <form method="post" action="" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="new_name">Name</label>
                        <input type="text" class="form-control" id="new_name" name="name"
                               maxlength="<?php echo LABORATORY_NAME_MAX; ?>"
                               value="<?php echo e(old('name')); ?>"
                               placeholder="Computer Laboratory 4" required>
                        <?php echo field_error($addErrors, 'name'); ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="new_location">Location</label>
                        <input type="text" class="form-control" id="new_location" name="location"
                               maxlength="<?php echo LABORATORY_LOCATION_MAX; ?>"
                               value="<?php echo e(old('location')); ?>"
                               placeholder="Block C - Room 202" required>
                        <?php echo field_error($addErrors, 'location'); ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold" for="new_capacity">Capacity</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="new_capacity" name="capacity"
                                       min="<?php echo LABORATORY_MIN_CAPACITY; ?>"
                                       max="<?php echo LABORATORY_MAX_CAPACITY; ?>"
                                       value="<?php echo e(old('capacity')); ?>"
                                       placeholder="40" required>
                                <span class="input-group-text">seats</span>
                            </div>
                            <?php echo field_error($addErrors, 'capacity'); ?>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-bold" for="new_computers">Computers</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="new_computers" name="computer_count"
                                       min="0" max="<?php echo LABORATORY_MAX_COMPUTERS; ?>"
                                       value="<?php echo e(old('computer_count')); ?>"
                                       placeholder="40" required>
                                <span class="input-group-text">machines</span>
                            </div>
                            <?php echo field_error($addErrors, 'computer_count'); ?>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-bold" for="new_status">Status</label>
                        <select class="form-select" id="new_status" name="status" required>
                            <?php $chosen = old('status', 'available'); ?>
                            <?php foreach (laboratory_statuses() as $option): ?>
                                <option value="<?php echo e($option); ?>"
                                    <?php echo $chosen === $option ? 'selected' : ''; ?>>
                                    <?php echo e(laboratory_status_label($option)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php echo field_error($addErrors, 'status'); ?>
                        <div class="form-text">
                            Only available laboratories can be booked by students.
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-bold" for="new_description">Description</label>
                        <textarea class="form-control" id="new_description" name="description"
                                  rows="3"
                                  maxlength="<?php echo LABORATORY_DESCRIPTION_MAX; ?>"
                                  placeholder="Optional notes shown to students"><?php echo e(old('description')); ?></textarea>
                        <?php echo field_error($addErrors, 'description'); ?>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-4">
                        <i class="bi bi-plus-lg me-1"></i> Add laboratory
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-shield-check me-1"></i> Deleting safely
            </div>
            <div class="card-body">
                <p class="small text-muted mb-0">
                    A laboratory can only be deleted while it has never been booked.
                    Once any booking refers to it, the record is kept and you are
                    asked to take it out of service instead, so no student's history
                    is ever lost. Marking a laboratory
                    <strong>in maintenance</strong> or <strong>inactive</strong> takes it
                    out of the booking form straight away without touching anything
                    already reserved.
                </p>
            </div>
        </div>
    </div>
</div>

<?php layout_end(); ?>
