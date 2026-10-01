<?php
/**
 * Computer Laboratory Booking System
 * -----------------------------------
 * Bootstrap alert rendering for flash messages and validation errors.
 */

/**
 * Icon for each alert type.
 *
 * @param  string $type
 * @return string
 */
function alert_icon($type)
{
    $icons = array(
        'success' => 'bi-check-circle-fill',
        'info'    => 'bi-info-circle-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'danger'  => 'bi-x-octagon-fill',
    );
    return isset($icons[$type]) ? $icons[$type] : 'bi-info-circle-fill';
}

/**
 * Render the flash messages queued by the previous request.
 */
function render_flashes()
{
    $flashes = take_flashes();
    if (empty($flashes)) {
        return;
    }

    echo '<div class="flash-stack">';
    foreach ($flashes as $flash) {
        $type = in_array($flash['type'], array('success', 'info', 'warning', 'danger'), true)
            ? $flash['type']
            : 'info';
        echo '<div class="alert alert-' . e($type) . ' alert-dismissible fade show d-flex align-items-start" role="alert">';
        echo '<i class="bi ' . e(alert_icon($type)) . ' me-2 mt-1"></i>';
        echo '<div class="flex-grow-1">' . e($flash['message']) . '</div>';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
    echo '</div>';
}

/**
 * Render a list of validation errors.
 *
 * @param array $errors
 */
function render_errors(array $errors)
{
    if (empty($errors)) {
        return;
    }

    echo '<div class="alert alert-danger d-flex align-items-start" role="alert">';
    echo '<i class="bi bi-x-octagon-fill me-2 mt-1"></i>';
    echo '<div><strong>Please fix the following:</strong><ul class="mb-0 mt-1">';
    foreach ($errors as $error) {
        echo '<li>' . e($error) . '</li>';
    }
    echo '</ul></div></div>';
}

/**
 * Render a single inline error next to a form group.
 *
 * @param array  $errors
 * @param string $key
 * @return string
 */
function field_error(array $errors, $key)
{
    if (empty($errors[$key])) {
        return '';
    }
    return '<div class="invalid-feedback d-block">' . e($errors[$key]) . '</div>';
}
