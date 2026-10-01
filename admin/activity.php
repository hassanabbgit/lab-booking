<?php
/**
 * Computer Laboratory Booking System - activity log (administrator).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$activity = db_all(
    'SELECT a.id, a.action, a.entity, a.entity_id, a.description, a.ip_address, a.created_at,
            u.name AS user_name, u.role
     FROM activity_logs a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC, a.id DESC
     LIMIT 100'
);

layout_start(array('title' => 'Activity Log', 'active' => 'activity'));
?>

<?php page_heading('Activity Log', 'The most recent 100 recorded actions'); ?>

<?php render_flashes(); ?>

<div class="card">
    <div class="card-header"><i class="bi bi-activity me-2 text-info"></i>Recorded actions</div>

    <?php if (empty($activity)): ?>
        <?php empty_state('bi-activity', 'Nothing recorded yet', 'Sign-ins and administrative changes will be listed here.'); ?>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP address</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($activity as $row): ?>
                <tr>
                    <td class="text-nowrap small"><?php echo e(format_date($row['created_at'], true)); ?></td>
                    <td>
                        <?php if (!empty($row['user_name'])): ?>
                            <?php echo e($row['user_name']); ?>
                        <?php else: ?>
                            <span class="text-muted">Guest</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?php echo e($row['action']); ?></span></td>
                    <td><?php echo e($row['description'] !== null && $row['description'] !== '' ? $row['description'] : '-'); ?></td>
                    <td class="small text-muted"><?php echo e($row['ip_address']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php layout_end(); ?>
