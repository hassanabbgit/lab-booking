<?php
/**
 * Computer Laboratory Booking System - my profile (student).
 *
 * Self-service: edit the account's own details, change its own password and
 * maintain its profile picture. The shared behaviour lives in
 * includes/profile.php; this file supplies the guard, the figures and the
 * layout.
 *
 * What is deliberately not offered here:
 *
 *   - The role and the account status. Those carry lockout guards in
 *     users.php, and user_update() does not write those columns, so there is
 *     nothing on this page that could reach them.
 *   - The student number, for a student whose number is wrong. It is a
 *     registered identity rather than a contact detail, so it is left to an
 *     administrator on the Users page. The field is shown read-only, and
 *     profile_details_input_from_post() pins the stored value, so posting a
 *     student_number by hand is ignored rather than merely not offered.
 *     Admins may edit their own staff number here, since it is theirs to keep.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_user();

$userId = current_user_id();
$user   = user_find($userId);

$errors   = array();
$pwErrors = array();
$notice   = '';

/* ================================================================= actions */

if (is_post()) {
    csrf_guard();

    $action = post('action');

    /* ------------------------------------------------------------- details */
    if ($action === 'details') {
        /* user_details_input_from_post(), reached through
           profile_details_input_from_post(), fills role and status in from the
           stored record so validation has something to check while the form
           carries neither, and pins this student's number to the stored value.
           user_update() never writes role or status at all. */
        $input  = profile_details_input_from_post($user);
        $errors = user_validate($input, $userId);

        if (empty($errors)) {
            $result = user_update($userId, $input);

            if ($result['ok']) {
                log_activity('update', 'user', $userId, 'Updated own profile details');
                flash('success', 'Your details were saved.');
                redirect('user/profile.php');
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
            redirect('user/profile.php');
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
            redirect('user/profile.php');
        }

        /* Kept on the page rather than redirected, so the rest of the profile
           stays visible and the message sits next to the upload control. */
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

        redirect('user/profile.php');
    }
}

/* Re-read after every action so the header, initials and figures cannot show a
   value that was just changed. */
if (empty($errors) && empty($pwErrors)) {
    clear_old();
}

$user = user_find($userId);
$bookings = user_booking_summary($userId);

layout_start(array('title' => 'My Profile', 'active' => ''));
?>

<?php page_heading('My Profile', 'Your account details and booking summary'); ?>

<?php render_flashes(); ?>

<?php if ($user['status'] !== 'active'): ?>
    <div class="alert alert-secondary">
        <i class="bi bi-slash-circle me-1"></i>
        This account is deactivated, so it cannot sign in. Your bookings and
        history are kept exactly as they are.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php stat_card('Total requests', $bookings['total'], 'bi-calendar-check', 'primary'); ?>
    <?php stat_card('Awaiting a decision', $bookings['pending'], 'bi-hourglass-split', 'warning'); ?>
    <?php stat_card('Approved', $bookings['approved'], 'bi-check-circle', 'success'); ?>
    <?php stat_card('Completed', $bookings['completed'], 'bi-flag', 'info'); ?>
</div>

<div class="row g-4">
    <!-- ---------------------------------------------------- identity + forms -->
    <div class="col-lg-4">
        <div class="mb-4">
            <?php profile_render_identity($user); ?>
        </div>

        <?php profile_render_avatar_form($user, $errors, $notice); ?>
    </div>

    <!-- --------------------------------------------- details + password + log -->
    <div class="col-lg-8">
        <div class="d-flex flex-column gap-4">
            <?php profile_render_details_form($user, $errors); ?>

            <?php profile_render_password_form($pwErrors); ?>
        </div>
    </div>
</div>

<?php layout_end(); ?>
