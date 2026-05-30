/* jshint esversion: 11 */
(function () {
    'use strict';

    var cfg      = window.restatifyForms || {};
    var forms    = cfg.forms   || [];
    var strings  = cfg.strings || {};
    var ajaxUrl  = cfg.ajaxUrl || '';
    var nonce    = cfg.nonce   || '';
    var attributionEl = null;

    // Map form ID → config for quick lookup
    var formMap = {};
    forms.forEach(function (f) { formMap[f.id] = f; });

    // Auto-close delay after success (ms)
    var AUTO_CLOSE_DELAY = 4500;

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------

    function init() {
        ensureAttributionUi();
        document.addEventListener('click', handleLinkClick);
        document.addEventListener('keydown', handleEsc);
        window.addEventListener('hashchange', handleLocationHash);
        document.addEventListener('restatify:form-open', handleExternalFormOpen);

        // Support direct navigation to a form hash.
        handleLocationHash();
    }

    function handleExternalFormOpen(event) {
        var detail = event && event.detail && typeof event.detail === 'object' ? event.detail : {};
        var formId = String(detail.formId || '').trim();
        var prefill = detail.prefill && typeof detail.prefill === 'object' ? detail.prefill : {};

        if (!formId && forms.length > 0 && forms[0] && forms[0].id) {
            formId = String(forms[0].id);
        }

        if (!formId) {
            return;
        }

        openPopup(formId);
        if (prefill && Object.keys(prefill).length > 0) {
            window.setTimeout(function () {
                var popup = document.getElementById('rsfm-popup-' + formId);
                if (!popup) { return; }
                applyPrefillToPopup(popup, prefill);
            }, 80);
        }
    }

    function handleLocationHash() {
        var formId = parseTriggerToFormId(window.location.hash || '');
        if (formId) {
            openPopup(formId);
        }
    }

    function parseTriggerToFormId(triggerValue) {
        var triggerPrefix = 'restatify-form-';
        var raw = (triggerValue || '').trim();
        if (raw === '') { return ''; }

        var hash = raw;
        var hashIndex = raw.indexOf('#');
        if (hashIndex >= 0) {
            hash = raw.slice(hashIndex);
        }

        if (!hash.startsWith('#restatify-form-')) {
            return '';
        }

        var rawId = hash.slice(1); // strip '#'
        return rawId.startsWith(triggerPrefix) ? rawId.slice(triggerPrefix.length) : rawId;
    }

    function ensureAttributionUi() {
        attributionEl = document.querySelector('.rsfm-provider-attribution');
        if (attributionEl) { return; }

        attributionEl = document.createElement('div');
        attributionEl.className = 'rsfm-provider-attribution';
        attributionEl.hidden = true;
        attributionEl.innerHTML = [
            '<a class="rsfm-provider-btn rsfm-provider-btn--privacy" href="#" target="_blank" rel="noopener noreferrer nofollow">Datenschutz</a>',
            '<a class="rsfm-provider-btn rsfm-provider-btn--terms" href="#" target="_blank" rel="noopener noreferrer nofollow">Bedingungen</a>'
        ].join('');

        document.body.appendChild(attributionEl);
    }

    function getProviderLinks(provider) {
        if (provider === 'recaptcha') {
            return {
                privacy: 'https://policies.google.com/privacy',
                terms: 'https://policies.google.com/terms',
                label: 'Google reCAPTCHA'
            };
        }

        if (provider === 'turnstile') {
            return {
                privacy: 'https://www.cloudflare.com/privacypolicy/',
                terms: 'https://www.cloudflare.com/website-terms/',
                label: 'Cloudflare Turnstile'
            };
        }

        return null;
    }

    function isProtectionActive(security) {
        if (!security || typeof security !== 'object') { return false; }

        var provider = security.captcha_provider || 'none';
        return provider !== 'none';
    }

    function setAttributionForPopup(popup) {
        if (!popup || !attributionEl) { return; }

        var formId   = popup.dataset.formId || '';
        var formCfg  = formMap[formId] || {};
        var security = formCfg.security || {};
        if (!isProtectionActive(security)) {
            hideAttribution();
            return;
        }

        var provider = security.captcha_provider || 'none';
        var links = getProviderLinks(provider);
        if (!links) {
            hideAttribution();
            return;
        }

        var privacyLink = attributionEl.querySelector('.rsfm-provider-btn--privacy');
        var termsLink   = attributionEl.querySelector('.rsfm-provider-btn--terms');
        if (!privacyLink || !termsLink) {
            hideAttribution();
            return;
        }

        privacyLink.href = links.privacy;
        termsLink.href = links.terms;
        privacyLink.textContent = links.label + ' Datenschutz';
        termsLink.textContent = links.label + ' Bedingungen';
        attributionEl.hidden = false;
    }

    function hideAttribution() {
        if (!attributionEl) { return; }
        attributionEl.hidden = true;
    }

    // -------------------------------------------------------------------------
    // Link click handler
    // -------------------------------------------------------------------------

    function handleLinkClick(e) {
        var target = e.target;

        // Walk up to find an anchor with href starting with #restatify-form-
        while (target && target !== document) {
            if (target.tagName === 'A') {
                var href = (target.getAttribute('href') || '').trim();
                var formId = parseTriggerToFormId(href);
                if (formId) {

                    if (formMap[formId] || document.getElementById('rsfm-popup-' + formId)) {
                        e.preventDefault();
                        openPopup(formId);
                    }
                    return;
                }
            }
            target = target.parentNode;
        }
    }

    // -------------------------------------------------------------------------
    // Popup open / close
    // -------------------------------------------------------------------------

    function openPopup(formId) {
        var popup = document.getElementById('rsfm-popup-' + formId);
        if (!popup) { return; }

        resetForm(popup);
        popup.hidden = false;
        document.body.style.overflow = 'hidden';
        setAttributionForPopup(popup);

        // Set nonce fresh on open
        var nonceInput = popup.querySelector('input[name="_nonce"]');
        if (nonceInput) { nonceInput.value = nonce; }

        // Focus first input
        setTimeout(function () {
            var first = popup.querySelector('input:not([type="hidden"]), textarea, select');
            if (first) { first.focus(); }
        }, 50);

        // Bind close triggers inside this popup
        popup.querySelectorAll('[data-rsfm-close]').forEach(function (el) {
            el.addEventListener('click', function () { closePopup(formId); });
        });

        // Validate initially (to set submit button state)
        validateAll(popup);
    }

    function applyPrefillToPopup(popup, prefill) {
        if (!popup || !prefill || typeof prefill !== 'object') { return; }

        var form = popup.querySelector('.rsfm-form');
        if (!form) { return; }

        var wrappers = form.querySelectorAll('[data-field-id]');
        wrappers.forEach(function (fieldWrap) {
            var fid = String(fieldWrap.dataset.fieldId || '');
            if (!fid) { return; }

            var input = form.querySelector('#rsfm-field-' + fid);
            if (!input || input.disabled) { return; }

            var value = pickPrefillValue(prefill, fieldWrap, input, fid);
            if (typeof value !== 'string' || value.trim() === '') { return; }

            input.value = value.trim();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        validateAll(popup);
    }

    function pickPrefillValue(prefill, fieldWrap, input, fid) {
        var idHint = normalizeFieldHint(fid);
        var labelEl = fieldWrap.querySelector('.rsfm-label');
        var labelHint = normalizeFieldHint(labelEl ? labelEl.textContent : '');
        var placeholderHint = normalizeFieldHint(input.getAttribute('placeholder') || '');
        var allHints = idHint + ' ' + labelHint + ' ' + placeholderHint;
        var type = String(input.type || '').toLowerCase();

        if (type === 'email' || hasAnyHint(allHints, ['email', 'e-mail', 'mail'])) {
            return String(prefill.email || '');
        }

        if (type === 'tel' || hasAnyHint(allHints, ['telefon', 'phone', 'tel', 'mobile', 'mobil', 'whatsapp'])) {
            return String(prefill.phone || prefill.contact_value || '');
        }

        if (type === 'textarea' || input.tagName === 'TEXTAREA' || hasAnyHint(allHints, ['nachricht', 'message', 'anliegen', 'beschreibung', 'details', 'note'])) {
            return String(prefill.message || prefill.note || '');
        }

        if (hasAnyHint(allHints, ['name', 'fullname', 'full name', 'vorname', 'nachname'])) {
            return String(prefill.name || '');
        }

        if (hasAnyHint(allHints, ['betreff', 'subject', 'titel', 'topic'])) {
            return String(prefill.subject || '');
        }

        return '';
    }

    function normalizeFieldHint(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/ä/g, 'ae')
            .replace(/ö/g, 'oe')
            .replace(/ü/g, 'ue')
            .replace(/ß/g, 'ss');
    }

    function hasAnyHint(haystack, needles) {
        return needles.some(function (needle) {
            return haystack.indexOf(String(needle || '')) !== -1;
        });
    }

    function closePopup(formId) {
        var popup = document.getElementById('rsfm-popup-' + formId);
        if (!popup) { return; }
        popup.hidden = true;
        document.body.style.overflow = '';
        hideAttribution();
    }

    function handleEsc(e) {
        if (e.key !== 'Escape') { return; }
        var open = document.querySelector('.rsfm-popup:not([hidden])');
        if (open) {
            closePopup(open.dataset.formId);
        }
    }

    // -------------------------------------------------------------------------
    // Form reset
    // -------------------------------------------------------------------------

    function resetForm(popup) {
        var form = popup.querySelector('.rsfm-form');
        if (!form) { return; }
        form.reset();
        clearErrors(popup);
        setStatus(popup, '', '');
        setSubmitEnabled(popup, true);
        form.classList.remove('is-submitted');

        // Re-open must fully restore UI after previous success state hid controls.
        form.querySelectorAll('.rsfm-form__actions').forEach(function (a) { a.style.display = ''; });
        form.querySelectorAll('.rsfm-field').forEach(function (f) { f.style.display = ''; });

        // A completed Turnstile token is single-use. Ensure a fresh challenge on reopen.
        if (window.turnstile && typeof window.turnstile.reset === 'function') {
            form.querySelectorAll('.cf-turnstile').forEach(function (widget) {
                try {
                    window.turnstile.reset(widget);
                } catch (e) {
                    // Ignore widget reset errors; submit flow still handles missing token safely.
                }
            });
        }
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    function validateAll(popup) {
        var form   = popup.querySelector('.rsfm-form');
        if (!form) { return true; }
        var fields = form.querySelectorAll('[data-field-id]');
        var allOk  = true;

        fields.forEach(function (fieldWrap) {
            var fid    = fieldWrap.dataset.fieldId;
            var input  = form.querySelector('#rsfm-field-' + fid);
            if (!input) { return; }

            var ok = validateField(popup, input, false);
            if (!ok) { allOk = false; }
        });

        setSubmitEnabled(popup, allOk);
        return allOk;
    }

    /**
     * Validates a single field. If `showError` is true, marks the field invalid.
     */
    function validateField(popup, input, showError) {
        var fid      = input.id.replace('rsfm-field-', '');
        var type     = input.type || 'text';
        var required = input.required;
        var value    = input.value.trim();

        // Checkboxes: required means must be checked
        if (type === 'checkbox') {
            if (required && !input.checked) {
                if (showError) { markInvalid(popup, fid, ''); }
                return false;
            }
            clearFieldError(popup, fid);
            return true;
        }

        // Radio groups: find by name
        if (type === 'radio') {
            var name    = input.name;
            var checked = !!popup.querySelector('input[name="' + name + '"]:checked');
            if (required && !checked) {
                if (showError) { markInvalid(popup, fid, ''); }
                return false;
            }
            clearFieldError(popup, fid);
            return true;
        }

        if (required && value === '') {
            if (showError) { markInvalid(popup, fid, ''); }
            return false;
        }

        if (value === '') {
            clearFieldError(popup, fid);
            return true;
        }

        // Type-specific client-side checks (mirrors PHP but lightweight)
        if (type === 'email') {
            var formId = popup.dataset.formId;
            var sec    = (formMap[formId] || {}).security || {};
            var mode   = getEmailValidationMode(formId, fid);
            var emailOk = validateEmail(value, mode);
            if (!emailOk) {
                if (showError) { markInvalid(popup, fid, 'Bitte gib eine gültige E-Mail-Adresse ein.'); }
                return false;
            }
        }

        clearFieldError(popup, fid);
        return true;
    }

    function validateEmail(value, mode) {
        if (mode === 'none') { return true; }
        if (!value.includes('@')) { return false; }
        if (mode === 'simple') { return true; }
        // Basic regex
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
        // dns check can only happen server-side
    }

    function getEmailValidationMode(formId, fieldId) {
        var formCfg = formMap[formId] || {};
        var fields  = formCfg.fields || [];
        for (var i = 0; i < fields.length; i++) {
            if (fields[i].id === fieldId) {
                return (fields[i].validation || {}).email_check || 'regex';
            }
        }
        return 'regex';
    }

    // -------------------------------------------------------------------------
    // Error display helpers
    // -------------------------------------------------------------------------

    function markInvalid(popup, fid, message) {
        var input = popup.querySelector('#rsfm-field-' + fid);
        if (input) {
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
        }
        var errEl = popup.querySelector('#rsfm-err-' + fid);
        if (errEl && message) { errEl.textContent = message; }
    }

    function clearFieldError(popup, fid) {
        var input = popup.querySelector('#rsfm-field-' + fid);
        if (input) {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
        }
        var errEl = popup.querySelector('#rsfm-err-' + fid);
        if (errEl) { errEl.textContent = ''; }
    }

    function clearErrors(popup) {
        popup.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
            el.removeAttribute('aria-invalid');
        });
        popup.querySelectorAll('.rsfm-field__error').forEach(function (el) {
            el.textContent = '';
        });
    }

    function setStatus(popup, message, type) {
        var el = popup.querySelector('.rsfm-status');
        if (!el) { return; }
        el.className = 'rsfm-status';
        el.textContent = '';

        if (!message) { return; }

        if (type === 'success') {
            el.classList.add('is-success');
            el.textContent = message;

            var okBtn = document.createElement('button');
            okBtn.type = 'button';
            okBtn.className = 'rsfm-status__ok-btn';
            okBtn.textContent = strings.ok || 'OK';
            okBtn.addEventListener('click', function () {
                closePopup(popup.dataset.formId);
            });
            el.appendChild(okBtn);
        } else if (type === 'error') {
            el.classList.add('is-error');
            el.textContent = message;
        }
    }

    function setSubmitEnabled(popup, enabled) {
        var btn = popup.querySelector('.rsfm-submit');
        if (btn) { btn.disabled = !enabled; }
    }

    // -------------------------------------------------------------------------
    // Live validation on input/change
    // -------------------------------------------------------------------------

    document.addEventListener('input', function (e) {
        var input  = e.target;
        var popup  = input.closest('.rsfm-popup');
        if (!popup) { return; }
        validateField(popup, input, true);
        validateAll(popup);
    });

    document.addEventListener('change', function (e) {
        var input = e.target;
        var popup = input.closest('.rsfm-popup');
        if (!popup) { return; }
        validateField(popup, input, true);
        validateAll(popup);
    });

    document.addEventListener('blur', function (e) {
        var input = e.target;
        if (!input.matches || !input.matches('.rsfm-input, .rsfm-input--textarea, .rsfm-input--select')) { return; }
        var popup = input.closest('.rsfm-popup');
        if (!popup) { return; }
        validateField(popup, input, true);
    }, true);

    // -------------------------------------------------------------------------
    // Form submission
    // -------------------------------------------------------------------------

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.classList.contains('rsfm-form')) { return; }
        e.preventDefault();

        var popup = form.closest('.rsfm-popup');
        if (!popup) { return; }

        // Final validation pass
        clearErrors(popup);
        if (!validateAll(popup)) {
            // Force-show all errors
            form.querySelectorAll('[data-field-id]').forEach(function (wrap) {
                var fid   = wrap.dataset.fieldId;
                var input = form.querySelector('#rsfm-field-' + fid);
                if (input) { validateField(popup, input, true); }
            });
            return;
        }

        submitForm(form, popup);
    });

    function submitForm(form, popup) {
        var submitBtn = popup.querySelector('.rsfm-submit');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('is-sending');
            submitBtn.textContent = strings.sending || 'Wird gesendet…';
        }

        setStatus(popup, '', '');

        var formId   = form.dataset.formId;
        var formCfg  = formMap[formId] || {};
        var security = formCfg.security || {};

        getCaptchaToken(security, formId).then(function (captchaToken) {
            var data     = new FormData();
            var elements = form.elements;

            data.append('action', 'restatify_forms_submit');
            data.append('_nonce', nonce);
            data.append('form_id', formId);
            data.append('_captcha', captchaToken);

            // Honeypot (always empty)
            if (security.honeypot) {
                data.append('_hp', '');
            }

            // Collect field values
            for (var i = 0; i < elements.length; i++) {
                var el   = elements[i];
                var name = el.name || '';
                if (!name.startsWith('fields[')) { continue; }
                if (el.type === 'checkbox') {
                    data.append(name, el.checked ? '1' : '0');
                } else if (el.type === 'radio') {
                    if (el.checked) { data.append(name, el.value); }
                } else {
                    data.append(name, el.value);
                }
            }

            return fetch(ajaxUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
            });
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('is-sending');
                submitBtn.textContent = 'Senden';
            }

            if (json.success) {
                var msg = (json.data && json.data.message) ? json.data.message : 'Vielen Dank!';
                setStatus(popup, msg, 'success');
                form.querySelectorAll('.rsfm-form__actions').forEach(function (a) { a.style.display = 'none'; });
                form.querySelectorAll('.rsfm-field').forEach(function (f) { f.style.display = 'none'; });

                // Auto-close
                setTimeout(function () {
                    closePopup(popup.dataset.formId);
                }, AUTO_CLOSE_DELAY);
            } else {
                var errMsg = (json.data && json.data.message) ? json.data.message : (strings.error || 'Fehler.');
                setStatus(popup, errMsg, 'error');

                // Per-field errors if provided
                var errors = (json.data && json.data.errors) ? json.data.errors : {};
                Object.keys(errors).forEach(function (fid) {
                    markInvalid(popup, fid, errors[fid]);
                });
            }
        }).catch(function () {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('is-sending');
                submitBtn.textContent = 'Senden';
            }
            setStatus(popup, strings.error || 'Es ist ein Fehler aufgetreten.', 'error');
        });
    }

    // -------------------------------------------------------------------------
    // CAPTCHA token acquisition
    // -------------------------------------------------------------------------

    function waitForTurnstileToken(formId, timeoutMs) {
        return new Promise(function (resolve) {
            var started = Date.now();

            var check = function () {
                var response = document.querySelector('#rsfm-popup-' + formId + ' [name="cf-turnstile-response"]');
                if (response && response.value) {
                    resolve(response.value);
                    return;
                }

                if ((Date.now() - started) >= timeoutMs) {
                    resolve('');
                    return;
                }

                window.setTimeout(check, 120);
            };

            check();
        });
    }

    function getCaptchaToken(security, formId) {
        var provider = security.captcha_provider || 'none';

        if (provider === 'recaptcha' && security.recaptcha_site_key && window.grecaptcha) {
            return window.grecaptcha.execute(security.recaptcha_site_key, { action: 'rsfm_submit_' + formId });
        }

        if (provider === 'turnstile') {
            var response = document.querySelector('#rsfm-popup-' + formId + ' [name="cf-turnstile-response"]');
            if (response && response.value) {
                return Promise.resolve(response.value);
            }
            // Widget may still be booting/rendering.
            return waitForTurnstileToken(formId, 2200);
        }

        return Promise.resolve('');
    }

    // -------------------------------------------------------------------------
    // Start
    // -------------------------------------------------------------------------

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Test hooks for unit tests (no effect on production behavior).
    if (typeof window !== 'undefined') {
        window.__RSFM_FORMS_TEST__ = {
            parseTriggerToFormId: parseTriggerToFormId,
            isProtectionActive: isProtectionActive,
            getProviderLinks: getProviderLinks
        };
    }

}());
