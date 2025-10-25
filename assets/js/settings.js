/**
 * Settings page JavaScript for WP Easy Force Password Change
 *
 * @package WP_Easy\ForcePW_Change
 */

(async function() {
    'use strict';

    let editorView = null;
    let saveTimeout = null;

    /**
     * Initialize settings page
     */
    async function init() {
        // Initialize toggle
        initToggle();

        // Initialize CodeMirror 6 editor
        await initCodeMirror();

        // Initialize reset button
        initResetButton();

        // Initialize save as default button
        initSaveAsDefaultButton();

        // Initialize email template handlers
        initEmailTemplateSaveButton();
        initPlaceholderCopyButtons();
    }

    /**
     * Initialize enable/disable toggle
     */
    function initToggle() {
        const toggle = document.getElementById('wpe-fpc-enabled');
        if (!toggle) return;

        toggle.addEventListener('change', () => {
            saveSettings();
        });
    }

    /**
     * Initialize CodeMirror 6 Editor (using esm.sh like document-downloader)
     */
    async function initCodeMirror() {
        const textarea = document.getElementById('wpe-fpc-css-editor');
        const container = document.getElementById('wpe-fpc-css-editor-container');

        if (!textarea || !container) return;

        try {
            textarea.style.display = 'none';

            // Import all modules separately from esm.sh (same approach as document-downloader)
            const [
                {EditorState},
                {EditorView, keymap, lineNumbers, highlightActiveLine, highlightActiveLineGutter},
                {defaultHighlightStyle, syntaxHighlighting, indentOnInput},
                {history, historyKeymap, defaultKeymap, indentWithTab},
                {autocompletion, closeBrackets, closeBracketsKeymap, completionKeymap},
                {css},
                {dracula}
            ] = await Promise.all([
                import('https://esm.sh/@codemirror/state'),
                import('https://esm.sh/@codemirror/view'),
                import('https://esm.sh/@codemirror/language'),
                import('https://esm.sh/@codemirror/commands'),
                import('https://esm.sh/@codemirror/autocomplete'),
                import('https://esm.sh/@codemirror/lang-css'),
                import('https://esm.sh/@uiw/codemirror-theme-dracula')
            ]);

            // Custom theme with height and autocomplete fix
            const customTheme = EditorView.theme({
                "&": {
                    height: "500px",
                    border: "1px solid #44475a",
                    borderRadius: "4px",
                    fontSize: "14px"
                },
                ".cm-scroller": {
                    overflow: "auto",
                    fontFamily: "'Consolas', 'Monaco', 'Courier New', monospace"
                },
                // Fix autocomplete text color
                ".cm-tooltip.cm-tooltip-autocomplete": {
                    "& > ul > li": {
                        color: "#f8f8f2"
                    },
                    "& > ul > li[aria-selected]": {
                        background: "#44475a",
                        color: "#f8f8f2"
                    }
                }
            }, { dark: true });

            // Extensions
            const extensions = [
                dracula,
                customTheme,
                lineNumbers(),
                highlightActiveLineGutter(),
                history(),
                indentOnInput(),
                closeBrackets(),
                autocompletion(),
                highlightActiveLine(),
                syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
                keymap.of([
                    indentWithTab,
                    ...defaultKeymap,
                    ...historyKeymap,
                    ...closeBracketsKeymap,
                    ...completionKeymap
                ]),
                css(),
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        handleEditorChange();
                    }
                })
            ];

            // Create editor
            editorView = new EditorView({
                state: EditorState.create({
                    doc: textarea.value,
                    extensions
                }),
                parent: container
            });

        } catch (error) {
            console.error('Failed to load CodeMirror:', error);
            textarea.style.display = 'block';
            textarea.addEventListener('input', handleEditorChange);
        }
    }

    /**
     * Handle editor content change
     */
    function handleEditorChange() {
        // Clear existing timeout
        if (saveTimeout) {
            clearTimeout(saveTimeout);
        }

        // Set new timeout for auto-save (1 second debounce)
        saveTimeout = setTimeout(() => {
            saveSettings();
        }, 1000);
    }

    /**
     * Save settings via AJAX
     */
    async function saveSettings() {
        const enabledToggle = document.getElementById('wpe-fpc-enabled');
        const textarea = document.getElementById('wpe-fpc-css-editor');
        const indicator = document.getElementById('wpe-fpc-save-indicator');

        if (!enabledToggle || !indicator) return;

        // Get current CSS value
        let cssValue = '';
        if (editorView) {
            cssValue = editorView.state.doc.toString();
        } else if (textarea) {
            cssValue = textarea.value;
        }

        // Show saving indicator
        showSaveIndicator('saving');

        const formData = new FormData();
        formData.append('action', 'wpe_fpc_save_settings');
        formData.append('nonce', wpeFpcSettings.nonce);
        formData.append('enabled', enabledToggle.checked ? '1' : '0');
        formData.append('custom_css', cssValue);

        try {
            const response = await fetch(wpeFpcSettings.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();

            if (data.success) {
                showSaveIndicator('success');
            } else {
                showSaveIndicator('error');
                console.error('Save failed:', data.data?.message || 'Unknown error');
            }
        } catch (error) {
            showSaveIndicator('error');
            console.error('Save error:', error);
        }
    }

    /**
     * Show save indicator with status
     *
     * @param {string} status Status: 'saving', 'success', 'error'
     */
    function showSaveIndicator(status) {
        const indicator = document.getElementById('wpe-fpc-save-indicator');
        if (!indicator) return;

        const textSpan = indicator.querySelector('.wpe-fpc-save-text');
        const icon = indicator.querySelector('.dashicons');

        // Remove all status classes
        indicator.classList.remove('wpe-fpc-saving', 'wpe-fpc-error', 'wpe-fpc-show');

        // Update based on status
        switch (status) {
            case 'saving':
                indicator.classList.add('wpe-fpc-saving', 'wpe-fpc-show');
                icon.className = 'dashicons dashicons-update';
                textSpan.textContent = 'Saving...';
                break;

            case 'success':
                indicator.classList.add('wpe-fpc-show');
                icon.className = 'dashicons dashicons-saved';
                textSpan.textContent = wpeFpcSettings.strings.saved;

                // Hide after 3 seconds
                setTimeout(() => {
                    indicator.classList.remove('wpe-fpc-show');
                }, 3000);
                break;

            case 'error':
                indicator.classList.add('wpe-fpc-error', 'wpe-fpc-show');
                icon.className = 'dashicons dashicons-warning';
                textSpan.textContent = wpeFpcSettings.strings.saveFailed;

                // Hide after 5 seconds
                setTimeout(() => {
                    indicator.classList.remove('wpe-fpc-show');
                }, 5000);
                break;
        }
    }

    /**
     * Initialize reset button
     */
    function initResetButton() {
        const resetButton = document.getElementById('wpe-fpc-reset-css');
        if (!resetButton) return;

        resetButton.addEventListener('click', async () => {
            // Confirm reset
            if (!confirm(wpeFpcSettings.strings.resetConfirm)) {
                return;
            }

            // Disable button
            resetButton.disabled = true;
            const originalText = resetButton.textContent;
            resetButton.textContent = 'Resetting...';

            const formData = new FormData();
            formData.append('action', 'wpe_fpc_reset_css');
            formData.append('nonce', wpeFpcSettings.nonce);

            try {
                const response = await fetch(wpeFpcSettings.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await response.json();

                if (data.success) {
                    // Update editor with default CSS
                    const defaultCss = data.data.css;

                    if (editorView) {
                        // Update CodeMirror 6
                        editorView.dispatch({
                            changes: {
                                from: 0,
                                to: editorView.state.doc.length,
                                insert: defaultCss
                            }
                        });
                        editorView.focus();
                    } else {
                        // Update textarea
                        const textarea = document.getElementById('wpe-fpc-css-editor');
                        if (textarea) {
                            textarea.value = defaultCss;
                        }
                    }

                    showNotification(wpeFpcSettings.strings.resetSuccess, 'success');
                } else {
                    showNotification(data.data?.message || wpeFpcSettings.strings.resetFailed, 'error');
                }
            } catch (error) {
                console.error('Reset error:', error);
                showNotification(wpeFpcSettings.strings.resetFailed, 'error');
            } finally {
                // Re-enable button
                resetButton.disabled = false;
                resetButton.textContent = originalText;
            }
        });
    }

    /**
     * Initialize save as default button
     */
    function initSaveAsDefaultButton() {
        const saveDefaultButton = document.getElementById('wpe-fpc-save-as-default');
        if (!saveDefaultButton) return;

        saveDefaultButton.addEventListener('click', async () => {
            // Confirm save as default
            if (!confirm(wpeFpcSettings.strings.saveAsDefaultConfirm)) {
                return;
            }

            // Disable button
            saveDefaultButton.disabled = true;
            const originalText = saveDefaultButton.textContent;
            saveDefaultButton.textContent = 'Saving...';

            // Get current CSS value
            let cssValue = '';
            if (editorView) {
                cssValue = editorView.state.doc.toString();
            } else {
                const textarea = document.getElementById('wpe-fpc-css-editor');
                if (textarea) {
                    cssValue = textarea.value;
                }
            }

            const formData = new FormData();
            formData.append('action', 'wpe_fpc_save_as_default');
            formData.append('nonce', wpeFpcSettings.nonce);
            formData.append('custom_css', cssValue);

            try {
                const response = await fetch(wpeFpcSettings.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await response.json();

                if (data.success) {
                    showNotification(wpeFpcSettings.strings.saveAsDefaultSuccess, 'success');
                } else {
                    showNotification(data.data?.message || wpeFpcSettings.strings.saveAsDefaultFailed, 'error');
                }
            } catch (error) {
                console.error('Save as default error:', error);
                showNotification(wpeFpcSettings.strings.saveAsDefaultFailed, 'error');
            } finally {
                // Re-enable button
                saveDefaultButton.disabled = false;
                saveDefaultButton.textContent = originalText;
            }
        });
    }

    /**
     * Show notification message
     *
     * @param {string} message Message to display
     * @param {string} type    Type: 'success', 'error', 'info'
     */
    function showNotification(message, type = 'success') {
        // Create notice element
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible`;
        notice.innerHTML = `<p>${escapeHtml(message)}</p>`;

        // Add dismiss button
        const dismissButton = document.createElement('button');
        dismissButton.type = 'button';
        dismissButton.className = 'notice-dismiss';
        dismissButton.innerHTML = '<span class="screen-reader-text">Dismiss this notice.</span>';
        dismissButton.addEventListener('click', () => {
            notice.remove();
        });
        notice.appendChild(dismissButton);

        // Insert after h1
        const heading = document.querySelector('.wpe-fpc-settings-wrap h1');
        if (heading) {
            heading.parentNode.insertBefore(notice, heading.nextSibling);
        }

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            notice.classList.add('wpe-fpc-fade-out');
            setTimeout(() => notice.remove(), 300);
        }, 5000);
    }

    /**
     * Escape HTML to prevent XSS
     *
     * @param {string} text Text to escape
     * @return {string} Escaped text
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, (m) => map[m]);
    }

    /**
     * Initialize email template save button
     */
    function initEmailTemplateSaveButton() {
        const saveButton = document.getElementById('wpe-fpc-save-email-template');
        if (!saveButton) return;

        saveButton.addEventListener('click', async () => {
            // Get email template content from TinyMCE
            let emailTemplate = '';
            if (typeof tinymce !== 'undefined' && tinymce.get('wpe_fpc_email_template')) {
                emailTemplate = tinymce.get('wpe_fpc_email_template').getContent();
            } else {
                const textarea = document.getElementById('wpe_fpc_email_template');
                if (textarea) {
                    emailTemplate = textarea.value;
                }
            }

            if (!emailTemplate) {
                showNotification(wpeFpcSettings.strings.emailTemplateFailed, 'error');
                return;
            }

            // Disable button
            saveButton.disabled = true;
            const originalText = saveButton.textContent;
            saveButton.textContent = 'Saving...';

            const formData = new FormData();
            formData.append('action', 'wpe_fpc_save_email_template');
            formData.append('nonce', wpeFpcSettings.nonce);
            formData.append('email_template', emailTemplate);

            try {
                const response = await fetch(wpeFpcSettings.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await response.json();

                if (data.success) {
                    showNotification(wpeFpcSettings.strings.emailTemplateSaved, 'success');
                } else {
                    showNotification(data.data?.message || wpeFpcSettings.strings.emailTemplateFailed, 'error');
                }
            } catch (error) {
                console.error('Save error:', error);
                showNotification(wpeFpcSettings.strings.emailTemplateFailed, 'error');
            } finally {
                // Re-enable button
                saveButton.disabled = false;
                saveButton.textContent = originalText;
            }
        });
    }

    /**
     * Initialize placeholder copy buttons
     */
    function initPlaceholderCopyButtons() {
        const copyButtons = document.querySelectorAll('.wpe-fpc-copy-placeholder');
        if (!copyButtons.length) return;

        copyButtons.forEach(button => {
            button.addEventListener('click', async () => {
                const placeholder = button.getAttribute('data-placeholder');
                if (!placeholder) return;

                try {
                    // Copy to clipboard
                    await navigator.clipboard.writeText(placeholder);

                    // Visual feedback
                    const originalText = button.textContent;
                    button.textContent = '✓ Copied!';
                    button.style.backgroundColor = '#00a32a';
                    button.style.color = '#fff';

                    setTimeout(() => {
                        button.textContent = originalText;
                        button.style.backgroundColor = '';
                        button.style.color = '';
                    }, 2000);

                    showNotification(wpeFpcSettings.strings.placeholderCopied, 'success');
                } catch (error) {
                    console.error('Copy error:', error);
                    showNotification(wpeFpcSettings.strings.placeholderFailed, 'error');
                }
            });
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
