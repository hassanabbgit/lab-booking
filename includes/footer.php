<?php
/**
 * Computer Laboratory Booking System - closing tags, footer and scripts.
 * Included by layout_end(). Do not include this file directly.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/bootstrap.php';
}

$__isAppLayout = isset($GLOBALS['__layout'])
    && $GLOBALS['__layout']['layout'] === 'app'
    && is_logged_in();
?>
<?php if ($__isAppLayout): ?>
        </div><!-- /.container-fluid -->
    </main>
</div><!-- /.app-shell -->
<?php endif; ?>

<?php if (!$__isAppLayout): ?>
    </div><!-- /.container -->
</div><!-- /.plain-shell -->
<?php endif; ?>

<footer class="app-footer">
  <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span>&copy; <?php echo date('Y'); ?> <?php echo e(APP_NAME); ?></span>
    <span class="text-muted">
      <?php echo e(APP_ORG); ?> &middot;
      Final Year Project
    </span>
  </div>
</footer>

<!-- JS is served from the local vendor folder so the app works offline. -->
<script src="<?php echo e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script src="<?php echo e(asset('js/app.js')); ?>"></script>
</body>
</html>
