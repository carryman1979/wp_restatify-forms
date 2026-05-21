<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages plugin options: loading, saving, sanitizing and default values.
 */
final class Restatify_Forms_Options {

    /**
     * Returns all stored forms, normalized.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_all_forms(): array {
        $saved = get_option( Restatify_Forms_Constants::OPTION_KEY, [] );
        if ( ! is_array( $saved ) ) {
            return [];
        }

        return array_values(
            array_filter(
                array_map( [ $this, 'normalize_form' ], $saved )
            )
        );
    }

    /**
     * Returns a single form by ID, or null.
     *
     * @return array<string,mixed>|null
     */
    public function get_form( string $id ): ?array {
        foreach ( $this->get_all_forms() as $form ) {
            if ( ( $form['id'] ?? '' ) === $id ) {
                return $form;
            }
        }

        return null;
    }

    /**
     * Creates or updates a form config.
     */
    public function save_form( array $form ): bool {
        $form = $this->sanitize_form( $form );
        if ( empty( $form['id'] ) ) {
            return false;
        }

        $all   = $this->get_all_forms();
        $index = null;

        foreach ( $all as $i => $f ) {
            if ( ( $f['id'] ?? '' ) === $form['id'] ) {
                $index = $i;
                break;
            }
        }

        if ( $index !== null ) {
            $all[ $index ] = $form;
        } else {
            $all[] = $form;
        }

        $current = get_option( Restatify_Forms_Constants::OPTION_KEY, [] );
        if ( ! is_array( $current ) ) {
            $current = [];
        }

        $next = array_values( $all );

        // update_option returns false when data is unchanged; treat idempotent saves as success.
        if ( $current == $next ) {
            $this->register_form_polylang_strings( $form );
            return true;
        }

        $saved = (bool) update_option( Restatify_Forms_Constants::OPTION_KEY, $next );

        if ( $saved ) {
            $this->register_form_polylang_strings( $form );
        }

        return $saved;
    }

    /**
     * Registers all translatable form strings in Polylang (if active).
     */
    public function register_polylang_strings(): void {
        if ( ! function_exists( 'pll_register_string' ) ) {
            return;
        }

        foreach ( $this->get_all_forms() as $form ) {
            $this->register_form_polylang_strings( $form );
        }

        if ( class_exists( '\\Restatify\\Shared\\Util\\PrivacyLegalNotice', false ) ) {
            $privacy_legal_notice_class = '\\Restatify\\Shared\\Util\\PrivacyLegalNotice';
            $privacy_legal_notice_class::registerPolylangStrings();
        }
    }

    /**
     * Returns a Polylang-translated copy of the form (if active).
     *
     * @param array<string,mixed> $form
     * @return array<string,mixed>
     */
    public function localize_form( array $form ): array {
        if ( ! function_exists( 'pll__' ) ) {
            return $form;
        }

        $id = (string) ( $form['id'] ?? '' );
        if ( $id === '' ) {
            return $form;
        }

        $form['title']    = $this->pll_translate( (string) ( $form['title'] ?? '' ) );
        $form['subtitle'] = $this->pll_translate( (string) ( $form['subtitle'] ?? '' ) );
        $form['text']     = $this->pll_translate( (string) ( $form['text'] ?? '' ) );

        if ( isset( $form['submission'] ) && is_array( $form['submission'] ) ) {
            $form['submission']['owner_subject'] = $this->pll_translate( (string) ( $form['submission']['owner_subject'] ?? '' ) );
            $form['submission']['owner_html_body'] = $this->pll_translate( (string) ( $form['submission']['owner_html_body'] ?? '' ) );
            $form['submission']['owner_text_body'] = $this->pll_translate( (string) ( $form['submission']['owner_text_body'] ?? '' ) );
            $form['submission']['confirmation_subject'] = $this->pll_translate( (string) ( $form['submission']['confirmation_subject'] ?? '' ) );
            $form['submission']['confirmation_html_body'] = $this->pll_translate( (string) ( $form['submission']['confirmation_html_body'] ?? '' ) );
            $form['submission']['confirmation_text_body'] = $this->pll_translate( (string) ( $form['submission']['confirmation_text_body'] ?? '' ) );
        }

        if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
            foreach ( $form['fields'] as $i => $field ) {
                if ( ! is_array( $field ) ) {
                    continue;
                }

                $field_id = (string) ( $field['id'] ?? '' );
                if ( $field_id === '' ) {
                    continue;
                }

                $form['fields'][ $i ]['label'] = $this->pll_translate( (string) ( $field['label'] ?? '' ) );
                $form['fields'][ $i ]['placeholder'] = $this->pll_translate( (string) ( $field['placeholder'] ?? '' ) );

                if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
                    foreach ( $field['options'] as $j => $opt ) {
                        $form['fields'][ $i ]['options'][ $j ] = $this->pll_translate( (string) $opt );
                    }
                }
            }
        }

        return $form;
    }

    /**
     * Deletes a form by ID.
     */
    public function delete_form( string $id ): bool {
        $all = $this->get_all_forms();
        $all = array_values( array_filter( $all, fn( $f ) => ( $f['id'] ?? '' ) !== $id ) );

        return (bool) update_option( Restatify_Forms_Constants::OPTION_KEY, $all );
    }

    /**
     * Returns default form structure.
     *
     * @return array<string,mixed>
     */
    public function get_form_defaults(): array {
        return [
            'id'       => '',
            'title'    => '',
            'subtitle' => '',
            'text'     => '',
            'trigger'  => '',
            'fields'   => [],
            'security' => [
                'honeypot'             => true,
                'captcha_provider'     => 'none',
                'recaptcha_site_key'   => '',
                'recaptcha_secret_key' => '',
                'turnstile_site_key'   => '',
                'turnstile_secret_key' => '',
                'privacy_policy_url'   => function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '',
            ],
            'submission' => [
                'mode'                        => 'mail',
                'endpoint_url'                => '',
                'endpoint_format'             => 'json',
                'endpoint_auth_type'          => 'none',
                'endpoint_auth_value'         => '',
                'recipients'                  => [
                    [ 'email' => (string) get_option( 'admin_email', '' ), 'type' => 'to' ],
                ],
                'owner_subject'               => 'Neue Formular-Einsendung: {form_title}',
                'owner_html_body'             => $this->get_default_owner_template(),
                'owner_text_body'             => $this->get_default_owner_text_template(),
                'owner_html_enabled'          => true,
                'confirmation_enabled'        => false,
                'confirmation_subject'        => 'Ihre Anfrage bei {site_name}',
                'confirmation_html_body'      => $this->get_default_confirmation_template(),
                'confirmation_text_body'      => $this->get_default_confirmation_text_template(),
                'confirmation_html_enabled'   => true,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string,mixed>|null
     */
    private function normalize_form( mixed $form ): ?array {
        if ( ! is_array( $form ) || empty( $form['id'] ) ) {
            return null;
        }

        $normalized = wp_parse_args( $form, $this->get_form_defaults() );

        if ( isset( $normalized['submission'] ) && is_array( $normalized['submission'] ) ) {
            $normalized['submission'] = $this->apply_dynamic_submission_defaults( $normalized['submission'] );
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $form
     * @return array<string,mixed>
     */
    private function sanitize_form( array $form ): array {
        $defaults = $this->get_form_defaults();
        $form     = wp_parse_args( $form, $defaults );

        $form['id']       = sanitize_key( (string) $form['id'] );
        $form['title']    = sanitize_text_field( (string) $form['title'] );
        $form['subtitle'] = sanitize_text_field( (string) $form['subtitle'] );
        $form['text']     = wp_kses_post( (string) $form['text'] );
        $form['trigger']  = sanitize_text_field( (string) $form['trigger'] );

        // Fields
        $form['fields'] = is_array( $form['fields'] )
            ? array_values( array_filter( array_map( [ $this, 'sanitize_field' ], $form['fields'] ) ) )
            : [];

        // Security
        if ( ! is_array( $form['security'] ) ) {
            $form['security'] = [];
        }
        $form['security'] = wp_parse_args( $form['security'], $defaults['security'] );

        $form['security']['honeypot'] = (bool) $form['security']['honeypot'];

        $form['security']['captcha_provider'] = in_array(
            $form['security']['captcha_provider'],
            Restatify_Forms_Constants::CAPTCHA_PROVIDERS,
            true
        ) ? $form['security']['captcha_provider'] : 'none';

        $form['security']['recaptcha_site_key']   = sanitize_text_field( (string) $form['security']['recaptcha_site_key'] );
        $form['security']['recaptcha_secret_key'] = sanitize_text_field( (string) $form['security']['recaptcha_secret_key'] );
        $form['security']['turnstile_site_key']   = sanitize_text_field( (string) $form['security']['turnstile_site_key'] );
        $form['security']['turnstile_secret_key'] = sanitize_text_field( (string) $form['security']['turnstile_secret_key'] );
        $form['security']['privacy_policy_url']   = esc_url_raw( (string) $form['security']['privacy_policy_url'] );

        // Submission
        if ( ! is_array( $form['submission'] ) ) {
            $form['submission'] = [];
        }
        $form['submission'] = wp_parse_args( $form['submission'], $defaults['submission'] );

        $form['submission']['mode'] = in_array(
            $form['submission']['mode'],
            Restatify_Forms_Constants::SUBMISSION_MODES,
            true
        ) ? $form['submission']['mode'] : 'mail';

        $form['submission']['endpoint_url'] = esc_url_raw( (string) $form['submission']['endpoint_url'] );

        $form['submission']['endpoint_format'] = in_array(
            $form['submission']['endpoint_format'],
            Restatify_Forms_Constants::ENDPOINT_FORMATS,
            true
        ) ? $form['submission']['endpoint_format'] : 'json';

        $form['submission']['endpoint_auth_type'] = in_array(
            $form['submission']['endpoint_auth_type'],
            Restatify_Forms_Constants::ENDPOINT_AUTH_TYPES,
            true
        ) ? $form['submission']['endpoint_auth_type'] : 'none';

        $form['submission']['endpoint_auth_value']       = sanitize_text_field( (string) $form['submission']['endpoint_auth_value'] );
        $form['submission']['owner_subject']             = sanitize_text_field( (string) $form['submission']['owner_subject'] );
        $form['submission']['owner_html_body']           = wp_kses_post( (string) $form['submission']['owner_html_body'] );
        $form['submission']['owner_text_body']           = sanitize_textarea_field( (string) ( $form['submission']['owner_text_body'] ?? '' ) );
        $form['submission']['owner_html_enabled']        = (bool) $form['submission']['owner_html_enabled'];
        $form['submission']['confirmation_enabled']      = (bool) $form['submission']['confirmation_enabled'];
        $form['submission']['confirmation_subject']      = sanitize_text_field( (string) $form['submission']['confirmation_subject'] );
        $form['submission']['confirmation_html_body']    = wp_kses_post( (string) $form['submission']['confirmation_html_body'] );
        $form['submission']['confirmation_text_body']    = sanitize_textarea_field( (string) ( $form['submission']['confirmation_text_body'] ?? '' ) );
        $form['submission']['confirmation_html_enabled'] = (bool) $form['submission']['confirmation_html_enabled'];

        // Recipients
        if ( is_array( $form['submission']['recipients'] ) ) {
            $recipients = [];
            foreach ( $form['submission']['recipients'] as $r ) {
                if ( ! is_array( $r ) ) {
                    continue;
                }
                $email = sanitize_email( (string) ( $r['email'] ?? '' ) );
                if ( ! is_email( $email ) ) {
                    continue;
                }
                $type         = in_array( $r['type'] ?? '', Restatify_Forms_Constants::RECIPIENT_TYPES, true )
                    ? $r['type']
                    : 'to';
                $recipients[] = [ 'email' => $email, 'type' => $type ];
            }
            if ( empty( $recipients ) ) {
                $recipients[] = [ 'email' => sanitize_email( (string) get_option( 'admin_email', '' ) ), 'type' => 'to' ];
            }
            $form['submission']['recipients'] = $recipients;
        }

        return $form;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function sanitize_field( mixed $field ): ?array {
        if ( ! is_array( $field ) || empty( $field['id'] ) ) {
            return null;
        }

        $type = in_array( $field['type'] ?? '', Restatify_Forms_Constants::FIELD_TYPES, true )
            ? $field['type']
            : 'text';

        return [
            'id'            => sanitize_key( (string) $field['id'] ),
            'type'          => $type,
            'label'         => sanitize_text_field( (string) ( $field['label'] ?? '' ) ),
            'placeholder'   => sanitize_text_field( (string) ( $field['placeholder'] ?? '' ) ),
            'required'      => (bool) ( $field['required'] ?? false ),
            'options'       => is_array( $field['options'] ?? null )
                ? array_values( array_map( 'sanitize_text_field', $field['options'] ) )
                : [],
            'default_value' => sanitize_text_field( (string) ( $field['default_value'] ?? '' ) ),
            'validation'    => is_array( $field['validation'] ?? null )
                ? $this->sanitize_field_validation( $type, $field['validation'] )
                : [],
        ];
    }

    /**
     * @param array<string,mixed> $v
     * @return array<string,string>
     */
    private function sanitize_field_validation( string $type, array $v ): array {
        $result = [];

        if ( $type === 'email' ) {
            $result['email_check'] = in_array( $v['email_check'] ?? '', Restatify_Forms_Constants::EMAIL_VALIDATION_MODES, true )
                ? $v['email_check']
                : 'regex';
        }

        if ( $type === 'tel' ) {
            $result['tel_check'] = in_array( $v['tel_check'] ?? '', Restatify_Forms_Constants::TEL_VALIDATION_MODES, true )
                ? $v['tel_check']
                : 'simple';
        }

        return $result;
    }

    private function get_default_owner_template(): string {
        $branding = $this->get_mail_branding_context();

        $content = '<p style="margin:0 0 16px;">Es wurde eine neue Formular-Einsendung erfasst.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">'
            . '<tr><td style="padding:0 0 10px;"><strong>Formular:</strong> {form_title}</td></tr>'
            . '<tr><td style="padding:0 0 10px;"><strong>Datum:</strong> {date}</td></tr>'
            . '</table>'
            . '<div style="margin:16px 0 0;">{fields_table}</div>'
            . '<p style="margin:22px 0 0;">Bitte prüfe die Angaben vor einer manuellen Weiterverarbeitung.</p>';

        return $this->build_default_html_mail( $branding, 'Neue Einsendung', 'Neue Formular-Einsendung', $content );
    }

    private function get_default_confirmation_template(): string {
        $branding = $this->get_mail_branding_context();

        $content = '<p style="margin:0 0 16px;">Vielen Dank für deine Anfrage. Wir haben deine Nachricht erhalten und melden uns zeitnah bei dir.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">'
            . '<tr><td style="padding:0 0 10px;"><strong>Formular:</strong> {form_title}</td></tr>'
            . '<tr><td style="padding:0 0 10px;"><strong>Eingang:</strong> {date}</td></tr>'
            . '</table>'
            . '<div style="margin:16px 0 0;">{fields_table}</div>';

        return $this->build_default_html_mail( $branding, 'Eingangsbestätigung', 'Vielen Dank für deine Anfrage', $content );
    }

    /**
     * @param array<string,mixed> $submission
     * @return array<string,mixed>
     */
    private function apply_dynamic_submission_defaults( array $submission ): array {
        $defaults = $this->get_form_defaults()['submission'];

        $owner_html = (string) ( $submission['owner_html_body'] ?? '' );
        if ( $owner_html === '' || $this->looks_like_legacy_owner_template( $owner_html ) ) {
            $submission['owner_html_body'] = $defaults['owner_html_body'];
        }

        $confirmation_html = (string) ( $submission['confirmation_html_body'] ?? '' );
        if ( $confirmation_html === '' || $this->looks_like_legacy_confirmation_template( $confirmation_html ) ) {
            $submission['confirmation_html_body'] = $defaults['confirmation_html_body'];
        }

        if ( (string) ( $submission['owner_text_body'] ?? '' ) === '' ) {
            $submission['owner_text_body'] = $defaults['owner_text_body'];
        }

        if ( (string) ( $submission['confirmation_text_body'] ?? '' ) === '' ) {
            $submission['confirmation_text_body'] = $defaults['confirmation_text_body'];
        }

        return wp_parse_args( $submission, $defaults );
    }

    private function looks_like_legacy_owner_template( string $html ): bool {
        return str_contains( $html, 'Neue Formular-Einsendung' )
            && str_contains( $html, '{form_title}' )
            && str_contains( $html, '{fields_table}' );
    }

    private function looks_like_legacy_confirmation_template( string $html ): bool {
        return ( str_contains( $html, 'Vielen Dank' ) || str_contains( $html, 'Ihre Anfrage' ) )
            && str_contains( $html, '{form_title}' )
            && str_contains( $html, '{fields_table}' );
    }

    /**
     * @return array<string,string>
     */
    private function get_mail_branding_context(): array {
        $site_name = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );

        $branding = [
            'site_name'       => $site_name !== '' ? $site_name : 'Restatify',
            'home_url'        => home_url( '/' ),
            'logo_url'        => $this->get_placeholder_logo_url(),
            'primary_color'   => '#2563eb',
            'secondary_color' => '#0f766e',
            'background_color'=> '#eef4ff',
            'surface_color'   => '#ffffff',
            'text_color'      => '#0f172a',
            'muted_color'     => '#52607a',
            'contrast_color'  => '#ffffff',
        ];

        if ( ! $this->is_restatify_theme_active() ) {
            return $branding;
        }

        $branding['logo_url'] = $this->get_restatify_theme_logo_url();
        $palette = $this->get_restatify_theme_palette();
        $branding['primary_color'] = $palette['primary'] ?? '#ff6b00';
        $branding['secondary_color'] = $palette['secondary'] ?? '#00c2ff';
        $branding['background_color'] = $palette['background'] ?? '#f8fafc';
        $branding['text_color'] = $palette['text'] ?? '#0b1221';
        $branding['muted_color'] = '#5b6577';

        return $branding;
    }

    private function is_restatify_theme_active(): bool {
        $theme = wp_get_theme();
        if ( ! $theme->exists() ) {
            return false;
        }

        return in_array( 'wp_restatify-base-theme', [ $theme->get_stylesheet(), $theme->get_template() ], true );
    }

    private function get_restatify_theme_logo_url(): string {
        $custom_logo_id = (int) get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id > 0 ) {
            $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
            if ( is_string( $logo_url ) && $logo_url !== '' ) {
                return $logo_url;
            }
        }

        return $this->get_placeholder_logo_url();
    }

    /**
     * @return array<string,string>
     */
    private function get_restatify_theme_palette(): array {
        $palette = [];
        $theme_json_path = get_template_directory() . '/theme.json';
        if ( ! file_exists( $theme_json_path ) ) {
            return $palette;
        }

        $content = file_get_contents( $theme_json_path );
        if ( ! is_string( $content ) || $content === '' ) {
            return $palette;
        }

        $decoded = json_decode( $content, true );
        $items = is_array( $decoded['settings']['color']['palette'] ?? null ) ? $decoded['settings']['color']['palette'] : [];
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $slug = sanitize_key( (string) ( $item['slug'] ?? '' ) );
            $color = sanitize_hex_color( (string) ( $item['color'] ?? '' ) );
            if ( $slug === '' || $color === null ) {
                continue;
            }

            $palette[ $slug ] = $color;
        }

        return $palette;
    }

    private function get_placeholder_logo_url(): string {
        return plugins_url( 'assets/mail-logo-placeholder.svg', RESTATIFY_FORMS_PLUGIN_FILE );
    }

    /**
     * @param array<string,string> $branding
     */
    private function build_default_html_mail( array $branding, string $eyebrow, string $headline, string $content ): string {
        $logo_url = esc_url( $branding['logo_url'] );
        $site_name = esc_html( $branding['site_name'] );
        $home_url = esc_url( $branding['home_url'] );
        $primary_color = esc_attr( $branding['primary_color'] );
        $secondary_color = esc_attr( $branding['secondary_color'] );
        $background_color = esc_attr( $branding['background_color'] );
        $surface_color = esc_attr( $branding['surface_color'] );
        $text_color = esc_attr( $branding['text_color'] );
        $muted_color = esc_attr( $branding['muted_color'] );
        $contrast_color = esc_attr( $branding['contrast_color'] );
        $logo_markup = '<img src="' . $logo_url . '" alt="' . $site_name . '" style="display:block;max-width:220px;width:auto;max-height:56px;height:auto;border:0;">';

        return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0;padding:0;background:' . $background_color . ';font-family:Arial,sans-serif;color:' . $text_color . ';">'
            . '<tr><td align="center" style="padding:32px 16px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:680px;border-collapse:collapse;background:' . $surface_color . ';border:1px solid rgba(11,18,33,0.08);border-radius:24px;overflow:hidden;box-shadow:0 18px 48px rgba(11,18,33,0.08);">'
            . '<tr><td style="padding:28px 32px;background:linear-gradient(135deg,' . $primary_color . ' 0%,' . $secondary_color . ' 100%);color:' . $contrast_color . ';">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;"><tr>'
            . '<td align="left" style="vertical-align:middle;">' . $logo_markup . '</td>'
            . '<td align="right" style="vertical-align:middle;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;font-weight:700;opacity:0.95;">' . esc_html( $eyebrow ) . '</td>'
            . '</tr></table>'
            . '</td></tr>'
            . '<tr><td style="padding:32px;">'
            . '<h1 style="margin:0 0 20px;font-size:28px;line-height:1.2;color:' . $text_color . ';">' . esc_html( $headline ) . '</h1>'
            . '<div style="font-size:16px;line-height:1.65;color:' . $text_color . ';">' . $content . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:0 32px 32px;"><div style="height:1px;background:linear-gradient(90deg,' . $primary_color . ' 0%,' . $secondary_color . ' 100%);"></div></td></tr>'
            . '<tr><td style="padding:0 32px 32px;font-size:13px;line-height:1.6;color:' . $muted_color . ';">'
            . '<p style="margin:0 0 10px;"><strong>Disclaimer:</strong> Diese Nachricht enthält organisatorische Informationen zu deiner Anfrage. Bitte sende keine sensiblen Daten per E-Mail.</p>'
            . '<p style="margin:0 0 10px;">Diese E-Mail wurde maschinell erstellt. Antworten auf diese Nachricht werden möglicherweise nicht gelesen.</p>'
            . '<p style="margin:0 0 10px;">Schütze die Umwelt, indem du diese E-Mail nicht ausdruckst.</p>'
            . '<p style="margin:0;">' . $site_name . ' · <a href="' . $home_url . '" style="color:' . $primary_color . ';text-decoration:none;">' . $home_url . '</a></p>'
            . '</td></tr>'
            . '</table>'
            . '</td></tr>'
            . '</table>';
    }

    private function get_default_owner_text_template(): string {
        return "Neue Anfrage eingegangen\n"
            . "Formular: {form_title}\n"
            . "Datum: {date}\n\n"
            . "{fields_text}\n\n"
            . "Bitte prüfe die Angaben vor einer manuellen Weiterverarbeitung.\n\n"
            . "Disclaimer: Diese Nachricht enthält organisatorische Informationen. Bitte sende keine sensiblen Daten per E-Mail.\n"
            . "Diese E-Mail wurde maschinell erstellt. Antworten auf diese Nachricht werden möglicherweise nicht gelesen.\n"
            . "Schütze die Umwelt, indem du diese E-Mail nicht ausdruckst.";
    }

    private function get_default_confirmation_text_template(): string {
        return "Vielen Dank für deine Anfrage\n\n"
            . "Wir haben deine Nachricht erhalten und melden uns zeitnah bei dir.\n\n"
            . "Formular: {form_title}\n"
            . "Eingang: {date}\n\n"
            . "{fields_text}\n\n"
            . "Disclaimer: Diese Nachricht enthält organisatorische Informationen zu deiner Anfrage. Bitte sende keine sensiblen Daten per E-Mail.\n"
            . "Diese E-Mail wurde maschinell erstellt. Antworten auf diese Nachricht werden möglicherweise nicht gelesen.\n"
            . "Schütze die Umwelt, indem du diese E-Mail nicht ausdruckst.";
    }

    /**
     * @param array<string,mixed> $form
     */
    private function register_form_polylang_strings( array $form ): void {
        if ( ! function_exists( 'pll_register_string' ) ) {
            return;
        }

        $id = (string) ( $form['id'] ?? '' );
        if ( $id === '' ) {
            return;
        }

        $group = 'Restatify Forms';

        $this->pll_register( "restatify_forms_{$id}_title", (string) ( $form['title'] ?? '' ), $group );
        $this->pll_register( "restatify_forms_{$id}_subtitle", (string) ( $form['subtitle'] ?? '' ), $group );
        $this->pll_register( "restatify_forms_{$id}_text", (string) ( $form['text'] ?? '' ), $group, true );

        $submission = is_array( $form['submission'] ?? null ) ? $form['submission'] : [];
        $this->pll_register( "restatify_forms_{$id}_owner_subject", (string) ( $submission['owner_subject'] ?? '' ), $group );
        $this->pll_register( "restatify_forms_{$id}_owner_html_body", (string) ( $submission['owner_html_body'] ?? '' ), $group, true );
        $this->pll_register( "restatify_forms_{$id}_owner_text_body", (string) ( $submission['owner_text_body'] ?? '' ), $group, true );
        $this->pll_register( "restatify_forms_{$id}_confirmation_subject", (string) ( $submission['confirmation_subject'] ?? '' ), $group );
        $this->pll_register( "restatify_forms_{$id}_confirmation_html_body", (string) ( $submission['confirmation_html_body'] ?? '' ), $group, true );
        $this->pll_register( "restatify_forms_{$id}_confirmation_text_body", (string) ( $submission['confirmation_text_body'] ?? '' ), $group, true );

        $fields = is_array( $form['fields'] ?? null ) ? $form['fields'] : [];
        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }

            $field_id = (string) ( $field['id'] ?? '' );
            if ( $field_id === '' ) {
                continue;
            }

            $this->pll_register( "restatify_forms_{$id}_field_{$field_id}_label", (string) ( $field['label'] ?? '' ), $group );
            $this->pll_register( "restatify_forms_{$id}_field_{$field_id}_placeholder", (string) ( $field['placeholder'] ?? '' ), $group );

            $options = is_array( $field['options'] ?? null ) ? $field['options'] : [];
            foreach ( $options as $j => $opt ) {
                $this->pll_register( "restatify_forms_{$id}_field_{$field_id}_option_{$j}", (string) $opt, $group );
            }
        }
    }

    private function pll_register( string $name, string $value, string $group, bool $multiline = false ): void {
        if ( class_exists( '\\Restatify\\Shared\\I18n\\PolylangAdapter', false ) ) {
            \Restatify\Shared\I18n\PolylangAdapter::register( $name, $value, $group, $multiline );
            return;
        }

        if ( $value === '' || ! function_exists( 'pll_register_string' ) ) {
            return;
        }

        pll_register_string( $name, $value, $group, $multiline );
    }

    private function pll_translate( string $value ): string {
        if ( class_exists( '\\Restatify\\Shared\\I18n\\PolylangAdapter', false ) ) {
            return \Restatify\Shared\I18n\PolylangAdapter::translate( $value );
        }

        if ( $value === '' || ! function_exists( 'pll__' ) ) {
            return $value;
        }

        return pll__( $value );
    }
}
