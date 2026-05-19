<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

        if ( $provider === 'recaptcha' ) {
            return $this->verify_recaptcha( (string) ( $security['recaptcha_secret_key'] ?? '' ), $token );
        }

        if ( $provider === 'turnstile' ) {
            return $this->verify_turnstile( (string) ( $security['turnstile_secret_key'] ?? '' ), $token );
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function verify_recaptcha( string $secret, string $token ): bool {
        if ( $secret === '' ) {
            $this->debug_log('reCAPTCHA secret is empty.', [ 'provider' => 'recaptcha' ]);
            return false;
        }

        $response = wp_remote_post(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'body'    => [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $this->get_client_ip(),
                ],
                'timeout' => 10,
            ]
        );

        if ( is_wp_error( $response ) ) {
            $this->debug_log(
                'reCAPTCHA request failed.',
                [
                    'provider' => 'recaptcha',
                    'error'    => $response->get_error_message(),
                ]
            );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $success = is_array( $body ) && ! empty( $body['success'] );
        $score   = is_array( $body ) ? (float) ( $body['score'] ?? 0 ) : 0.0;
        $valid   = $success && $score >= 0.5;

        if ( ! $valid ) {
            $this->debug_log(
                'reCAPTCHA verification failed.',
                [
                    'provider'    => 'recaptcha',
                    'success'     => $success,
                    'score'       => $score,
                    'error_codes' => is_array( $body ) ? ( $body['error-codes'] ?? [] ) : [ 'invalid-json-response' ],
                ]
            );
        }

        return $valid;
    }

    private function verify_turnstile( string $secret, string $token ): bool {
        if ( $secret === '' ) {
            $this->debug_log('Turnstile secret is empty.', [ 'provider' => 'turnstile' ]);
            return false;
        }

        $response = wp_remote_post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [
                'body'    => [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $this->get_client_ip(),
                ],
                'timeout' => 10,
            ]
        );

        if ( is_wp_error( $response ) ) {
            $this->debug_log(
                'Turnstile request failed.',
                [
                    'provider' => 'turnstile',
                    'error'    => $response->get_error_message(),
                ]
            );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        $valid = is_array( $body ) && ! empty( $body['success'] );
        if ( ! $valid ) {
            $this->debug_log(
                'Turnstile verification failed.',
                [
                    'provider'    => 'turnstile',
                    'error_codes' => is_array( $body ) ? ( $body['error-codes'] ?? [] ) : [ 'invalid-json-response' ],
                ]
            );
        }

        return $valid;
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
