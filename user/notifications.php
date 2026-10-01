<?php
/**
 * Computer Laboratory Booking System - notifications (shared by both roles).
 *
 * Copied to admin/ and user/ so the URL matches the folder the visitor is in.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_user();

$userId = current_user_id();

$selfPath = is_admin() ? 'admin/notifications.php' : 'user/notifications.php';

// Mark everything as read. This changes state, so it is a POST behind a CSRF
// token rather than a plain link that anyone could trigger with an <img> tag.
if (is_post() && post('action') === 'mark_all_read') {
    csrf_guard();
    db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', array($userId));
    flash('success', 'All notifications marked as read.');
    redirect($selfPath);
}

$notifications = db_all(
    'SELECT id, title, message, link, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 50',
    array($userId)
);

$unread = 0;
foreach ($notifications as $notification) {
    if ((int) $notification['is_read'] === 0) {
        $unread++;
    }
}

layout_start(array(
    'title'  => 'Notifications',
    'active'  => '',
    'layout'  => 'app',
));
?>

<?php
$markAllButton = '';
if ($unread > 0) {
    $markAllButton =
        '<form method="post" action="" class="d-inline">'
        . csrf_field()
        . '<input type="hidden" name="action" value="mark_all_read">'
        . '<button type="submit" class="btn btn-outline-primary">'
        . '<i class="bi bi-check2-all me-1"></i> Mark all read</button>'
        . '</form>';
}

page_heading(
    'Notifications',
    $unread > 0 ? $unread . ' unread message' . ($unread === 1 ? '' : 's') : 'You are all caught up',
    $markAllButton
);

render_flashes();
?>

<?php if (!empty($notifications)): ?>
    <div class="card">
        <div class="list-group list-group-flush">
            <?php foreach ($notifications as $notification): ?>
                <div class="list-group-item d-flex gap-3<?php echo (int) $notification['is_read'] === 0 ? ' bg-light' : ''; ?>">
                    <div class="flex-shrink-0">
                        <span class="badge rounded-circle p-2
                            <?php echo (int) $notification['is_read'] === 0 ? 'bg-primary' : 'bg-secondary'; ?>"
                              style="width:auto;height:auto">
                            <i class="bi <?php echo contains($notification['title'], 'approved') ? 'bi-check-circle' : (contains($notification['title'], 'rejected') ? 'bi-x-circle' : 'bi-bell'); ?>"></i>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between gap-2">
                            <strong><?php echo e($notification['title']); ?></strong>
                            <small class="text-muted text-nowrap"><?php echo e(format_date($notification['created_at'], true)); ?></small>
                        </div>
                        <p class="small mb-0 text-muted"><?php echo e($notification['message']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <?php empty_state('bi-bell-slash', 'No notifications', 'Updates about your bookings will appear here.'); ?>
    </div>
<?php endif; ?>

<?php layout_end(); ?>
