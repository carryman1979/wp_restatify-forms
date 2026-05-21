<?php
/**
 * Plugin Name: Restatify Forms
 * Description: Multi-form popup builder with configurable fields, email templates and custom endpoint forwarding.
 * Version: 1.0.5
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
    define( 'RESTATIFY_FORMS_VERSION', '1.0.5' );
}

if ( ! defined( 'RESTATIFY_FORMS_SHARED_VERSION' ) ) {
    define( 'RESTATIFY_FORMS_SHARED_VERSION', '1.0.2' );
}

if ( class_exists( 'Restatify_Forms_Plugin', false ) ) {
    return;
}

require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-shared-library.php';

$restatify_forms_shared_root = restatify_forms_shared_bootstrap();

$restatify_forms_require_all = static function ( string $shared_root, array $relative_paths ): bool {
    foreach ( $relative_paths as $relative_path ) {
        $full_path = $shared_root . '/src/php/' . ltrim( (string) $relative_path, '/' );
        if ( ! file_exists( $full_path ) ) {
            return false;
        }

        require_once $full_path;
    }

    return true;
};

if ( ! $restatify_forms_require_all(
    $restatify_forms_shared_root,
    [
        'SharedRegistry.php',
        'Util/TokenReplacer.php',
        'Mail/MailDispatcher.php',
        'Mail/PlaceholderCatalog.php',
        'I18n/PolylangAdapter.php',
        'Util/PrivacyLegalNotice.php',
    ]
) ) {
    throw new RuntimeException( 'Missing required shared dependency: wp_restatify-shared/src/php/Util/PrivacyLegalNotice.php' );
}

require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-constants.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-options.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-captcha.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-mailer.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-submission.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-ui.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-admin-page.php';
require_once RESTATIFY_FORMS_PLUGIN_DIR . 'includes/class-restatify-forms-plugin.php';

new Restatify_Forms_Plugin( RESTATIFY_FORMS_PLUGIN_FILE );
