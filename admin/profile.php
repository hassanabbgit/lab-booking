<?php
/**
 * Computer Laboratory Booking System - my profile (administrator).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$userId = current_user_id();

$profile = db_one('SELECT * FROM users WHERE id = ?', array($userId));

$decided = (int) db_value(
    "SELECT COUNT(*) FROM bookings WHERE reviewed_by = ?",
    array($userId)
);

layout_start(array('title' => 'My Profile', 'active' => ''));
?>

<?php page_heading('My Profile', 'Your administrator account'); ?>

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
                <span class="badge bg-dark">Administrator</span>

                <hr class="my-3">

                <div class="d-flex justify-content-between small py-1">
                    <span class="text-muted">Staff number</span>
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
            <div class="card-header"><i class="bi bi-shield-lock me-2 text-primary"></i>Administrator access</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-4">
                        <?php stat_card('Decisions made', $decided, 'bi-check2-square', 'primary'); ?>
                    </div>
                    <div class="col-6 col-md-4">
                        <?php stat_card(
                            'Pending reviews',
                            (int) db_value("SELECT COUNT(*) FROM bookings WHERE status = 'pending'"),
                            'bi-inbox',
                            'warning'
                        ); ?>
                    </div>
                    <div class="col-6 col-md-4">
                        <?php stat_card(
                            'Total users',
                            (int) db_value('SELECT COUNT(*) FROM users'),
                            'bi-people',
                            'info'
                        ); ?>
                    </div>
                </div>

                <?php phase_notice('the User Management module', 'Account self-service is not enabled yet.'); ?>

                <p class="small text-muted mb-0">
                    You can change your own password and contact details from here once
                    password management is implemented. Until then, another administrator
                    can update your record from the <strong>Users</strong> page.
                </p>
            </div>
        </div>
    </div>

</div>

<?php layout_end(); ?>
