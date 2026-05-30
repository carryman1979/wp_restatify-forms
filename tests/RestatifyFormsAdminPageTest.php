<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/includes/class-restatify-forms-admin-page.php';

final class RestatifyFormsAdminPageTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['rsfm_test_wp_scripts'] = [];
        $GLOBALS['rsfm_test_wp_styles'] = [];
        $GLOBALS['rsfm_test_wp_localized'] = [];
        $GLOBALS['rsfm_test_wp_editor_enqueued'] = false;
        $_GET = [];
    }

    public function testEnqueueAdminAssetsLoadsMailEditorHelperBeforeMainScript(): void {
        $options = new Restatify_Forms_Options([
            [
                'id' => 'kontaktformular',
                'title' => 'Kontaktformular',
                'security' => [],
                'submission' => [],
                'fields' => [],
            ],
        ]);

        $page = new Restatify_Forms_Admin_Page($options);
        $page->enqueue_admin_assets('toplevel_page_' . Restatify_Forms_Constants::ADMIN_PAGE_SLUG);

        self::assertArrayHasKey('wp-restatify-forms-admin-mail-editor-helpers', $GLOBALS['rsfm_test_wp_scripts']);
        self::assertArrayHasKey('wp-restatify-forms-admin', $GLOBALS['rsfm_test_wp_scripts']);

        $mainScript = $GLOBALS['rsfm_test_wp_scripts']['wp-restatify-forms-admin'];
        self::assertContains('wp-restatify-forms-admin-mail-editor-helpers', $mainScript['deps']);
    }

    public function testEnqueueAdminAssetsSkipsUnknownAdminHook(): void {
        $page = new Restatify_Forms_Admin_Page(new Restatify_Forms_Options());
        $page->enqueue_admin_assets('settings_page_unrelated-plugin');

        self::assertSame([], $GLOBALS['rsfm_test_wp_scripts']);
        self::assertSame([], $GLOBALS['rsfm_test_wp_styles']);
        self::assertSame([], $GLOBALS['rsfm_test_wp_localized']);
    }
}
