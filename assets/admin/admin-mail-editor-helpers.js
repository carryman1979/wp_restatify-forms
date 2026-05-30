/* jshint esversion: 11 */
(function (window) {
    'use strict';

    var HELPERS = {
        initSharedMailEditorHelpers: initSharedMailEditorHelpers,
        setHtmlTemplateValue: setHtmlTemplateValue,
        initMailTemplateEditors: initMailTemplateEditors,
        bindMailEditorSync: bindMailEditorSync,
    };

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

    function initMailTemplateEditors(context) {
        if (!context || typeof context !== 'object') {
            return;
        }

        initMailEditor(context.ownerBodyTextarea, 'owner_html_body', context);
        initMailEditor(context.confirmBodyTextarea, 'confirmation_html_body', context);
    }

    function initMailEditor(textarea, submissionKey, context) {
        if (!(textarea instanceof HTMLTextAreaElement)) { return; }
        if (!canUseWpEditor()) { return; }

        var slot = context.mailEditorState[submissionKey];
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
        bindMailEditorSync(textarea, submissionKey, context);
    }

    function bindMailEditorSync(textarea, submissionKey, context) {
        var slot = context.mailEditorState[submissionKey];
        if (!slot || slot.bindingAttached || !(textarea instanceof HTMLTextAreaElement)) {
            return;
        }

        var codeMirrorTextarea = null;
        if (submissionKey === 'owner_html_body') {
            codeMirrorTextarea = context.ownerBodyCodeTextarea;
        }
        if (submissionKey === 'confirmation_html_body') {
            codeMirrorTextarea = context.confirmBodyCodeTextarea;
        }

        var syncTextarea = function () {
            context.state.form.submission[submissionKey] = textarea.value || '';
            if (codeMirrorTextarea) {
                codeMirrorTextarea.value = textarea.value || '';
            }
            context.state.dirty = true;
        };

        textarea.addEventListener('input', syncTextarea);

        var attachEditorBinding = function () {
            var editor = getTinyMceEditor(textarea.id);
            if (!editor) {
                window.requestAnimationFrame(attachEditorBinding);
                return;
            }

            editor.on('change keyup SetContent', function () {
                context.state.form.submission[submissionKey] = editor.getContent();
                if (codeMirrorTextarea) {
                    codeMirrorTextarea.value = editor.getContent();
                }
                context.state.dirty = true;
            });
        };

        window.requestAnimationFrame(attachEditorBinding);
        slot.bindingAttached = true;
    }

    window.RsfmAdminMailEditorHelpers = HELPERS;
})(window);