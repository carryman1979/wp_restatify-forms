<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once (getenv('RESTATIFY_SHARED_TEST_ROOT') ?: dirname(__DIR__, 4) . '/wp_restatify-shared') . '/tests/bootstrap.php';
require_once dirname(__DIR__) . '/includes/class-restatify-forms-captcha.php';

final class RestatifyFormsCaptchaTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['restatify_shared_http_calls'] = [];
        $GLOBALS['restatify_shared_http_response'] = [
            'response' => ['code' => 200],
            'body' => '{"success":true,"score":0.9}',
        ];
        $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
    }

    public function testPreservesDisabledAndMissingKeyBehaviorWithoutRequests(): void {
        $captcha = new Restatify_Forms_Captcha();
        self::assertTrue($captcha->verify([], ''));
        self::assertTrue($captcha->verify(['captcha_provider' => 'recaptcha'], ''));
        self::assertTrue($captcha->verify(['captcha_provider' => 'turnstile'], ''));
        self::assertSame([], $GLOBALS['restatify_shared_http_calls']);
    }

    public function testRecaptchaUsesSharedScoreValidationAndDirectClientIp(): void {
        $security = ['captcha_provider' => 'recaptcha', 'recaptcha_site_key' => 'site', 'recaptcha_secret_key' => 'secret'];
        $captcha = new Restatify_Forms_Captcha();
        self::assertTrue($captcha->verify($security, 'token'));
        self::assertSame('192.0.2.1', $GLOBALS['restatify_shared_http_calls'][0][1]['body']['remoteip']);
        $GLOBALS['restatify_shared_http_response']['body'] = '{"success":true,"score":0.1}';
        self::assertFalse($captcha->verify($security, 'token'));
        self::assertFalse($captcha->verify($security, ''));
    }

    public function testTurnstileAndUnknownProviderFailClosedOnErrors(): void {
        $security = ['captcha_provider' => 'turnstile', 'turnstile_site_key' => 'site', 'turnstile_secret_key' => 'secret'];
        $captcha = new Restatify_Forms_Captcha();
        self::assertTrue($captcha->verify($security, 'token'));
        $GLOBALS['restatify_shared_http_response'] = new WP_Error('transport');
        self::assertFalse($captcha->verify($security, 'token'));
        self::assertFalse($captcha->verify(['captcha_provider' => 'unknown'], 'token'));
    }
}
