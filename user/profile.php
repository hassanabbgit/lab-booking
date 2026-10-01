<?php
/**
 * Computer Laboratory Booking System - my profile (student).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_user();

$userId = current_user_id();

$profile = db_one('SELECT * FROM users WHERE id = ?', array($userId));

$stats = array();
foreach (db_all('SELECT status, COUNT(*) AS total FROM bookings WHERE user_id = ? GROUP BY status', array($userId)) as $row) {
    $stats[$row['status']] = (int) $row['total'];
}

layout_start(array('title' => 'My Profile', 'active' => ''));
?>

<?php page_heading('My Profile', 'Your account details and booking summary'); ?>

<?php render_flashes(); ?>

<div class="row g-3">

    <div class="col-lg-4">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="avatar mx-auto mb-3" style="width:72px;height:72px;font-size:1.5rem">
                    <?php echo e(initials($profile['name'])); ?>
                </div>
                <h2 class="h5 fw-bold mb-1"><?php echo e($profile['name']); ?></h2>
                <p class="text-muted small mb-2"><?php echo e($profile['email']); ?></p>
                <span class="badge bg-primary">Student</span>
                <?php if ($profile['status'] !== 'active'): ?>
                    <span class="badge bg-danger">Deactivated</span>
                <?php endif; ?>

                <hr class="my-3">

                <div class="d-flex justify-content-between small py-1">
                    <span class="text-muted">Student number</span>
                    <strong><?php echo e($profile['student_no'] !== null && $profile['student_no'] !== '' ? $profile['student_no'] : '-'); ?></strong>
                </div>
                <div class="d-flex justify-content-between small py-1">
                    <span class="text-muted">Phone</span>
                    <strong><?php echo e($profile['phone'] !== null && $profile['phone'] !== '' ? $profile['phone'] : '-'); ?></strong>
                </div>
                <div class="d-flex justify-content-between small py-1">
                    <span class="text-muted">Member since</span>
                    <strong><?php echo e(format_date($profile['created_at'])); ?></strong>
                </div>
                <div class="d-flex justify-content-between small py-1">
                    <span class="text-muted">Last sign-in</span>
                    <strong><?php echo e(format_date($profile['last_login_at'], true)); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart me-2 text-primary"></i>My booking summary</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <?php
                    $tiles = array(
                        array('Total',     isset($stats['pending']) + isset($stats['approved']) + isset($stats['rejected']) + isset($stats['cancelled']) + isset($stats['completed']), 'bi-calendar-check', 'primary'),
                        array('Pending',   isset($stats['pending'])   ? $stats['pending']   : 0, 'bi-hourglass-split', 'warning'),
                        array('Approved',  isset($stats['approved'])  ? $stats['approved']  : 0, 'bi-check-circle',    'success'),
                        array('Completed', isset($stats['completed']) ? $stats['completed'] : 0, 'bi-flag',            'info'),
                    );
                    foreach ($tiles as $tile) {
                        echo '<div class="col-6 col-md-3">';
                        stat_card($tile[0], $tile[1], $tile[2], $tile[3]);
                        echo '</div>';
                    }
                    ?>
                </div>

                <?php phase_notice('the User Management module', 'Editing your own details is not enabled yet.'); ?>

                <p class="small text-muted mb-0">
                    If your name, email or student number is wrong, ask an administrator
                    to correct it from the <strong>Users</strong> page.
                </p>
            </div>
        </div>
    </div>

</div>

<?php layout_end(); ?>
