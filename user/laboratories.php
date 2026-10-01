<?php
/**
 * Computer Laboratory Booking System - laboratory directory (student).
 *
 * Search and status filter, with a link through to each laboratory's own page.
 * Only the user's own bookings are counted when showing how busy a laboratory
 * is; nobody else can infer anything from this page.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_user();

$userId = current_user_id();

$search = query('q', '');
$onlyAvailable = query('available', '') === '1';

$where = array();
$params = array();

if ($search !== '') {
    $where[] = '(l.name LIKE ? OR l.location LIKE ? OR l.description LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($onlyAvailable) {
    $where[] = "l.status = 'available'";
}

$sql = 'SELECT l.*'
     . ' FROM laboratories l';

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

// Unavailable laboratories sort last whatever the search.
$sql .= " ORDER BY l.status <> 'available', l.name ASC";

$labs = db_all($sql, $params);

// How busy each laboratory is over the next fortnight, and whether the student
// already holds a session there.
$busy = array();
foreach (db_all(
    'SELECT laboratory_id, COUNT(*) AS total
       FROM bookings
      WHERE user_id = ?
        AND booking_date >= CURDATE()
        AND status IN (\'pending\', \'approved\')
      GROUP BY laboratory_id',
    array($userId)
) as $row) {
    $busy[(int) $row['laboratory_id']] = (int) $row['total'];
}

$slots = active_time_slots();

layout_start(array('title' => 'Laboratories', 'active' => 'laboratories'));
?>

<?php page_heading('Laboratories', 'Every computer laboratory in this facility'); ?>

<?php render_flashes(); ?>

<form method="get" action="" class="row g-2 mb-4">
    <div class="col-sm-8 col-lg-6">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" id="q" name="q"
                   value="<?php echo e($search); ?>"
                   placeholder="Search by name, location or description">
        </div>
    </div>
    <div class="col-sm-4 col-lg-3 d-flex align-items-center">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="available" name="available" value="1"
                   <?php echo $onlyAvailable ? 'checked' : ''; ?>>
            <label class="form-check-label" for="available">Only show bookable ones</label>
        </div>
    </div>
    <div class="col-lg-3 d-flex align-items-center justify-content-lg-end">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
    </div>
</form>

<?php if (empty($labs)): ?>
    <?php echo empty_state(
        'bi-search',
        'No laboratories match',
        $search === '' ? 'None are open for booking right now.' : 'Try a different search.'
    ); ?>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($labs as $lab): ?>
            <?php $mine = isset($busy[(int) $lab['id']]) ? $busy[(int) $lab['id']] : 0; ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h2 class="h6 fw-bold mb-0"><?php echo e($lab['name']); ?></h2>
                            <span class="badge <?php echo e(lab_status_badge_class($lab['status'])); ?>">
                                <?php echo e(laboratory_status_label($lab['status'])); ?>
                            </span>
                        </div>

                        <p class="small text-muted mb-3">
                            <i class="bi bi-geo-alt me-1"></i><?php echo e($lab['location']); ?>
                        </p>

                        <p class="small mb-3"><?php echo e($lab['description']); ?></p>

                        <div class="d-flex gap-3 small border-top pt-3">
                            <span><i class="bi bi-people me-1"></i><?php echo (int) $lab['capacity']; ?> seats</span>
                            <span>
                                <i class="bi bi-pc-display me-1"></i><?php echo (int) $lab['computer_count']; ?>
                                <?php echo (int) $lab['computer_count'] === 1 ? 'computer' : 'computers'; ?>
                            </span>
                        </div>

                        <?php if ($mine > 0): ?>
                            <div class="small text-primary mt-2">
                                <i class="bi bi-calendar-check me-1"></i>
                                You have <?php echo (int) $mine; ?> upcoming
                                <?php echo $mine === 1 ? 'session' : 'sessions'; ?> here
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white border-top-0 d-flex gap-2">
                        <a class="btn btn-sm btn-outline-secondary flex-fill"
                           href="<?php echo e(url('user/laboratory.php?id=' . (int) $lab['id'])); ?>">
                            Details
                        </a>
                        <?php if ($lab['status'] === 'available'): ?>
                            <a class="btn btn-sm btn-primary flex-fill"
                               href="<?php echo e(url('user/book.php?lab=' . (int) $lab['id'])); ?>">
                                <i class="bi bi-calendar-plus me-1"></i> Book
                            </a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-secondary flex-fill" disabled>
                                <i class="bi bi-lock me-1"></i> Unavailable
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="small text-muted mt-3 mb-0">
        Sessions are
        <?php $slotParts = array(); foreach ($slots as $s) { $slotParts[] = e($s['label']) . ' (' . e(format_range($s['start_time'], $s['end_time'])) . ')'; } ?>
        <?php echo implode(', ', $slotParts); ?>.
    </p>
<?php endif; ?>

<?php layout_end(); ?>
