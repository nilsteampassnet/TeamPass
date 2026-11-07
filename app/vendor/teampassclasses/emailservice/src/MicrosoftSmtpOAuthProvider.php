<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeampassClasses\EmailService;

use PHPMailer\PHPMailer\OAuthTokenProvider;

/** Exchange Online app-only XOAUTH2; no fallback to password authentication. */
final class MicrosoftSmtpOAuthProvider implements OAuthTokenProvider
{
    /** Use the mailbox explicitly authorized in Exchange, independently of user sign-in. */
    public function __construct(private readonly string $mailbox, private readonly EmailAccessTokenProviderInterface $tokens)
    {
        if (filter_var($mailbox, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\x00-\x20\x7f]/', $mailbox)) {
            throw new EmailTransportException(EmailSendResult::failure('configuration'));
        }
    }

    /** Return the SASL payload only to PHPMailer; never log or persist it. */
    public function getOauth64(): string
    {
        return base64_encode('user=' . $this->mailbox . "\x01auth=Bearer " . $this->tokens->getAccessToken() . "\x01\x01");
    }
}
