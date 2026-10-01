<?php
/**
 * Computer Laboratory Booking System - user management (administrator).
 *
 * The list every administrator works from, with search, role and status
 * filters, live totals and the add form. Each row links through to
 * admin/user.php, which is where editing, role changes, deactivation and
 * password resets happen.
 *
 * Adding is done here rather than on the details page because an account that
 * does not exist yet has no details page to live on.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$search = query('q', '');
$role   = query('role', '');
$status = query('status', '');

// An invented filter is ignored rather than erroring, so a stale bookmark or a
// hand-edited URL still shows the list.
if ($role !== '' && !in_array($role, user_roles(), true)) {
    $role = '';
}

if ($status !== '' && !in_array($status, user_statuses(), true)) {
    $status = '';
}

$where = array();
$params = array();

if ($search !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR student_no LIKE ? OR phone LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($role !== '') {
    $where[] = 'role = ?';
    $params[] = $role;
}

if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}

$sql = 'SELECT * FROM users';
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
// Administrators first, then deactivated accounts, then by name: the accounts an
// administrator is most likely to be looking for come first.
$sql .= " ORDER BY FIELD(role, 'admin', 'user'), FIELD(status, 'inactive', 'active'), name ASC";

$users = db_all($sql, $params);

/* Booking counts for every account, in one pass rather than one query per row. */
$counts = array();
foreach (db_all(
    'SELECT user_id,
            COUNT(*) AS total,
            SUM(status = \'pending\' OR status = \'approved\') AS active
       FROM bookings GROUP BY user_id'
) as $row) {
    $counts[(int) $row['user_id']] = array(
        'total'  => (int) $row['total'],
        'active' => (int) $row['active'],
    );
}

$totals = user_totals();

/* ================================================================== add */

$addErrors = array();

if (is_post() && post('action') === 'create') {
    csrf_guard();

    $input     = user_input_from_post();
    $password  = post('password');
    $confirm   = post('password_confirm');
    $addErrors = user_validate($input, 0);

    $problem = user_validate_password($password);
    if ($problem !== '') {
        $addErrors['password'] = $problem;
    } elseif ($password !== $confirm) {
        $addErrors['password_confirm'] = 'The two passwords do not match.';
    }

    if (empty($addErrors)) {
        $result = user_create($input, $password);

        if ($result['ok']) {
            log_activity('create', 'user', $result['id'],
                'Added ' . $input['name'] . ' as ' . lcfirst(user_role_label($input['role'])));
            flash('success', $input['name'] . ' was added as a '
                . lcfirst(user_role_label($input['role'])) . '.');
            redirect('admin/user.php?id=' . $result['id']);
        }

        $addErrors[$result['field'] === '' ? 'name' : $result['field']] = $result['error'];
    }

    // Keep what was typed so the form can be corrected rather than retyped. The
    // passwords are deliberately not kept: echoing a password back into the
    // page would put it in the browser's HTML and in any proxy log along the way.
    keep_old($input);
} else {
    clear_old();
}

layout_start(array('title' => 'Users', 'active' => 'users'));
?>

<?php page_heading('Users',
    'Add accounts, and decide who can sign in and who can administer the system'); ?>

<?php render_flashes(); ?>

<div class="row g-3 mb-4">
    <?php stat_card('Accounts', $totals['total'], 'bi-people', 'primary'); ?>
    <?php stat_card('Students', $totals['students'], 'bi-mortarboard', 'info'); ?>
    <?php stat_card('Administrators', $totals['admins'], 'bi-shield-lock', 'dark'); ?>
    <?php stat_card('Active', $totals['active'], 'bi-check-circle', 'success'); ?>
    <?php stat_card('Deactivated', $totals['inactive'], 'bi-slash-circle', 'secondary'); ?>
</div>

<div class="row g-4">
    <!-- -------------------------------------------------------- the list -->
    <div class="col-lg-8">
        <form method="get" action="" class="row g-2 mb-3">
            <div class="col-sm-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="q" name="q"
                           value="<?php echo e($search); ?>"
                           placeholder="Search name, email, student number or phone">
                </div>
            </div>
            <div class="col-sm-6">
                <div class="d-flex gap-2">
                    <select class="form-select" name="role" aria-label="Filter by role">
                        <option value="">Any role</option>
                        <?php foreach (user_roles() as $option): ?>
                            <option value="<?php echo e($option); ?>"
                                <?php echo $role === $option ? 'selected' : ''; ?>>
                                <?php echo e(user_role_label($option)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select" name="status" aria-label="Filter by status">
                        <option value="">Any status</option>
                        <?php foreach (user_statuses() as $option): ?>
                            <option value="<?php echo e($option); ?>"
                                <?php echo $status === $option ? 'selected' : ''; ?>>
                                <?php echo e(user_status_label($option)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-list-ul me-1"></i> All accounts
                <span class="badge bg-light text-dark ms-1"><?php echo count($users); ?></span>
            </div>

            <?php if (empty($users)): ?>
                <?php
                echo empty_state(
                    'bi-people',
                    $search === '' && $role === '' && $status === ''
                        ? 'No accounts yet'
                        : 'Nothing matches that filter',
                    $search === '' && $role === '' && $status === ''
                        ? 'Add the first one with the form alongside.'
                        : 'Try a different search, or clear the filter.'
                );
                ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Student no.</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last sign-in</th>
                                <th class="text-center">Bookings</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <?php
                                $userId = (int) $u['id'];
                                $c = isset($counts[$userId])
                                    ? $counts[$userId] : array('total' => 0, 'active' => 0);
                                ?>
                                <tr class="<?php echo $u['status'] === 'active' ? '' : 'opacity-75'; ?>">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge rounded-pill <?php echo e(user_role_badge_class($u['role'])); ?>"
                                                  title="<?php echo e(user_role_label($u['role'])); ?>">
                                                <?php echo e(initials($u['name'])); ?>
                                            </span>
                                            <div>
                                                <a class="fw-bold"
                                                   href="<?php echo e(url('admin/user.php?id=' . $userId)); ?>">
                                                    <?php echo e($u['name']); ?>
                                                </a>
                                                <div class="small text-muted"><?php echo e($u['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small">
                                        <?php echo $u['student_no'] === null || $u['student_no'] === ''
                                            ? '<span class="text-muted">-</span>'
                                            : e($u['student_no']); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo e(user_role_badge_class($u['role'])); ?>">
                                            <?php echo e(user_role_label($u['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo e(user_status_badge_class($u['status'])); ?>">
                                            <?php echo e(user_status_label($u['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?php if ($u['last_login_at'] === null): ?>
                                            Never
                                        <?php else: ?>
                                            <?php echo e(time_ago($u['last_login_at'])); ?>
                                        <?php endif; ?>
                                    </td>
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
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?php echo e(url('admin/user.php?id=' . $userId)); ?>">
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
                <i class="bi bi-person-plus me-1"></i> Add an account
            </div>
            <div class="card-body">
                <?php if (!empty($addErrors)): ?>
                    <?php render_errors($addErrors); ?>
                <?php endif; ?>

                <form method="post" action="" novalidate autocomplete="off">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="new_name">Name</label>
                        <input type="text" class="form-control<?php echo isset($addErrors['name']) ? ' is-invalid' : ''; ?>"
                               id="new_name" name="name" maxlength="<?php echo USER_NAME_MAX; ?>"
                               value="<?php echo e(old('name')); ?>"
                               placeholder="Jane Chisopa" required>
                        <?php echo field_error($addErrors, 'name'); ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="new_email">Email address</label>
                        <input type="email" class="form-control<?php echo isset($addErrors['email']) ? ' is-invalid' : ''; ?>"
                               id="new_email" name="email" maxlength="<?php echo USER_EMAIL_MAX; ?>"
                               value="<?php echo e(old('email')); ?>"
                               placeholder="j.chisopa@student.lab.edu.zm" required>
                        <?php echo field_error($addErrors, 'email'); ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="new_student_no">Student number</label>
                        <input type="text" class="form-control<?php echo isset($addErrors['student_no']) ? ' is-invalid' : ''; ?>"
                               id="new_student_no" name="student_no" maxlength="<?php echo USER_STUDENT_NO_MAX; ?>"
                               value="<?php echo e(old('student_no')); ?>"
                               placeholder="Leave blank for staff">
                        <?php echo field_error($addErrors, 'student_no'); ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="new_phone">Phone</label>
                        <input type="text" class="form-control<?php echo isset($addErrors['phone']) ? ' is-invalid' : ''; ?>"
                               id="new_phone" name="phone" maxlength="<?php echo USER_PHONE_MAX; ?>"
                               value="<?php echo e(old('phone')); ?>"
                               placeholder="Optional">
                        <?php echo field_error($addErrors, 'phone'); ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold" for="new_role">Role</label>
                            <select class="form-select<?php echo isset($addErrors['role']) ? ' is-invalid' : ''; ?>"
                                    id="new_role" name="role" required>
                                <?php $chosenRole = old('role', 'user'); ?>
                                <?php foreach (user_roles() as $option): ?>
                                    <option value="<?php echo e($option); ?>"
                                        <?php echo $chosenRole === $option ? 'selected' : ''; ?>>
                                        <?php echo e(user_role_label($option)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php echo field_error($addErrors, 'role'); ?>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-bold" for="new_status">Status</label>
                            <select class="form-select<?php echo isset($addErrors['status']) ? ' is-invalid' : ''; ?>"
                                    id="new_status" name="status" required>
                                <?php $chosenStatus = old('status', 'active'); ?>
                                <?php foreach (user_statuses() as $option): ?>
                                    <option value="<?php echo e($option); ?>"
                                        <?php echo $chosenStatus === $option ? 'selected' : ''; ?>>
                                        <?php echo e(user_status_label($option)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php echo field_error($addErrors, 'status'); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="new_password">Password</label>
                        <input type="password" class="form-control<?php echo isset($addErrors['password']) ? ' is-invalid' : ''; ?>"
                               id="new_password" name="password" autocomplete="new-password"
                               placeholder="At least <?php echo USER_PASSWORD_MIN; ?> characters" required>
                        <?php echo field_error($addErrors, 'password'); ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="new_password_confirm">Repeat password</label>
                        <input type="password" class="form-control<?php echo isset($addErrors['password_confirm']) ? ' is-invalid' : ''; ?>"
                               id="new_password_confirm" name="password_confirm" autocomplete="new-password"
                               placeholder="Type it again" required>
                        <?php echo field_error($addErrors, 'password_confirm'); ?>
                    </div>

                    <div class="form-text mb-3">
                        Tell the person their password. There is no self-service
                        password change yet, so they will need an administrator to
                        reset it if they forget it.
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-lg me-1"></i> Add account
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-shield-exclamation me-1"></i> Not locking yourself out
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Accounts are never deleted. Bookings, notifications and the
                    activity log all point at a user, so <strong>deactivating</strong>
                    is how an account is removed: the history stays intact and the
                    account can be switched back on at any time.
                </p>
                <p class="small text-muted mb-0">
                    Two things are refused on purpose. You cannot change your own
                    role or status, and the last active administrator cannot be
                    demoted or deactivated, because that would leave nobody able
                    to undo it. Promote somebody else first.
                </p>
            </div>
        </div>
    </div>
</div>

<?php layout_end(); ?>
