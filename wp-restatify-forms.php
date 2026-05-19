<?php
/**
 * Plugin Name: Restatify Forms
 * Description: Multi-form popup builder with configurable fields, email templates and custom endpoint forwarding.
 * Version: 1.0.4
 * Author: Restatify
 * License: GPL-2.0-or-later
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * Text Domain: wp-restatify-forms
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'RESTATIFY_FORMS_PLUGIN_FILE' ) ) {
    define( 'RESTATIFY_FORMS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'RESTATIFY_FORMS_PLUGIN_DIR' ) ) {
    define( 'RESTATIFY_FORMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'RESTATIFY_FORMS_PLUGIN_URL' ) ) {
    define( 'RESTATIFY_FORMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'RESTATIFY_FORMS_VERSION' ) ) {
    define( 'RESTATIFY_FORMS_VERSION', '1.0.4' );
}

if ( ! defined( 'RESTATIFY_FORMS_SHARED_VERSION' ) ) {
    define( 'RESTATIFY_FORMS_SHARED_VERSION', '1.0.0' );
}

if ( class_exists( 'Restatify_Forms_Plugin', false ) ) {
    return;
}

$restatify_forms_require_all = static function ( array $paths ): void {
    foreach ( $paths as $path ) {
        if ( is_string( $path ) && $path !== '' && file_exists( $path ) ) {
            require_once $path;
        }
    }
};

$restatify_forms_require_all(
    [
        dirname( RESTATIFY_FORMS_PLUGIN_DIR, 3 ) . '/wp_restatify-shared/src/php/SharedRegistry.php',
        dirname( RESTATIFY_FORMS_PLUGIN_DIR, 3 ) . '/wp_restatify-shared/src/php/Util/TokenReplacer.php',
        dirname( RESTATIFY_FORMS_PLUGIN_DIR, 3 ) . '/wp_restatify-shared/src/php/Mail/MailDispatcher.php',
        dirname( RESTATIFY_FORMS_PLUGIN_DIR, 3 ) . '/wp_restatify-shared/src/php/Mail/PlaceholderCatalog.php',
        dirname( RESTATIFY_FORMS_PLUGIN_DIR, 3 ) . '/wp_restatify-shared/src/php/I18n/PolylangAdapter.php',
    ]
);

require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-constants.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-options.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-captcha.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-mailer.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-submission.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-ui.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-admin-page.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-plugin.php';

new Restatify_Forms_Plugin( RESTATIFY_FORMS_PLUGIN_FILE );
