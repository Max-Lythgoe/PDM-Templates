<?php

/**
 * Plugin Name: PDM Templates
 * Description: Advanced template editor with block patterns, default templates, and Word document conversion
 * Version: 1.0.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Performance Driven Marketing
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pdm-templates
 */

namespace PDM\Templates;

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

// -----------------------------------------------------------------------
// Plugin Update Checker
// -----------------------------------------------------------------------
require_once plugin_dir_path(__FILE__) . 'plugin-update-checker-5.6/plugin-update-checker-5.6/load-v5p6.php';
$pdm_templates_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/Max-Lythgoe/PDM-Templates/',
    __FILE__,
    'pdm-templates'
);


// Define plugin constants
define('PDM_TEMPLATES_VERSION', '1.0.0');
define('PDM_TEMPLATES_PATH', plugin_dir_path(__FILE__));
define('PDM_TEMPLATES_URL', plugin_dir_url(__FILE__));
define('PDM_TEMPLATES_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'PDM\\Templates\\';
    $base_dir = PDM_TEMPLATES_PATH . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace(array('\\', '_'), array('/', '-'), $relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
function init()
{
    // Load text domain
    load_plugin_textdomain('pdm-templates', false, dirname(PDM_TEMPLATES_BASENAME) . '/languages');

    // Initialize components
    new Post_Type();
    new Template_Options();
    new Pattern_Registration();
    new Bulk_Upload();
    new Section_Mapping();
    new Page_Generator();
}
add_action('plugins_loaded', __NAMESPACE__ . '\\init');

// Activation hook
function activate()
{
    // Trigger CPT registration
    new Post_Type();

    // Flush rewrite rules
    flush_rewrite_rules();

    // Create default templates
    Default_Templates::create_default_templates();
}
register_activation_hook(__FILE__, __NAMESPACE__ . '\\activate');

// Deactivation hook
function deactivate()
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\deactivate');
