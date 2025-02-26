/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      secure-clipboard-cleaner.js
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

class ClipboardCleaner {
    constructor(duration) {
        this.duration = duration * 1000; // Convert to milliseconds
        this.clearTimeout = null;
        this.clearingTime = null;
        this.originalText = '';
        this.maxRetries = 8;
        this.retryDelay = 1000; // 1 second between each attempt
        this.retryDuration = this.maxRetries * this.retryDelay;
        this.manualClearButton = null;
    }

    scheduleClearing(onSuccess, onError) {
        this.clearingTime = Date.now() + this.duration;

        this.clearTimeout = setTimeout(() => {
            this.attemptClearing(this.maxRetries, onSuccess, onError);
        }, this.duration);
    }

    async attemptClearing(retriesLeft, onSuccess, onError) {
        try {
            await navigator.clipboard.writeText('');
            if (onSuccess) {
                // Remove existing messages
                toastr.remove();

                // Save clipboard status as safe
                localStorage.setItem('clipboardStatus', JSON.stringify({
                    status: 'safe',
                    timestamp: Date.now()
                }));

                // Call success callback
                onSuccess();
            }
        } catch (error) {
            if (retriesLeft === this.maxRetries) {
                // Show a message indicating the initial failure
                toastr.warning(
                    `${TRANSLATIONS_CLIPBOARD.clipboard_clearing_failed}`,
                    '', {
                        timeOut: this.retryDuration,
                        positionClass: 'toast-bottom-right',
                        progressBar: true
                    }
                );
            }

            if (retriesLeft > 0) {
                // Retry clearing after a delay
                setTimeout(() => {
                    this.attemptClearing(retriesLeft - 1, onSuccess, onError);
                }, this.retryDelay);
            } else {
                // If all attempts fail, show the button
                this.useFallbackApproach(onSuccess, onError);
            }
        }
    }

    useFallbackApproach(onSuccess, onError) {
        try {
            toastr.remove(); // Remove all notifications
            
            // Mark clipboard as unsafe
            this.markClipboardAsUnsafe();
    
            // Create an HTML container for the message and button
            const container = document.createElement('div');
            container.innerHTML = `
                <div>
                    <p>${TRANSLATIONS_CLIPBOARD.clipboard_unsafe}</p>
                    <button id="manual-clear-btn" class="btn btn-warning">${TRANSLATIONS_CLIPBOARD.clipboard_clear_now}</button>
                </div>
            `;
    
            // Show the notification with the button
            toastr.warning(container.innerHTML, '', {
                timeOut: 0, // Keep the toastr active until an action
                positionClass: 'toast-bottom-right',
                progressBar: false
            });
    
            // Add an event listener to the button after inserting it into the DOM
            document.getElementById('manual-clear-btn').addEventListener('click', async () => {
                toastr.remove(); // Remove all notifications
                try {
                    // Attempt to clear the clipboard
                    await navigator.clipboard.writeText('');
                    toastr.success(
                        `${TRANSLATIONS_CLIPBOARD.clipboard_cleared}`,
                        '', {
                            timeOut: 2000,
                            positionClass: 'toast-bottom-right',
                            progressBar: true
                        }
                    );
                    if (onSuccess) onSuccess();
                } catch (error) {
                    toastr.error(
                        `${TRANSLATIONS_CLIPBOARD.unable_to_clear_clipboard}`,
                        '', {
                            timeOut: 5000,
                            positionClass: 'toast-bottom-right',
                            progressBar: true
                        }
                    );
                    if (onError) onError(error);
                }
            });
    
        } catch (error) {
            if (onError) onError(error);
        }
    }

    markClipboardAsUnsafe() {
        try {
            // Save clipboard status as unsafe
            localStorage.setItem('clipboardStatus', JSON.stringify({
                status: 'unsafe',
                timestamp: Date.now()
            }));
        } catch (error) {
            return;
        }
    }

    cleanup() {
        if (this.clearTimeout) {
            clearTimeout(this.clearTimeout);
        }
    }

    static isClipboardSafe() {
        try {
            const clipboardStatus = localStorage.getItem('clipboardStatus');
            if (clipboardStatus) {
                const status = JSON.parse(clipboardStatus);
                // Consider the clipboard safe if it was cleared less than an hour ago
                if (Date.now() - status.timestamp < 3600000) {
                    return status.status === 'safe';
                }
            }
            return true;
        } catch {
            return false;
        }
    }
}