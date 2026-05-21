<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueues frontend assets and renders popup HTML for all active forms.
 */
final class Restatify_Forms_UI {

    public function __construct(
        private Restatify_Forms_Options $options
    ) {}

    // -------------------------------------------------------------------------
    // Asset enqueueing
    // -------------------------------------------------------------------------

    public function enqueue_assets(): void {
        if ( is_admin() ) {
            return;
        }

        $forms = array_map( [ $this->options, 'localize_form' ], $this->options->get_all_forms() );
        if ( empty( $forms ) ) {
            return;
        }

        $base_url  = RESTATIFY_FORMS_PLUGIN_URL . 'assets/frontend/';
        $base_path = RESTATIFY_FORMS_PLUGIN_DIR . 'assets/frontend/';

        wp_enqueue_style(
            'wp-restatify-forms',
            $base_url . 'forms.css',
            [],
            file_exists( $base_path . 'forms.css' )
                ? (string) filemtime( $base_path . 'forms.css' )
                : RESTATIFY_FORMS_VERSION
        );

        wp_enqueue_script(
            'wp-restatify-forms',
            $base_url . 'forms.js',
            [],
            file_exists( $base_path . 'forms.js' )
                ? (string) filemtime( $base_path . 'forms.js' )
                : RESTATIFY_FORMS_VERSION,
            true
        );

        // Build safe per-form config (no secret keys exposed).
        $forms_config = array_map( function ( array $form ): array {
            $sec = $form['security'] ?? [];
            return [
                'id'       => $form['id'],
                'security' => [
                    'honeypot'          => (bool) ( $sec['honeypot'] ?? true ),
                    'captcha_provider'  => $sec['captcha_provider'] ?? 'none',
                    'recaptcha_site_key'=> $sec['recaptcha_site_key'] ?? '',
                    'turnstile_site_key'=> $sec['turnstile_site_key'] ?? '',
                ],
            ];
        }, $forms );

        wp_localize_script( 'wp-restatify-forms', 'restatifyForms', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( Restatify_Forms_Constants::NONCE_SUBMIT ),
            'forms'   => array_values( $forms_config ),
            'strings' => [
                'sending'  => __( 'Wird gesendet…', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'ok'       => __( 'OK', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'error'    => __( 'Es ist ein Fehler aufgetreten.', Restatify_Forms_Constants::TEXT_DOMAIN ),
            ],
        ] );

        // Enqueue captcha providers only when needed.
        foreach ( $forms as $form ) {
            $provider = $form['security']['captcha_provider'] ?? 'none';

            if ( $provider === 'recaptcha' ) {
                $key = $form['security']['recaptcha_site_key'] ?? '';
                if ( $key !== '' && ! wp_script_is( 'google-recaptcha-v3', 'enqueued' ) ) {
                    wp_enqueue_script(
                        'google-recaptcha-v3',
                        'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $key ),
                        [],
                        null,
                        true
                    );
                }
            } elseif ( $provider === 'turnstile' ) {
                $key = $form['security']['turnstile_site_key'] ?? '';
                if ( $key !== '' && ! wp_script_is( 'cloudflare-turnstile', 'enqueued' ) ) {
                    wp_enqueue_script(
                        'cloudflare-turnstile',
                        'https://challenges.cloudflare.com/turnstile/v0/api.js',
                        [],
                        null,
                        true
                    );
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Popup rendering
    // -------------------------------------------------------------------------

    /**
     * Renders pre-built popup HTML for every active form in the footer.
     */
    public function render_popups(): void {
        if ( is_admin() || is_feed() ) {
            return;
        }

        $forms = array_map( [ $this->options, 'localize_form' ], $this->options->get_all_forms() );
        if ( empty( $forms ) ) {
            return;
        }

        echo "\n<!-- Restatify Forms -->\n";
        foreach ( $forms as $form ) {
            echo $this->render_popup( $form ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo "<!-- /Restatify Forms -->\n";
    }

    // -------------------------------------------------------------------------
    // WP link picker
    // -------------------------------------------------------------------------

    /**
     * Injects form trigger links into the WP link picker.
     *
     * @param array<int,array<string,mixed>> $results
     * @param array<string,mixed>            $query
     * @return array<int,array<string,mixed>>
     */
    public function extend_wp_link_query( array $results, array $query ): array {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return $results;
        }

        $search = strtolower( trim( (string) ( $query['s'] ?? '' ) ) );

        foreach ( array_map( [ $this->options, 'localize_form' ], $this->options->get_all_forms() ) as $form ) {
            $trigger = (string) ( $form['trigger'] ?? '' );
            $title   = (string) ( $form['title'] ?? $form['id'] );
            $picker_url = $this->normalize_trigger_for_picker( $trigger );

            if ( $trigger === '' || $picker_url === '' ) {
                continue;
            }

            if (
                $search !== ''
                && ! str_contains( strtolower( $title ), $search )
                && ! str_contains( strtolower( $trigger ), $search )
            ) {
                continue;
            }

            $results[] = [
                'ID'        => absint( crc32( $trigger ) ),
                'title'     => sprintf(
                    /* translators: %s: form title */
                    __( 'Formular-Popup: %s (Restatify)', Restatify_Forms_Constants::TEXT_DOMAIN ),
                    $title
                ),
                // Some Gutenberg link controls prefer `url`, while classic link UI uses `permalink`.
                'url'       => $picker_url,
                'permalink' => $picker_url,
                'info'      => __( 'Öffnet das Restatify Formular-Overlay beim Klick.', Restatify_Forms_Constants::TEXT_DOMAIN ),
            ];
        }

        return $results;
    }

    private function normalize_trigger_for_picker( string $trigger ): string {
        $trigger = trim( $trigger );
        if ( $trigger === '' ) {
            return '';
        }

        // Gutenberg button/link controls frequently collapse fragment-only values to "#".
        // Returning an absolute URL keeps the hash stable when saved.
        if ( str_starts_with( $trigger, '#restatify-form-' ) ) {
            return home_url( '/' ) . $trigger;
        }

        return $trigger;
    }

    // -------------------------------------------------------------------------
    // Private rendering helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $form
     */
    private function render_popup( array $form ): string {
        $id       = esc_attr( $form['id'] );
        $title    = esc_html( $form['title'] );
        $subtitle = esc_html( $form['subtitle'] );
        $text     = wp_kses_post( $form['text'] );

        $provider  = $form['security']['captcha_provider'] ?? 'none';
        $site_key  = $provider === 'recaptcha'
            ? ( $form['security']['recaptcha_site_key'] ?? '' )
            : ( $form['security']['turnstile_site_key'] ?? '' );
        $captcha   = '';
        $privacy_policy_url = (string) ( $form['security']['privacy_policy_url'] ?? '' );
        if ( $privacy_policy_url === '' && function_exists( 'get_privacy_policy_url' ) ) {
            $privacy_policy_url = (string) get_privacy_policy_url();
        }

        if ( $provider === 'turnstile' && $site_key !== '' ) {
            $captcha = '<div class="rsfm-captcha"><div class="cf-turnstile" data-sitekey="' . esc_attr( $site_key ) . '"></div></div>';
        }
        // reCAPTCHA v3 is invisible — token acquired in JS before submit.

        $fields_html = '';
        foreach ( $form['fields'] as $field ) {
            $fields_html .= $this->render_field( $field );
        }

        // Honeypot field — CSS hides it.
        if ( ! empty( $form['security']['honeypot'] ) ) {
            $fields_html .= '<div class="rsfm-field rsfm-field--honeypot" aria-hidden="true">'
                . '<label>Leave this empty</label>'
                . '<input type="text" name="_hp" value="" autocomplete="off" tabindex="-1">'
                . '</div>';
        }

        $close_label  = esc_attr__( 'Schließen', Restatify_Forms_Constants::TEXT_DOMAIN );
        $cancel_label = esc_html__( 'Abbrechen', Restatify_Forms_Constants::TEXT_DOMAIN );
        $submit_label = esc_html__( 'Senden', Restatify_Forms_Constants::TEXT_DOMAIN );

        $title_html    = $title !== '' ? "<h2 class=\"rsfm-dialog__title\" id=\"rsfm-title-{$id}\">{$title}</h2>" : '';
        $subtitle_html = $subtitle !== '' ? "<p class=\"rsfm-dialog__subtitle\">{$subtitle}</p>" : '';
        $text_html     = $text !== '' ? "<div class=\"rsfm-dialog__text\">{$text}</div>" : '';
        $privacy_legal_notice_class = '\\Restatify\\Shared\\Util\\PrivacyLegalNotice';
        $legal_html = $privacy_legal_notice_class::renderDefault( $privacy_policy_url, 'rsfm-legal-notice' );

        return <<<HTML
<div class="rsfm-popup" id="rsfm-popup-{$id}" data-form-id="{$id}" role="dialog" aria-modal="true" aria-labelledby="rsfm-title-{$id}" hidden>
  <div class="rsfm-overlay" data-rsfm-close></div>
  <div class="rsfm-dialog">
    <button class="rsfm-close" type="button" aria-label="{$close_label}" data-rsfm-close>&#215;</button>
    <div class="rsfm-dialog__inner">
      {$title_html}
      {$subtitle_html}
      {$text_html}
      <div class="rsfm-status" role="status" aria-live="polite" aria-atomic="true"></div>
      <form class="rsfm-form" novalidate data-form-id="{$id}">
        <input type="hidden" name="form_id" value="{$id}">
        {$fields_html}
        {$captcha}
        <div class="rsfm-form__actions">
          <button type="button" class="rsfm-btn rsfm-btn--ghost" data-rsfm-close>{$cancel_label}</button>
          <button type="submit" class="rsfm-btn rsfm-btn--primary rsfm-submit">{$submit_label}</button>
        </div>
      </form>
            {$legal_html}
    </div>
  </div>
</div>

HTML;
    }

    /**
     * @param array<string,mixed> $field
     */
    private function render_field( array $field ): string {
        $fid         = esc_attr( $field['id'] );
        $type        = $field['type'] ?? 'text';
        $label       = esc_html( $field['label'] ?? '' );
        $placeholder = esc_attr( $field['placeholder'] ?? '' );
        $required    = ! empty( $field['required'] );
        $req_attr    = $required ? ' required aria-required="true"' : '';
        $req_mark    = $required ? ' <span class="rsfm-required" aria-hidden="true">*</span>' : '';

        if ( $type === 'hidden' ) {
            $default = esc_attr( $field['default_value'] ?? '' );
            return "<input type=\"hidden\" name=\"fields[{$fid}]\" id=\"rsfm-field-{$fid}\" value=\"{$default}\">\n";
        }

        $input_html = $this->render_field_input( $field, $fid, $req_attr, $placeholder );

        return <<<HTML
<div class="rsfm-field" data-field-id="{$fid}">
  <label class="rsfm-label" for="rsfm-field-{$fid}">{$label}{$req_mark}</label>
  {$input_html}
  <span class="rsfm-field__error" aria-live="polite" id="rsfm-err-{$fid}"></span>
</div>

HTML;
    }

    /**
     * @param array<string,mixed> $field
     */
    private function render_field_input( array $field, string $fid, string $req_attr, string $placeholder ): string {
        $type          = $field['type'] ?? 'text';
        $default_value = esc_attr( $field['default_value'] ?? '' );
        $options       = is_array( $field['options'] ?? null ) ? $field['options'] : [];

        switch ( $type ) {
            case 'textarea':
                return "<textarea class=\"rsfm-input rsfm-input--textarea\" id=\"rsfm-field-{$fid}\" name=\"fields[{$fid}]\" placeholder=\"{$placeholder}\" rows=\"4\" aria-describedby=\"rsfm-err-{$fid}\"{$req_attr}></textarea>";

            case 'select':
                $opts_html = '<option value=""></option>';
                foreach ( $options as $opt ) {
                    $opt_esc    = esc_html( $opt );
                    $opt_val    = esc_attr( $opt );
                    $selected   = $opt === $field['default_value'] ? ' selected' : '';
                    $opts_html .= "<option value=\"{$opt_val}\"{$selected}>{$opt_esc}</option>";
                }
                return "<select class=\"rsfm-input rsfm-input--select\" id=\"rsfm-field-{$fid}\" name=\"fields[{$fid}]\" aria-describedby=\"rsfm-err-{$fid}\"{$req_attr}>{$opts_html}</select>";

            case 'checkbox':
                $checked = ( $field['default_value'] ?? '' ) === '1' ? ' checked' : '';
                return "<label class=\"rsfm-checkbox-label\"><input class=\"rsfm-checkbox\" type=\"checkbox\" id=\"rsfm-field-{$fid}\" name=\"fields[{$fid}]\" value=\"1\"{$checked}{$req_attr} aria-describedby=\"rsfm-err-{$fid}\"> "
                    . esc_html( $field['placeholder'] ?? '' ) . '</label>';

            case 'radio':
                $html = '<div class="rsfm-radio-group" role="group" aria-describedby="rsfm-err-' . $fid . '">';
                foreach ( $options as $opt ) {
                    $opt_esc  = esc_html( $opt );
                    $opt_val  = esc_attr( $opt );
                    $checked  = $opt === ( $field['default_value'] ?? '' ) ? ' checked' : '';
                    $html    .= "<label class=\"rsfm-radio-label\"><input type=\"radio\" name=\"fields[{$fid}]\" value=\"{$opt_val}\"{$checked}{$req_attr}> {$opt_esc}</label>";
                }
                $html .= '</div>';
                return $html;

            default:
                // text, email, tel, date.
                $input_type = in_array( $type, [ 'email', 'tel', 'date' ], true ) ? $type : 'text';
                return "<input class=\"rsfm-input\" type=\"{$input_type}\" id=\"rsfm-field-{$fid}\" name=\"fields[{$fid}]\" placeholder=\"{$placeholder}\" value=\"{$default_value}\" aria-describedby=\"rsfm-err-{$fid}\"{$req_attr}>";
        }
    }
}
