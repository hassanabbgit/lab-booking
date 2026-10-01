<?php
/**
 * public/ is the web-servable folder for generated artefacts:
 *   public/uploads/   user uploads (profile pictures, booking attachments)
 *   public/exports/   CSV / PDF reports produced by the Reports module
 *
 * Application entry points live at the project root so that /admin, /user and
 * /auth are reachable as short URLs. This file just points visitors at the
 * landing page if they land here directly.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

redirect('');
