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
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

class ClipboardCleaner {
    constructor(duration) {
        this.duration = duration * 1000; // Converting to milliseconds
        this.clearTimeout = null;
        this.clearingTime = null;
        this.originalText = '';
        this.maxRetries = 8;
        this.retryDelay = 1000; // 1 second between attempts
        this.retryDuration = this.maxRetries * this.retryDelay;
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
                // Remove waiting message
                toastr.remove();

                // Save clipboard status
                localStorage.setItem('clipboardStatus', JSON.stringify({
                    status: 'safe',
                    timestamp: Date.now()
                }));

                // Call success callback
                onSuccess();
            }
        } catch (error) {
            // Inform user that clearing failed
            if (retriesLeft === this.maxRetries) {
                toastr.warning(
                    "Clipboard clearing failed, you need to be active on the Teampass page, for example by clicking on it.",
                    '', {
                        timeOut: this.retryDuration,
                        positionClass: 'toast-bottom-right',
                        progressBar: true
                    }
                );
            }
            
            if (retriesLeft > 0) {
                // Define a new timeout to retry clearing
                setTimeout(() => {
                    this.attemptClearing(retriesLeft - 1, onSuccess, onError);
                }, this.retryDelay);
            } else {
                // If all retries failed, use fallback approach
                this.useFallbackApproach(onSuccess, onError);
            }
        }
    }

    useFallbackApproach(onSuccess, onError) {
        try {
            // Save the fact that the clipboard is now unsafe
            this.markClipboardAsUnsafe();
            
            // Informer l'utilisateur
            toastr.warning(
                "For security reasons, the clipboard has been marked as unsafe. Please clear it manually.",
                '', {
                    timeOut: 5000,
                    positionClass: 'toast-bottom-right',
                    progressBar: true
                }
            );

            if (onSuccess) onSuccess();
        } catch (error) {
            if (onError) onError(error);
        }
    }

    markClipboardAsUnsafe() {
        try {
            // Save in local storage the fact that the clipboard is now unsafe
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
                // Consider the clipboard as safe if it has been cleared less than an hour ago
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