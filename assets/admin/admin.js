/* jshint esversion: 11 */
(function () {
    'use strict';

    var cfg      = window.rsfmAdmin || {};
    var strings  = cfg.strings  || {};
    var ajaxUrl  = cfg.ajaxUrl  || '';
    var nonce    = cfg.nonce    || '';
    var listUrl  = cfg.listUrl  || '';
    var isNew    = !!cfg.isNew;

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------

    var state = {
        currentStep : 0,
        form        : JSON.parse(JSON.stringify(cfg.form || cfg.formDefaults || {})),
        dirty       : false,
    };

    // Ensure nested objects exist
    state.form.security   = state.form.security   || {};
    state.form.submission = state.form.submission || {};
    state.form.fields     = state.form.fields     || [];

    // -------------------------------------------------------------------------
    // DOM references
    // -------------------------------------------------------------------------

    var stepperItems  = document.querySelectorAll('.rsfm-stepper__item');
    var stepPanels    = document.querySelectorAll('.rsfm-step-panel');
    var prevBtn       = document.getElementById('rsfm-prev-btn');
    var nextBtn       = document.getElementById('rsfm-next-btn');
    var saveBtn       = document.getElementById('rsfm-save-btn');
    var successNotice = document.getElementById('rsfm-save-success');
    var errorNotice   = document.getElementById('rsfm-save-error');
    var errorNoticeText = errorNotice ? errorNotice.querySelector('p') : null;

    // Step 1 bindings
    var titleInput   = document.getElementById('rsfm-field-title');
    var idInput      = document.getElementById('rsfm-field-id');
    var triggerInput = document.getElementById('rsfm-field-trigger');
    var subtitleInput = document.getElementById('rsfm-field-subtitle');
    var textInput    = document.getElementById('rsfm-field-text');

    // Step 2
    var fieldList    = document.getElementById('rsfm-field-list');
    var addFieldBtn  = document.getElementById('rsfm-add-field-btn');

    // Step 3
    var honeypotCb        = document.getElementById('rsfm-security-honeypot');
    var captchaRadios     = document.querySelectorAll('[name="rsfm-captcha-provider"]');
    var recaptchaConfig   = document.getElementById('rsfm-recaptcha-config');
    var turnstileConfig   = document.getElementById('rsfm-turnstile-config');
    var recaptchaSiteKey  = document.getElementById('rsfm-recaptcha-site-key');
    var recaptchaSecretKey= document.getElementById('rsfm-recaptcha-secret-key');
    var turnstileSiteKey  = document.getElementById('rsfm-turnstile-site-key');
    var turnstileSecretKey= document.getElementById('rsfm-turnstile-secret-key');
    var privacyPolicyUrlInput = document.getElementById('rsfm-privacy-policy-url');

    // Step 4 – Submission mode
    var submissionRadios = document.querySelectorAll('[name="rsfm-submission-mode"]');
    var mailConfig       = document.getElementById('rsfm-mail-config');
    var endpointConfig   = document.getElementById('rsfm-endpoint-config');
    var recipientsList   = document.getElementById('rsfm-recipients-list');
    var addRecipientBtn  = document.getElementById('rsfm-add-recipient-btn');

    // Step 4 – Mail templates
    var ownerSubjectInput      = document.getElementById('rsfm-owner-subject');
    var ownerBodyTextarea      = document.getElementById('rsfm-owner-body');
    var ownerBodyCodeTextarea  = document.getElementById('rsfm-owner-body-code');
    var ownerTextBodyTextarea  = document.getElementById('rsfm-owner-text-body');
    var ownerHtmlCb            = document.getElementById('rsfm-owner-html-enabled');
    var confirmationEnabledCb  = document.getElementById('rsfm-confirmation-enabled');
    var confirmationConfig     = document.getElementById('rsfm-confirmation-config');
    var confirmSubjectInput    = document.getElementById('rsfm-confirmation-subject');
    var confirmBodyTextarea    = document.getElementById('rsfm-confirmation-body');
    var confirmBodyCodeTextarea= document.getElementById('rsfm-confirmation-body-code');
    var confirmTextBodyTextarea= document.getElementById('rsfm-confirmation-text-body');
    var confirmHtmlCb          = document.getElementById('rsfm-confirmation-html-enabled');
    var templateResetButtons   = document.querySelectorAll('[data-rsfm-reset-template]');

    // Step 4 – Endpoint
    var endpointUrlInput      = document.getElementById('rsfm-endpoint-url');
    var endpointFormatRadios  = document.querySelectorAll('[name="rsfm-endpoint-format"]');
    var endpointAuthRadios    = document.querySelectorAll('[name="rsfm-endpoint-auth"]');
    var endpointAuthValueRow  = document.getElementById('rsfm-endpoint-auth-value-row');
    var endpointAuthValueInput= document.getElementById('rsfm-endpoint-auth-value');

    // Field type modal
    var fieldTypeModal = document.getElementById('rsfm-field-type-modal');
    var fieldTypeCards = document.querySelectorAll('.rsfm-field-type-card');

    // Mail template editors (WYSIWYG)
    var mailEditorState = {
        owner_html_body: { initialized: false, bindingAttached: false },
        confirmation_html_body: { initialized: false, bindingAttached: false },
    };

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------

    function init() {
        populateFromState();
        bindStaticEvents();
        initMailTemplateEditors();
        initSharedMailEditorHelpers();
        renderFieldList();
        renderRecipientsList();
        updateStepUI();
    }

    function initSharedMailEditorHelpers() {
        var shared = window.RestatifySharedMailEditor;
        if (!shared) {
            return;
        }

        if (typeof shared.initTabSystem === 'function') {
            shared.initTabSystem({
                tabSelector: '[data-rsfm-tab]',
                panelSelector: '[data-rsfm-tab-panel]',
                tabGroupAttr: 'data-rsfm-tab',
                panelGroupAttr: 'data-rsfm-tab-panel',
                panelAttr: 'data-rsfm-panel',
                activeClass: 'is-active',
                onSwitch: function (group, panel) {
                    if (panel === 'code') {
                        if (group === 'owner' && typeof shared.syncCodeFromEditor === 'function') {
                            shared.syncCodeFromEditor('rsfm-owner-body', '[data-rs-mail-html-code-for]', 'data-rs-mail-html-code-for');
                        }
                        if (group === 'confirmation' && typeof shared.syncCodeFromEditor === 'function') {
                            shared.syncCodeFromEditor('rsfm-confirmation-body', '[data-rs-mail-html-code-for]', 'data-rs-mail-html-code-for');
                        }
                    }
                }
            });
        }

        if (typeof shared.bindHtmlCodeSync === 'function') {
            shared.bindHtmlCodeSync({
                codeSelector: '[data-rs-mail-html-code-for]',
                codeForAttr: 'data-rs-mail-html-code-for'
            });
        }

        if (typeof shared.initPlaceholderButtons === 'function') {
            shared.initPlaceholderButtons({
                buttonSelector: '.rsfm-placeholder-chip',
                placeholderAttr: 'data-placeholder',
                targetAttr: 'data-target',
                onAfterEditorInsert: function (editorId) {
                    if (typeof shared.syncCodeFromEditor === 'function') {
                        shared.syncCodeFromEditor(editorId, '[data-rs-mail-html-code-for]', 'data-rs-mail-html-code-for');
                    }
                }
            });
        }
    }

    function canUseWpEditor() {
        return !!(window.wp && window.wp.editor && typeof window.wp.editor.initialize === 'function');
    }

    function getTinyMceEditor(editorId) {
        if (!window.tinymce || typeof window.tinymce.get !== 'function') {
            return null;
        }
        return window.tinymce.get(editorId);
    }

    function setMailEditorValue(textarea, value) {
        if (!textarea) { return; }
        textarea.value = value || '';

        var editor = getTinyMceEditor(textarea.id);
        if (editor && typeof editor.setContent === 'function') {
            editor.setContent(value || '');
        }
    }

    function setHtmlTemplateValue(htmlTextarea, codeTextarea, value) {
        setMailEditorValue(htmlTextarea, value || '');
        if (codeTextarea) {
            codeTextarea.value = value || '';
        }
    }

    function syncHtmlTemplateState(submissionKey, htmlTextarea, codeTextarea, value) {
        state.form.submission[submissionKey] = value || '';
        if (htmlTextarea && htmlTextarea.value !== (value || '')) {
            htmlTextarea.value = value || '';
        }
        if (codeTextarea && codeTextarea.value !== (value || '')) {
            codeTextarea.value = value || '';
        }
        state.dirty = true;
    }

    function initMailEditor(textarea, submissionKey) {
        if (!(textarea instanceof HTMLTextAreaElement)) { return; }
        if (!canUseWpEditor()) { return; }

        var slot = mailEditorState[submissionKey];
        if (!slot || slot.initialized) {
            return;
        }

        window.wp.editor.initialize(textarea.id, {
            tinymce: {
                wpautop: true,
                toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo,removeformat',
                toolbar2: '',
            },
            quicktags: false,
            mediaButtons: false,
        });

        slot.initialized = true;
        bindMailEditorSync(textarea, submissionKey);
    }

    function bindMailEditorSync(textarea, submissionKey) {
        var slot = mailEditorState[submissionKey];
        if (!slot || slot.bindingAttached || !(textarea instanceof HTMLTextAreaElement)) {
            return;
        }

        var codeMirrorTextarea = null;
        if (submissionKey === 'owner_html_body') {
            codeMirrorTextarea = ownerBodyCodeTextarea;
        }
        if (submissionKey === 'confirmation_html_body') {
            codeMirrorTextarea = confirmBodyCodeTextarea;
        }

        var syncTextarea = function () {
            state.form.submission[submissionKey] = textarea.value || '';
            if (codeMirrorTextarea) {
                codeMirrorTextarea.value = textarea.value || '';
            }
            state.dirty = true;
        };

        textarea.addEventListener('input', syncTextarea);

        var attachEditorBinding = function () {
            var editor = getTinyMceEditor(textarea.id);
            if (!editor) {
                window.requestAnimationFrame(attachEditorBinding);
                return;
            }

            editor.on('change keyup SetContent', function () {
                state.form.submission[submissionKey] = editor.getContent();
                if (codeMirrorTextarea) {
                    codeMirrorTextarea.value = editor.getContent();
                }
                state.dirty = true;
            });
        };

        window.requestAnimationFrame(attachEditorBinding);
        slot.bindingAttached = true;
    }

    function initMailTemplateEditors() {
        initMailEditor(ownerBodyTextarea, 'owner_html_body');
        initMailEditor(confirmBodyTextarea, 'confirmation_html_body');
    }

    function resetMailTemplate(group) {
        var defaults = (cfg.formDefaults && cfg.formDefaults.submission) ? cfg.formDefaults.submission : null;
        if (!defaults) {
            return;
        }

        if (group === 'owner') {
            state.form.submission.owner_subject = defaults.owner_subject || '';
            state.form.submission.owner_html_body = defaults.owner_html_body || '';
            state.form.submission.owner_text_body = defaults.owner_text_body || '';

            if (ownerSubjectInput) {
                ownerSubjectInput.value = state.form.submission.owner_subject;
            }
            if (ownerBodyTextarea) {
                setHtmlTemplateValue(ownerBodyTextarea, ownerBodyCodeTextarea, state.form.submission.owner_html_body);
            }
            if (ownerTextBodyTextarea) {
                ownerTextBodyTextarea.value = state.form.submission.owner_text_body;
            }
            state.dirty = true;
            return;
        }

        if (group === 'confirmation') {
            state.form.submission.confirmation_subject = defaults.confirmation_subject || '';
            state.form.submission.confirmation_html_body = defaults.confirmation_html_body || '';
            state.form.submission.confirmation_text_body = defaults.confirmation_text_body || '';

            if (confirmSubjectInput) {
                confirmSubjectInput.value = state.form.submission.confirmation_subject;
            }
            if (confirmBodyTextarea) {
                setHtmlTemplateValue(confirmBodyTextarea, confirmBodyCodeTextarea, state.form.submission.confirmation_html_body);
            }
            if (confirmTextBodyTextarea) {
                confirmTextBodyTextarea.value = state.form.submission.confirmation_text_body;
            }
            state.dirty = true;
        }
    }

    // -------------------------------------------------------------------------
    // Populate UI from state
    // -------------------------------------------------------------------------

    function populateFromState() {
        var f  = state.form;
        var sec = f.security;
        var sub = f.submission;

        // Step 1
        if (titleInput)    titleInput.value   = f.title    || '';
        if (idInput)       idInput.value      = f.id       || '';
        if (triggerInput)  triggerInput.value = f.trigger  || computeTrigger(f.id || '');
        if (subtitleInput) subtitleInput.value= f.subtitle || '';
        if (textInput)     textInput.value    = f.text     || '';

        // Step 3
        if (honeypotCb) honeypotCb.checked = !!sec.honeypot;
        captchaRadios.forEach(function (r) {
            r.checked = (r.value === (sec.captcha_provider || 'none'));
        });
        if (recaptchaSiteKey)   recaptchaSiteKey.value   = sec.recaptcha_site_key   || '';
        if (recaptchaSecretKey) recaptchaSecretKey.value = sec.recaptcha_secret_key || '';
        if (turnstileSiteKey)   turnstileSiteKey.value   = sec.turnstile_site_key   || '';
        if (turnstileSecretKey) turnstileSecretKey.value = sec.turnstile_secret_key || '';
        if (privacyPolicyUrlInput) privacyPolicyUrlInput.value = sec.privacy_policy_url || '';
        updateCaptchaConfigVisibility();

        // Step 4 – mode
        submissionRadios.forEach(function (r) {
            r.checked = (r.value === (sub.mode || 'mail'));
        });
        updateSubmissionModeVisibility();

        // Mail templates
        if (ownerSubjectInput)   ownerSubjectInput.value   = sub.owner_subject           || '';
        if (ownerBodyTextarea)   setHtmlTemplateValue(ownerBodyTextarea, ownerBodyCodeTextarea, sub.owner_html_body || '');
        if (ownerTextBodyTextarea) ownerTextBodyTextarea.value = sub.owner_text_body || '';
        if (ownerHtmlCb)         ownerHtmlCb.checked       = !!sub.owner_html_enabled;
        if (confirmationEnabledCb) confirmationEnabledCb.checked = !!sub.confirmation_enabled;
        if (confirmSubjectInput) confirmSubjectInput.value = sub.confirmation_subject     || '';
        if (confirmBodyTextarea) setHtmlTemplateValue(confirmBodyTextarea, confirmBodyCodeTextarea, sub.confirmation_html_body || '');
        if (confirmTextBodyTextarea) confirmTextBodyTextarea.value = sub.confirmation_text_body || '';
        if (confirmHtmlCb)       confirmHtmlCb.checked     = !!sub.confirmation_html_enabled;
        updateConfirmationConfigVisibility();

        // Endpoint
        if (endpointUrlInput)   endpointUrlInput.value   = sub.endpoint_url         || '';
        endpointFormatRadios.forEach(function (r) {
            r.checked = (r.value === (sub.endpoint_format || 'json'));
        });
        endpointAuthRadios.forEach(function (r) {
            r.checked = (r.value === (sub.endpoint_auth_type || 'none'));
        });
        if (endpointAuthValueInput) endpointAuthValueInput.value = sub.endpoint_auth_value || '';
        updateEndpointAuthVisibility();
    }

    // -------------------------------------------------------------------------
    // Bind static events
    // -------------------------------------------------------------------------

    function bindStaticEvents() {
        // Step navigation
        stepperItems.forEach(function (item) {
            item.addEventListener('click', function () {
                collectCurrentStepData();
                goToStep(parseInt(item.dataset.step, 10));
            });
        });

        if (prevBtn) prevBtn.addEventListener('click', function () {
            collectCurrentStepData();
            goToStep(state.currentStep - 1);
        });
        if (nextBtn) nextBtn.addEventListener('click', function () {
            collectCurrentStepData();
            goToStep(state.currentStep + 1);
        });
        if (saveBtn) saveBtn.addEventListener('click', saveForm);

        // Step 1 – auto-generate ID and trigger from title
        if (titleInput) {
            titleInput.addEventListener('input', function () {
                state.form.title = titleInput.value;
                if (isNew || !idInput.value) {
                    var slug = slugify(titleInput.value);
                    idInput.value      = slug;
                    state.form.id      = slug;
                    var trigger        = computeTrigger(slug);
                    triggerInput.value = trigger;
                    state.form.trigger = trigger;
                }
                state.dirty = true;
            });
        }

        if (idInput) {
            idInput.addEventListener('input', function () {
                var slug = slugify(idInput.value);
                idInput.value      = slug;
                state.form.id      = slug;
                var trigger        = computeTrigger(slug);
                triggerInput.value = trigger;
                state.form.trigger = trigger;
                state.dirty = true;
            });
        }

        if (subtitleInput) subtitleInput.addEventListener('input', function () {
            state.form.subtitle = subtitleInput.value;
            state.dirty = true;
        });
        if (textInput) textInput.addEventListener('input', function () {
            state.form.text = textInput.value;
            state.dirty = true;
        });

        // Step 2 – field builder
        if (addFieldBtn) addFieldBtn.addEventListener('click', openFieldTypeModal);

        fieldTypeCards.forEach(function (card) {
            card.addEventListener('click', function () {
                addField(card.dataset.fieldType);
                closeFieldTypeModal();
            });
        });

        if (fieldTypeModal) {
            document.querySelectorAll('[data-rsfm-modal-close]').forEach(function (el) {
                el.addEventListener('click', closeFieldTypeModal);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && fieldTypeModal && !fieldTypeModal.hidden) {
                    closeFieldTypeModal();
                }
            });
        }

        // Step 3
        if (honeypotCb) honeypotCb.addEventListener('change', function () {
            state.form.security.honeypot = honeypotCb.checked;
            state.dirty = true;
        });
        captchaRadios.forEach(function (r) {
            r.addEventListener('change', function () {
                state.form.security.captcha_provider = r.value;
                updateCaptchaConfigVisibility();
                state.dirty = true;
            });
        });
        if (recaptchaSiteKey)    recaptchaSiteKey.addEventListener('input',    function () { state.form.security.recaptcha_site_key = recaptchaSiteKey.value; });
        if (recaptchaSecretKey)  recaptchaSecretKey.addEventListener('input',  function () { state.form.security.recaptcha_secret_key = recaptchaSecretKey.value; });
        if (turnstileSiteKey)    turnstileSiteKey.addEventListener('input',    function () { state.form.security.turnstile_site_key = turnstileSiteKey.value; });
        if (turnstileSecretKey)  turnstileSecretKey.addEventListener('input',  function () { state.form.security.turnstile_secret_key = turnstileSecretKey.value; });
        if (privacyPolicyUrlInput) privacyPolicyUrlInput.addEventListener('input', function () {
            state.form.security.privacy_policy_url = privacyPolicyUrlInput.value;
            state.dirty = true;
        });

        // Step 4 – mode
        submissionRadios.forEach(function (r) {
            r.addEventListener('change', function () {
                state.form.submission.mode = r.value;
                updateSubmissionModeVisibility();
                state.dirty = true;
            });
        });

        // Mail
        if (ownerSubjectInput)     ownerSubjectInput.addEventListener('input',     function () { state.form.submission.owner_subject = ownerSubjectInput.value; });
        if (ownerBodyTextarea)     bindMailEditorSync(ownerBodyTextarea, 'owner_html_body');
        if (ownerBodyCodeTextarea) ownerBodyCodeTextarea.addEventListener('input', function () {
            syncHtmlTemplateState('owner_html_body', ownerBodyTextarea, ownerBodyCodeTextarea, ownerBodyCodeTextarea.value);
        });
        if (ownerTextBodyTextarea) ownerTextBodyTextarea.addEventListener('input', function () {
            state.form.submission.owner_text_body = ownerTextBodyTextarea.value;
            state.dirty = true;
        });
        if (ownerHtmlCb)           ownerHtmlCb.addEventListener('change',          function () { state.form.submission.owner_html_enabled = ownerHtmlCb.checked; });
        if (confirmationEnabledCb) confirmationEnabledCb.addEventListener('change',function () {
            state.form.submission.confirmation_enabled = confirmationEnabledCb.checked;
            updateConfirmationConfigVisibility();
        });
        if (confirmSubjectInput)   confirmSubjectInput.addEventListener('input',   function () { state.form.submission.confirmation_subject = confirmSubjectInput.value; });
        if (confirmBodyTextarea)   bindMailEditorSync(confirmBodyTextarea, 'confirmation_html_body');
        if (confirmBodyCodeTextarea) confirmBodyCodeTextarea.addEventListener('input', function () {
            syncHtmlTemplateState('confirmation_html_body', confirmBodyTextarea, confirmBodyCodeTextarea, confirmBodyCodeTextarea.value);
        });
        if (confirmTextBodyTextarea) confirmTextBodyTextarea.addEventListener('input', function () {
            state.form.submission.confirmation_text_body = confirmTextBodyTextarea.value;
            state.dirty = true;
        });
        if (confirmHtmlCb)         confirmHtmlCb.addEventListener('change',        function () { state.form.submission.confirmation_html_enabled = confirmHtmlCb.checked; });

        templateResetButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var group = btn.getAttribute('data-rsfm-reset-template') || '';
                resetMailTemplate(group);
            });
        });

        // Recipients
        if (addRecipientBtn) addRecipientBtn.addEventListener('click', function () {
            state.form.submission.recipients = state.form.submission.recipients || [];
            state.form.submission.recipients.push({ email: '', type: 'to' });
            renderRecipientsList();
            state.dirty = true;
        });

        // Endpoint
        if (endpointUrlInput) endpointUrlInput.addEventListener('input', function () {
            state.form.submission.endpoint_url = endpointUrlInput.value;
        });
        endpointFormatRadios.forEach(function (r) {
            r.addEventListener('change', function () { state.form.submission.endpoint_format = r.value; });
        });
        endpointAuthRadios.forEach(function (r) {
            r.addEventListener('change', function () {
                state.form.submission.endpoint_auth_type = r.value;
                updateEndpointAuthVisibility();
            });
        });
        if (endpointAuthValueInput) endpointAuthValueInput.addEventListener('input', function () {
            state.form.submission.endpoint_auth_value = endpointAuthValueInput.value;
        });

        // Delete buttons on list page
        document.querySelectorAll('.rsfm-delete-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm(strings.confirmDelete || 'Wirklich löschen?')) { return; }
                var fid   = btn.dataset.formId;
                var dnonce= btn.dataset.nonce;
                var data  = new FormData();
                data.append('action', 'restatify_forms_delete');
                data.append('nonce', dnonce);
                data.append('form_id', fid);
                fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (json) {
                        if (json.success) { window.location.reload(); }
                        else { alert((json.data && json.data.message) || 'Fehler.'); }
                    });
            });
        });
    }

    // -------------------------------------------------------------------------
    // Step navigation
    // -------------------------------------------------------------------------

    function goToStep(step) {
        var max = stepPanels.length - 1;
        step = Math.max(0, Math.min(max, step));
        state.currentStep = step;
        updateStepUI();
    }

    function updateStepUI() {
        var s = state.currentStep;

        stepperItems.forEach(function (item, i) {
            item.classList.toggle('is-active', i === s);
            item.setAttribute('aria-selected', i === s ? 'true' : 'false');
        });

        stepPanels.forEach(function (panel, i) {
            panel.hidden = (i !== s);
        });

        if (prevBtn) prevBtn.disabled = (s === 0);
        if (nextBtn) nextBtn.style.display = (s === stepPanels.length - 1) ? 'none' : '';
    }

    // -------------------------------------------------------------------------
    // Collect current step data into state
    // -------------------------------------------------------------------------

    function collectCurrentStepData() {
        // Most data is already collected via input event listeners.
        // Fields are managed separately via renderFieldList.
        // This is a final sync pass.
        if (state.currentStep === 0) {
            if (titleInput)   state.form.title    = titleInput.value;
            if (idInput)      state.form.id       = idInput.value;
            if (triggerInput) state.form.trigger  = triggerInput.value;
            if (subtitleInput)state.form.subtitle = subtitleInput.value;
            if (textInput)    state.form.text     = textInput.value;
        }
    }

    // -------------------------------------------------------------------------
    // Visibility toggles
    // -------------------------------------------------------------------------

    function updateCaptchaConfigVisibility() {
        var provider = state.form.security.captcha_provider || 'none';
        if (recaptchaConfig) recaptchaConfig.style.display = (provider === 'recaptcha') ? 'block' : 'none';
        if (turnstileConfig) turnstileConfig.style.display = (provider === 'turnstile') ? 'block' : 'none';
    }

    function updateSubmissionModeVisibility() {
        var mode = state.form.submission.mode || 'mail';
        if (mailConfig)     mailConfig.style.display     = (mode === 'mail')     ? 'block' : 'none';
        if (endpointConfig) endpointConfig.style.display = (mode === 'endpoint') ? 'block' : 'none';
    }

    function updateConfirmationConfigVisibility() {
        if (confirmationConfig) {
            confirmationConfig.style.display = (!!state.form.submission.confirmation_enabled) ? 'block' : 'none';
        }
    }

    function updateEndpointAuthVisibility() {
        var auth = state.form.submission.endpoint_auth_type || 'none';
        if (endpointAuthValueRow) {
            endpointAuthValueRow.style.display = (auth !== 'none') ? 'block' : 'none';
        }
    }

    // -------------------------------------------------------------------------
    // Field builder
    // -------------------------------------------------------------------------

    function openFieldTypeModal() {
        if (fieldTypeModal) fieldTypeModal.hidden = false;
    }

    function closeFieldTypeModal() {
        if (fieldTypeModal) fieldTypeModal.hidden = true;
    }

    function addField(type) {
        var id = generateFieldId(type);
        var newField = {
            id            : id,
            type          : type,
            label         : defaultLabel(type),
            placeholder   : '',
            required      : false,
            options       : (type === 'select' || type === 'radio') ? ['Option 1', 'Option 2'] : [],
            default_value : '',
            validation    : defaultValidation(type),
        };
        state.form.fields.push(newField);
        state.dirty = true;
        renderFieldList();
        // Scroll to and open the new field editor
        setTimeout(function () {
            var items = fieldList.querySelectorAll('.rsfm-field-item');
            var last  = items[items.length - 1];
            if (last) {
                last.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                var editor = last.querySelector('.rsfm-field-item__editor');
                if (editor) { editor.hidden = false; }
            }
        }, 50);
    }

    function generateFieldId(type) {
        var base  = type;
        var count = state.form.fields.filter(function (f) { return f.type === type; }).length + 1;
        var id    = base + '_' + count;
        while (state.form.fields.some(function (f) { return f.id === id; })) {
            count++;
            id = base + '_' + count;
        }
        return id;
    }

    function defaultLabel(type) {
        return (strings.fieldTypeLabels && strings.fieldTypeLabels[type]) || type;
    }

    function defaultValidation(type) {
        if (type === 'email') { return { email_check: 'regex' }; }
        if (type === 'tel')   { return { tel_check: 'simple' }; }
        return {};
    }

    function removeField(index) {
        state.form.fields.splice(index, 1);
        state.dirty = true;
        renderFieldList();
    }

    function moveField(from, to) {
        var fields = state.form.fields;
        if (to < 0 || to >= fields.length) { return; }
        var item = fields.splice(from, 1)[0];
        fields.splice(to, 0, item);
        state.dirty = true;
        renderFieldList();
    }

    // -------------------------------------------------------------------------
    // Field list rendering
    // -------------------------------------------------------------------------

    function renderFieldList() {
        if (!fieldList) { return; }
        fieldList.innerHTML = '';

        if (state.form.fields.length === 0) {
            return; // CSS ::before will show empty message
        }

        state.form.fields.forEach(function (field, index) {
            var item = buildFieldItem(field, index);
            fieldList.appendChild(item);
        });
    }

    function buildFieldItem(field, index) {
        var div = document.createElement('div');
        div.className = 'rsfm-field-item';
        div.setAttribute('draggable', 'false');
        div.dataset.fieldIndex = index;

        // ── Header row
        var header = document.createElement('div');
        header.className = 'rsfm-field-item__header';

        // Drag handle
        var drag = document.createElement('span');
        drag.className = 'rsfm-field-item__drag';
        drag.setAttribute('aria-hidden', 'true');
        drag.setAttribute('draggable', 'true');
        drag.innerHTML = '&#8942;&#8942;';
        drag.title = 'Feld verschieben (Drag & Drop)';

        // Type badge
        var badge = document.createElement('span');
        badge.className = 'rsfm-field-item__type-badge';
        badge.textContent = (strings.fieldTypeLabels && strings.fieldTypeLabels[field.type]) || field.type;

        // Label
        var labelEl = document.createElement('span');
        labelEl.className = 'rsfm-field-item__label';
        labelEl.textContent = field.label || '(' + field.id + ')';

        // Required mark
        var reqMark = document.createElement('span');
        reqMark.className = 'rsfm-field-item__required';
        reqMark.textContent = field.required ? '* ' + (strings.required || 'Pflichtfeld') : '';

        // Actions
        var actions = document.createElement('div');
        actions.className = 'rsfm-field-item__actions';

        var upBtn = document.createElement('button');
        upBtn.type = 'button';
        upBtn.className = 'button button-small';
        upBtn.title = strings.moveUp || '↑';
        upBtn.textContent = '↑';
        upBtn.disabled = (index === 0);
        upBtn.addEventListener('click', function () { moveField(index, index - 1); });

        var downBtn = document.createElement('button');
        downBtn.type = 'button';
        downBtn.className = 'button button-small';
        downBtn.title = strings.moveDown || '↓';
        downBtn.textContent = '↓';
        downBtn.disabled = (index === state.form.fields.length - 1);
        downBtn.addEventListener('click', function () { moveField(index, index + 1); });

        var editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'button button-small';
        editBtn.textContent = strings.edit || 'Bearbeiten';
        editBtn.addEventListener('click', function () {
            var editor = div.querySelector('.rsfm-field-item__editor');
            if (editor) { editor.hidden = !editor.hidden; }
        });

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'button button-small rsfm-delete-btn';
        removeBtn.textContent = strings.remove || 'Entfernen';
        removeBtn.addEventListener('click', function () {
            if (!confirm(strings.confirmRemove || 'Feld entfernen?')) { return; }
            removeField(index);
        });

        actions.appendChild(upBtn);
        actions.appendChild(downBtn);
        actions.appendChild(editBtn);
        actions.appendChild(removeBtn);

        header.appendChild(drag);
        header.appendChild(badge);
        header.appendChild(labelEl);
        header.appendChild(reqMark);
        header.appendChild(actions);
        div.appendChild(header);

        // ── Inline editor
        var editor = buildFieldEditor(field, index);
        div.appendChild(editor);

        // ── Drag & Drop events (handle-only)
        drag.addEventListener('dragstart', function (e) {
            e.stopPropagation();
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(index));
            setTimeout(function () { div.classList.add('is-dragging'); }, 0);
        });

        drag.addEventListener('dragend', function () {
            div.setAttribute('draggable', 'false');
            div.classList.remove('is-dragging');
            fieldList.querySelectorAll('.rsfm-field-item').forEach(function (el) {
                el.classList.remove('drag-over-above', 'drag-over-below');
            });
        });

        div.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var rect    = div.getBoundingClientRect();
            var midY    = rect.top + rect.height / 2;
            var isAbove = e.clientY < midY;
            div.classList.toggle('drag-over-above', isAbove);
            div.classList.toggle('drag-over-below', !isAbove);
        });

        div.addEventListener('dragleave', function () {
            div.classList.remove('drag-over-above', 'drag-over-below');
        });

        div.addEventListener('drop', function (e) {
            e.preventDefault();
            div.classList.remove('drag-over-above', 'drag-over-below');
            var fromIndex = parseInt(e.dataTransfer.getData('text/plain'), 10);
            var toIndex   = parseInt(div.dataset.fieldIndex, 10);
            if (isNaN(fromIndex) || fromIndex === toIndex) { return; }

            var rect    = div.getBoundingClientRect();
            var isAbove = e.clientY < rect.top + rect.height / 2;
            if (!isAbove && toIndex < state.form.fields.length - 1) { toIndex++; }

            moveField(fromIndex, toIndex);
        });

        return div;
    }

    function buildFieldEditor(field, index) {
        var editor = document.createElement('div');
        editor.className = 'rsfm-field-item__editor';
        editor.hidden = true;

        function addRow(labelText, inputEl) {
            var row = document.createElement('div');
            row.className = 'rsfm-editor-row';
            var lbl = document.createElement('label');
            lbl.textContent = labelText;
            row.appendChild(lbl);
            row.appendChild(inputEl);
            editor.appendChild(row);
        }

        function makeInput(value, placeholder) {
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'regular-text';
            inp.value = value || '';
            if (placeholder) { inp.placeholder = placeholder; }
            return inp;
        }

        // Label
        var labelInp = makeInput(field.label);
        labelInp.addEventListener('input', function () {
            state.form.fields[index].label = labelInp.value;
            // Update the displayed label in the header
            var header = editor.parentElement.querySelector('.rsfm-field-item__label');
            if (header) { header.textContent = labelInp.value || '(' + field.id + ')'; }
            state.dirty = true;
        });
        addRow(strings.label || 'Label', labelInp);

        // Placeholder (not for checkbox/radio/select/hidden)
        if (!['checkbox', 'radio', 'select', 'hidden'].includes(field.type)) {
            var phInp = makeInput(field.placeholder, 'z.B. Ihr Name');
            phInp.addEventListener('input', function () {
                state.form.fields[index].placeholder = phInp.value;
                state.dirty = true;
            });
            addRow(strings.placeholder || 'Platzhalter', phInp);
        }

        // Required
        var reqRow = document.createElement('div');
        reqRow.className = 'rsfm-editor-checkbox-row';
        var reqCb = document.createElement('input');
        reqCb.type = 'checkbox';
        reqCb.checked = !!field.required;
        reqCb.addEventListener('change', function () {
            state.form.fields[index].required = reqCb.checked;
            var reqMark = editor.parentElement.querySelector('.rsfm-field-item__required');
            if (reqMark) { reqMark.textContent = reqCb.checked ? '* ' + (strings.required || 'Pflichtfeld') : ''; }
            state.dirty = true;
        });
        reqRow.appendChild(reqCb);
        reqRow.appendChild(document.createTextNode(' ' + (strings.required || 'Pflichtfeld')));
        editor.appendChild(reqRow);

        // Select/Radio options
        if (field.type === 'select' || field.type === 'radio') {
            var optSection = document.createElement('div');
            optSection.className = 'rsfm-editor-row';
            var optLabel = document.createElement('label');
            optLabel.textContent = strings.selectOptions || 'Optionen';
            optSection.appendChild(optLabel);

            var optList = document.createElement('div');
            optList.className = 'rsfm-options-list';

            function renderOptions() {
                optList.innerHTML = '';
                (state.form.fields[index].options || []).forEach(function (opt, oi) {
                    var row = document.createElement('div');
                    row.className = 'rsfm-option-row';
                    var inp = document.createElement('input');
                    inp.type = 'text';
                    inp.className = 'regular-text';
                    inp.value = opt;
                    inp.addEventListener('input', function () {
                        state.form.fields[index].options[oi] = inp.value;
                        state.dirty = true;
                    });
                    var rmBtn = document.createElement('button');
                    rmBtn.type = 'button';
                    rmBtn.className = 'button button-small';
                    rmBtn.textContent = '×';
                    rmBtn.title = strings.removeOption || 'Option entfernen';
                    rmBtn.addEventListener('click', function () {
                        state.form.fields[index].options.splice(oi, 1);
                        renderOptions();
                        state.dirty = true;
                    });
                    row.appendChild(inp);
                    row.appendChild(rmBtn);
                    optList.appendChild(row);
                });
            }

            renderOptions();
            optSection.appendChild(optList);

            var addOptBtn = document.createElement('button');
            addOptBtn.type = 'button';
            addOptBtn.className = 'button button-small';
            addOptBtn.textContent = strings.addOption || '+ Option';
            addOptBtn.addEventListener('click', function () {
                state.form.fields[index].options = state.form.fields[index].options || [];
                state.form.fields[index].options.push('');
                renderOptions();
                state.dirty = true;
            });
            optSection.appendChild(addOptBtn);
            editor.appendChild(optSection);

            // Default value (select)
            if (field.type === 'select') {
                var dvInp = makeInput(field.default_value, '');
                dvInp.addEventListener('input', function () { state.form.fields[index].default_value = dvInp.value; });
                addRow(strings.defaultValue || 'Standardwert', dvInp);
            }
        }

        // Checkbox default
        if (field.type === 'checkbox') {
            var cbDefault = document.createElement('div');
            cbDefault.className = 'rsfm-editor-checkbox-row';
            var cbInp = document.createElement('input');
            cbInp.type = 'checkbox';
            cbInp.checked = (field.default_value === '1');
            cbInp.addEventListener('change', function () {
                state.form.fields[index].default_value = cbInp.checked ? '1' : '0';
                state.dirty = true;
            });
            cbDefault.appendChild(cbInp);
            cbDefault.appendChild(document.createTextNode(' ' + (strings.defaultValue || 'Standardwert: aktiviert')));
            editor.appendChild(cbDefault);
        }

        // Email validation mode
        if (field.type === 'email') {
            var emailValRow = document.createElement('div');
            emailValRow.className = 'rsfm-editor-row';
            var evLabel = document.createElement('label');
            evLabel.textContent = strings.validationMode || 'Validierungsmodus';
            emailValRow.appendChild(evLabel);

            var evSelect = document.createElement('select');
            var modes    = cfg.emailValidationModes || ['none', 'simple', 'regex', 'dns'];
            var modeLabels = (strings.emailValidationLabels) || {};
            modes.forEach(function (m) {
                var opt = document.createElement('option');
                opt.value = m;
                opt.textContent = modeLabels[m] || m;
                opt.selected = ((field.validation || {}).email_check || 'regex') === m;
                evSelect.appendChild(opt);
            });
            evSelect.addEventListener('change', function () {
                state.form.fields[index].validation = state.form.fields[index].validation || {};
                state.form.fields[index].validation.email_check = evSelect.value;
                state.dirty = true;
            });
            emailValRow.appendChild(evSelect);
            editor.appendChild(emailValRow);
        }

        // Tel validation mode
        if (field.type === 'tel') {
            var telValRow = document.createElement('div');
            telValRow.className = 'rsfm-editor-row';
            var tvLabel = document.createElement('label');
            tvLabel.textContent = strings.validationMode || 'Validierungsmodus';
            telValRow.appendChild(tvLabel);

            var tvSelect = document.createElement('select');
            var tmodes   = cfg.telValidationModes || ['none', 'simple', 'e164'];
            var tLabels  = (strings.telValidationLabels) || {};
            tmodes.forEach(function (m) {
                var opt = document.createElement('option');
                opt.value = m;
                opt.textContent = tLabels[m] || m;
                opt.selected = ((field.validation || {}).tel_check || 'simple') === m;
                tvSelect.appendChild(opt);
            });
            tvSelect.addEventListener('change', function () {
                state.form.fields[index].validation = state.form.fields[index].validation || {};
                state.form.fields[index].validation.tel_check = tvSelect.value;
                state.dirty = true;
            });
            telValRow.appendChild(tvSelect);
            editor.appendChild(telValRow);
        }

        // Hidden field default value
        if (field.type === 'hidden') {
            var hidInp = makeInput(field.default_value, '');
            hidInp.addEventListener('input', function () {
                state.form.fields[index].default_value = hidInp.value;
                state.dirty = true;
            });
            addRow(strings.defaultValue || 'Wert', hidInp);
        }

        return editor;
    }

    // -------------------------------------------------------------------------
    // Recipients rendering
    // -------------------------------------------------------------------------

    function renderRecipientsList() {
        if (!recipientsList) { return; }
        recipientsList.innerHTML = '';

        var recipients = state.form.submission.recipients || [];
        recipients.forEach(function (rec, ri) {
            var row = document.createElement('div');
            row.className = 'rsfm-recipient-row';

            var emailInp = document.createElement('input');
            emailInp.type = 'email';
            emailInp.className = 'regular-text';
            emailInp.value = rec.email || '';
            emailInp.placeholder = 'name@beispiel.de';
            emailInp.addEventListener('input', function () {
                state.form.submission.recipients[ri].email = emailInp.value;
                state.dirty = true;
            });

            var typeSelect = document.createElement('select');
            var recTypes   = cfg.recipientTypes || ['to', 'cc', 'bcc'];
            var recLabels  = (strings.recipientTypeLabels) || {};
            recTypes.forEach(function (t) {
                var opt = document.createElement('option');
                opt.value = t;
                opt.textContent = recLabels[t] || t.toUpperCase();
                opt.selected = (rec.type === t);
                typeSelect.appendChild(opt);
            });
            typeSelect.addEventListener('change', function () {
                state.form.submission.recipients[ri].type = typeSelect.value;
                state.dirty = true;
            });

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'button button-small';
            removeBtn.textContent = '×';
            removeBtn.disabled = (recipients.length <= 1);
            removeBtn.addEventListener('click', function () {
                state.form.submission.recipients.splice(ri, 1);
                renderRecipientsList();
                state.dirty = true;
            });

            row.appendChild(emailInp);
            row.appendChild(typeSelect);
            row.appendChild(removeBtn);
            recipientsList.appendChild(row);
        });
    }

    // -------------------------------------------------------------------------
    // Save
    // -------------------------------------------------------------------------

    function saveForm() {
        collectCurrentStepData();

        // Validate that an ID exists
        if (!state.form.id) {
            alert('Bitte vergib zuerst eine Formular-ID (Schritt 1).');
            goToStep(0);
            return;
        }

        // Confirmation mail requires at least one required email field.
        if (state.form.submission.confirmation_enabled && !hasRequiredEmailField()) {
            alert('Für die Absender-Bestätigung muss mindestens ein E-Mail-Feld als Pflichtfeld markiert sein.');
            goToStep(1);
            return;
        }

        hideNotices();
        if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Speichern…'; }

        var data = new FormData();
        data.append('action', 'restatify_forms_save');
        data.append('nonce', nonce);
        data.append('form_data', JSON.stringify(state.form));

        fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = '💾 Speichern'; }

                if (json.success) {
                    state.dirty = false;
                    if (isNew && json.data && json.data.form_id) {
                        // After first save, redirect to edit URL so we're in "edit" mode
                        isNew = false;
                        history.replaceState(
                            null,
                            '',
                            listUrl.replace('admin.php?', 'admin.php?action=edit&form_id=' + json.data.form_id + '&page=')
                                .replace('page=wp-restatify-forms', 'page=wp-restatify-forms')
                        );
                        // Simpler: just update URL param
                        var url = new URL(window.location.href);
                        url.searchParams.set('action', 'edit');
                        url.searchParams.set('form_id', json.data.form_id);
                        history.replaceState(null, '', url.toString());
                    }
                    showNotice(successNotice);
                } else {
                    var message = (json && json.data && json.data.message)
                        ? json.data.message
                        : 'Fehler beim Speichern. Bitte versuche es erneut.';
                    if (errorNoticeText) {
                        errorNoticeText.textContent = message;
                    }
                    showNotice(errorNotice);
                }
            })
            .catch(function () {
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = '💾 Speichern'; }
                if (errorNoticeText) {
                    errorNoticeText.textContent = 'Fehler beim Speichern. Bitte versuche es erneut.';
                }
                showNotice(errorNotice);
            });
    }

    function showNotice(el) {
        if (!el) { return; }
        el.hidden = false;
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        setTimeout(function () { el.hidden = true; }, 4000);
    }

    function hideNotices() {
        if (successNotice) successNotice.hidden = true;
        if (errorNotice)   errorNotice.hidden   = true;
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    function slugify(text) {
        return text
            .toLowerCase()
            .replace(/[äÄ]/g, 'ae')
            .replace(/[öÖ]/g, 'oe')
            .replace(/[üÜ]/g, 'ue')
            .replace(/[ß]/g, 'ss')
            .replace(/[^a-z0-9\-]/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '')
            .slice(0, 60);
    }

    function hasRequiredEmailField() {
        return (state.form.fields || []).some(function (field) {
            return field && field.type === 'email' && !!field.required;
        });
    }

    function computeTrigger(id) {
        return id ? '#restatify-form-' + id : '';
    }

    // -------------------------------------------------------------------------
    // Warn before leaving with unsaved changes
    // -------------------------------------------------------------------------

    window.addEventListener('beforeunload', function (e) {
        if (state.dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());
