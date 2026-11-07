<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeampassClasses\EmailService;

use GuzzleHttp\ClientInterface;
use PHPMailer\PHPMailer\PHPMailer;

/** Fixed Graph/Gmail MIME endpoints; never rebuild the business message. */
final class ApiTransport implements EmailTransportInterface
{
    // Conservative v1 application limit, independent of larger provider limits.
    public const MAX_MIME_BYTES = 2097152;

    /** Inject HTTP and tokens so automated tests never need real provider credentials. */
    public function __construct(
        private readonly EmailSettings $settings,
        private readonly EmailAccessTokenProviderInterface $tokens,
        private readonly ClientInterface $http
    ) {
        if (!in_array($settings->transport, ['microsoft_graph', 'gmail_api'], true)) {
            throw new EmailTransportException(EmailSendResult::failure('configuration'));
        }
    }

    /** Submit prepared MIME once, except for one explicit pre-acceptance 401 rejection. */
    public function send(PHPMailer $message): EmailSendResult
    {
        $graph = $this->settings->transport === 'microsoft_graph';
        $sender = $graph ? $this->settings->microsoftMailbox : $this->settings->googleSender;
        // SMTP MIME omits Bcc. Refuse it rather than silently losing recipients.
        if ($message->getBccAddresses() !== [] || strcasecmp($message->From, $sender) !== 0
            || ($message->Sender !== '' && strcasecmp($message->Sender, $sender) !== 0)
        ) {
            return EmailSendResult::failure('message');
        }
        $mime = $message->getSentMIMEMessage();
        if ($mime === '' || strlen($mime) > self::MAX_MIME_BYTES) {
            return EmailSendResult::failure('message');
        }
        $url = $graph ? 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($sender) . '/sendMail'
            : 'https://gmail.googleapis.com/gmail/v1/users/' . rawurlencode($sender) . '/messages/send';
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $accessToken = $this->tokens->getAccessToken();
            } catch (EmailTransportException $e) {
                return $e->result;
            } catch (\Throwable $e) {
                return EmailSendResult::failure('configuration');
            }
            try {
                $options = ['headers' => ['Authorization' => 'Bearer ' . $accessToken]];
                if ($graph) {
                    $options['headers']['Content-Type'] = 'text/plain';
                    $options['body'] = base64_encode($mime);
                } else {
                    $options['json'] = ['raw' => rtrim(strtr(base64_encode($mime), '+/', '-_'), '=')];
                }
                $response = $this->http->request('POST', $url, EmailHttp::options($this->settings, $options));
                $status = $response->getStatusCode();
                if ($graph && $status === 202) {
                    $response->getBody()->close();
                    return EmailSendResult::accepted();
                }
                if (!$graph && $status === 200) {
                    $data = EmailHttp::json($response);
                    $id = $data['id'] ?? null;
                    return is_string($id) && preg_match('/\A[a-f0-9]{1,64}\z/i', $id) === 1
                        ? EmailSendResult::accepted($id) : EmailSendResult::indeterminate();
                }
                $delay = EmailHttp::retryAfter($response);
                $response->getBody()->close();
                if ($status === 401) {
                    $this->tokens->invalidateAccessToken($accessToken);
                    if ($attempt === 0) { continue; }
                }
                return match (true) {
                    $status === 401 => EmailSendResult::failure('authentication'),
                    $status === 403 => EmailSendResult::failure('permission'),
                    $status === 429 => EmailSendResult::failure('throttled', true, $delay),
                    // Non-idempotent send: a 5xx or timeout can follow provider acceptance.
                    $status >= 500 || $status === 408 => EmailSendResult::indeterminate('temporary'),
                    $status >= 400 => EmailSendResult::failure('rejected'),
                    default => EmailSendResult::indeterminate(),
                };
            } catch (\Throwable $e) {
                return EmailSendResult::indeterminate(EmailHttp::networkCode($e));
            }
        }
        return EmailSendResult::failure('authentication');
    }
}
