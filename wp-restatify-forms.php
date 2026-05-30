<?php
/**
 * Plugin Name: Restatify Forms
 * Description: Multi-form popup builder with configurable fields, email templates and custom endpoint forwarding.
 * Version: 1.0.7
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
    define( 'RESTATIFY_FORMS_VERSION', '1.0.7' );
}

if ( ! defined( 'RESTATIFY_FORMS_SHARED_VERSION' ) ) {
    define( 'RESTATIFY_FORMS_SHARED_VERSION', '1.0.2' );
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

$restatify_forms_local_shared_root = dirname( RESTATIFY_FORMS_PLUGIN_DIR, 3 ) . '/wp_restatify-shared';
$restatify_forms_use_local_latest_shared = is_dir( $restatify_forms_local_shared_root . '/src/php' );
$restatify_forms_versioned_shared_roots = [];

if ( $restatify_forms_use_local_latest_shared ) {
    $restatify_forms_shared_base_path = $restatify_forms_local_shared_root;
    $restatify_forms_shared_base_url  = home_url( '/wp_restatify-shared' );
} else {
    if ( defined( 'WP_PLUGIN_DIR' ) && is_string( WP_PLUGIN_DIR ) && WP_PLUGIN_DIR !== '' ) {
        $restatify_forms_versioned_shared_roots[] = [
            'path' => WP_PLUGIN_DIR . '/wp_restatify-shared',
            'url'  => ( defined( 'WP_PLUGIN_URL' ) && is_string( WP_PLUGIN_URL ) && WP_PLUGIN_URL !== '' )
                ? rtrim( WP_PLUGIN_URL, '/' ) . '/wp_restatify-shared'
                : '',
        ];
    }
    if ( defined( 'WPMU_PLUGIN_DIR' ) && is_string( WPMU_PLUGIN_DIR ) && WPMU_PLUGIN_DIR !== '' ) {
        $restatify_forms_versioned_shared_roots[] = [
            'path' => WPMU_PLUGIN_DIR . '/wp_restatify-shared',
            'url'  => ( defined( 'WPMU_PLUGIN_URL' ) && is_string( WPMU_PLUGIN_URL ) && WPMU_PLUGIN_URL !== '' )
                ? rtrim( WPMU_PLUGIN_URL, '/' ) . '/wp_restatify-shared'
                : '',
        ];
    }

    $restatify_forms_shared_base_path = '';
    $restatify_forms_shared_base_url  = '';

    foreach ( $restatify_forms_versioned_shared_roots as $restatify_forms_root ) {
        $restatify_forms_versioned_path = rtrim( (string) $restatify_forms_root['path'], '/' ) . '/versions/' . RESTATIFY_FORMS_SHARED_VERSION;
        if ( is_dir( $restatify_forms_versioned_path . '/src/php' ) ) {
            $restatify_forms_shared_base_path = $restatify_forms_versioned_path;
            $restatify_forms_root_url = (string) $restatify_forms_root['url'];
            $restatify_forms_shared_base_url = $restatify_forms_root_url !== ''
                ? rtrim( $restatify_forms_root_url, '/' ) . '/versions/' . RESTATIFY_FORMS_SHARED_VERSION
                : '';
            break;
        }
    }

    if ( $restatify_forms_shared_base_path === '' && count( $restatify_forms_versioned_shared_roots ) > 0 ) {
        $restatify_forms_first_root = $restatify_forms_versioned_shared_roots[0];
        $restatify_forms_shared_base_path = rtrim( (string) $restatify_forms_first_root['path'], '/' ) . '/versions/' . RESTATIFY_FORMS_SHARED_VERSION;
        $restatify_forms_first_root_url = (string) ( $restatify_forms_first_root['url'] ?? '' );
        $restatify_forms_shared_base_url = $restatify_forms_first_root_url !== ''
            ? rtrim( $restatify_forms_first_root_url, '/' ) . '/versions/' . RESTATIFY_FORMS_SHARED_VERSION
            : '';
    }
}

if ( ! defined( 'RESTATIFY_FORMS_SHARED_BASE_PATH' ) ) {
    define( 'RESTATIFY_FORMS_SHARED_BASE_PATH', $restatify_forms_shared_base_path );
}

if ( ! defined( 'RESTATIFY_FORMS_SHARED_BASE_URL' ) ) {
    define( 'RESTATIFY_FORMS_SHARED_BASE_URL', $restatify_forms_shared_base_url );
}

$restatify_forms_shared_candidates = static function ( string $relative_path ) use ( $restatify_forms_shared_base_path ): array {
    if ( ! is_string( $restatify_forms_shared_base_path ) || $restatify_forms_shared_base_path === '' ) {
        return [];
    }

    return [ rtrim( $restatify_forms_shared_base_path, '/' ) . '/' . ltrim( $relative_path, '/' ) ];
};

$restatify_forms_symbol_exists = static function ( string $symbol ): bool {
    if ( $symbol === '' ) {
        return false;
    }

    return class_exists( $symbol, false )
        || interface_exists( $symbol, false )
        || trait_exists( $symbol, false );
};

$restatify_forms_require_shared = static function ( string $relative_path, string $symbol = '' ) use (
    $restatify_forms_require_all,
    $restatify_forms_shared_candidates,
    $restatify_forms_symbol_exists
): bool {
    if ( $symbol !== '' && $restatify_forms_symbol_exists( $symbol ) ) {
        return true;
    }

    $restatify_forms_require_all( $restatify_forms_shared_candidates( $relative_path ) );

    if ( $symbol !== '' ) {
        return $restatify_forms_symbol_exists( $symbol );
    }

    return true;
};

$restatify_forms_require_shared( 'src/php/SharedRegistry.php', '\\Restatify\\Shared\\SharedRegistry' );
$restatify_forms_require_shared( 'src/php/Util/TokenReplacer.php', '\\Restatify\\Shared\\Util\\TokenReplacer' );
$restatify_forms_require_shared( 'src/php/Mail/MailDispatcher.php', '\\Restatify\\Shared\\Mail\\MailDispatcher' );
$restatify_forms_require_shared( 'src/php/Mail/PlaceholderCatalog.php', '\\Restatify\\Shared\\Mail\\PlaceholderCatalog' );
$restatify_forms_require_shared( 'src/php/I18n/PolylangAdapter.php', '\\Restatify\\Shared\\I18n\\PolylangAdapter' );
$restatify_forms_require_shared( 'src/php/Util/PrivacyLegalNotice.php', '\\Restatify\\Shared\\Util\\PrivacyLegalNotice' );

if ( ! class_exists( '\\Restatify\\Shared\\Util\\PrivacyLegalNotice', false ) ) {
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

