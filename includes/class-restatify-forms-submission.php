<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles AJAX form submission: validation, spam protection and dispatching.
 */
final class Restatify_Forms_Submission {

    public function __construct(
        private Restatify_Forms_Options $options,
        private Restatify_Forms_Captcha $captcha,
        private Restatify_Forms_Mailer  $mailer
    ) {}

    /**
     * wp_ajax / wp_ajax_nopriv callback.
     */
    public function handle_ajax(): void {
        // Nonce verification.
        $nonce = sanitize_text_field( wp_unslash( $_POST['_nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, Restatify_Forms_Constants::NONCE_SUBMIT ) ) {
            wp_send_json_error(
                [ 'message' => __( 'Ungültige Sicherheitsüberprüfung.', Restatify_Forms_Constants::TEXT_DOMAIN ) ],
                403
            );
        }

        // Resolve form.
        $form_id = sanitize_key( (string) ( $_POST['form_id'] ?? '' ) );
        $form    = $this->options->get_form( $form_id );
        if ( $form === null ) {
            wp_send_json_error(
                [ 'message' => __( 'Formular nicht gefunden.', Restatify_Forms_Constants::TEXT_DOMAIN ) ],
                404
            );
        }

        // Honeypot.
        if ( ! empty( $form['security']['honeypot'] ) ) {
            $pot = sanitize_text_field( wp_unslash( $_POST['_hp'] ?? '' ) );
            if ( $pot !== '' ) {
                // Silent success for bots.
                wp_send_json_success( [ 'message' => $this->success_message() ] );
            }
        }

        // CAPTCHA.
        $captcha_token = sanitize_text_field( wp_unslash( $_POST['_captcha'] ?? '' ) );
        if ( ! $this->captcha->verify( $form['security'] ?? [], $captcha_token ) ) {
            wp_send_json_error(
                [ 'message' => __( 'Sicherheitsüberprüfung fehlgeschlagen. Bitte versuche es erneut.', Restatify_Forms_Constants::TEXT_DOMAIN ) ],
                400
            );
        }

        // Validate fields.
        $raw_fields         = is_array( $_POST['fields'] ?? null ) ? $_POST['fields'] : [];
        [ $valid, $errors, $data ] = $this->validate_fields( $form, $raw_fields );

        if ( ! $valid ) {
            wp_send_json_error(
                [
                    'message' => __( 'Bitte überprüfe deine Eingaben.', Restatify_Forms_Constants::TEXT_DOMAIN ),
                    'errors'  => $errors,
                ],
                422
            );
        }

        // Dispatch.
        $mode   = $form['submission']['mode'] ?? 'mail';
        $result = $mode === 'endpoint'
            ? $this->forward_to_endpoint( $form, $data )
            : $this->mailer->send( $form, $data );

        if ( ! $result ) {
            wp_send_json_error(
                [ 'message' => __( 'Das Formular konnte nicht übermittelt werden. Bitte versuche es später erneut.', Restatify_Forms_Constants::TEXT_DOMAIN ) ],
                500
            );
        }

        wp_send_json_success( [ 'message' => $this->success_message() ] );
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $raw
     * @return array{bool, array<string,string>, array<string,string>}
     */
    private function validate_fields( array $form, array $raw ): array {
        $errors = [];
        $data   = [];

        foreach ( $form['fields'] ?? [] as $field ) {
            $fid  = $field['id'] ?? '';
            $type = $field['type'] ?? 'text';

            if ( $fid === '' ) {
                continue;
            }

            if ( $type === 'textarea' ) {
                $value = sanitize_textarea_field( wp_unslash( (string) ( $raw[ $fid ] ?? '' ) ) );
            } elseif ( $type === 'checkbox' ) {
                $value = isset( $raw[ $fid ] ) && $raw[ $fid ] ? '1' : '0';
            } else {
                $value = sanitize_text_field( wp_unslash( (string) ( $raw[ $fid ] ?? '' ) ) );
            }

            $data[ $fid ] = $value;

            if ( ! empty( $field['required'] ) && $value === '' ) {
                $errors[ $fid ] = sprintf(
                    /* translators: %s: field label */
                    __( '"%s" ist ein Pflichtfeld.', Restatify_Forms_Constants::TEXT_DOMAIN ),
                    $field['label'] ?? $fid
                );
                continue;
            }

            if ( $value === '' ) {
                continue;
            }

            $error = $this->validate_field_value( $field, $value );
            if ( $error !== '' ) {
                $errors[ $fid ] = $error;
            }
        }

        return [ empty( $errors ), $errors, $data ];
    }

    /**
     * @param array<string,mixed> $field
     */
    private function validate_field_value( array $field, string $value ): string {
        $type       = $field['type'] ?? 'text';
        $validation = $field['validation'] ?? [];

        if ( $type === 'email' ) {
            return $this->validate_email( $value, (string) ( $validation['email_check'] ?? 'regex' ) );
        }

        if ( $type === 'tel' ) {
            return $this->validate_tel( $value, (string) ( $validation['tel_check'] ?? 'simple' ) );
        }

        return '';
    }

    private function validate_email( string $value, string $mode ): string {
        if ( $mode === 'none' ) {
            return '';
        }

        if ( ! str_contains( $value, '@' ) ) {
            return $this->invalid_email_message();
        }

        if ( $mode === 'simple' ) {
            return '';
        }

        if ( ! is_email( $value ) ) {
            return $this->invalid_email_message();
        }

        if ( $mode === 'regex' ) {
            return '';
        }

        // DNS check.
        if ( $mode === 'dns' ) {
            $at_pos = strrpos( $value, '@' );
            $domain = $at_pos !== false ? substr( $value, $at_pos + 1 ) : '';

            if ( $domain === '' ) {
                return $this->invalid_email_message();
            }

            if ( ! checkdnsrr( $domain, 'MX' ) && ! checkdnsrr( $domain, 'A' ) ) {
                return __( 'Die Domain der E-Mail-Adresse scheint nicht zu existieren.', Restatify_Forms_Constants::TEXT_DOMAIN );
            }
        }

        return '';
    }

    private function validate_tel( string $value, string $mode ): string {
        if ( $mode === 'none' ) {
            return '';
        }

        // Simple: plausible phone characters and minimum length.
        if ( ! preg_match( '/^[\d\s\+\-\(\)\.\/]{6,}$/', $value ) ) {
            return $this->invalid_tel_message();
        }

        if ( $mode === 'simple' ) {
            return '';
        }

        // E.164: +[country][number], 8–15 digits total.
        $stripped = str_replace( [ ' ', '-', '(', ')' ], '', $value );
        if ( $mode === 'e164' && ! preg_match( '/^\+[1-9]\d{7,14}$/', $stripped ) ) {
            return __( 'Bitte gib die Telefonnummer im internationalen Format an (z.B. +49 123 456789).', Restatify_Forms_Constants::TEXT_DOMAIN );
        }

        return '';
    }

    private function invalid_email_message(): string {
        return __( 'Bitte gib eine gültige E-Mail-Adresse ein.', Restatify_Forms_Constants::TEXT_DOMAIN );
    }

    private function invalid_tel_message(): string {
        return __( 'Bitte gib eine gültige Telefonnummer ein.', Restatify_Forms_Constants::TEXT_DOMAIN );
    }

    // -------------------------------------------------------------------------
    // Endpoint forwarding
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed>  $form
     * @param array<string,string> $data
     */
    private function forward_to_endpoint( array $form, array $data ): bool {
        $submission = $form['submission'] ?? [];
        $url        = esc_url_raw( (string) ( $submission['endpoint_url'] ?? '' ) );

        if ( $url === '' ) {
            return false;
        }

        $format    = $submission['endpoint_format'] ?? 'json';
        $auth_type = $submission['endpoint_auth_type'] ?? 'none';
        $auth_val  = (string) ( $submission['endpoint_auth_value'] ?? '' );

        $payload = array_merge(
            $data,
            [
                '_form_id'    => $form['id'],
                '_form_title' => $form['title'],
                '_timestamp'  => time(),
            ]
        );

        $headers = [
            'Content-Type' => $format === 'json'
                ? 'application/json'
                : 'application/x-www-form-urlencoded',
        ];

        if ( $auth_type === 'bearer' && $auth_val !== '' ) {
            $headers['Authorization'] = 'Bearer ' . $auth_val;
        } elseif ( $auth_type === 'basic' && $auth_val !== '' ) {
            // Expects "user:pass" in $auth_val.
            $headers['Authorization'] = 'Basic ' . base64_encode( $auth_val );
        }

        $response = wp_remote_post(
            $url,
            [
                'headers' => $headers,
                'body'    => $format === 'json' ? wp_json_encode( $payload ) : $payload,
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );

        return $code >= 200 && $code < 300;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function success_message(): string {
        return __( 'Vielen Dank! Ihre Nachricht wurde erfolgreich übermittelt.', Restatify_Forms_Constants::TEXT_DOMAIN );
    }
}
