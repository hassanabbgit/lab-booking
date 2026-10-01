<?php
/**
 * Computer Laboratory Booking System - sign in.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_guest();

$errors = array();

// Stashed after a failed registration attempt, so login can offer a jump to it.
$registered = !empty($_SESSION['_registered']);
unset($_SESSION['_registered']);

// Surface a brute-force lockout before the visitor types anything, so the
// reason the form seems to "ignore" them is visible.
$lockedFor = login_lockout_seconds();

if (is_post() && $lockedFor === 0) {
    csrf_guard();

    $email    = strtolower(post('email'));
    $password = post('password');

    if ($email === '' || $password === '') {
        $errors[] = 'Enter both your email address and password.';
    } else {
        $result = attempt_login($email, $password);

        if ($result['ok']) {
            login_user($result['user']);
            clear_old();
            flash('success', 'Welcome back, ' . $result['user']['name'] . '.');
            redirect(intended_path());
        }

        $errors[] = $result['error'];
    }

    keep_old(array('email' => $email));
}

layout_start(array('title' => 'Sign in', 'layout' => 'plain'));
?>

<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-head">
            <div class="brand-mark"><i class="bi bi-pc-display"></i></div>
            <h1><?php echo e(APP_NAME); ?></h1>
            <p>Sign in to book and manage laboratory sessions</p>
        </div>

        <div class="card-body">
            <?php render_flashes(); ?>

            <?php if ($registered): ?>
                <div class="alert alert-success d-flex align-items-start" role="alert">
                    <i class="bi bi-check-circle-fill me-2 mt-1"></i>
                    <div>Your account was created. Sign in below to continue.</div>
                </div>
            <?php endif; ?>

            <?php render_errors($errors); ?>

            <?php if ($lockedFor > 0): ?>
                <?php $lockedMins = (int) ceil($lockedFor / 60); ?>
                <div class="alert alert-warning d-flex align-items-start" role="alert">
                    <i class="bi bi-shield-lock-fill me-2 mt-1"></i>
                    <div>
                        <strong>Sign-in temporarily blocked.</strong><br>
                        Too many failed attempts from this device.
                        Try again in <?php echo (int) $lockedMins; ?>
                        minute<?php echo $lockedMins === 1 ? '' : 's'; ?>.
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="" novalidate>
                <?php echo csrf_field(); ?>

                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo e(old('email')); ?>"
                               placeholder="you@school.edu.zm" required autofocus
                               <?php echo $lockedFor > 0 ? 'disabled' : ''; ?>>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter your password" required
                               <?php echo $lockedFor > 0 ? 'disabled' : ''; ?>>
                        <button class="btn btn-outline-secondary" type="button"
                                data-toggle-password="password" title="Show password" tabindex="-1"
                                <?php echo $lockedFor > 0 ? 'disabled' : ''; ?>>
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2"
                        <?php echo $lockedFor > 0 ? 'disabled' : ''; ?>>
                    <i class="bi bi-box-arrow-in-right me-1"></i>
                    <?php echo $lockedFor > 0 ? 'Locked' : 'Sign in'; ?>
                </button>
            </form>

            <p class="text-center text-muted small mt-3 mb-0">
                No account yet? <a href="<?php echo e(url('auth/register.php')); ?>">Create one</a>
            </p>

            <div class="demo-creds">
                <strong><i class="bi bi-info-circle me-1"></i> Demo accounts</strong>
                Admin &mdash;
                <code>admin@lab.edu.zm</code> / <code>Admin@123</code><br>
                Student &mdash;
                <code>student@lab.edu.zm</code> / <code>Student@123</code>
            </div>
        </div>
    </div>
</div>

<?php layout_end(); ?>
