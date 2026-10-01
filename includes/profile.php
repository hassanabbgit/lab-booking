<?php
/**
 * Computer Laboratory Booking System - profile self-service.
 *
 * Loaded via includes/bootstrap.php, so both admin/profile.php and
 * user/profile.php can use it.
 *
 * The two profile pages are almost the same page with a different set of
 * figures under the heading, so the shared behaviour lives here rather than
 * being written twice: the details form, the password change, the profile
 * picture, and the panels that describe the account's own security state.
 *
 * What this file deliberately does not do:
 *
 *   1. It never changes a role or a status. Those carry the lockout guards in
 *      users.php, and a self-service page is exactly the place where such a
 *      guard would be quietly bypassed. user_update() does not write those
 *      columns at all, and the form does not carry them.
 *   2. It never deletes an account. See the note at the top of users.php.
 *
 * The password change re-checks the current password, which is the one thing
 * that makes it different from user_set_password(), the administrator's reset.
 * Somebody who walks up to an unlocked machine should not be able to take the
 * account over, and somebody who leaves a session open on a shared computer
 * should not be able to lock the real owner out.
 *
 * PHP 5.6 only: no scalar type hints, no ??, no arrow functions.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    exit('Direct access is not allowed.');
}

if (!defined('PROFILE_AVATAR_MAX_BYTES')) {
    /** Largest upload accepted, checked in PHP because php.ini allows far more. */
    define('PROFILE_AVATAR_MAX_BYTES', 2097152);   // 2 MB
    /** Stored picture is always this many pixels square. */
    define('PROFILE_AVATAR_EDGE', 256);
    /** Refuse to decode a source image larger than this on either edge. */
    define('PROFILE_AVATAR_MAX_SOURCE', 6000);
    /** JPEG quality for the re-encoded picture. */
    define('PROFILE_AVATAR_QUALITY', 88);
}

/* =========================================================================
 | Profile picture
 |
 | The uploaded file is decoded and re-encoded rather than copied. That is the
 | whole point: a file called photo.jpg can still carry a PHP payload appended
 | to it, or be a valid image and a valid script at once. Re-encoding through
 | GD throws the payload away and leaves only pixels, so what lands on disk is
 | a JPEG this code produced, whatever was sent to it.
 |
 | The stored name is always <user id>.jpg, so a user cannot influence the
 | path at all, and replacing a picture is an overwrite rather than an
 | accumulation of leftovers.
 |
 | public/uploads/.htaccess disables the PHP engine and denies a list of
 | script extensions as a second layer. Neither defence is relied on alone.
 * ====================================================================== */

/**
 * Where pictures are kept, created on first use.
 *
 * @return string Absolute path, with a trailing slash.
 */
function profile_avatar_dir()
{
    $dir = APP_ROOT . '/public/uploads/avatars/';

    if (!is_dir($dir)) {
        // The parent already exists in the repository with its .htaccess; only
        // the avatars folder is created at runtime.
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

/**
 * The picture file for one account.
 *
 * @param  int $userId
 * @return string Absolute path.
 */
function profile_avatar_file($userId)
{
    return profile_avatar_dir() . (int) $userId . '.jpg';
}

/**
 * The path stored in the users table: relative to the project root, so it can
 * be turned into a URL with url() without knowing where the project lives.
 *
 * @param  int $userId
 * @return string
 */
function profile_avatar_stored_path($userId)
{
    return 'public/uploads/avatars/' . (int) $userId . '.jpg';
}

/**
 * A usable URL for an account's picture, or '' when it has none.
 *
 * The stored value is checked against the exact shape this file writes, and the
 * file has to still be on disk. Both checks are because the value is rendered
 * into an attribute: a row edited by hand, or a file deleted outside the
 * application, should not turn into a broken image or an unexpected path.
 *
 * A modification time is added so a replaced picture is not served from the
 * browser's cache.
 *
 * @param  array $user
 * @return string
 */
function profile_avatar_url(array $user)
{
    $name = isset($user['avatar']) ? trim((string) $user['avatar']) : '';

    if ($name === '' || !preg_match('#^public/uploads/avatars/[0-9]+\.jpg$#', $name)) {
        return '';
    }

    $absolute = APP_ROOT . '/' . $name;
    if (!is_file($absolute)) {
        return '';
    }

    $mtime = @filemtime($absolute);

    return url($name) . ($mtime === false ? '' : '?v=' . (int) $mtime);
}

/**
 * Whether an account has a picture worth showing.
 *
 * @param  array $user
 * @return bool
 */
function profile_has_avatar(array $user)
{
    return profile_avatar_url($user) !== '';
}

/**
 * The result shape every avatar action returns.
 *
 * @param  bool   $ok
 * @param  string $error
 * @return array
 */
function profile_avatar_result($ok, $error = '')
{
    return array('ok' => (bool) $ok, 'error' => $error);
}

/**
 * Check that a file really is an image, and report how big it is.
 *
 * Split out from profile_avatar_read() so the type rules can be tested on their
 * own. The test suite cannot produce a genuine upload, and is_uploaded_file()
 * is checked before this is ever reached, so folding the two together would
 * leave the "is this actually an image" rule with no coverage at all.
 *
 * getimagesize() is used rather than $_FILES['type'], which is whatever the
 * browser claimed and is therefore attacker-controlled. getimagesize() reads
 * the file's own header.
 *
 * @param  string $path
 * @return array array('ok'=>bool,'error'=>string,'width'=>int,'height'=>int)
 */
function profile_avatar_inspect($path)
{
    $info = @getimagesize($path);

    if ($info === false || empty($info[0]) || empty($info[1])) {
        return array('ok' => false,
            'error' => 'That file is not an image. Use a JPEG, PNG, GIF or WebP file.',
            'width' => 0, 'height' => 0);
    }

    $width  = (int) $info[0];
    $height = (int) $info[1];

    if ($width < 1 || $height < 1) {
        return array('ok' => false, 'error' => 'That image is empty or damaged.', 'width' => 0, 'height' => 0);
    }

    if ($width > PROFILE_AVATAR_MAX_SOURCE || $height > PROFILE_AVATAR_MAX_SOURCE) {
        return array('ok' => false,
            'error' => 'That image is too large to process. Please use one under '
                . PROFILE_AVATAR_MAX_SOURCE . ' pixels wide.',
            'width' => $width, 'height' => $height);
    }

    $loaders = array(
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG  => 'imagecreatefrompng',
        IMAGETYPE_GIF  => 'imagecreatefromgif',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    );

    if (!isset($loaders[$info[2]]) || !function_exists($loaders[$info[2]])) {
        return array('ok' => false,
            'error' => 'That image format is not supported. Use a JPEG, PNG, GIF or WebP file.',
            'width' => $width, 'height' => $height);
    }

    return array('ok' => true, 'error' => '', 'width' => $width, 'height' => $height);
}

/**
 * Read an upload into a GD image, after checking it is really an image.
 *
 * @param  string $tmpName
 * @param  int    $error    The PHP upload error code
 * @param  int    $size
 * @return array array('ok'=>bool,'error'=>string,'image'=>resource|GdImage|null)
 */
function profile_avatar_read($tmpName, $error, $size)
{
    if ($error === UPLOAD_ERR_NO_FILE) {
        return profile_avatar_result(false, 'Choose an image to upload.');
    }

    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return profile_avatar_result(false, 'That image is larger than the '
            . round(PROFILE_AVATAR_MAX_BYTES / 1048576) . ' MB limit.');
    }

    if ($error !== UPLOAD_ERR_OK) {
        return profile_avatar_result(false, 'The upload did not finish. Please try again.');
    }

    if ($size > PROFILE_AVATAR_MAX_BYTES) {
        return profile_avatar_result(false, 'That image is larger than the '
            . round(PROFILE_AVATAR_MAX_BYTES / 1048576) . ' MB limit.');
    }

    if (!is_uploaded_file($tmpName)) {
        // A path that did not come through an upload is the signature of a
        // hand-crafted request pointing at a server file. Checked before the
        // file is opened at all, so a path pointing at a server file is never
        // read, let alone decoded.
        return profile_avatar_result(false, 'The upload could not be read.');
    }

    $checked = profile_avatar_inspect($tmpName);

    if (!$checked['ok']) {
        return profile_avatar_result(false, $checked['error']);
    }

    $loaders = array(
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG  => 'imagecreatefrompng',
        IMAGETYPE_GIF  => 'imagecreatefromgif',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    );

    $loader = $loaders[exif_imagetype($tmpName)];
    $image  = @$loader($tmpName);

    if ($image === false) {
        return profile_avatar_result(false, 'That image could not be read. It may be damaged.');
    }

    return array('ok' => true, 'error' => '', 'image' => $image);
}

/**
 * Store a new profile picture for an account.
 *
 * @param  int   $userId
 * @param  array $file  One entry from $_FILES
 * @return array array('ok'=>bool,'error'=>string)
 */
function profile_avatar_store($userId, array $file)
{
    $userId = (int) $userId;

    if ($userId < 1) {
        return profile_avatar_result(false, 'That account no longer exists.');
    }

    if (!function_exists('imagecreatetruecolor')) {
        return profile_avatar_result(false, 'Picture uploads are not available on this server.');
    }

    $tmp  = isset($file['tmp_name']) ? $file['tmp_name'] : '';
    $size = isset($file['size']) ? (int) $file['size'] : 0;
    $err  = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

    $read = profile_avatar_read($tmp, $err, $size);

    if (!$read['ok']) {
        return profile_avatar_result(false, $read['error']);
    }

    $source = $read['image'];
    $width  = imagesx($source);
    $height = imagesy($source);

    /* Crop the centre to a square, then scale it down in one resample. Cropping
       first and scaling second is what keeps a wide photograph from being
       squashed: taking a 256px slice and enlarging it would blur, whereas
       taking the centre 256px of a 4000px image and shrinking it stays sharp. */
    $side = $width < $height ? $width : $height;
    $srcX = (int) floor(($width - $side) / 2);
    $srcY = (int) floor(($height - $side) / 2);

    $target = imagecreatetruecolor(PROFILE_AVATAR_EDGE, PROFILE_AVATAR_EDGE);

    /* Flattened onto white rather than kept transparent, so a PNG with an alpha
       channel does not come out with a black background in a browser that
       renders transparency as black. */
    $white = imagecolorallocate($target, 255, 255, 255);
    imagefilledrectangle($target, 0, 0, PROFILE_AVATAR_EDGE - 1, PROFILE_AVATAR_EDGE - 1, $white);

    imagecopyresampled(
        $target, $source,
        0, 0, $srcX, $srcY,
        PROFILE_AVATAR_EDGE, PROFILE_AVATAR_EDGE,
        $side, $side
    );

    $path = profile_avatar_file($userId);
    $ok   = imagejpeg($target, $path, PROFILE_AVATAR_QUALITY);

    imagedestroy($target);
    imagedestroy($source);

    if (!$ok || !is_file($path)) {
        return profile_avatar_result(false, 'The picture could not be saved.');
    }

    /* Best effort: the file is already usable, so a failure to tidy up the
       permissions must not be reported as a failed upload. */
    @chmod($path, 0644);

    try {
        db_exec('UPDATE users SET avatar = ? WHERE id = ?', array(
            profile_avatar_stored_path($userId), $userId
        ));
    } catch (Exception $e) {
        error_log('CLBS avatar column update failed: ' . $e->getMessage());
        @unlink($path);
        return profile_avatar_result(false,
            'The picture was uploaded but could not be attached to the account.');
    }

    return profile_avatar_result(true);
}

/**
 * Remove an account's picture and forget the stored path.
 *
 * @param  int $userId
 * @return array array('ok'=>bool,'error'=>string)
 */
function profile_avatar_remove($userId)
{
    $userId = (int) $userId;

    if ($userId < 1) {
        return profile_avatar_result(false, 'That account no longer exists.');
    }

    $path = profile_avatar_file($userId);

    if (is_file($path)) {
        // Only ever the exact path this file generates, never anything built
        // from user input.
        @unlink($path);
    }

    try {
        db_exec('UPDATE users SET avatar = NULL WHERE id = ?', array($userId));
    } catch (Exception $e) {
        error_log('CLBS avatar column clear failed: ' . $e->getMessage());
        return profile_avatar_result(false, 'The picture was removed but the account could not be updated.');
    }

    return profile_avatar_result(true);
}

/* =========================================================================
 | Password
 * ====================================================================== */

/**
 * Change an account's own password, proving the current one first.
 *
 * @param  int    $userId
 * @param  string $current
 * @param  string $new
 * @param  string $confirm
 * @return array array('ok'=>bool,'error'=>string,'field'=>string)
 */
function profile_change_password($userId, $current, $new, $confirm)
{
    $userId = (int) $userId;

    $user = db_one('SELECT id, name, password FROM users WHERE id = ?', array($userId));

    if ($user === null) {
        return array('ok' => false, 'field' => 'current_password',
            'error' => 'That account no longer exists.');
    }

    if (trim($current) === '') {
        return array('ok' => false, 'field' => 'current_password',
            'error' => 'Enter your current password.');
    }

    if (!password_verify($current, $user['password'])) {
        /* Deliberately vague. Saying "wrong password" separately from "no such
           account" is fine here, because the account is already known to the
           person filling the form in; it is the login page that has to avoid
           confirming which emails exist. */
        return array('ok' => false, 'field' => 'current_password',
            'error' => 'That is not your current password.');
    }

    $problem = user_validate_password($new);

    if ($problem !== '') {
        return array('ok' => false, 'field' => 'new_password', 'error' => $problem);
    }

    if ($new !== $confirm) {
        return array('ok' => false, 'field' => 'confirm_password',
            'error' => 'The two passwords do not match.');
    }

    if (password_verify($new, $user['password'])) {
        return array('ok' => false, 'field' => 'new_password',
            'error' => 'The new password is the same as the current one.');
    }

    try {
        db_exec('UPDATE users SET password = ? WHERE id = ?', array(
            password_hash($new, PASSWORD_DEFAULT), $userId
        ));
    } catch (Exception $e) {
        error_log('CLBS profile password change failed: ' . $e->getMessage());
        return array('ok' => false, 'field' => 'new_password',
            'error' => 'The password could not be changed. Please try again.');
    }

    /* A new id for the session that made the change, so a token captured
       beforehand cannot ride on the authenticated session afterwards.

       headers_sent() is checked as well as session_status(). A real request
       reaches this point before anything is printed, but the check keeps the
       call safe from anywhere that has already emitted output, rather than
       risking a warning on a path that is about to succeed. */
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }

    log_activity('password_change', 'user', $userId, 'Changed own password');

    return array('ok' => true, 'field' => '', 'error' => '');
}

/* =========================================================================
 | Shared rendering
 |
 | The two profile pages differ only in their heading and their figures, so
 | the panels below are written once here. The page files pass in the account
 | and its numbers and nothing else.
 * ====================================================================== */

/**
 * The identity card: picture, name, badges and the fixed facts.
 *
 * @param array $user
 */
function profile_render_identity(array $user)
{
    $isAdmin = $user['role'] === 'admin';
    ?>
    <div class="card text-center h-100">
        <div class="card-body">
            <?php $picture = profile_avatar_url($user); ?>
            <?php if ($picture !== ''): ?>
                <img src="<?php echo e($picture); ?>"
                     alt="Profile picture of <?php echo e($user['name']); ?>"
                     class="rounded-circle mb-3"
                     style="width:96px;height:96px;object-fit:cover"
                     width="96" height="96">
            <?php else: ?>
                <div class="avatar mx-auto mb-3" style="width:96px;height:96px;font-size:2rem">
                    <?php echo e(initials($user['name'])); ?>
                </div>
            <?php endif; ?>

            <h2 class="h5 fw-bold mb-1"><?php echo e($user['name']); ?></h2>
            <p class="text-muted small mb-2"><?php echo e($user['email']); ?></p>

            <span class="badge <?php echo e(user_role_badge_class($user['role'])); ?>">
                <?php echo e(user_role_label($user['role'])); ?>
            </span>
            <?php if ($user['status'] !== 'active'): ?>
                <span class="badge <?php echo e(user_status_badge_class($user['status'])); ?>">
                    <?php echo e(user_status_label($user['status'])); ?>
                </span>
            <?php endif; ?>

            <hr class="my-3">

            <div class="d-flex justify-content-between small py-1">
                <span class="text-muted"><?php echo $isAdmin ? 'Staff number' : 'Student number'; ?></span>
                <strong><?php echo e($user['student_no'] !== null && $user['student_no'] !== ''
                    ? $user['student_no'] : '-'); ?></strong>
            </div>
            <div class="d-flex justify-content-between small py-1">
                <span class="text-muted">Phone</span>
                <strong><?php echo e($user['phone'] !== null && $user['phone'] !== ''
                    ? $user['phone'] : '-'); ?></strong>
            </div>
            <div class="d-flex justify-content-between small py-1">
                <span class="text-muted">Member since</span>
                <strong><?php echo e(format_date($user['created_at'])); ?></strong>
            </div>
            <div class="d-flex justify-content-between small py-1">
                <span class="text-muted">Last sign-in</span>
                <strong><?php echo e(format_date($user['last_login_at'], true)); ?></strong>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Upload or remove the profile picture.
 *
 * @param array  $user
 * @param array  $errors Field name => message
 * @param string $notice
 */
function profile_render_avatar_form(array $user, array $errors = array(), $notice = '')
{
    $has = profile_has_avatar($user);
    ?>
    <div class="card">
        <div class="card-header bg-white fw-bold">
            <i class="bi bi-camera me-1"></i> Profile picture
        </div>
        <div class="card-body">
            <?php if ($notice !== ''): ?>
                <div class="alert alert-<?php echo e($has ? 'success' : 'info'); ?> py-2 small">
                    <?php echo e($notice); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($errors['avatar'])): ?>
                <div class="invalid-feedback d-block mb-2"><?php echo e($errors['avatar']); ?></div>
            <?php endif; ?>

            <p class="small text-muted">
                A JPEG, PNG, GIF or WebP file, up to
                <?php echo round(PROFILE_AVATAR_MAX_BYTES / 1048576); ?> MB.
                The picture is cropped to a square and stored at
                <?php echo PROFILE_AVATAR_EDGE; ?>&times;<?php echo PROFILE_AVATAR_EDGE; ?> pixels.
                Without one, your initials are shown instead.
            </p>

            <form method="post" action="" enctype="multipart/form-data" class="mb-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="avatar">

                <label class="form-label fw-bold small" for="avatar_file">
                    <?php echo $has ? 'Replace picture' : 'Upload a picture'; ?>
                </label>
                <input type="file" class="form-control form-control-sm mb-2"
                       id="avatar_file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-upload me-1"></i> <?php echo $has ? 'Replace' : 'Upload'; ?>
                </button>
            </form>

            <?php if ($has): ?>
                <form method="post" action=""
                      onsubmit="return confirm('Remove your profile picture?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="avatar_remove">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i> Remove picture
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * The fields the self-service "my details" form collects.
 *
 * Wraps user_details_input_from_post() so both profile pages read the request
 * the same way, and so the student-number rule is enforced on the server rather
 * than by leaving an input off the form. Hiding a field is only a hint; a
 * student who posts a student_number directly must be ignored, which is what
 * this does.
 *
 * A student number is a registered identity, not a contact detail, so a student
 * changes it through an administrator on the Users page. An administrator's own
 * staff number is theirs to keep, so theirs is editable here.
 *
 * @param  array $user the record as returned by user_find()
 * @return array keys as user_validate()
 */
function profile_details_input_from_post(array $user)
{
    $input = user_details_input_from_post($user);

    if ($user['role'] !== 'admin') {
        $input['student_no'] = $user['student_no'];
    }

    return $input;
}

/**
 * The "edit my details" form.
 *
 * The fields match the administrator's edit form in admin/user.php so the two
 * cannot drift apart, but there is no role or status here: those carry
 * lockout guards and are not this page's business. A student gets no student
 * number field either, for the reason given on profile_details_input_from_post().
 *
 * @param array $user
 * @param array $errors
 */
function profile_render_details_form(array $user, array $errors = array())
{
    $isAdmin = $user['role'] === 'admin';
    ?>
    <div class="card">
        <div class="card-header bg-white fw-bold">
            <i class="bi bi-pencil-square me-1"></i> My details
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <?php render_errors($errors); ?>
            <?php endif; ?>

            <form method="post" action="" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="details">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="pf_name">Name</label>
                        <input type="text" class="form-control<?php echo isset($errors['name']) ? ' is-invalid' : ''; ?>"
                               id="pf_name" name="name" maxlength="<?php echo USER_NAME_MAX; ?>"
                               value="<?php echo e(old('name', $user['name'])); ?>" required>
                        <?php echo field_error($errors, 'name'); ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="pf_email">Email address</label>
                        <input type="email" class="form-control<?php echo isset($errors['email']) ? ' is-invalid' : ''; ?>"
                               id="pf_email" name="email" maxlength="<?php echo USER_EMAIL_MAX; ?>"
                               value="<?php echo e(old('email', $user['email'])); ?>" required>
                        <?php echo field_error($errors, 'email'); ?>
                        <div class="form-text">This is also the address you sign in with.</div>
                    </div>

                    <?php if ($isAdmin): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="pf_student_no">Staff number</label>
                            <input type="text" class="form-control<?php echo isset($errors['student_no']) ? ' is-invalid' : ''; ?>"
                                   id="pf_student_no" name="student_no" maxlength="<?php echo USER_STUDENT_NO_MAX; ?>"
                                   value="<?php echo e(old('student_no', $user['student_no'])); ?>">
                            <?php echo field_error($errors, 'student_no'); ?>
                        </div>
                    <?php else: ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Student number</label>
                            <?php if ($user['student_no'] !== null && $user['student_no'] !== ''): ?>
                                <input type="text" class="form-control" value="<?php echo e($user['student_no']); ?>" disabled>
                            <?php else: ?>
                                <input type="text" class="form-control" value="" disabled placeholder="None on file">
                            <?php endif; ?>
                            <div class="form-text">
                                If this is wrong, ask an administrator to change it
                                on the Users page.
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="pf_phone">Phone</label>
                        <input type="text" class="form-control<?php echo isset($errors['phone']) ? ' is-invalid' : ''; ?>"
                               id="pf_phone" name="phone" maxlength="<?php echo USER_PHONE_MAX; ?>"
                               value="<?php echo e(old('phone', $user['phone'])); ?>">
                        <?php echo field_error($errors, 'phone'); ?>
                    </div>
                </div>

                <div class="form-text mt-3">
                    Your role and account status are not changed here. An
                    administrator cannot alter their own access, and this page
                    offers no way around that.
                </div>

                <button type="submit" class="btn btn-primary mt-3">
                    <i class="bi bi-check-lg me-1"></i> Save changes
                </button>
            </form>
        </div>
    </div>
    <?php
}

/**
 * The change-password form.
 *
 * @param array $errors Field name => message
 */
function profile_render_password_form(array $errors = array())
{
    ?>
    <div class="card">
        <div class="card-header bg-white fw-bold">
            <i class="bi bi-key me-1"></i> Change password
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <?php render_errors($errors); ?>
            <?php endif; ?>

            <form method="post" action="" autocomplete="off" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="password">

                <div class="mb-3">
                    <label class="form-label fw-bold" for="pf_current">Current password</label>
                    <input type="password" class="form-control<?php echo isset($errors['current_password']) ? ' is-invalid' : ''; ?>"
                           id="pf_current" name="current_password" autocomplete="current-password" required>
                    <?php echo field_error($errors, 'current_password'); ?>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" for="pf_new">New password</label>
                    <input type="password" class="form-control<?php echo isset($errors['new_password']) ? ' is-invalid' : ''; ?>"
                           id="pf_new" name="new_password" autocomplete="new-password"
                           placeholder="At least <?php echo USER_PASSWORD_MIN; ?> characters" required>
                    <?php echo field_error($errors, 'new_password'); ?>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" for="pf_confirm">Repeat new password</label>
                    <input type="password" class="form-control<?php echo isset($errors['confirm_password']) ? ' is-invalid' : ''; ?>"
                           id="pf_confirm" name="confirm_password" autocomplete="new-password"
                           placeholder="Type it again" required>
                    <?php echo field_error($errors, 'confirm_password'); ?>
                </div>

                <div class="form-text mb-3">
                    You will stay signed in on this device, and the change takes
                    effect the next time you sign in anywhere.
                </div>

                <button type="submit" class="btn btn-primary w-100"
                        data-confirm="Change your password? The current one stops working straight away.">
                    <i class="bi bi-shield-lock me-1"></i> Change password
                </button>
            </form>
        </div>
    </div>
    <?php
}
