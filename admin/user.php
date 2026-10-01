<?php
/**
 * Computer Laboratory Booking System - one user (administrator).
 *
 * Shows the full record, the booking history attached to it, and the things an
 * administrator can do to it: edit the details, change the role, deactivate or
 * reactivate the account, and reset a forgotten password.
 *
 * Every action re-reads the row before acting rather than trusting the form, so
 * a stale page cannot overwrite a change made in another tab. Role and status
 * are deliberately not part of the edit form: they each carry a guard that
 * prevents locking yourself out, and folding them into a general save would
 * make that guard easy to bypass by accident.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$id = query_int('id', 0);

$user = $id > 0 ? user_find($id) : null;

if ($user === null) {
    http_response_code(404);
    layout_start(array('title' => 'Account not found', 'active' => 'users'));
    echo empty_state(
        'bi-question-circle',
        'Account not found',
        'That account does not exist.'
    );
    echo '<div class="text-center"><a class="btn btn-primary" href="'
        . e(url('admin/users.php')) . '">Back to users</a></div>';
    layout_end();
    exit;
}

$actorId = current_user_id();
$isSelf  = (int) $user['id'] === (int) $actorId;
$bookings = user_booking_summary($id);
$errors   = array();
$pwErrors = array();

/* ================================================================= actions */

if (is_post()) {
    csrf_guard();

    $action = post('action');

    /* ------------------------------------------------------------- update */
    if ($action === 'update') {
        $input  = user_details_input_from_post($user);
        $errors = user_validate($input, $id);

        if (empty($errors)) {
            $result = user_update($id, $input);

            if ($result['ok']) {
                log_activity('update', 'user', $id, 'Updated ' . $input['name']);
                flash('success', 'Changes to ' . $input['name'] . ' were saved.');
                redirect('admin/user.php?id=' . $id);
            }

            $errors[$result['field'] === '' ? 'name' : $result['field']] = $result['error'];
        }

        keep_old($input);
    }

    /* --------------------------------------------------------------- role */
    elseif ($action === 'role') {
        $wanted = post('role');
        $result = user_set_role($id, $wanted, $actorId);

        if ($result['ok']) {
            log_activity('update', 'user', $id, user_access_change_summary($user, 'role', $wanted));
            flash('success', $user['name'] . ' is now a '
                . lcfirst(user_role_label($wanted)) . '.');
            redirect('admin/user.php?id=' . $id);
        }

        flash('danger', $result['error']);
        redirect('admin/user.php?id=' . $id);
    }

    /* ------------------------------------------------------------- status */
    elseif ($action === 'status') {
        $wanted = post('status');
        $result = user_set_status($id, $wanted, $actorId);

        if ($result['ok']) {
            log_activity('update', 'user', $id, user_access_change_summary($user, 'status', $wanted));
            flash('success', $wanted === 'active'
                ? $user['name'] . ' can sign in again.'
                : $user['name'] . ' has been deactivated and can no longer sign in.');
            redirect('admin/user.php?id=' . $id);
        }

        flash('danger', $result['error']);
        redirect('admin/user.php?id=' . $id);
    }

    /* ----------------------------------------------------------- password */
    elseif ($action === 'password') {
        $password = post('password');
        $confirm  = post('password_confirm');

        $problem = user_validate_password($password);
        if ($problem !== '') {
            $pwErrors['password'] = $problem;
        } elseif ($password !== $confirm) {
            $pwErrors['password_confirm'] = 'The two passwords do not match.';
        } else {
            $result = user_set_password($id, $password);

            if ($result['ok']) {
                log_activity('update', 'user', $id, 'Reset the password for ' . $user['name']);
                flash('success', 'The password for ' . $user['name'] . ' was reset.');
                redirect('admin/user.php?id=' . $id);
            }

            $pwErrors['password'] = $result['error'];
        }
    }
}

/* The rows are re-read after any action so the page cannot show a stale state
   if something above changed it and did not redirect. */
$user     = user_find($id);
$isSelf   = (int) $user['id'] === (int) $actorId;
$bookings = user_booking_summary($id);

/* The account's own bookings, newest first, for the history table. */
$history = db_all(
    'SELECT b.*, l.name AS lab_name
       FROM bookings b
       JOIN laboratories l ON l.id = b.laboratory_id
      WHERE b.user_id = ?
      ORDER BY b.booking_date DESC, b.start_time DESC
      LIMIT 20',
    array($id)
);

/* Whether this account is the last active administrator, which is what decides
   if the role and status controls are offered at all. */
$isLastActiveAdmin = ($user['role'] === 'admin' && $user['status'] === 'active')
    && active_admin_count($id) === 0;

layout_start(array('title' => $user['name'], 'active' => 'users'));
?>

<?php page_heading($user['name'],
    $user['email'] . ' - ' . user_role_label($user['role']) . ', ' . user_status_label($user['status'])); ?>

<?php render_flashes(); ?>

<?php if ($isSelf): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i>
        This is your own account. You can edit your details and reset your own
        password, but your role and status are left alone: an administrator
        cannot change their own access, because that is how people get locked
        out of their own system.
    </div>
<?php elseif ($isLastActiveAdmin): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        This is the only active administrator. Their role and status cannot be
        changed until somebody else is promoted, otherwise there would be no way
        to undo it.
    </div>
<?php endif; ?>

<?php if ($user['status'] === 'inactive'): ?>
    <div class="alert alert-secondary">
        <i class="bi bi-slash-circle me-1"></i>
        This account is deactivated. It cannot sign in, and any session it still
        has was ended on that account's next request. All of its bookings and
        history are kept exactly as they are.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php stat_card('Bookings', $bookings['total'], 'bi-calendar-check', 'primary'); ?>
    <?php stat_card('Live', $bookings['active'], 'bi-hourglass-split', 'warning'); ?>
    <?php stat_card('Completed', $bookings['completed'], 'bi-check-circle', 'success'); ?>
    <?php stat_card('Cancelled', $bookings['cancelled'], 'bi-slash-circle', 'secondary'); ?>
</div>

<div class="row g-4">
    <!-- ------------------------------------------------------ the record -->
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-person-vcard me-1"></i> Account
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-5">Name</dt>
                    <dd class="col-sm-7"><?php echo e($user['name']); ?></dd>

                    <dt class="col-sm-5">Email</dt>
                    <dd class="col-sm-7"><?php echo e($user['email']); ?></dd>

                    <dt class="col-sm-5">Student number</dt>
                    <dd class="col-sm-7">
                        <?php echo $user['student_no'] === null || $user['student_no'] === ''
                            ? '<span class="text-muted">None</span>' : e($user['student_no']); ?>
                    </dd>

                    <dt class="col-sm-5">Phone</dt>
                    <dd class="col-sm-7">
                        <?php echo $user['phone'] === null || $user['phone'] === ''
                            ? '<span class="text-muted">None</span>' : e($user['phone']); ?>
                    </dd>

                    <dt class="col-sm-5">Role</dt>
                    <dd class="col-sm-7">
                        <span class="badge <?php echo e(user_role_badge_class($user['role'])); ?>">
                            <?php echo e(user_role_label($user['role'])); ?>
                        </span>
                    </dd>

                    <dt class="col-sm-5">Status</dt>
                    <dd class="col-sm-7">
                        <span class="badge <?php echo e(user_status_badge_class($user['status'])); ?>">
                            <?php echo e(user_status_label($user['status'])); ?>
                        </span>
                    </dd>

                    <dt class="col-sm-5">Last sign-in</dt>
                    <dd class="col-sm-7">
                        <?php if ($user['last_login_at'] === null): ?>
                            <span class="text-muted">Never signed in</span>
                        <?php else: ?>
                            <?php echo e(format_date($user['last_login_at'], true)); ?>
                            <div class="text-muted"><?php echo e(time_ago($user['last_login_at'])); ?></div>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-5">Created</dt>
                    <dd class="col-sm-7"><?php echo e(format_date($user['created_at'], true)); ?></dd>

                    <dt class="col-sm-5">Last changed</dt>
                    <dd class="col-sm-7"><?php echo e(format_date($user['updated_at'], true)); ?></dd>
                </dl>
            </div>
        </div>

        <!-- ------------------------------------------------------- access -->
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-shield-lock me-1"></i> Role and access
            </div>
            <div class="card-body">
                <?php if ($isSelf || $isLastActiveAdmin): ?>
                    <p class="small text-muted mb-0">
                        <?php if ($isSelf): ?>
                            You cannot change your own role or status.
                        <?php else: ?>
                            This is the only active administrator, so their access
                            cannot be reduced until somebody else is promoted.
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="small text-muted">
                        Changing either of these takes effect on that account's
                        very next request, without waiting for a session to expire.
                    </p>

                    <?php if ($user['role'] === 'admin'): ?>
                        <form method="post" action="" class="mb-2">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="role">
                            <input type="hidden" name="role" value="user">
                            <button type="submit" class="btn btn-outline-warning w-100"
                                    data-confirm="Make <?php echo e($user['name']); ?> a student again? They will lose access to the administrator pages on their next click.">
                                <i class="bi bi-arrow-down-circle me-1"></i>
                                Demote to <?php echo e(user_role_label('user')); ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="" class="mb-2">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="role">
                            <input type="hidden" name="role" value="admin">
                            <button type="submit" class="btn btn-outline-primary w-100"
                                    data-confirm="Promote <?php echo e($user['name']); ?> to administrator? They will be able to manage every account, laboratory and booking.">
                                <i class="bi bi-arrow-up-circle me-1"></i>
                                Promote to <?php echo e(user_role_label('admin')); ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($user['status'] === 'active'): ?>
                        <form method="post" action="">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="status" value="inactive">
                            <button type="submit" class="btn btn-outline-danger w-100"
                                    data-confirm="Deactivate <?php echo e($user['name']); ?>? They will be signed out and cannot sign in again until you reactivate them. Their bookings are kept.">
                                <i class="bi bi-slash-circle me-1"></i> Deactivate account
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="btn btn-outline-success w-100"
                                    data-confirm="Reactivate <?php echo e($user['name']); ?>? They will be able to sign in again with the same password.">
                                <i class="bi bi-check-circle me-1"></i> Reactivate account
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ------------------------------------------------------ password -->
        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-key me-1"></i> Reset password
            </div>
            <div class="card-body">
                <?php if (!empty($pwErrors)): ?>
                    <?php render_errors($pwErrors); ?>
                <?php endif; ?>

                <form method="post" action="" autocomplete="off" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="password">

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="pw">New password</label>
                        <input type="password" class="form-control<?php echo isset($pwErrors['password']) ? ' is-invalid' : ''; ?>"
                               id="pw" name="password" autocomplete="new-password"
                               placeholder="At least <?php echo USER_PASSWORD_MIN; ?> characters" required>
                        <?php echo field_error($pwErrors, 'password'); ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="pw_confirm">Repeat password</label>
                        <input type="password" class="form-control<?php echo isset($pwErrors['password_confirm']) ? ' is-invalid' : ''; ?>"
                               id="pw_confirm" name="password_confirm" autocomplete="new-password"
                               placeholder="Type it again" required>
                        <?php echo field_error($pwErrors, 'password_confirm'); ?>
                    </div>

                    <button type="submit" class="btn btn-outline-primary w-100"
                            data-confirm="Reset the password for <?php echo e($user['name']); ?>? The old password stops working immediately.">
                        <i class="bi bi-arrow-repeat me-1"></i> Reset password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- -------------------------------------------------------- the forms -->
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-pencil-square me-1"></i> Edit details
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <?php render_errors($errors); ?>
                <?php endif; ?>

                <form method="post" action="" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="name">Name</label>
                            <input type="text" class="form-control<?php echo isset($errors['name']) ? ' is-invalid' : ''; ?>"
                                   id="name" name="name" maxlength="<?php echo USER_NAME_MAX; ?>"
                                   value="<?php echo e(old('name', $user['name'])); ?>" required>
                            <?php echo field_error($errors, 'name'); ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="email">Email address</label>
                            <input type="email" class="form-control<?php echo isset($errors['email']) ? ' is-invalid' : ''; ?>"
                                   id="email" name="email" maxlength="<?php echo USER_EMAIL_MAX; ?>"
                                   value="<?php echo e(old('email', $user['email'])); ?>" required>
                            <?php echo field_error($errors, 'email'); ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="student_no">Student number</label>
                            <input type="text" class="form-control<?php echo isset($errors['student_no']) ? ' is-invalid' : ''; ?>"
                                   id="student_no" name="student_no" maxlength="<?php echo USER_STUDENT_NO_MAX; ?>"
                                   value="<?php echo e(old('student_no', $user['student_no'])); ?>">
                            <?php echo field_error($errors, 'student_no'); ?>
                            <div class="form-text">Leave blank for staff accounts.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="phone">Phone</label>
                            <input type="text" class="form-control<?php echo isset($errors['phone']) ? ' is-invalid' : ''; ?>"
                                   id="phone" name="phone" maxlength="<?php echo USER_PHONE_MAX; ?>"
                                   value="<?php echo e(old('phone', $user['phone'])); ?>">
                            <?php echo field_error($errors, 'phone'); ?>
                        </div>
                    </div>

                    <div class="form-text mt-3">
                        The role and status are set from the panel on the left, not
                        here, so that a routine edit can never quietly change
                        somebody's access.
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">
                        <i class="bi bi-check-lg me-1"></i> Save changes
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-clock-history me-1"></i> Booking history
                <span class="badge bg-light text-dark ms-1"><?php echo (int) $bookings['total']; ?></span>
            </div>

            <?php if (empty($history)): ?>
                <?php
                echo empty_state(
                    'bi-calendar-x',
                    'No bookings',
                    $user['role'] === 'admin'
                        ? 'Administrator accounts do not book laboratories.'
                        : 'This account has not requested a laboratory session yet.'
                );
                ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ref.</th>
                                <th>Laboratory</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $b): ?>
                                <tr>
                                    <td class="small"><?php echo e($b['booking_ref']); ?></td>
                                    <td class="small"><?php echo e($b['lab_name']); ?></td>
                                    <td class="small text-nowrap">
                                        <?php echo e(format_date($b['booking_date'])); ?>
                                    </td>
                                    <td class="small text-nowrap">
                                        <?php echo e(format_range($b['start_time'], $b['end_time'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo e(status_badge_class($b['status'])); ?>">
                                            <?php echo e(booking_status_label($b['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-link px-0"
                                           href="<?php echo e(url('admin/booking.php?id=' . (int) $b['id'])); ?>">
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ((int) $bookings['total'] > count($history)): ?>
                    <div class="card-footer bg-white small text-muted">
                        Showing the <?php echo count($history); ?> most recent of
                        <?php echo (int) $bookings['total']; ?> bookings.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<p class="mt-4">
    <a href="<?php echo e(url('admin/users.php')); ?>">
        <i class="bi bi-arrow-left me-1"></i> Back to all users
    </a>
</p>

<?php layout_end(); ?>
