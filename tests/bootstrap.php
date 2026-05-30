<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool {
        if ($capability !== 'edit_posts') {
            return false;
        }

        return (bool) ($GLOBALS['rsfm_test_can_edit_posts'] ?? true);
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string {
        return 'http://localhost/restatify.tech' . $path;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string {
        return $text;
    }
}

if (!function_exists('absint')) {
    function absint(int|string|float $maybeint): int {
        return abs((int) $maybeint);
    }
}

if (!class_exists('Restatify_Forms_Constants')) {
    final class Restatify_Forms_Constants {
        public const NONCE_ADMIN = 'restatify_forms_admin_nonce';
        public const ADMIN_PAGE_SLUG = 'wp-restatify-forms';
        public const FIELD_TYPES = [ 'text' ];
        public const EMAIL_VALIDATION_MODES = [ 'none' ];
        public const TEL_VALIDATION_MODES = [ 'none' ];
        public const CAPTCHA_PROVIDERS = [ 'none' ];
        public const SUBMISSION_MODES = [ 'mail' ];
        public const ENDPOINT_FORMATS = [ 'json' ];
        public const ENDPOINT_AUTH_TYPES = [ 'none' ];
        public const RECIPIENT_TYPES = [ 'to' ];
        public const TEXT_DOMAIN = 'wp-restatify-forms';
    }
}

if (!class_exists('Restatify_Forms_Options')) {
    final class Restatify_Forms_Options {
        /** @var array<int,array<string,mixed>> */
        private array $forms;
        private array $defaults;

        /** @param array<int,array<string,mixed>> $forms */
        public function __construct(array $forms = [], array $defaults = []) {
            $this->forms = $forms;
            $this->defaults = $defaults ?: [
                'id' => 'contact',
                'security' => [],
                'submission' => [],
                'fields' => [],
            ];
        }

        /** @return array<int,array<string,mixed>> */
        public function get_all_forms(): array {
            return $this->forms;
        }

        /** @return array<string,mixed>|null */
        public function get_form(string $formId): ?array {
            foreach ($this->forms as $form) {
                if ((string) ($form['id'] ?? '') === $formId) {
                    return $form;
                }
            }

            return null;
        }

        /** @return array<string,mixed> */
        public function get_form_defaults(): array {
            return $this->defaults;
        }

        /** @param array<string,mixed> $form */
        public function localize_form(array $form): array {
            return $form;
        }
    }
}

if (!function_exists('wp_enqueue_editor')) {
    function wp_enqueue_editor(): void {
        $GLOBALS['rsfm_test_wp_editor_enqueued'] = true;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, bool $inFooter = false): void {
        $GLOBALS['rsfm_test_wp_scripts'][$handle] = [
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
            'in_footer' => $inFooter,
        ];
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false): void {
        $GLOBALS['rsfm_test_wp_styles'][$handle] = [
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
        ];
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script(string $handle, string $objectName, array $l10n): void {
        $GLOBALS['rsfm_test_wp_localized'][$handle] = [
            'object' => $objectName,
            'data' => $l10n,
        ];
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        return 'http://localhost/restatify.tech/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action): string {
        return 'nonce-' . $action;
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '');
    }
}

if (!defined('RESTATIFY_FORMS_PLUGIN_DIR')) {
    define('RESTATIFY_FORMS_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('RESTATIFY_FORMS_PLUGIN_URL')) {
    define('RESTATIFY_FORMS_PLUGIN_URL', 'http://localhost/restatify.tech/wp-content/plugins/wp-restatify-forms/');
}

if (!defined('RESTATIFY_FORMS_VERSION')) {
    define('RESTATIFY_FORMS_VERSION', 'test');
}

require_once dirname(__DIR__) . '/includes/class-restatify-forms-ui.php';
