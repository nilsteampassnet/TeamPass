<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * This program is free software under the GNU General Public License, version 3
 * or later, without any warranty. See <https://www.gnu.org/licenses/>.
 */

namespace TeampassClasses\EmailService;

/** Carry only classified metadata across transport and token boundaries. */
final class EmailTransportException extends \RuntimeException
{
    /** Do not chain provider exceptions, which can contain credentials in their traces. */
    public function __construct(public readonly EmailSendResult $result)
    {
        parent::__construct($result->errorCode);
    }
}
