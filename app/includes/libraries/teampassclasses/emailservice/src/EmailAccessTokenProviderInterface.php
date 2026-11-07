<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * This program is free software under the GNU General Public License, version 3
 * or later, without any warranty. See <https://www.gnu.org/licenses/>.
 */

namespace TeampassClasses\EmailService;

interface EmailAccessTokenProviderInterface
{
    /** Obtain a token without requiring an interactive user session. */
    public function getAccessToken(): string;

    /** Reject only the token observed by a failed authenticated request. */
    public function invalidateAccessToken(#[\SensitiveParameter] string $accessToken): void;
}
