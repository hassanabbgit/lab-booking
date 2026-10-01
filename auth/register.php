<?php
/**
 * Computer Laboratory Booking System - student self-registration.
 *
 * Anyone may register, but always as a student. Administrator accounts are
 * created from the admin Users page, never from the public form.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_guest();

$errors = array();

if (is_post()) {
    csrf_guard();

    $name       = post('name');
    $email      = strtolower(post('email'));
    $studentNo  = strtoupper(post('student_no'));
    $phone      = post('phone');
    $password   = post('password');
    $confirm    = post('password_confirm');

    if ($name === '' || $email === '' || $password === '') {
        $errors[] = 'Name, email address and password are all required.';
    }

    if ($name !== '' && strlen($name) < 3) {
        $errors['name'] = 'Please enter your full name.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That does not look like a valid email address.';
    }

    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $errors['phone'] = 'Enter a valid phone number, or leave it blank.';
    }

    if (strlen($password) > 0 && strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    }

    if ($password !== '' && $password !== $confirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    if (empty($errors)) {
        $taken = db_one('SELECT id FROM users WHERE email = ? LIMIT 1', array($email));
        if ($taken !== null) {
            $errors['email'] = 'An account already uses that email address.';
        } elseif ($studentNo !== '' && db_one('SELECT id FROM users WHERE student_no = ? LIMIT 1', array($studentNo)) !== null) {
            $errors['student_no'] = 'That student number is already registered.';
        }
    }

    if (empty($errors)) {
        // role is hard-coded to 'user': the public form must never be able to
        // create an administrator, whatever it is sent.
        try {
            $userId = db_insert(
                'INSERT INTO users (name, email, password, role, status, student_no, phone)
                 VALUES (?, ?, ?, \'user\', \'active\', ?, ?)',
                array(
                    $name,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $studentNo === '' ? null : $studentNo,
                    $phone === '' ? null : $phone,
                )
            );
        } catch (PDOException $e) {
            // Two people can pass the duplicate check at the same moment, so the
            // UNIQUE index is what actually guarantees uniqueness. Translate its
            // error instead of letting the visitor see a 500.
            if ($e->getCode() === '23000') {
                $errors['email'] = 'An account already uses that email address.';
            } else {
                error_log('CLBS registration failed: ' . $e->getMessage());
                $errors[] = 'We could not create your account. Please try again.';
            }
            $userId = null;
        }

        if ($userId !== null) {
            log_activity('register', 'user', $userId, 'Self-registered as a student');

            clear_old();
            $_SESSION['_registered'] = true;
            flash('success', 'Account created. Please sign in.');
            redirect('auth/login.php');
        }
    }

    keep_old(array(
        'name'      => $name,
        'email'     => $email,
        'student_no' => $studentNo,
        'phone'     => $phone,
    ));
}

layout_start(array('title' => 'Create account', 'layout' => 'plain'));
?>

<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-head">
            <div class="brand-mark"><i class="bi bi-person-plus"></i></div>
            <h1>Create your account</h1>
            <p>Register to request laboratory bookings</p>
        </div>

        <div class="card-body">
            <?php render_flashes(); ?>
            <?php render_errors($errors); ?>

            <form method="post" action="" novalidate>
                <?php echo csrf_field(); ?>

                <div class="mb-3">
                    <label class="form-label" for="name">Full name</label>
                    <input type="text" class="form-control<?php echo isset($errors['name']) ? ' is-invalid' : ''; ?>"
                           id="name" name="name" value="<?php echo e(old('name')); ?>"
                           placeholder="e.g. Chisoka Mwange" required autofocus>
                    <?php echo field_error($errors, 'name'); ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input type="email" class="form-control<?php echo isset($errors['email']) ? ' is-invalid' : ''; ?>"
                           id="email" name="email" value="<?php echo e(old('email')); ?>"
                           placeholder="you@school.edu.zm" required>
                    <?php echo field_error($errors, 'email'); ?>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="student_no">Student number</label>
                        <input type="text" class="form-control<?php echo isset($errors['student_no']) ? ' is-invalid' : ''; ?>"
                               id="student_no" name="student_no" value="<?php echo e(old('student_no')); ?>"
                               placeholder="e.g. NCC-2026-014">
                        <?php echo field_error($errors, 'student_no'); ?>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="phone">Phone <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control<?php echo isset($errors['phone']) ? ' is-invalid' : ''; ?>"
                               id="phone" name="phone" value="<?php echo e(old('phone')); ?>"
                               placeholder="+260 97x xxx xxx">
                        <?php echo field_error($errors, 'phone'); ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control<?php echo isset($errors['password']) ? ' is-invalid' : ''; ?>"
                               id="password" name="password" placeholder="At least 8 characters" required>
                        <button class="btn btn-outline-secondary" type="button"
                                data-toggle-password="password" title="Show password" tabindex="-1">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <?php echo field_error($errors, 'password'); ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password_confirm">Confirm password</label>
                    <input type="password" class="form-control<?php echo isset($errors['password_confirm']) ? ' is-invalid' : ''; ?>"
                           id="password_confirm" name="password_confirm" placeholder="Repeat your password" required>
                    <?php echo field_error($errors, 'password_confirm'); ?>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-check-circle me-1"></i> Create account
                </button>
            </form>

            <p class="text-center text-muted small mt-3 mb-0">
                Already registered? <a href="<?php echo e(url('auth/login.php')); ?>">Sign in</a>
            </p>
        </div>
    </div>
</div>

<?php layout_end(); ?>
