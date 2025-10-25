/**
 * Admin JavaScript for WP Easy Force Password Change
 *
 * @package WP_Easy\ForcePW_Change
 */

(function() {
    'use strict';

    /**
     * Initialize admin functionality when DOM is ready
     */
    document.addEventListener('DOMContentLoaded', () => {
        initForceResetButton();
        initGenerateLinkButton();
        initBulkActionConfirmation();
    });

    /**
     * Initialize force reset button
     */
    function initForceResetButton() {
        const button = document.getElementById('wpe-fpc-force-reset-btn');
        if (!button) {
            return;
        }

        button.addEventListener('click', (e) => {
            e.preventDefault();

            const userId = button.getAttribute('data-user-id');
            const nonce = button.getAttribute('data-nonce');
            const sendEmailCheckbox = document.getElementById('wpe-fpc-send-email-checkbox');
            const sendEmail = sendEmailCheckbox && sendEmailCheckbox.checked ? '1' : '0';

            if (!userId || !nonce) {
                showNotification('Error: Missing required data.', 'error');
                return;
            }

            // Build URL
            const url = new URL(wpeFpcData.ajaxUrl.replace('admin-ajax.php', 'admin-post.php'));
            url.searchParams.set('action', 'wpe_fpc_force_reset');
            url.searchParams.set('user_id', userId);
            url.searchParams.set('send_email', sendEmail);
            url.searchParams.set('_wpnonce', nonce);

            // Navigate to URL
            window.location.href = url.toString();
        });
    }

    /**
     * Initialize generate & copy link button
     */
    function initGenerateLinkButton() {
        const button = document.getElementById('wpe-fpc-generate-link');
        if (!button) {
            return;
        }

        button.addEventListener('click', async (e) => {
            e.preventDefault();

            const userId = button.getAttribute('data-user-id');
            if (!userId) {
                showNotification('Error: User ID not found.', 'error');
                return;
            }

            // Disable button and show loading state
            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = 'Generating...';

            try {
                // Generate reset link
                const resetUrl = await generateResetLink(userId);

                if (resetUrl) {
                    // Show URL in container
                    const container = document.getElementById('wpe-fpc-reset-url-container');
                    const input = document.getElementById('wpe-fpc-reset-url');

                    if (container && input) {
                        input.value = resetUrl;
                        container.style.display = 'block';
                        container.classList.add('wpe-fpc-show');

                        // Select and copy to clipboard
                        input.select();
                        input.setSelectionRange(0, 99999); // For mobile devices

                        try {
                            await navigator.clipboard.writeText(resetUrl);
                            showNotification(wpeFpcData.strings.copySuccess, 'success');
                        } catch (clipboardError) {
                            // Fallback for older browsers
                            try {
                                document.execCommand('copy');
                                showNotification(wpeFpcData.strings.copySuccess, 'success');
                            } catch (execCommandError) {
                                showNotification(wpeFpcData.strings.copyError, 'error');
                            }
                        }
                    }
                } else {
                    showNotification('Failed to generate reset link.', 'error');
                }
            } catch (error) {
                console.error('Error generating reset link:', error);
                showNotification('An error occurred. Please try again.', 'error');
            } finally {
                // Re-enable button
                button.disabled = false;
                button.textContent = originalText;
            }
        });
    }

    /**
     * Generate reset link via AJAX (using current page URL logic)
     *
     * @param {string} userId User ID
     * @return {Promise<string|null>} Reset URL or null on failure
     */
    async function generateResetLink(userId) {
        // For now, we'll construct the URL based on WordPress login URL
        // In a future enhancement, this could be an AJAX call to get the actual key

        const formData = new FormData();
        formData.append('action', 'wpe_fpc_get_reset_url');
        formData.append('user_id', userId);
        formData.append('nonce', wpeFpcData.nonce);

        try {
            const response = await fetch(wpeFpcData.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();

            if (data.success && data.data.reset_url) {
                return data.data.reset_url;
            } else {
                console.error('API error:', data.data?.message || 'Unknown error');
                return null;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            return null;
        }
    }

    /**
     * Initialize bulk action confirmation
     */
    function initBulkActionConfirmation() {
        const bulkActionForm = document.getElementById('posts-filter');
        if (!bulkActionForm) {
            return;
        }

        bulkActionForm.addEventListener('submit', (e) => {
            const action = getSelectedBulkAction();

            if (action === 'wpe_fpc_force_bulk' || action === 'wpe_fpc_revoke_bulk') {
                const confirmed = confirm(wpeFpcData.strings.confirmBulk);
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }

    /**
     * Get selected bulk action from either top or bottom dropdown
     *
     * @return {string} Selected action
     */
    function getSelectedBulkAction() {
        const topAction = document.getElementById('bulk-action-selector-top');
        const bottomAction = document.getElementById('bulk-action-selector-bottom');

        if (topAction && topAction.value !== '-1') {
            return topAction.value;
        }

        if (bottomAction && bottomAction.value !== '-1') {
            return bottomAction.value;
        }

        return '';
    }

    /**
     * Show notification message
     *
     * @param {string} message Message to display
     * @param {string} type    Type: 'success' or 'error'
     */
    function showNotification(message, type = 'success') {
        // Create notice element
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible wpe-fpc-notice`;
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

        // Insert at top of page
        const firstHeading = document.querySelector('.wrap h1, .wrap h2');
        if (firstHeading) {
            firstHeading.parentNode.insertBefore(notice, firstHeading.nextSibling);
        } else {
            const wrap = document.querySelector('.wrap');
            if (wrap) {
                wrap.insertBefore(notice, wrap.firstChild);
            }
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
        return text.replace(/[&<>"']/g, (m) => map[m]);
    }

    /**
     * Add copy button click handlers for any existing reset URL fields
     */
    function initCopyButtons() {
        const copyButtons = document.querySelectorAll('[data-wpe-fpc-copy]');

        copyButtons.forEach(button => {
            button.addEventListener('click', async (e) => {
                e.preventDefault();
                const targetId = button.getAttribute('data-wpe-fpc-copy');
                const targetInput = document.getElementById(targetId);

                if (!targetInput) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(targetInput.value);
                    showNotification(wpeFpcData.strings.copySuccess, 'success');

                    // Visual feedback
                    const originalText = button.textContent;
                    button.textContent = 'Copied!';
                    setTimeout(() => {
                        button.textContent = originalText;
                    }, 2000);
                } catch (error) {
                    // Fallback
                    targetInput.select();
                    try {
                        document.execCommand('copy');
                        showNotification(wpeFpcData.strings.copySuccess, 'success');
                    } catch (execError) {
                        showNotification(wpeFpcData.strings.copyError, 'error');
                    }
                }
            });
        });
    }

    // Initialize copy buttons when DOM is ready
    document.addEventListener('DOMContentLoaded', initCopyButtons);

})();
