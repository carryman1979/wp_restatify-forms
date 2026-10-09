<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Restatify\Shared\Security\CaptchaVerifier;

/**
 * Verifies CAPTCHA tokens for reCAPTCHA v3 and Cloudflare Turnstile.
 */
final class Restatify_Forms_Captcha {

    /**
     * Verifies a token against the configured provider.
     *
     * @param array<string,mixed> $security Form security config.
     */
    public function verify( array $security, string $token ): bool {
        $provider = $security['captcha_provider'] ?? 'none';
        $provider = is_string( $provider ) ? $provider : 'none';

        if ( $provider === 'none' ) {
            return true;
        }

        // Keep forms submit-capable when a provider was selected but keys are missing.
        // In that case, the CAPTCHA is effectively disabled instead of hard-failing every request.
        if ( $provider === 'recaptcha' ) {
            $site_key = (string) ( $security['recaptcha_site_key'] ?? '' );
            $secret   = (string) ( $security['recaptcha_secret_key'] ?? '' );
            if ( $site_key === '' || $secret === '' ) {
                $this->debug_log(
                    'reCAPTCHA selected but keys are missing; verification bypassed.',
                    [ 'provider' => 'recaptcha' ]
                );
                return true;
            }
        }

        if ( $provider === 'turnstile' ) {
            $site_key = (string) ( $security['turnstile_site_key'] ?? '' );
            $secret   = (string) ( $security['turnstile_secret_key'] ?? '' );
            if ( $site_key === '' || $secret === '' ) {
                $this->debug_log(
                    'Turnstile selected but keys are missing; verification bypassed.',
                    [ 'provider' => 'turnstile' ]
                );
                return true;
            }
        }

        if ( $token === '' ) {
            $this->debug_log(
                'CAPTCHA token is empty.',
                [ 'provider' => $provider ]
            );
            return false;
        }

        $secret = '';
        if ( $provider === 'recaptcha' ) {
            $secret = (string) ( $security['recaptcha_secret_key'] ?? '' );
        } elseif ( $provider === 'turnstile' ) {
            $secret = (string) ( $security['turnstile_secret_key'] ?? '' );
        } else {
            return false;
        }

        $verified = CaptchaVerifier::verify(
            $provider,
            $secret,
            $token,
            $this->get_client_ip()
        );

        if ( ! $verified ) {
            $this->debug_log('CAPTCHA verification failed.', [ 'provider' => $provider ]);
        }

        return $verified;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function debug_log( string $message, array $context = [] ): void {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }

        $line = '[Restatify Forms CAPTCHA] ' . $message;
        if ( $context !== [] ) {
            $line .= ' ' . wp_json_encode( $context );
        }

        error_log( $line );
    }

    private function get_client_ip(): string {
        // Only use the direct connection IP — do NOT trust proxy headers here.
        return sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    }
}
