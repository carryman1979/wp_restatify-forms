<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders the admin settings page and handles admin AJAX actions.
 */
final class Restatify_Forms_Admin_Page {

    public function __construct(
        private Restatify_Forms_Options $options
    ) {}

    // -------------------------------------------------------------------------
    // Asset enqueueing
    // -------------------------------------------------------------------------

    /**
     * Enqueues scripts and styles for forms list and editor admin pages.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public function enqueue_admin_assets( string $hook ): void {
        $valid_hooks = [
            'toplevel_page_' . Restatify_Forms_Constants::ADMIN_PAGE_SLUG,
            'restatify-forms_page_wp-restatify-forms-edit',
        ];

        if ( ! in_array( $hook, $valid_hooks, true ) ) {
            return;
        }

        if ( function_exists( 'wp_enqueue_editor' ) ) {
            wp_enqueue_editor();
        }

        $base_url  = RESTATIFY_FORMS_PLUGIN_URL . 'assets/admin/';
        $base_path = RESTATIFY_FORMS_PLUGIN_DIR . 'assets/admin/';
        $shared_base_path = defined( 'RESTATIFY_FORMS_SHARED_BASE_PATH' ) && is_string( RESTATIFY_FORMS_SHARED_BASE_PATH ) && RESTATIFY_FORMS_SHARED_BASE_PATH !== ''
            ? RESTATIFY_FORMS_SHARED_BASE_PATH
            : ABSPATH . 'wp_restatify-shared';
        $shared_base_url = defined( 'RESTATIFY_FORMS_SHARED_BASE_URL' ) && is_string( RESTATIFY_FORMS_SHARED_BASE_URL ) && RESTATIFY_FORMS_SHARED_BASE_URL !== ''
            ? RESTATIFY_FORMS_SHARED_BASE_URL
            : home_url( '/wp_restatify-shared' );
        $shared_js_path = rtrim( $shared_base_path, '/' ) . '/src/js/mail-template-editor.js';
        $shared_js_url  = rtrim( $shared_base_url, '/' ) . '/src/js/mail-template-editor.js';

        wp_enqueue_script(
            'restatify-shared-mail-template-editor',
            $shared_js_url,
            [],
            file_exists( $shared_js_path )
                ? (string) filemtime( $shared_js_path )
                : RESTATIFY_FORMS_VERSION,
            true
        );

        wp_enqueue_style(
            'wp-restatify-forms-admin',
            $base_url . 'admin.css',
            [ 'wp-components' ],
            file_exists( $base_path . 'admin.css' )
                ? (string) filemtime( $base_path . 'admin.css' )
                : RESTATIFY_FORMS_VERSION
        );

        wp_enqueue_script(
            'wp-restatify-forms-admin-mail-editor-helpers',
            $base_url . 'admin-mail-editor-helpers.js',
            [ 'restatify-shared-mail-template-editor' ],
            file_exists( $base_path . 'admin-mail-editor-helpers.js' )
                ? (string) filemtime( $base_path . 'admin-mail-editor-helpers.js' )
                : RESTATIFY_FORMS_VERSION,
            true
        );

        wp_enqueue_script(
            'wp-restatify-forms-admin',
            $base_url . 'admin.js',
            [ 'jquery', 'wp-restatify-forms-admin-mail-editor-helpers' ],
            file_exists( $base_path . 'admin.js' )
                ? (string) filemtime( $base_path . 'admin.js' )
                : RESTATIFY_FORMS_VERSION,
            true
        );

        $action  = sanitize_key( (string) ( $_GET['action'] ?? 'list' ) );
        $form_id = sanitize_key( (string) ( $_GET['form_id'] ?? '' ) );

        $current_form = null;
        $is_new       = true;

        if ( $action === 'edit' && $form_id !== '' ) {
            $current_form = $this->options->get_form( $form_id );
            $is_new       = false;
        }

        if ( $current_form === null ) {
            $current_form = $this->options->get_form_defaults();
            $is_new       = true;
        }

        wp_localize_script( 'wp-restatify-forms-admin', 'rsfmAdmin', [
            'form'                => $current_form,
            'isNew'               => $is_new,
            'formDefaults'        => $this->options->get_form_defaults(),
            'fieldTypes'          => Restatify_Forms_Constants::FIELD_TYPES,
            'emailValidationModes'=> Restatify_Forms_Constants::EMAIL_VALIDATION_MODES,
            'telValidationModes'  => Restatify_Forms_Constants::TEL_VALIDATION_MODES,
            'captchaProviders'    => Restatify_Forms_Constants::CAPTCHA_PROVIDERS,
            'submissionModes'     => Restatify_Forms_Constants::SUBMISSION_MODES,
            'endpointFormats'     => Restatify_Forms_Constants::ENDPOINT_FORMATS,
            'endpointAuthTypes'   => Restatify_Forms_Constants::ENDPOINT_AUTH_TYPES,
            'recipientTypes'      => Restatify_Forms_Constants::RECIPIENT_TYPES,
            'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
            'nonce'               => wp_create_nonce( Restatify_Forms_Constants::NONCE_ADMIN ),
            'listUrl'             => admin_url( 'admin.php?page=' . Restatify_Forms_Constants::ADMIN_PAGE_SLUG ),
            'strings'             => $this->get_admin_strings(),
        ] );
    }

    // -------------------------------------------------------------------------
    // Page rendering – Forms List
    // -------------------------------------------------------------------------

    public function render_list_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $forms    = $this->options->get_all_forms();
        $edit_url = admin_url( 'admin.php?page=' . Restatify_Forms_Constants::ADMIN_PAGE_SLUG . '&action=edit&form_id=' );
        $new_url  = admin_url( 'admin.php?page=' . Restatify_Forms_Constants::ADMIN_PAGE_SLUG . '&action=edit' );

        echo '<div class="wrap rsfm-wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Restatify Forms', Restatify_Forms_Constants::TEXT_DOMAIN ) . '</h1>';
        echo '<a href="' . esc_url( $new_url ) . '" class="page-title-action">'
            . esc_html__( '+ Neues Formular', Restatify_Forms_Constants::TEXT_DOMAIN ) . '</a>';
        echo '<hr class="wp-header-end">';

        if ( empty( $forms ) ) {
            echo '<div class="rsfm-empty-state">';
            echo '<p>' . esc_html__( 'Noch keine Formulare vorhanden.', Restatify_Forms_Constants::TEXT_DOMAIN ) . '</p>';
            echo '<a href="' . esc_url( $new_url ) . '" class="button button-primary">'
                . esc_html__( 'Erstes Formular erstellen', Restatify_Forms_Constants::TEXT_DOMAIN ) . '</a>';
            echo '</div>';
            echo '</div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped rsfm-forms-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Formular', Restatify_Forms_Constants::TEXT_DOMAIN ) . '</th>';
        echo '<th>' . esc_html__( 'Trigger-Link', Restatify_Forms_Constants::TEXT_DOMAIN ) . '</th>';
        echo '<th>' . esc_html__( 'Versand', Restatify_Forms_Constants::TEXT_DOMAIN ) . '</th>';
        echo '<th>' . esc_html__( 'Felder', Restatify_Forms_Constants::TEXT_DOMAIN ) . '</th>';
        echo '<th>' . esc_html__( 'Aktionen', Restatify_Forms_Constants::TEXT_DOMAIN ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $forms as $form ) {
            $fid        = esc_attr( $form['id'] );
            $title      = esc_html( $form['title'] ?: $form['id'] );
            $trigger    = esc_html( $form['trigger'] ?? '' );
            $mode       = $form['submission']['mode'] ?? 'mail';
            $mode_label = $mode === 'endpoint'
                ? esc_html__( 'Custom Endpoint', Restatify_Forms_Constants::TEXT_DOMAIN )
                : esc_html__( 'Mailer', Restatify_Forms_Constants::TEXT_DOMAIN );
            $field_count = count( $form['fields'] ?? [] );
            $form_edit_url = esc_url( $edit_url . $fid );

            $nonce_delete = wp_create_nonce( 'rsfm_delete_' . $fid );

            echo "<tr>";
            echo "<td><strong><a href=\"{$form_edit_url}\">{$title}</a></strong><br><small style='color:#646970'>{$fid}</small></td>";
            echo "<td><code>{$trigger}</code></td>";
            echo "<td>{$mode_label}</td>";
            echo "<td>{$field_count}</td>";
            echo "<td>";
            echo "<a href=\"{$form_edit_url}\" class=\"button button-small\">"
                . esc_html__( 'Bearbeiten', Restatify_Forms_Constants::TEXT_DOMAIN ) . '</a> ';
            echo "<button type=\"button\" class=\"button button-small rsfm-delete-btn\" "
                . "data-form-id=\"{$fid}\" data-nonce=\"{$nonce_delete}\">"
                . esc_html__( 'Löschen', Restatify_Forms_Constants::TEXT_DOMAIN ) . '</button>';
            echo "</td>";
            echo "</tr>";
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    // -------------------------------------------------------------------------
    // Page rendering – Form Editor (multi-step)
    // -------------------------------------------------------------------------

    public function render_edit_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $form_id      = sanitize_key( (string) ( $_GET['form_id'] ?? '' ) );
        $current_form = $form_id !== '' ? $this->options->get_form( $form_id ) : null;
        $is_new       = $current_form === null;

        if ( $is_new ) {
            $page_title = esc_html__( 'Neues Formular erstellen', Restatify_Forms_Constants::TEXT_DOMAIN );
        } else {
            $page_title = sprintf(
                /* translators: %s: form title */
                esc_html__( 'Formular bearbeiten: %s', Restatify_Forms_Constants::TEXT_DOMAIN ),
                esc_html( $current_form['title'] ?: $form_id )
            );
        }

        $list_url = esc_url( admin_url( 'admin.php?page=' . Restatify_Forms_Constants::ADMIN_PAGE_SLUG ) );

        ?>
        <div class="wrap rsfm-wrap rsfm-editor">
            <h1 class="wp-heading-inline"><?php echo $page_title; ?></h1>
            <a href="<?php echo $list_url; ?>" class="page-title-action"><?php esc_html_e( '← Alle Formulare', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></a>
            <hr class="wp-header-end">

            <div class="rsfm-notice rsfm-notice--success" id="rsfm-save-success" hidden>
                <p><?php esc_html_e( 'Formular gespeichert.', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></p>
            </div>
            <div class="rsfm-notice rsfm-notice--error" id="rsfm-save-error" hidden>
                <p><?php esc_html_e( 'Fehler beim Speichern. Bitte versuche es erneut.', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></p>
            </div>

            <!-- Step indicator -->
            <div class="rsfm-stepper" id="rsfm-stepper" role="tablist" aria-label="<?php esc_attr_e( 'Formular-Schritte', Restatify_Forms_Constants::TEXT_DOMAIN ); ?>">
                <?php
                $steps = [
                    [ 'icon' => '1', 'label' => __( 'Basisdaten', Restatify_Forms_Constants::TEXT_DOMAIN ) ],
                    [ 'icon' => '2', 'label' => __( 'Felder', Restatify_Forms_Constants::TEXT_DOMAIN ) ],
                    [ 'icon' => '3', 'label' => __( 'Sicherheit', Restatify_Forms_Constants::TEXT_DOMAIN ) ],
                    [ 'icon' => '4', 'label' => __( 'Versand', Restatify_Forms_Constants::TEXT_DOMAIN ) ],
                ];
                foreach ( $steps as $i => $step ) :
                    $active_class = $i === 0 ? ' is-active' : '';
                    ?>
                    <button type="button" class="rsfm-stepper__item<?php echo $active_class; ?>"
                        data-step="<?php echo $i; ?>" role="tab"
                        aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                        aria-controls="rsfm-step-panel-<?php echo $i; ?>">
                        <span class="rsfm-stepper__num"><?php echo esc_html( $step['icon'] ); ?></span>
                        <span class="rsfm-stepper__label"><?php echo esc_html( $step['label'] ); ?></span>
                    </button>
                    <?php
                endforeach;
                ?>
            </div>

            <!-- Step panels -->
            <div class="rsfm-step-panels">

                <!-- Step 1: Basisdaten -->
                <div class="rsfm-step-panel is-active" id="rsfm-step-panel-0" data-step="0" role="tabpanel">
                    <div class="rsfm-card">
                        <h2 class="rsfm-card__title"><?php esc_html_e( 'Basisdaten', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></h2>

                        <div class="rsfm-field-row">
                            <label class="rsfm-field-label" for="rsfm-field-title"><?php esc_html_e( 'Formular-Titel', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                            <input type="text" id="rsfm-field-title" class="regular-text" data-bind="title" placeholder="<?php esc_attr_e( 'z.B. Kontaktformular', Restatify_Forms_Constants::TEXT_DOMAIN ); ?>">
                            <p class="description"><?php esc_html_e( 'Wird als Überschrift im Popup angezeigt.', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></p>
                        </div>

                        <div class="rsfm-field-row">
                            <label class="rsfm-field-label" for="rsfm-field-id"><?php esc_html_e( 'Formular-ID (Slug)', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                            <input type="text" id="rsfm-field-id" class="regular-text" data-bind="id" placeholder="z.B. kontakt">
                            <p class="description"><?php esc_html_e( 'Nur Kleinbuchstaben, Zahlen und Bindestriche. Wird automatisch aus dem Titel generiert.', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></p>
                        </div>

                        <div class="rsfm-field-row">
                            <label class="rsfm-field-label" for="rsfm-field-trigger"><?php esc_html_e( 'Trigger-Link', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                            <input type="text" id="rsfm-field-trigger" class="regular-text rsfm-trigger-display" data-bind="trigger" readonly>
                            <p class="description"><?php esc_html_e( 'Setze diesen Link im Gutenberg-Editor als URL ein, um das Popup zu öffnen.', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></p>
                        </div>

                        <div class="rsfm-field-row">
                            <label class="rsfm-field-label" for="rsfm-field-subtitle"><?php esc_html_e( 'Untertitel', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                            <input type="text" id="rsfm-field-subtitle" class="large-text" data-bind="subtitle">
                        </div>

                        <div class="rsfm-field-row">
                            <label class="rsfm-field-label" for="rsfm-field-text"><?php esc_html_e( 'Beschreibungstext', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                            <textarea id="rsfm-field-text" class="large-text" rows="3" data-bind="text"></textarea>
                            <p class="description"><?php esc_html_e( 'Wird oberhalb des Formulars im Popup angezeigt.', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Felder (Formularbuilder) – JS-driven -->
                <div class="rsfm-step-panel" id="rsfm-step-panel-1" data-step="1" role="tabpanel" hidden>
                    <div class="rsfm-card">
                        <div class="rsfm-card__header">
                            <h2 class="rsfm-card__title"><?php esc_html_e( 'Felder', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></h2>
                            <button type="button" class="button button-primary" id="rsfm-add-field-btn">
                                <?php esc_html_e( '+ Feld hinzufügen', Restatify_Forms_Constants::TEXT_DOMAIN ); ?>
                            </button>
                        </div>
                        <div id="rsfm-field-list" class="rsfm-field-list" data-empty-label="<?php esc_attr_e( 'Noch keine Felder. Klicke auf „+ Feld hinzufügen".', Restatify_Forms_Constants::TEXT_DOMAIN ); ?>">
                            <!-- rendered by admin.js -->
                        </div>
                    </div>
                </div>

                <!-- Step 3: Sicherheit -->
                <div class="rsfm-step-panel" id="rsfm-step-panel-2" data-step="2" role="tabpanel" hidden>
                    <div class="rsfm-card">
                        <h2 class="rsfm-card__title"><?php esc_html_e( 'Sicherheit', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></h2>

                        <div class="rsfm-field-row">
                            <label class="rsfm-toggle-label">
                                <input type="checkbox" id="rsfm-security-honeypot" data-bind-security="honeypot">
                                <span><?php esc_html_e( 'Honeypot-Feld aktivieren', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Fügt ein verstecktes Feld ein, das von Bots häufig befüllt wird.', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></p>
                        </div>

                        <div class="rsfm-field-row">
                            <label class="rsfm-field-label"><?php esc_html_e( 'CAPTCHA-Anbieter', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                            <div class="rsfm-radio-group">
                                <label><input type="radio" name="rsfm-captcha-provider" value="none" data-bind-security="captcha_provider"> <?php esc_html_e( 'Keiner', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                <label><input type="radio" name="rsfm-captcha-provider" value="recaptcha" data-bind-security="captcha_provider"> <?php esc_html_e( 'Google reCAPTCHA v3', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                <label><input type="radio" name="rsfm-captcha-provider" value="turnstile" data-bind-security="captcha_provider"> <?php esc_html_e( 'Cloudflare Turnstile', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                            </div>
                        </div>

                        <div class="rsfm-captcha-config" id="rsfm-recaptcha-config">
                            <h3><?php esc_html_e( 'Google reCAPTCHA v3', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></h3>
                            <div class="rsfm-field-row">
                                <label class="rsfm-field-label" for="rsfm-recaptcha-site-key"><?php esc_html_e( 'Site Key', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                <input type="text" id="rsfm-recaptcha-site-key" class="large-text" data-bind-security="recaptcha_site_key">
                            </div>
                            <div class="rsfm-field-row">
                                <label class="rsfm-field-label" for="rsfm-recaptcha-secret-key"><?php esc_html_e( 'Secret Key', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                <input type="password" id="rsfm-recaptcha-secret-key" class="large-text" data-bind-security="recaptcha_secret_key" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="rsfm-captcha-config" id="rsfm-turnstile-config">
                            <h3><?php esc_html_e( 'Cloudflare Turnstile', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></h3>
                            <div class="rsfm-field-row">
                                <label class="rsfm-field-label" for="rsfm-turnstile-site-key"><?php esc_html_e( 'Site Key', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                <input type="text" id="rsfm-turnstile-site-key" class="large-text" data-bind-security="turnstile_site_key">
                            </div>
                            <div class="rsfm-field-row">
                                <label class="rsfm-field-label" for="rsfm-turnstile-secret-key"><?php esc_html_e( 'Secret Key', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                <input type="password" id="rsfm-turnstile-secret-key" class="large-text" data-bind-security="turnstile_secret_key" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="rsfm-field-row">
                            <label class="rsfm-field-label" for="rsfm-privacy-policy-url"><?php esc_html_e( 'URL zur Datenschutzerklärung', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                            <input type="url" id="rsfm-privacy-policy-url" class="large-text code" data-bind-security="privacy_policy_url" placeholder="https://example.com/datenschutz">
                            <p class="description"><?php esc_html_e( 'Wird als Link im Legal-Hinweis unter dem Formular angezeigt.', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Versand -->
                <div class="rsfm-step-panel" id="rsfm-step-panel-3" data-step="3" role="tabpanel" hidden>
                    <div class="rsfm-card">
                        <h2 class="rsfm-card__title"><?php esc_html_e( 'Versand', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></h2>

                        <div class="rsfm-field-row">
                            <label class="rsfm-field-label"><?php esc_html_e( 'Versandmodus', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                            <div class="rsfm-radio-group">
                                <label><input type="radio" name="rsfm-submission-mode" value="mail" data-bind-submission="mode"> <?php esc_html_e( 'Standard-Mailer (wp_mail)', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                <label><input type="radio" name="rsfm-submission-mode" value="endpoint" data-bind-submission="mode"> <?php esc_html_e( 'Custom Endpoint (Webhook)', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                            </div>
                        </div>

                        <!-- Mail config -->
                        <div id="rsfm-mail-config">
                            <h3><?php esc_html_e( 'Empfänger', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></h3>
                            <div id="rsfm-recipients-list" class="rsfm-recipients-list">
                                <!-- rendered by admin.js -->
                            </div>
                            <button type="button" class="button" id="rsfm-add-recipient-btn">
                                <?php esc_html_e( '+ Empfänger hinzufügen', Restatify_Forms_Constants::TEXT_DOMAIN ); ?>
                            </button>

                            <h3 style="margin-top:1.5rem"><?php esc_html_e( 'Benachrichtigung an Empfänger', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></h3>
                            <div class="rsfm-field-row">
                                <label class="rsfm-field-label" for="rsfm-owner-subject"><?php esc_html_e( 'Betreff', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                <input type="text" id="rsfm-owner-subject" class="large-text" data-bind-submission="owner_subject">
                            </div>
                            <div class="rsfm-field-row">
                                <div class="rsfm-template-editor">
                                    <div class="rsfm-template-tabs" data-rsfm-template-tabs>
                                        <button type="button" class="button button-secondary is-active" data-rsfm-tab="owner" data-rsfm-panel="html"><?php esc_html_e( 'Visuell', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></button>
                                        <button type="button" class="button button-secondary" data-rsfm-tab="owner" data-rsfm-panel="code"><?php esc_html_e( 'Code', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></button>
                                        <button type="button" class="button button-secondary" data-rsfm-tab="owner" data-rsfm-panel="text"><?php esc_html_e( 'Text', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></button>
                                        <button type="button" class="button button-secondary" data-rsfm-reset-template="owner"><?php esc_html_e( 'Reset', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></button>
                                    </div>
                                    <div class="rsfm-template-panel is-active" data-rsfm-tab-panel="owner" data-rsfm-panel="html">
                                        <div class="rsfm-template-placeholders">
                                            <span class="rsfm-placeholder-label"><?php esc_html_e( 'Platzhalter:', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></span>
                                            <?php foreach ( class_exists( '\\Restatify\\Shared\\Mail\\PlaceholderCatalog', false ) ? \Restatify\Shared\Mail\PlaceholderCatalog::formsMail( true ) : [ '{form_title}', '{site_name}', '{date}', '{fields_table}', '{fields_text}' ] as $ph ) : ?>
                                                <button type="button" class="rsfm-placeholder-chip" data-placeholder="<?php echo esc_attr( $ph ); ?>" data-target="rsfm-owner-body"><?php echo esc_html( $ph ); ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                        <textarea id="rsfm-owner-body" class="large-text code" rows="10" data-bind-submission="owner_html_body"></textarea>
                                    </div>
                                    <div class="rsfm-template-panel" data-rsfm-tab-panel="owner" data-rsfm-panel="code" hidden>
                                        <div class="rsfm-template-placeholders">
                                            <span class="rsfm-placeholder-label"><?php esc_html_e( 'Platzhalter:', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></span>
                                            <?php foreach ( class_exists( '\\Restatify\\Shared\\Mail\\PlaceholderCatalog', false ) ? \Restatify\Shared\Mail\PlaceholderCatalog::formsMail( true ) : [ '{form_title}', '{site_name}', '{date}', '{fields_table}', '{fields_text}' ] as $ph ) : ?>
                                                <button type="button" class="rsfm-placeholder-chip" data-placeholder="<?php echo esc_attr( $ph ); ?>" data-target="rsfm-owner-body-code"><?php echo esc_html( $ph ); ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                        <textarea id="rsfm-owner-body-code" class="large-text code" rows="10" data-rs-mail-html-code-for="rsfm-owner-body"></textarea>
                                    </div>
                                    <div class="rsfm-template-panel" data-rsfm-tab-panel="owner" data-rsfm-panel="text" hidden>
                                        <div class="rsfm-template-placeholders">
                                            <span class="rsfm-placeholder-label"><?php esc_html_e( 'Platzhalter:', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></span>
                                            <?php foreach ( class_exists( '\\Restatify\\Shared\\Mail\\PlaceholderCatalog', false ) ? \Restatify\Shared\Mail\PlaceholderCatalog::formsMail( false ) : [ '{form_title}', '{site_name}', '{date}', '{fields_text}' ] as $ph ) : ?>
                                                <button type="button" class="rsfm-placeholder-chip" data-placeholder="<?php echo esc_attr( $ph ); ?>" data-target="rsfm-owner-text-body"><?php echo esc_html( $ph ); ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                        <textarea id="rsfm-owner-text-body" class="large-text code" rows="10" data-bind-submission="owner_text_body"></textarea>
                                    </div>
                                </div>
                                <label class="rsfm-toggle-label" style="margin-top:.5rem">
                                    <input type="checkbox" id="rsfm-owner-html-enabled" data-bind-submission="owner_html_enabled">
                                    <span><?php esc_html_e( 'Als HTML-E-Mail senden', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></span>
                                </label>
                            </div>

                            <h3 style="margin-top:1.5rem"><?php esc_html_e( 'Bestätigungs-E-Mail an Absender', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></h3>
                            <div class="rsfm-field-row">
                                <label class="rsfm-toggle-label">
                                    <input type="checkbox" id="rsfm-confirmation-enabled" data-bind-submission="confirmation_enabled">
                                    <span><?php esc_html_e( 'Bestätigungs-E-Mail aktivieren', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></span>
                                </label>
                                <p class="description"><?php esc_html_e( 'Erfordert ein E-Mail-Feld im Formular.', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></p>
                            </div>
                            <div id="rsfm-confirmation-config">
                                <div class="rsfm-field-row">
                                    <label class="rsfm-field-label" for="rsfm-confirmation-subject"><?php esc_html_e( 'Betreff', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                    <input type="text" id="rsfm-confirmation-subject" class="large-text" data-bind-submission="confirmation_subject">
                                </div>
                                <div class="rsfm-field-row">
                                    <div class="rsfm-template-editor">
                                        <div class="rsfm-template-tabs" data-rsfm-template-tabs>
                                            <button type="button" class="button button-secondary is-active" data-rsfm-tab="confirmation" data-rsfm-panel="html"><?php esc_html_e( 'Visuell', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></button>
                                            <button type="button" class="button button-secondary" data-rsfm-tab="confirmation" data-rsfm-panel="code"><?php esc_html_e( 'Code', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></button>
                                            <button type="button" class="button button-secondary" data-rsfm-tab="confirmation" data-rsfm-panel="text"><?php esc_html_e( 'Text', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></button>
                                            <button type="button" class="button button-secondary" data-rsfm-reset-template="confirmation"><?php esc_html_e( 'Reset', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></button>
                                        </div>
                                        <div class="rsfm-template-panel is-active" data-rsfm-tab-panel="confirmation" data-rsfm-panel="html">
                                            <div class="rsfm-template-placeholders">
                                                <span class="rsfm-placeholder-label"><?php esc_html_e( 'Platzhalter:', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></span>
                                                <?php foreach ( class_exists( '\\Restatify\\Shared\\Mail\\PlaceholderCatalog', false ) ? \Restatify\Shared\Mail\PlaceholderCatalog::formsMail( true ) : [ '{form_title}', '{site_name}', '{date}', '{fields_table}', '{fields_text}' ] as $ph ) : ?>
                                                    <button type="button" class="rsfm-placeholder-chip" data-placeholder="<?php echo esc_attr( $ph ); ?>" data-target="rsfm-confirmation-body"><?php echo esc_html( $ph ); ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                            <textarea id="rsfm-confirmation-body" class="large-text code" rows="10" data-bind-submission="confirmation_html_body"></textarea>
                                        </div>
                                        <div class="rsfm-template-panel" data-rsfm-tab-panel="confirmation" data-rsfm-panel="code" hidden>
                                            <div class="rsfm-template-placeholders">
                                                <span class="rsfm-placeholder-label"><?php esc_html_e( 'Platzhalter:', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></span>
                                                <?php foreach ( class_exists( '\\Restatify\\Shared\\Mail\\PlaceholderCatalog', false ) ? \Restatify\Shared\Mail\PlaceholderCatalog::formsMail( true ) : [ '{form_title}', '{site_name}', '{date}', '{fields_table}', '{fields_text}' ] as $ph ) : ?>
                                                    <button type="button" class="rsfm-placeholder-chip" data-placeholder="<?php echo esc_attr( $ph ); ?>" data-target="rsfm-confirmation-body-code"><?php echo esc_html( $ph ); ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                            <textarea id="rsfm-confirmation-body-code" class="large-text code" rows="10" data-rs-mail-html-code-for="rsfm-confirmation-body"></textarea>
                                        </div>
                                        <div class="rsfm-template-panel" data-rsfm-tab-panel="confirmation" data-rsfm-panel="text" hidden>
                                            <div class="rsfm-template-placeholders">
                                                <span class="rsfm-placeholder-label"><?php esc_html_e( 'Platzhalter:', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></span>
                                                <?php foreach ( class_exists( '\\Restatify\\Shared\\Mail\\PlaceholderCatalog', false ) ? \Restatify\Shared\Mail\PlaceholderCatalog::formsMail( false ) : [ '{form_title}', '{site_name}', '{date}', '{fields_text}' ] as $ph ) : ?>
                                                    <button type="button" class="rsfm-placeholder-chip" data-placeholder="<?php echo esc_attr( $ph ); ?>" data-target="rsfm-confirmation-text-body"><?php echo esc_html( $ph ); ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                            <textarea id="rsfm-confirmation-text-body" class="large-text code" rows="10" data-bind-submission="confirmation_text_body"></textarea>
                                        </div>
                                    </div>
                                    <label class="rsfm-toggle-label" style="margin-top:.5rem">
                                        <input type="checkbox" id="rsfm-confirmation-html-enabled" data-bind-submission="confirmation_html_enabled">
                                        <span><?php esc_html_e( 'Als HTML-E-Mail senden', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Endpoint config -->
                        <div id="rsfm-endpoint-config">
                            <h3><?php esc_html_e( 'Endpoint-Konfiguration', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></h3>

                            <div class="rsfm-field-row">
                                <label class="rsfm-field-label" for="rsfm-endpoint-url"><?php esc_html_e( 'Endpoint-URL', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                <input type="url" id="rsfm-endpoint-url" class="large-text" data-bind-submission="endpoint_url" placeholder="https://...">
                            </div>

                            <div class="rsfm-field-row">
                                <label class="rsfm-field-label"><?php esc_html_e( 'Datenformat', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                <div class="rsfm-radio-group">
                                    <label><input type="radio" name="rsfm-endpoint-format" value="json" data-bind-submission="endpoint_format"> JSON</label>
                                    <label><input type="radio" name="rsfm-endpoint-format" value="form" data-bind-submission="endpoint_format"> Form-Encoded</label>
                                </div>
                            </div>

                            <div class="rsfm-field-row">
                                <label class="rsfm-field-label"><?php esc_html_e( 'Authentifizierung', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                <div class="rsfm-radio-group">
                                    <label><input type="radio" name="rsfm-endpoint-auth" value="none" data-bind-submission="endpoint_auth_type"> <?php esc_html_e( 'Keine', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                    <label><input type="radio" name="rsfm-endpoint-auth" value="bearer" data-bind-submission="endpoint_auth_type"> Bearer Token</label>
                                    <label><input type="radio" name="rsfm-endpoint-auth" value="basic" data-bind-submission="endpoint_auth_type"> Basic Auth (user:pass)</label>
                                </div>
                            </div>

                            <div class="rsfm-field-row" id="rsfm-endpoint-auth-value-row">
                                <label class="rsfm-field-label" for="rsfm-endpoint-auth-value"><?php esc_html_e( 'Auth-Wert', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></label>
                                <input type="password" id="rsfm-endpoint-auth-value" class="large-text" data-bind-submission="endpoint_auth_value" autocomplete="new-password">
                                <p class="description"><?php esc_html_e( 'Bearer: Token eingeben. Basic: "benutzername:passwort"', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- .rsfm-step-panels -->

            <!-- Navigation -->
            <div class="rsfm-editor-nav">
                <button type="button" class="button" id="rsfm-prev-btn" disabled><?php esc_html_e( '← Zurück', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></button>
                <button type="button" class="button" id="rsfm-next-btn"><?php esc_html_e( 'Weiter →', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></button>
                <button type="button" class="button button-primary" id="rsfm-save-btn">
                    <?php esc_html_e( '💾 Speichern', Restatify_Forms_Constants::TEXT_DOMAIN ); ?>
                </button>
            </div>

        </div><!-- .rsfm-editor -->

        <!-- Field type picker modal -->
        <div class="rsfm-modal" id="rsfm-field-type-modal" hidden role="dialog" aria-modal="true" aria-labelledby="rsfm-modal-title">
            <div class="rsfm-modal__backdrop" data-rsfm-modal-close></div>
            <div class="rsfm-modal__box">
                <h3 class="rsfm-modal__title" id="rsfm-modal-title"><?php esc_html_e( 'Feldtyp auswählen', Restatify_Forms_Constants::TEXT_DOMAIN ); ?></h3>
                <div class="rsfm-field-type-grid" id="rsfm-field-type-grid">
                    <?php
                    $type_icons = [
                        'text'     => '✏️',
                        'email'    => '📧',
                        'tel'      => '📞',
                        'textarea' => '📝',
                        'select'   => '🔽',
                        'checkbox' => '☑️',
                        'radio'    => '🔘',
                        'date'     => '📅',
                        'hidden'   => '🔒',
                    ];
                    $type_labels = [
                        'text'     => __( 'Einzeiliger Text', Restatify_Forms_Constants::TEXT_DOMAIN ),
                        'email'    => __( 'E-Mail', Restatify_Forms_Constants::TEXT_DOMAIN ),
                        'tel'      => __( 'Telefon', Restatify_Forms_Constants::TEXT_DOMAIN ),
                        'textarea' => __( 'Mehrzeiliger Text', Restatify_Forms_Constants::TEXT_DOMAIN ),
                        'select'   => __( 'Dropdown', Restatify_Forms_Constants::TEXT_DOMAIN ),
                        'checkbox' => __( 'Checkbox', Restatify_Forms_Constants::TEXT_DOMAIN ),
                        'radio'    => __( 'Radio-Buttons', Restatify_Forms_Constants::TEXT_DOMAIN ),
                        'date'     => __( 'Datum', Restatify_Forms_Constants::TEXT_DOMAIN ),
                        'hidden'   => __( 'Versteckt', Restatify_Forms_Constants::TEXT_DOMAIN ),
                    ];
                    foreach ( Restatify_Forms_Constants::FIELD_TYPES as $type_key ) :
                        ?>
                        <button type="button" class="rsfm-field-type-card" data-field-type="<?php echo esc_attr( $type_key ); ?>">
                            <span class="rsfm-field-type-icon"><?php echo $type_icons[ $type_key ] ?? ''; ?></span>
                            <span class="rsfm-field-type-label"><?php echo esc_html( $type_labels[ $type_key ] ); ?></span>
                        </button>
                        <?php
                    endforeach;
                    ?>
                </div>
                <button type="button" class="rsfm-modal__close" data-rsfm-modal-close aria-label="<?php esc_attr_e( 'Schließen', Restatify_Forms_Constants::TEXT_DOMAIN ); ?>">&#215;</button>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX: Save form
    // -------------------------------------------------------------------------

    public function ajax_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', Restatify_Forms_Constants::TEXT_DOMAIN ) ], 403 );
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, Restatify_Forms_Constants::NONCE_ADMIN ) ) {
            wp_send_json_error( [ 'message' => __( 'Ungültige Sicherheitsüberprüfung.', Restatify_Forms_Constants::TEXT_DOMAIN ) ], 403 );
        }

        $raw_json  = wp_unslash( $_POST['form_data'] ?? '' );
        $form_data = json_decode( $raw_json, true );

        if ( ! is_array( $form_data ) ) {
            wp_send_json_error( [ 'message' => __( 'Ungültige Formulardaten.', Restatify_Forms_Constants::TEXT_DOMAIN ) ], 400 );
        }

        if ( ! $this->validate_form_data( $form_data ) ) {
            wp_send_json_error(
                [ 'message' => __( 'Für die Absender-Bestätigung muss mindestens ein E-Mail-Feld als Pflichtfeld vorhanden sein.', Restatify_Forms_Constants::TEXT_DOMAIN ) ],
                400
            );
        }

        if ( $this->options->save_form( $form_data ) ) {
            wp_send_json_success( [
                'message' => __( 'Formular gespeichert.', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'form_id' => sanitize_key( (string) ( $form_data['id'] ?? '' ) ),
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Fehler beim Speichern.', Restatify_Forms_Constants::TEXT_DOMAIN ) ], 500 );
        }
    }

    // -------------------------------------------------------------------------
    // AJAX: Delete form
    // -------------------------------------------------------------------------

    public function ajax_delete(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', Restatify_Forms_Constants::TEXT_DOMAIN ) ], 403 );
        }

        $form_id = sanitize_key( (string) ( $_POST['form_id'] ?? '' ) );
        $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, 'rsfm_delete_' . $form_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Ungültige Sicherheitsüberprüfung.', Restatify_Forms_Constants::TEXT_DOMAIN ) ], 403 );
        }

        if ( $this->options->delete_form( $form_id ) ) {
            wp_send_json_success( [ 'message' => __( 'Formular gelöscht.', Restatify_Forms_Constants::TEXT_DOMAIN ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Fehler beim Löschen.', Restatify_Forms_Constants::TEXT_DOMAIN ) ], 500 );
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string,mixed>
     */
    private function get_admin_strings(): array {
        return [
            'fieldTypeLabels' => [
                'text'     => __( 'Einzeiliger Text', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'email'    => __( 'E-Mail', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'tel'      => __( 'Telefon', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'textarea' => __( 'Mehrzeiliger Text', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'select'   => __( 'Dropdown', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'checkbox' => __( 'Checkbox', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'radio'    => __( 'Radio-Buttons', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'date'     => __( 'Datum', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'hidden'   => __( 'Versteckt', Restatify_Forms_Constants::TEXT_DOMAIN ),
            ],
            'emailValidationLabels' => [
                'none'   => __( 'Keine Prüfung', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'simple' => __( 'Einfach (hat @)', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'regex'  => __( 'Standard-Regex', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'dns'    => __( 'DNS-Prüfung (Domain muss existieren)', Restatify_Forms_Constants::TEXT_DOMAIN ),
            ],
            'telValidationLabels' => [
                'none'   => __( 'Keine Prüfung', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'simple' => __( 'Einfaches Format', Restatify_Forms_Constants::TEXT_DOMAIN ),
                'e164'   => __( 'E.164 (international, z.B. +49…)', Restatify_Forms_Constants::TEXT_DOMAIN ),
            ],
            'recipientTypeLabels' => [
                'to'  => 'TO',
                'cc'  => 'CC',
                'bcc' => 'BCC',
            ],
            'confirmDelete'   => __( 'Dieses Formular wirklich löschen?', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'confirmRemove'   => __( 'Dieses Feld wirklich entfernen?', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'addOption'       => __( '+ Option hinzufügen', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'removeOption'    => __( 'Option entfernen', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'moveUp'          => __( '↑', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'moveDown'        => __( '↓', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'edit'            => __( 'Bearbeiten', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'remove'          => __( 'Entfernen', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'collapse'        => __( 'Schließen', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'required'        => __( 'Pflichtfeld', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'label'           => __( 'Label', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'placeholder'     => __( 'Platzhalter', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'defaultValue'    => __( 'Standardwert', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'validationMode'  => __( 'Validierungsmodus', Restatify_Forms_Constants::TEXT_DOMAIN ),
            'selectOptions'   => __( 'Auswahloptionen', Restatify_Forms_Constants::TEXT_DOMAIN ),
        ];
    }

    /**
     * @param array<string,mixed> $form_data
     */
    private function validate_form_data( array $form_data ): bool {
        $submission = is_array( $form_data['submission'] ?? null ) ? $form_data['submission'] : [];
        $needs_confirmation = ! empty( $submission['confirmation_enabled'] );

        if ( ! $needs_confirmation ) {
            return true;
        }

        $fields = is_array( $form_data['fields'] ?? null ) ? $form_data['fields'] : [];
        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }

            if ( ( $field['type'] ?? '' ) === 'email' && ! empty( $field['required'] ) ) {
                return true;
            }
        }

        return false;
    }
}
