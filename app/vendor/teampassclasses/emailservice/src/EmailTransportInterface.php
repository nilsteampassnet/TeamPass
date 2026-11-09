<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * This program is free software under the GNU General Public License, version 3
 * or later, without any warranty. See <https://www.gnu.org/licenses/>.
 */

namespace TeampassClasses\EmailService;

use PHPMailer\PHPMailer\PHPMailer;

/** Deliver an already prepared message without rebuilding its content. */
interface EmailTransportInterface
{
    /** Send the prepared message and report acceptance, never inbox delivery. */
    public function send(PHPMailer $message): EmailSendResult;
}
