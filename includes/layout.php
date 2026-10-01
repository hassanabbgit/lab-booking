<?php
/**
 * Computer Laboratory Booking System
 * -----------------------------------
 * Page chrome. layout_start() and layout_end() must always be paired.
 *
 * They keep <head>, <body> and the main container in one place so individual
 * pages only contain their own content and can never leave the HTML
 * unbalanced.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/bootstrap.php';
}

/**
 * Open the page.
 *
 * @param array $opts
 *   title   string  Browser title (defaults to the app name)
 *   subtitle string Optional muted line under the page title
 *   layout  string  'app' (navbar + sidebar) or 'plain' (centred, no sidebar)
 *   active  string  Key of the active sidebar link
 */
function layout_start(array $opts = array())
{
    $defaults = array(
        'title'    => '',
        'subtitle' => '',
        'layout'   => 'app',
        'active'   => '',
    );
    $opts = array_merge($defaults, $opts);

    $pageTitle = $opts['title'] === '' ? APP_SHORT_NAME : $opts['title'] . ' | ' . APP_SHORT_NAME;

    $GLOBALS['__layout'] = $opts;
    $GLOBALS['__active'] = $opts['active'];

    require __DIR__ . '/header.php';
}

/**
 * Close the page.
 */
function layout_end()
{
    require __DIR__ . '/footer.php';
}

/**
 * Page title block shown at the top of the content area.
 *
 * @param string $title
 * @param string $subtitle
 * @param string $actions Pre-built HTML for buttons on the right
 */
function page_heading($title, $subtitle = '', $actions = '')
{
    echo '<div class="page-heading">';
    echo '<div><h1 class="page-title">' . e($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p class="page-subtitle">' . e($subtitle) . '</p>';
    }
    echo '</div>';
    if ($actions !== '') {
        echo '<div class="page-actions">' . $actions . '</div>';
    }
    echo '</div>';
}

/**
 * A dashboard statistic tile.
 *
 * @param string $label
 * @param string $value
 * @param string $icon    Bootstrap icon name
 * @param string $tone    primary|success|warning|danger|info
 * @param string $link    Optional href, making the tile clickable
 */
function stat_card($label, $value, $icon, $tone = 'primary', $link = '')
{
    $tag = $link === '' ? 'div' : 'a';
    $href = $link === '' ? '' : ' href="' . e($link) . '"';

    echo '<' . $tag . $href . ' class="stat-card tone-' . e($tone) . '">';
    echo '<div class="stat-icon"><i class="bi ' . e($icon) . '"></i></div>';
    echo '<div class="stat-body">';
    echo '<div class="stat-value">' . e($value) . '</div>';
    echo '<div class="stat-label">' . e($label) . '</div>';
    echo '</div></' . $tag . '>';
}

/**
 * Placeholder shown when a table or list has nothing to display.
 *
 * @param string $icon
 * @param string $title
 * @param string $text
 */
function empty_state($icon, $title, $text = '')
{
    echo '<div class="empty-state">';
    echo '<i class="bi ' . e($icon) . '"></i>';
    echo '<h3>' . e($title) . '</h3>';
    if ($text !== '') {
        echo '<p>' . e($text) . '</p>';
    }
    echo '</div>';
}

/**
 * Banner marking a page that is scaffolded but not yet implemented.
 *
 * @param string $phase  Which phase delivers the real feature.
 * @param string $title
 */
function phase_notice($phase, $title)
{
    echo '<div class="alert alert-info d-flex align-items-start" role="alert">';
    echo '<i class="bi bi-cone-striped me-2 mt-1"></i>';
    echo '<div><strong>' . e($title) . '</strong><br>';
    echo 'The layout and access control are in place. Full functionality arrives in '
        . e($phase) . '.</div></div>';
}

/**
 * Full page placeholder for a module that is routed and guarded but whose
 * CRUD arrives in a later phase.
 *
 * @param array $opts
 *   title     string  Page title
 *   subtitle  string  Muted line under the title
 *   icon      string  Bootstrap icon
 *   phase     string  Which phase delivers it
 *   features  array   Bulleted list of what the finished page will do
 */
function page_stub(array $opts)
{
    $defaults = array(
        'title'    => '',
        'subtitle' => '',
        'icon'     => 'bi-cone-striped',
        'phase'    => 'a later phase',
        'features' => array(),
    );
    $opts = array_merge($defaults, $opts);

    page_heading($opts['title'], $opts['subtitle']);
    render_flashes();
    phase_notice($opts['phase'], 'This module is scaffolded and access-controlled.');

    echo '<div class="card"><div class="card-body">';
    echo '<p class="text-muted small mb-3">'
        . 'When this phase is complete the page will provide:</p>';

    if (empty($opts['features'])) {
        empty_state($opts['icon'], 'Nothing to show yet', 'This module has not been implemented in the current phase.');
    } else {
        echo '<ul class="list-unstyled mb-0">';
        foreach ($opts['features'] as $feature) {
            echo '<li class="d-flex align-items-start gap-2 py-1">'
                . '<i class="bi bi-check2-circle text-success mt-1"></i>'
                . '<span>' . e($feature) . '</span></li>';
        }
        echo '</ul>';
    }

    echo '</div></div>';
}
