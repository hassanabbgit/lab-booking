<?php
/**
 * Computer Laboratory Booking System - my profile (administrator).
 *
 * The same self-service page as user/profile.php, with an administrator's
 * figures underneath the heading. The shared behaviour lives in
 * includes/profile.php.
 *
 * The role and the account status are not offered here. An administrator
 * cannot change their own access, because that is how people lock themselves
 * out of their own system, and user_update() does not write those two columns,
 * so there is nothing on this page that could reach them. Both are changed by
 * another administrator from the Users page.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$userId = current_user_id();
$user   = user_find($userId);

$errors   = array();
$pwErrors = array();

/* ================================================================= actions */

if (is_post()) {
    csrf_guard();

    $action = post('action');

    /* ------------------------------------------------------------- details */
    if ($action === 'details') {
        /* role and status are filled in from the stored record, so validation
           has something to check while the form carries neither, and
           user_update() never writes those two columns. An administrator's own
           staff number is editable here, which is why this goes through the
           profile wrapper rather than the raw helper. */
        $input  = profile_details_input_from_post($user);
        $errors = user_validate($input, $userId);

        if (empty($errors)) {
            $result = user_update($userId, $input);

            if ($result['ok']) {
                log_activity('update', 'user', $userId, 'Updated own profile details');
                flash('success', 'Your details were saved.');
                redirect('admin/profile.php');
            }

            $errors[$result['field'] === '' ? 'name' : $result['field']] = $result['error'];
        }

        keep_old($input);
    }

    /* ------------------------------------------------------------ password */
    elseif ($action === 'password') {
        $result = profile_change_password(
            $userId,
            post('current_password'),
            post('new_password'),
            post('confirm_password')
        );

        if ($result['ok']) {
            flash('success', 'Your password was changed. The old one no longer works.');
            redirect('admin/profile.php');
        }

        $pwErrors[$result['field']] = $result['error'];
    }

    /* -------------------------------------------------------------- avatar */
    elseif ($action === 'avatar') {
        $file   = isset($_FILES['avatar']) && is_array($_FILES['avatar']) ? $_FILES['avatar'] : array();
        $result = profile_avatar_store($userId, $file);

        if ($result['ok']) {
            log_activity('update', 'user', $userId, 'Set a profile picture');
            flash('success', 'Your profile picture was updated.');
            redirect('admin/profile.php');
        }

        $errors['avatar'] = $result['error'];
    }

    elseif ($action === 'avatar_remove') {
        $result = profile_avatar_remove($userId);

        if ($result['ok']) {
            log_activity('update', 'user', $userId, 'Removed profile picture');
            flash('success', 'Your profile picture was removed.');
        } else {
            flash('danger', $result['error']);
        }

        redirect('admin/profile.php');
    }
}

if (empty($errors) && empty($pwErrors)) {
    clear_old();
}

$user = user_find($userId);

/* The figures an administrator cares about: their own decisions, what is
   waiting in the queue, and the size of the system they are looking after. */
$decisions = (int) db_value(
    "SELECT COUNT(*) FROM bookings WHERE reviewed_by = ?",
    array($userId)
);

layout_start(array('title' => 'My Profile', 'active' => ''));
?>

<?php page_heading('My Profile', 'Your administrator account'); ?>

<?php render_flashes(); ?>

<?php if ($user['status'] !== 'active'): ?>
    <div class="alert alert-secondary">
        <i class="bi bi-slash-circle me-1"></i>
        This account is deactivated, so it cannot sign in. Ask another
        administrator to reactivate it.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php stat_card('Decisions made', $decisions, 'bi-check2-square', 'primary'); ?>
    <?php stat_card('Awaiting review', (int) db_value(
        "SELECT COUNT(*) FROM bookings WHERE status = 'pending'"),
        'bi-inbox', 'warning'); ?>
    <?php stat_card('Accounts', (int) db_value('SELECT COUNT(*) FROM users'), 'bi-people', 'info'); ?>
    <?php stat_card('Active administrators', active_admin_count(), 'bi-shield-check', 'success'); ?>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="mb-4">
            <?php profile_render_identity($user); ?>
        </div>

        <?php profile_render_avatar_form($user, $errors, ''); ?>
    </div>

    <div class="col-lg-8">
        <div class="d-flex flex-column gap-4">
            <?php profile_render_details_form($user, $errors); ?>

            <?php profile_render_password_form($pwErrors); ?>
        </div>
    </div>
</div>

<?php layout_end(); ?>
