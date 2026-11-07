<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * This program is free software under the GNU General Public License, version 3
 * or later, without any warranty. See <https://www.gnu.org/licenses/>.
 */

namespace TeampassClasses\EmailService;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/** Keep PHPMailer's existing SMTP delivery and TLS behaviour. */
final class SmtpTransport implements EmailTransportInterface
{
    /** Send the MIME message prepared once by EmailService. */
    public function send(PHPMailer $message): EmailSendResult
    {
        if ($message->postSend() !== true) {
            throw new Exception($message->ErrorInfo);
        }

        return EmailSendResult::accepted();
    }
}
