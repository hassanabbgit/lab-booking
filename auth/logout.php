<?php
/**
 * Computer Laboratory Booking System - sign out.
 *
 * POST-only so that a stray <img src=".../logout.php"> cannot sign a user out,
 * and it ends the session before doing anything else.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (is_post()) {
    csrf_guard();
    logout_user();
    flash('success', 'You have been signed out.');
    redirect('auth/login.php');
}

// A direct GET should not destroy the session; offer a confirmation instead.
layout_start(array('title' => 'Sign out', 'layout' => 'plain'));
?>

<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-head">
            <div class="brand-mark"><i class="bi bi-box-arrow-right"></i></div>
            <h1>Sign out</h1>
            <p>Are you sure you want to end this session?</p>
        </div>
        <div class="card-body text-center">
            <form method="post" action="">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn btn-danger w-100 py-2">
                    <i class="bi bi-box-arrow-right me-1"></i> Yes, sign me out
                </button>
            </form>
            <a href="<?php echo e(url(home_path())); ?>" class="btn btn-link text-muted mt-2">
                Stay signed in
            </a>
        </div>
    </div>
</div>

<?php layout_end(); ?>
